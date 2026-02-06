function toggleCustomLengthInput() {
    var selectElement = document.getElementById("post_length");
    var customInput = document.getElementById("customLengthInput");
    var customPostLong = document.getElementById("custom_post_length");

    if (selectElement.value === "custom") {
        customInput.style.display = "block";        
    } else {
        customInput.style.display = "none";        
        customPostLong.value = ""; // Limpia el valor del campo personalizado si no se usa
    }
}

function updatePostLength() {
    var selectElement = document.getElementById("post_length");
    var customPostLong = document.getElementById("custom_post_length");

    // Validar el valor personalizado
    var customValue = parseInt(customPostLong.value, 10);
    if (isNaN(customValue) || customValue < 10 || customValue > 4000) {
        alert("Please enter a valid number between 10 and 4000.");
        return;
    }

    // Crear o actualizar una opción temporal en el selector para reflejar el valor
    var customOption = selectElement.querySelector('option[value="custom"]');
    customOption.textContent = `Custom (${customValue} words)`; // Actualiza el texto visible
    selectElement.value = "custom"; // Asegura que el selector esté en "Custom"
}

  // wordpress, news
  // muestra u oculta el campo de la url del sitio externo de wordpress si selecciona la opcion de wordpress externo
  document.addEventListener('DOMContentLoaded', () => {
  
    toggleCustomStyleInput();

  
  const website_type = document.querySelectorAll('input[name="website_type"]');
  const div_website_domainname = document.getElementById('div_website_domainname');
  const div_website_category_id = document.getElementById('div_website_category_id');
  const elemento_domain_name = document.getElementById('domain_name');
  const div_news = document.getElementById('div_news');
  const elemento_news_keyword = document.getElementById('news_keyword');
  
  const div_rss = document.getElementById('div_rss');
  const elemento_rss_source = document.getElementById('rss_source');

  const div_ai = document.getElementById('div_ai');
  const elemento_ai_keywords = document.getElementById('ai_keywords');



  // Función para actualizar la interfaz en base al valor seleccionado
    const updateUI = (value) => {
      if (value === 'wordpress') {
        div_website_domainname.style.display = 'block';
        div_website_category_id.style.display = 'block';
        website_category_id.required = true;
        elemento_domain_name.required = true;
      } else {
        div_website_domainname.style.display = 'none';
        div_website_category_id.style.display = 'none';
        elemento_domain_name.required = false;
        website_category_id.required = false;
      }
      if (value === 'news') {
        div_news.style.display = 'block';
        elemento_news_keyword.required = true;
      } else {
        div_news.style.display = 'none';
        elemento_news_keyword.required = false;
      }
      if (value === 'rss') {
        div_rss.style.display = 'block';
        elemento_rss_source.required = true;
      } else {
        div_rss.style.display = 'none';
        elemento_rss_source.required = false;
      }
      if (value === 'ai') {
        div_ai.style.display = 'block';
        elemento_ai_keywords.required = true;
      } else {
        div_ai.style.display = 'none';
        elemento_ai_keywords.required = false;
      }

    };

  // Verifica el valor inicial del radio seleccionado al cargar
    website_type.forEach((radio) => {
      if (radio.checked) {
        updateUI(radio.value); // Ejecuta la función con el valor inicial
      }

      // Escucha cambios posteriores
        radio.addEventListener('change', () => {
          updateUI(radio.value); // Actualiza cuando cambie
        });
    });
  }); 

// JavaScript para mostrar/ocultar el campo personalizado según la selección
function toggleCustomStyleInput() {
    var selectElement = document.getElementById("narration");
    var customInput = document.getElementById("customStyleInput");
    
    if (selectElement) {
        if (selectElement.value === "Custom") {
            customInput.style.display = "block";
        } else {
            customInput.style.display = "none";
        }
    }    
}

function preSelectedOptions() {
  let select = document.getElementById("website_category_id");
  let selectedOptions = [...select.selectedOptions];

  let values = selectedOptions.map(option => option.value);
  let texts = selectedOptions.map(option => option.text);

  website_category_name = selectedOptions.map(option => option.text);
  document.querySelector('input[name="website_category_name"]').value = website_category_name.join(',');    

}


jQuery(window).on("load", function() {
  console.log("Start to process");
  jQuery("#loading").hide();
});


document.addEventListener('DOMContentLoaded', function() {
  var redirectDiv = document.getElementById('redirecion');
  if ( redirectDiv ) { // Solo se ejecuta si el div existe
      var url = redirectDiv.getAttribute('data-url');
      if ( url ) {
          setTimeout(function() {
              window.location.href = url;
          }, 3000);
      }
  }
  
  // Toggle ASI tip when disable_ai_images checkbox changes
  var disableImagesCheckbox = document.getElementById('disable_ai_images_checkbox');
  var asiTip = document.getElementById('bw_asi_tip');
  
  if (disableImagesCheckbox && asiTip) {
    disableImagesCheckbox.addEventListener('change', function() {
      if (this.checked) {
        asiTip.style.display = 'flex';
      } else {
        asiTip.style.display = 'none';
      }
    });
  }
});
