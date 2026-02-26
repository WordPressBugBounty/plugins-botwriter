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


function botwriter_updateEmail() {
  var email_blog = document.getElementById('botwriter_email').value;
  var api_key = document.getElementById('botwriter_api_key').value;

  console.log('Email: ' + email_blog);
  console.log('API KEY: ' + api_key);

  // Disable the button and start the countdown
  var button = document.getElementById('button_confirm_email');
  button.disabled = true;

  // Countdown timer
  var countdown = 200;
  var originalText = button.innerHTML;
  button.innerHTML = `Wait ${countdown} seconds to send again`;

  var timer = setInterval(function() {
    countdown--;
    if (countdown > 0) {
      button.innerHTML = `Wait ${countdown} seconds to send again if you don't receive the email`;
    } else {
      clearInterval(timer);
      button.innerHTML = originalText;
      button.disabled = false;
    }
  }, 1000);

  // Make an AJAX request to update the email and generate the token
  jQuery(document).ready(function($) {
      $.ajax({
          url: 'https://wpbotwriter.com/public/envio_email_confirmacion.php', 
          type: 'POST',
          dataType: 'text', // Expecting text instead of JSON
          data: { 
              email_blog: email_blog,
              api_key: api_key
          },
          beforeSend: function() {
              console.log('Sending AJAX request...');
          },
          success: function(response) {
              try {
                  console.log('Response:', response);
                  var responseDiv = jQuery('#response_email'); 
                  responseDiv.empty();
                  responseDiv.append('<strong>' + response + '</strong>');
              } catch (error) {                  
                  console.error('Error processing response:', error);                 
              }
          },
          error: function(jqXHR, textStatus, errorThrown) {
              console.error('AJAX Error:', textStatus, errorThrown);
              alert('AJAX Error: ' + textStatus + ' ' + errorThrown);
          }
      });
  });
}


function botwriter_getUserData() {
  var url = document.getElementById('botwriter_domain_name').value;
  var api_key = document.getElementById('botwriter_api_key').value;

  console.log('URL: ' + url);
  console.log('API KEY: ' + api_key);

  // Realizar una solicitud AJAX para obtener los datos del usuario
  jQuery(document).ready(function($) {
      $.ajax({
          url: 'https://wpbotwriter.com/public/getUserData.php', // Cambia 'ruta_al_php.php' por la URL real del archivo PHP
          type: 'POST',
          dataType: 'text', // Esperamos texto en lugar de JSON
          data: {
              api_key: api_key,
              url: url
          },
          success: function(response) {
              try {
                  console.log('Respuesta:', response);

                  // Intentar convertir el texto a un objeto JSON                  
                  const jsonResponse = JSON.parse(response.trim());
                  
                  if (jsonResponse.status === 'error') {
                      console.error('Error:', jsonResponse.message);
                      //alert('Error: ' + jsonResponse.message);
                  } else {

                      console.log('Success:', jsonResponse);
                      crear_texto_con_variables(jsonResponse);
                      //alert('Success: ' + JSON.stringify(jsonResponse));
                      
                  }
              } catch (error) {
                  // Manejar errores de análisis JSON
                  console.error('Error al analizar JSON:', error);
                  //alert('Error al analizar JSON: ' + error.message);
              }
          },
          error: function(jqXHR, textStatus, errorThrown) {              
              console.error('AJAX Error:', textStatus, errorThrown);
              alert('AJAX Error: ' + textStatus + ' ' + errorThrown);
          }
      });
  });
}

function crear_texto_con_variables(jsonResponse) {
  console.log('aleluya');  
  // AÑADE AL <div id="response_div"> EL TEXTO CON LAS VARIABLES
  var respuesta_div = jQuery('#response_div');
  respuesta_div.empty();
  respuesta_div.append('<h3>Subscription</h3>');
  
  if (jsonResponse.plan_name){
    respuesta_div.append('<p>Plan Name: ' + jsonResponse.plan_name + '</p>');
  }

  var state = jsonResponse.state;
  if (state === 'deleted'){
    state= 'canceled';
  }
  
  if (state){
    respuesta_div.append('<p>State: ' + state + '</p>');
  }
  if (state==='canceled'){
    respuesta_div.append('<p><a href="https://wpbotwriter.com" target="_blank">Subscribe again</a></p>');
  }

  
  if (jsonResponse.user_email){
    respuesta_div.append('<p>User Email: ' + jsonResponse.user_email + '</p>');
  }

  //last_payment
  if (jsonResponse.last_payment){
    respuesta_div.append('<p>Last Payment Date: ' + jsonResponse.last_payment.date + '</p>');
  }

  // escribe respuesta.next_payment.date
  if (jsonResponse.next_payment){
    respuesta_div.append('<p>Next Payment Date: ' + jsonResponse.next_payment.date + '</p>');
  }

  if (state!=='canceled'){
    if (jsonResponse.update_url){
      respuesta_div.append('<p>Update Payment Method: <a href="' + jsonResponse.update_url + '">Update Payment Method</a></p>');
    }
    
    if (jsonResponse.cancel_url){           
      respuesta_div.append('<p>Cancel Subscription: <a href="' + jsonResponse.cancel_url + '">Cancel Subscripcion</a> </p>');
    }
  }
  
  

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


