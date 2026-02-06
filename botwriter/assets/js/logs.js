document.addEventListener("DOMContentLoaded", () => { 
    botwriter_logs_countup();
    botwriter_logs_delete_init();
});


function botwriter_logs_countup() {
  var i = 1;
  var interval = setInterval(function() {    
    var countupElement = document.getElementById('countup');
    if (countupElement) {
      countupElement.innerHTML = "Working on tasks, every 120s a new one is done (can be modified in settings)... " + i + " seconds";            
    }
    i++;
    // cada 2' refrescar la página
    if (i % 60 == 0) {
      location.reload();
    }
  }, 1000);
}

function botwriter_logs_delete_init() {
    document.querySelectorAll('.botwriter-delete-log').forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            var logId = this.getAttribute('data-log-id');
            var row = this.closest('tr');
            
            // Confirmation dialog
            if (!confirm(botwriter_logs_vars.confirm_delete)) {
                return;
            }
            
            // Disable button during request
            button.disabled = true;
            button.innerHTML = '<span class="dashicons dashicons-update spin" style="vertical-align: middle;"></span>';
            
            // AJAX request
            var formData = new FormData();
            formData.append('action', 'botwriter_delete_log');
            formData.append('log_id', logId);
            formData.append('nonce', botwriter_logs_vars.nonce);
            
            fetch(botwriter_logs_vars.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // Remove row with fade effect
                    row.style.transition = 'opacity 0.3s';
                    row.style.opacity = '0';
                    setTimeout(function() {
                        row.remove();
                    }, 300);
                } else {
                    alert(data.data.message || botwriter_logs_vars.error_delete);
                    button.disabled = false;
                    button.innerHTML = '<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>';
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                alert(botwriter_logs_vars.error_delete);
                button.disabled = false;
                button.innerHTML = '<span class="dashicons dashicons-trash" style="vertical-align: middle;"></span>';
            });
        });
    });
}
