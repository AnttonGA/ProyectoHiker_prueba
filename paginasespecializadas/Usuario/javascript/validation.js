document.addEventListener('DOMContentLoaded', function () {
  const form = document.getElementById('formularioRuta');

  // Event listeners for navigation buttons
  document.getElementById('nextStep1').addEventListener('click', function() {
      if (validateStep1()) {
          document.getElementById('step1').style.display = 'none';
          document.getElementById('step2').style.display = 'block';
      }
  });

  document.getElementById('prevStep1').addEventListener('click', function() {
      document.getElementById('step2').style.display = 'none';
      document.getElementById('step1').style.display = 'block';
  });

  document.getElementById('nextStep2').addEventListener('click', function() {
      if (validateStep2()) {
          document.getElementById('step2').style.display = 'none';
          document.getElementById('step3').style.display = 'block';
      }
  });

  document.getElementById('prevStep2').addEventListener('click', function() {
      document.getElementById('step3').style.display = 'none';
      document.getElementById('step2').style.display = 'block';
  });

  form.addEventListener('submit', function (event) {
      if (!validateStep1() || !validateStep2() || !validateStep3()) {
          event.preventDefault();
      }
  });

  function validateStep1() {
      let isValid = true;

      // Validate name
      const nombre = document.getElementById('nombre').value.trim();
      if (nombre === '') {
          showError('nombre', 'Por favor, ingresa el nombre de la ruta.');
          isValid = false;
      } else {
          hideError('nombre');
      }

      // Validate route type
      const tipoRuta = document.getElementById('tipo_ruta').value;
      if (tipoRuta === '') {
          showError('tipo_ruta', 'Por favor, selecciona el tipo de ruta.');
          isValid = false;
      } else {
          hideError('tipo_ruta');
      }

      // Validate route location
      const locationRuta = document.getElementById('location_ruta').value;
      if (locationRuta === '') {
          showError('location_ruta', 'Por favor, selecciona la ubicación de la ruta.');
          isValid = false;
      } else {
          hideError('location_ruta');
      }

      // Validate difficulty
      const dificultad = document.getElementById('dificultad').value;
      if (dificultad === '') {
          showError('dificultad', 'Por favor, selecciona la dificultad de la ruta.');
          isValid = false;
      } else {
          hideError('dificultad');
      }

      return isValid;
  }

  function validateStep2() {
      let isValid = true;

      // Validate description
      const descripcion = document.getElementById('descripcion').value.trim();
      if (descripcion === '') {
          showError('descripcion', 'Por favor, ingresa una descripción de la ruta.');
          isValid = false;
      } else {
          hideError('descripcion');
      }

      // Validate question 1
      const pregunta1 = document.getElementById('pregunta1').value.trim();
      if (pregunta1 === '') {
          showError('pregunta1', 'Por favor, responde la pregunta 1.');
          isValid = false;
      } else {
          hideError('pregunta1');
      }

      // Validate question 2
      const pregunta2 = document.getElementById('pregunta2').value.trim();
      if (pregunta2 === '') {
          showError('pregunta2', 'Por favor, responde la pregunta 2.');
          isValid = false;
      } else {
          hideError('pregunta2');
      }

      // Validate question 3
      const pregunta3 = document.getElementById('pregunta3').value.trim();
      if (pregunta3 === '') {
          showError('pregunta3', 'Por favor, responde la pregunta 3.');
          isValid = false;
      } else {
          hideError('pregunta3');
      }

      // Validate question 4
      const pregunta4 = document.getElementById('pregunta4').value.trim();
      if (pregunta4 === '') {
          showError('pregunta4', 'Por favor, responde la pregunta 4.');
          isValid = false;
      } else {
          hideError('pregunta4');
      }

      return isValid;
  }

  function validateStep3() {
      let isValid = true;

      // Validate GPX file
      const archivoGPX = document.getElementById('archivo_gpx');
      if (archivoGPX.files.length === 0) {
          showError('archivo_gpx', 'Por favor, selecciona un archivo GPX.');
          isValid = false;
      } else {
          const extension = archivoGPX.files[0].name.split('.').pop().toLowerCase();
          if (extension !== 'gpx') {
              showError('archivo_gpx', 'El archivo debe tener extensión GPX.');
              isValid = false;
          } else {
              hideError('archivo_gpx');
          }
      }

      // Validate route images
      const imagenes = document.getElementById('imagenes');
      if (imagenes.files.length === 0) {
          showError('imagenes', 'Por favor, selecciona imágenes de la ruta.');
          isValid = false;
      } else {
          hideError('imagenes');
      }

      return isValid;
  }

  function showError(fieldId, message) {
    const field = document.getElementById(fieldId);
    const errorElement = field.nextElementSibling;

    errorElement.innerText = message;
    errorElement.style.display = 'block';
    field.classList.add('is-invalid');

    setTimeout(function () {
        hideError(fieldId);
    }, 3000);
}

function hideError(fieldId) {
    const field = document.getElementById(fieldId);
    const errorElement = field.nextElementSibling;

    errorElement.innerText = '';
    errorElement.style.display = 'none';
    field.classList.remove('is-invalid');
}

});