<?php
/**
 * BotWriter Service Response Handler
 *
 * The remote server (api.wpbotwriter.com) owns all service-level decisions.
 * It returns structured errors with:
 *   - error          (string)  Machine-readable code for logging
 *   - error_message  (string)  Human-readable text (may contain safe HTML links)
 *   - error_level    (int)     0 = log only, 1 = show admin notice
 *   - terminal       (bool)    If true, the plugin should stop retrying
 *
 * The plugin shows error_message in logs as-is.
 * When error_level = 1, the plugin also creates a dismissible admin notice.
 *
 * @package BotWriter
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handle a service error during task processing.
 *
 * If error_level is 1, creates an admin notice with the human-readable message.
 * If terminal is true, marks retries exhausted.
 *
 * @param array  &$data          Log/task data array (modified in place).
 * @param string $error_message  Human-readable message from the server (may contain HTML).
 * @param string $phase          'phase1' or 'phase2' — for logging context.
 * @param int    $error_level    0 = log only, 1 = admin notice.
 * @param bool   $terminal       Whether to stop retrying.
 */
function botwriter_handle_service_error( &$data, $error_message, $phase = 'phase1', $error_level = 0, $terminal = true ) {

    // error_level 1 → create a dismissible admin notice
    if ( (int) $error_level === 1 && function_exists( 'botwriter_announcements_add' ) ) {
        botwriter_announcements_add(
            __( 'Service notice', 'botwriter' ),
            $error_message
        );
    }

    if ( $terminal ) {
        $data['intentosfase1'] = 8;
    }
    $data['task_status']   = 'error';
    $data['error']         = $error_message;

    botwriter_log( "Service error handled ({$phase})", [
        'log_id'        => $data['id'] ?? null,
        'task_id'       => $data['id_task'] ?? null,
        'error_message' => $error_message,
        'error_level'   => $error_level,
    ] );
}

/**
 * Translate a WooCommerce ticket-rejection reason into a user-facing message.
 *
 * The server now sends error_message alongside reason codes, so this function
 * is only a fallback for the rare case where error_message is not present.
 *
 * @param string $reason         Reason code from the BotWriter service.
 * @param string $error_message  Optional human-readable message from the server.
 * @return string                User-facing message.
 */
function botwriter_translate_service_reason( $reason, $error_message = '' ) {
    if ( ! empty( $error_message ) ) {
        return esc_html( $error_message );
    }
    return __( 'The BotWriter service could not process this request.', 'botwriter' );
}
