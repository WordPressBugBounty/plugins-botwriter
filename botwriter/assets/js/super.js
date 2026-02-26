document.addEventListener("DOMContentLoaded", () => {
  onDomContentLoaded();

  // Toggle ASI tip when disable_ai_images checkbox changes
  var disableImagesCheckbox = document.getElementById('disable_ai_images_checkbox');
  var asiTip = document.getElementById('bw_asi_tip');

  if (disableImagesCheckbox && asiTip) {
    disableImagesCheckbox.addEventListener('change', function () {
      if (this.checked) {
        asiTip.style.display = 'flex';
      } else {
        asiTip.style.display = 'none';
      }
    });
  }

  // Auto-initialize manual mode if set via URL parameter
  var superMode = document.getElementById('super_mode');
  if (superMode && superMode.value === 'manual') {
    toggleCustomPromptInput();
  }
});

function onDomContentLoaded() {
  // Asignamos el listener para el botón de editar:
  document.querySelectorAll('.super-edit').forEach(editButton => {
    editButton.addEventListener('click', (event) => {
      const article = event.currentTarget.closest('.super-article');
      // Si el artículo está en modo edición, guardamos; si no, activamos la edición
      if (article.classList.contains('editing')) {
        guardarEdicion(article);
      } else {
        activarEdicion(article);
      }
    });
  });

  // Asignamos el listener para el botón de eliminar:
  document.querySelectorAll('.super-delete').forEach(deleteButton => {
    deleteButton.addEventListener('click', (event) => {
      const article = event.currentTarget.closest('.super-article');
      // Si el artículo está en modo edición, el botón funcionará como "cancelar"
      if (article.classList.contains('editing')) {
        cancelarEdicion(article);
      } else {
        eliminarArticulo(article);
      }
    });
  });

}



function activarEdicion(article) {
  if (article.classList.contains('editing')) return; // Por seguridad

  const editButton = article.querySelector('.super-edit');
  const deleteButton = article.querySelector('.super-delete');
  const titleElement = article.querySelector('.super-title');
  const textElement = article.querySelector('.super-text');

  // Guardamos los textos originales en propiedades temporales del artículo
  article._originalTitle = titleElement.textContent;
  article._originalText = textElement.textContent;

  // Reemplazamos el título por un input
  const titleInput = document.createElement('input');
  titleInput.type = 'text';
  titleInput.value = article._originalTitle;
  titleInput.className = 'super-title-input';
  titleElement.replaceWith(titleInput);

  // Reemplazamos el contenido por un textarea
  const textArea = document.createElement('textarea');
  textArea.value = article._originalText;
  textArea.className = 'super-text-input';
  textElement.replaceWith(textArea);

  // Actualizamos el texto de los botones:
  // - El botón de editar ahora muestra el icono de guardar (💾)
  // - El botón de eliminar cambia a "cancelar" (❌ Cancelar)
  editButton.textContent = '💾';
  deleteButton.textContent = '❌ Cancel';

  // Marcamos el artículo como "en edición" y guardamos referencias a los nuevos elementos
  article.classList.add('editing');
  article._titleInput = titleInput;
  article._textArea = textArea;
}

function guardarEdicion(article) {
  const editButton = article.querySelector('.super-edit');
  const deleteButton = article.querySelector('.super-delete');
  const titleInput = article._titleInput;
  const textArea = article._textArea;

  const newTitle = titleInput.value;
  const newText = textArea.value;
  const articleId = article.getAttribute('data-id');

  jQuery.post(botwriter_super_ajax.ajax_url, {
    action: 'botwriter_actualizar_articulo',
    id: articleId,
    title: newTitle,
    content: newText,
    _ajax_nonce: botwriter_super_ajax.nonce
  }, (response) => {
    if (response.success) {
      // Creamos nuevos elementos para mostrar el contenido actualizado
      const updatedTitle = document.createElement('h2');
      updatedTitle.className = 'super-title';
      updatedTitle.textContent = newTitle;
      titleInput.replaceWith(updatedTitle);

      const updatedText = document.createElement('p');
      updatedText.className = 'super-text';
      updatedText.textContent = newText;
      textArea.replaceWith(updatedText);

      // Restauramos los textos originales de los botones
      editButton.textContent = '✏️';
      deleteButton.textContent = '❌';

      // Se elimina el estado de edición y las propiedades temporales
      article.classList.remove('editing');
      delete article._titleInput;
      delete article._textArea;
      delete article._originalTitle;
      delete article._originalText;

      // Efecto visual de éxito (opcional)
      article.classList.add('update-success');
      setTimeout(() => {
        article.classList.remove('update-success');
      }, 2000);
    } else {
      alert('Error updating the article.');
    }
  });
}

function cancelarEdicion(article) {
  const editButton = article.querySelector('.super-edit');
  const deleteButton = article.querySelector('.super-delete');
  const titleInput = article._titleInput;
  const textArea = article._textArea;

  // Restauramos el título original
  const revertedTitle = document.createElement('h2');
  revertedTitle.className = 'super-title';
  revertedTitle.textContent = article._originalTitle;
  titleInput.replaceWith(revertedTitle);

  // Restauramos el contenido original
  const revertedText = document.createElement('p');
  revertedText.className = 'super-text';
  revertedText.textContent = article._originalText;
  textArea.replaceWith(revertedText);

  // Restauramos los textos de los botones y el estado del artículo
  editButton.textContent = '✏️';
  deleteButton.textContent = '❌';
  article.classList.remove('editing');

  // Eliminamos las propiedades temporales
  delete article._titleInput;
  delete article._textArea;
  delete article._originalTitle;
  delete article._originalText;
}

function eliminarArticulo(article) {
  const articleId = article.getAttribute('data-id');
  const confirmDelete = confirm("Are you sure you want to delete this article? This action cannot be undone.");

  if (confirmDelete) {
    jQuery.post(botwriter_super_ajax.ajax_url, {
      action: 'botwriter_eliminar_articulo',
      id: articleId,
      _ajax_nonce: botwriter_super_ajax.nonce
    }, (response) => {
      if (response.success) {
        // Aplicamos un efecto de desvanecimiento y removemos el artículo
        article.classList.add('delete-fadeout');
        setTimeout(() => {
          article.remove();
        }, 1000);
      } else {
        alert('Error deleting the article.');
        console.error(response);
      }
    });
  }
}

// final de edicion articulos




// cuando se carge el dom se ejecutara la funcion super1 cada 5segudnos:
let id_event_check = 0;
document.addEventListener("DOMContentLoaded", () => {
  botriter_chequear_super1();
  super1status = document.getElementById('super1status');
  var status = super1status.value;
  if (status == "inqueue") {  //completed tassk super1
    botwriter_countup();
  }
});

function botriter_chequear_super1() {
  super1status = document.getElementById('super1status');
  var status = super1status.value;
  if (status == "completed" || status == "inqueue") {  //completed tassk super1
    // desabilitar crear titulos
    document.getElementById('super1button_createtask').disabled = true;
    // desabilitar todo lo de form id="form_super1"
    document.querySelectorAll('#form_super1 input, #form_super1 select, #form_super1 textarea').forEach(element => {
      element.disabled = true;
    });
    id_event_check = setInterval(super1, 5000);
    super1();
    if (status == "completed") {
      document.getElementById('countup').innerHTML = "Finished creating titles.<br><br><strong>You can edit the titles now.</strong><p>Once the Super Task is saved, Botwriter will write the full articles and generate the images one by one as specified in the properties of this Super Task.</p>";
    }
  }
}



// crea una funcion que llame al action super_check1 de php
function super1() {
  console.log("super1 check");
  jQuery.post(botwriter_super_ajax.ajax_url, {
    action: 'botwriter_check_super1',
    _ajax_nonce: botwriter_super_ajax.nonce
  }, (response) => {
    console.log(response);
    if (response.success) {

      const resultadosDiv = document.getElementById('resultados');
      if (resultadosDiv.innerHTML.trim() === "") {
        resultadosDiv.innerHTML = response.data;
      }
      document.getElementById('super1status').value = "completed";
      clearInterval(id_event_check);
      document.getElementById('form_part2').style.display = "block";
      onDomContentLoaded();

    } else {
      if (response.data == "inqueue") {
        console.log("todavia no termino");
      }
      if (response.data == "error") {
        console.log("error en la creacion de titulos");
        document.getElementById('super1status').value = "error";
        clearInterval(id_event_check);
        const resultadosDiv = document.getElementById('resultados');
        resultadosDiv.innerHTML = "<h2>Error creating titles. Please try again, later.</h2>";
        botwriter_reset_super1(); // in botwriter.js

      }
    }
  });
}


// al pulsar el boton de crear titulos
jQuery(document).ready(function ($) {
  $('#super1button_createtask').on('click', function (e) {
    e.preventDefault();

    const prompt = $('#super1_prompt').val();

    // --- MANUAL MODE ---
    if (prompt === 'Manual') {
      var manualTitles = $('#super1_manual_titles').val();
      if (!manualTitles || manualTitles.trim() === '') {
        alert('Please enter at least one title.');
        return;
      }
      // desabilitar el boton y form
      $('#super1button_createtask').prop('disabled', true);
      document.querySelectorAll('#form_super1 input, #form_super1 select, #form_super1 textarea').forEach(element => {
        element.disabled = true;
      });
      document.getElementById('super1status').value = "inqueue";
      document.getElementById('countup').innerHTML = "Processing manual titles...";

      $.ajax({
        url: botwriter_super_ajax.ajax_url,
        type: 'POST',
        data: {
          action: 'botwriter_create_super1_manual',
          manual_titles: manualTitles,
          global_prompt: $('#super1_manual_global_prompt').val(),
          category_id: $('#category_id').val(),
          _ajax_nonce: botwriter_super_ajax.nonce
        },
        success: function (response) {
          console.log(response);
          if (response.success) {
            document.getElementById('super1status').value = "completed";
            const resultadosDiv = document.getElementById('resultados');
            resultadosDiv.innerHTML = response.data;
            document.getElementById('form_part2').style.display = "block";
            document.getElementById('countup').innerHTML = "Titles loaded successfully.<br><br><strong>You can edit the titles now.</strong><p>Once the Super Task is saved, Botwriter will write the full articles and generate the images one by one as specified in the properties of this Super Task.</p>";
            onDomContentLoaded();
          } else {
            alert('Error: ' + response.data);
            document.getElementById('super1status').value = "";
            document.getElementById('countup').innerHTML = "";
            $('#super1button_createtask').prop('disabled', false);
            document.querySelectorAll('#form_super1 input, #form_super1 select, #form_super1 textarea').forEach(element => {
              element.disabled = false;
            });
          }
        },
        error: function (xhr, status, error) {
          console.error('Manual AJAX request error:', status, error);
          alert('Error processing titles. Please try again.');
          $('#super1button_createtask').prop('disabled', false);
          document.querySelectorAll('#form_super1 input, #form_super1 select, #form_super1 textarea').forEach(element => {
            element.disabled = false;
          });
        }
      });
      return; // exit, don't run the auto logic
    }

    // --- AUTO MODE (existing logic) ---
    // desabilitar el boton
    $('#super1button_createtask').prop('disabled', true);
    // y todo el form primera parte
    document.querySelectorAll('#form_super1 input, #form_super1 select, #form_super1 textarea').forEach(element => {
      element.disabled = true;
    });

    // cuenta arriba
    document.getElementById('super1status').value = "inqueue";
    botwriter_countup();
    const custom_prompt = $('#super1_custom_prompt').val();
    const category_id = $('#category_id').val();
    const numarticles = $('#super1_numarticles').val();


    $.ajax({
      url: botwriter_super_ajax.ajax_url,
      type: 'POST',
      data: {
        action: 'botwriter_create_super1',
        prompt: prompt,
        custom_prompt: custom_prompt,
        category_id: category_id,
        numarticles: numarticles,
        _ajax_nonce: botwriter_super_ajax.nonce
      },
      success: function (response) {
        console.log(response);
        botriter_chequear_super1();
      },
      error: function (xhr, status, error) {
        console.error('AJAX request error:', status, error);
      }
    });
  });
});




// la funcion  cuenta arriba cada 1sg, se ejecuta al pulsar el boton de crear titulos
function botwriter_countup() {
  var i = 1;
  var interval = setInterval(function () {
    document.getElementById('countup').innerHTML = "Creating titles, please wait a moment (30-60sg)... " + i + " seconds";
    if (document.getElementById('super1status').value == "completed") {
      document.getElementById('countup').innerHTML = "Finished creating titles.<br><br><strong>You can edit the titles now.</strong><p>Once the Super Task is saved, Botwriter will write the full articles and generate the images one by one as specified in the properties of this Super Task.</p>";
      clearInterval(interval);
    }
    if (document.getElementById('super1status').value == "error") {
      document.getElementById('countup').innerHTML = "";
      clearInterval(interval);
    }

    i++;
  }, 1000);
}




function toggleCustomPromptInput() {
  var select = document.getElementById('super1_prompt');
  var customInput = document.getElementById('customPromptInput');
  var categoryInput = document.getElementById('div_category_id');
  var manualInput = document.getElementById('manualTitlesInput');
  var numArticlesDiv = document.getElementById('div_numarticles');

  // Hide all optional sections first
  customInput.style.display = 'none';
  categoryInput.style.display = 'none';
  if (manualInput) manualInput.style.display = 'none';
  if (numArticlesDiv) numArticlesDiv.style.display = 'block';

  if (select.value === 'Custom') {
    customInput.style.display = 'block';
    categoryInput.style.display = 'block';
  } else if (select.value === 'Manual') {
    if (manualInput) manualInput.style.display = 'block';
    categoryInput.style.display = 'block';
    if (numArticlesDiv) numArticlesDiv.style.display = 'none'; // not needed, count comes from titles
  }
}