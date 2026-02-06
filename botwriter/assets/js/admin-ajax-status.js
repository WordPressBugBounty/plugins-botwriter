jQuery(document).ready(function($) {
    $('.icono-status').on('click', function(e) {
        e.preventDefault();
        
        const id = $(this).data('id');
        const nuevoStatus = $(this).data('status');
        const iconElement = $(this);

        $.ajax({
            url: botwriter_ajax_object.ajax_url,
            type: 'POST',
            data: {
                action: 'botwriter_cambiar_status', // Change status of task
                id: id,
                status: nuevoStatus,
                nonce: botwriter_ajax_object.nonce
            },
            success: function(response) {
                if(response.success) {
                    const nuevoIcono = nuevoStatus ? 'dashicons-yes' : 'dashicons-dismiss';
                    const nuevoTexto = nuevoStatus ? 'Desactivate' : 'Activate';

                    iconElement.attr('class', 'icono-status dashicons ' + nuevoIcono);
                    iconElement.data('status', nuevoStatus ? 0 : 1);
                    iconElement.attr('title', nuevoTexto);
                } else {
                    alert('Error changing status.');
                }
            },
            error: function() {
                alert('Error AJAX request.');
            }
        });
    });
});
