// When the window finishes loading, hide the loading element and log a message
//jQuery(window).on("load", function() {
//  console.log("Start to process");
//  jQuery("#loading").hide();
//});

// Function to insert a tag at the current cursor position in the 'prompt' textarea
function insertTag(tag) {
  var textarea = document.getElementById('prompt');
  var cursorPos = textarea.selectionStart;
  var textBefore = textarea.value.substring(0, cursorPos);
  var textAfter  = textarea.value.substring(cursorPos, textarea.value.length);

  textarea.value = textBefore + tag + textAfter;
  textarea.focus();
  textarea.selectionStart = cursorPos + tag.length;
  textarea.selectionEnd = cursorPos + tag.length;
}



function fetchRSSFeed() {
  // Obtener la URL del RSS desde un input o configurarla manualmente
  var rssUrl = document.getElementById('rss_source').value;

  console.log('Fetching RSS from URL:', rssUrl);

  jQuery(document).ready(function($) {
      $.ajax({
          url: botwriter_ajax.ajax_url,
          type: 'POST',
          dataType: 'json',
          data: {
              action: 'botwriter_check_rss',
              nonce: botwriter_ajax.rss_nonce,
              url: rssUrl
          },
          success: function(response) {
              var rss_response = jQuery('#rss_response');
              rss_response.empty();
              if (response.success) {
                  rss_response.append('<p style="color: blue;">RSS SOURCE IS OK!</p>');
                  rss_response.append('<p><strong>' + response.data.title + '</strong></p>');
                  console.log('RSS Feed:', response.data);
              } else {
                  var errorMsg = (response.data && response.data.error) ? response.data.error : 'Unknown error';
                  console.error('Error:', errorMsg);
                  rss_response.append('<p style="color: red;">' + errorMsg + '</p>');
              }
          },
          error: function(jqXHR, textStatus, errorThrown) {
              console.error('AJAX Error:', textStatus, errorThrown);
              alert('AJAX Error: ' + textStatus + ' ' + errorThrown);
          }
      });
  });
}


// Function to insert an HTML tag at the current cursor position in the 'content' textarea
function insertHTMLTag(tag) {
  var textarea = document.getElementById('content');
  var cursorPos = textarea.selectionStart;
  var textBefore = textarea.value.substring(0, cursorPos);
  var textAfter  = textarea.value.substring(cursorPos, textarea.value.length);

  textarea.value = textBefore + tag + textAfter;
  textarea.focus();
  textarea.selectionStart = cursorPos + tag.length;
  textarea.selectionEnd = cursorPos + tag.length;
}

// Function to refresh website categories by making an AJAX request
function refreshWebsiteCategories() {
  jQuery("#loading").show();

  var domainNameInput = document.getElementById('domain_name');
  var domainName = domainNameInput.value.trim(); // Elimina espacios en blanco

  var adminEmailInput = document.getElementById('botwriter_admin_email');
  var adminEmail = adminEmailInput.value;

  var adminDomainInput = document.getElementById('botwriter_domain_name');
  var adminDomain = adminDomainInput.value;

  // Asegurar que el dominio tenga https://
  function ensureHttps(url) {
      if (!/^https?:\/\//i.test(url)) {
          return "https://" + url;
      }
      return url;
  }

  // Aplicar la corrección al dominio del usuario
  var websiteDomainName = ensureHttps(domainName);

  

  // Realizar la solicitud AJAX (local, via WordPress admin-ajax)
  jQuery(document).ready(function($) {
      $.ajax({
          url: botwriter_ajax.ajax_url,
          method: "POST",
          data: {
              action: 'botwriter_get_wp_categories',
              nonce: botwriter_ajax.wp_categories_nonce,
              website_domainname: websiteDomainName
          },
          success: function(response) {
              jQuery("#loading").hide();
              // wp_send_json_success wraps data in response.data
              var categories = response.success ? response.data : response;
              var multiselect = $('#website_category_id');
              multiselect.empty();
              console.log('Categories:', categories);

              if (!response.success) {
                  var errMsg = (response.data && response.data.error) ? response.data.error : 'Failed to fetch categories.';
                  alert(errMsg);
                  return;
              }

              // Llenar el select con las categorías obtenidas
              $.each(categories, function(index, category) {
                  multiselect.append($('<option>', {
                      value: category.id,
                      text: category.name
                  }));
              });
              multiselect.show();
              $('.btn.btn-primary').html('<i class="bi bi-arrow-clockwise"></i> Refresh');
          },
          error: function(jqXHR, textStatus, errorThrown) {
              jQuery("#loading").hide();
              var errorMessage = "Error refreshing website categories.";
              if (jqXHR.status === 0) {
                  errorMessage = "Connection error. Please check your internet connection.";
              } else if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.error) {
                  errorMessage = jqXHR.responseJSON.data.error;
              }
              alert(errorMessage);
          }
      });
  });
}

  
 
function botwriter_reset_super1() {     
  // Mostrar el elemento de carga
  jQuery("#loading").show();
  

  jQuery.post(botwriter_ajax.ajax_url, {
      action: 'botwriter_eliminar_super1',
      _ajax_nonce: botwriter_ajax.nonce
  })
  .done(function(response) {
      // refrescar la pagina
      location.reload();      
      console.log("ok");
  })
  .fail(function(xhr, status, error) {
      console.error('AJAX request error:', status, error);
  });
};

jQuery(document).on('click', '.botwriter-template-delete', function(e) {
    var msg = jQuery(this).data('confirm') || 'Are you sure?';
    if (!window.confirm(msg)) {
        e.preventDefault();
    }
});


