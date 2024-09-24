document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#loginForm');
    const fields = ['usuario', 'password'];
    const fieldElements = {};

    fields.forEach(field => {
        const element = document.getElementById(field);
        if (element) {
            fieldElements[field] = element;
        } else {
            console.error(`Elemento no encontrado: ${field}`);
        }
    });

    function validateAndSubmit(event) {
        event.preventDefault();
        let isValid = true;

        resetearErrores();

        // Validaciones de campos
        fields.forEach(field => {
            if (fieldElements[field]) {
                if (fieldElements[field].value.trim() === '') {
                    showError(field, `${field.charAt(0).toUpperCase() + field.slice(1)} es requerido`);
                    isValid = false;
                }
            } else {
                console.error(`Campo no encontrado: ${field}`);
                isValid = false;
            }
        });

        if (isValid) {
            const formData = new FormData(form);

            fetch('acceso.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Ocurrió un error. Por favor, intenta de nuevo más tarde.', 'error');
            });
        }
    }

    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.error(`Campo no encontrado para mostrar error: ${fieldId}`);
            return;
        }
        let errorElement = field.nextElementSibling;

        if (!errorElement || !errorElement.classList.contains('error')) {
            errorElement = document.createElement('div');
            errorElement.className = 'error';
            field.parentNode.insertBefore(errorElement, field.nextSibling);
        }

        errorElement.innerText = message;
        errorElement.style.display = 'block';
        field.classList.add('is-invalid');

        setTimeout(() => hideError(fieldId), 3000);
    }

    function hideError(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.error(`Campo no encontrado para ocultar error: ${fieldId}`);
            return;
        }
        const errorElement = field.nextElementSibling;

        if (errorElement && errorElement.classList.contains('error')) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
        field.classList.remove('is-invalid');
    }

    function resetearErrores() {
        fields.forEach(field => hideError(field));
    }

    function showMessage(message, type) {
        const messageDiv = document.getElementById('message');
        if (!messageDiv) {
            console.error('Elemento de mensaje no encontrado');
            return;
        }
        messageDiv.innerHTML = `<p class="${type}">${message}</p>`;
        messageDiv.style.display = 'block';

        // Ocultar el mensaje después de 3 segundos
        setTimeout(() => {
            messageDiv.style.display = 'none';
        }, 3000);
    }

    if (form) {
        form.addEventListener('submit', validateAndSubmit);
    } else {
        console.error('Formulario no encontrado');
    }
});