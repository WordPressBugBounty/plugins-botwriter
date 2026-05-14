<?php
/**
 * BotWriter SEO module bootstrap.
 *
 * Loads everything in dependency order:
 *   core/ -> utilities, scoring, audit, Yoast compatibility, DB tables
 *   seo engine -> anchors, candidates, prompt, insertion, postprocess
 *   bulk/ -> scheduler, analysis & action jobs
 *   ai/ -> FAQ generation used by kept bulk actions
 *   embeddings/ -> index module used by kept bulk actions
 *   external/ -> SERP adapter used by kept bulk actions
 *   autopilot/ -> redirects support for slug changes
 *   admin-page.php -> reduced SEO admin router + page renderers
 *
 * @package BotWriter
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$botwriter_seo_dir = plugin_dir_path( __FILE__ );

// 1) Foundation
require_once $botwriter_seo_dir . 'utils.php';
require_once $botwriter_seo_dir . 'core/db.php';
require_once $botwriter_seo_dir . 'core/yoast-compat.php';
require_once $botwriter_seo_dir . 'core/score.php';
require_once $botwriter_seo_dir . 'core/readability.php';
require_once $botwriter_seo_dir . 'core/report.php';
require_once $botwriter_seo_dir . 'core/audit.php';

// 2) Internal-link engine
require_once $botwriter_seo_dir . 'anchors.php';
require_once $botwriter_seo_dir . 'candidates.php';
require_once $botwriter_seo_dir . 'prompt.php';
require_once $botwriter_seo_dir . 'insertion.php';
require_once $botwriter_seo_dir . 'postprocess.php';

// 3) Bulk
require_once $botwriter_seo_dir . 'bulk/scheduler.php';
require_once $botwriter_seo_dir . 'bulk/undo.php';
require_once $botwriter_seo_dir . 'bulk/targeting.php';
require_once $botwriter_seo_dir . 'bulk/actions.php';   // defines botwriter_seo_ai_config used elsewhere
require_once $botwriter_seo_dir . 'bulk/analysis.php';
require_once $botwriter_seo_dir . 'bulk/action-optimize-images.php';

// 4) AI editorial
require_once $botwriter_seo_dir . 'ai/faq.php';

// 5) Social metadata
require_once $botwriter_seo_dir . 'social.php';

// 6) Embeddings
require_once $botwriter_seo_dir . 'embeddings/index.php';

// 7) External services
require_once $botwriter_seo_dir . 'external/serp.php';

// 8) Autopilot
require_once $botwriter_seo_dir . 'autopilot/redirects.php';

// 9) Admin UI router (loads page renderers internally)
require_once $botwriter_seo_dir . 'llmstxt.php';
require_once $botwriter_seo_dir . 'media-alt.php';
require_once $botwriter_seo_dir . 'admin-page.php';
