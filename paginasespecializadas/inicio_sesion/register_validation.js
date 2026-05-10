document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('#registroForm');
    const fields = ['nombre', 'apellidos', 'mail', 'usuarioa', 'passworda', 'telefono'];
    const fieldElements = {};

    // Verificar cada campo y registrar si no se encuentra
    fields.forEach(field => {
        const element = document.getElementById(field);
        if (element) {
            fieldElements[field] = element;
        } else {
            console.error(`Elemento no encontrado: ${field}`);
        }
    });

    const terminos = document.getElementById('terminos');
    if (!terminos) {
        console.error('Elemento no encontrado: terminos');
    }

    function validarUsuarioEmail() {
        return new Promise((resolve, reject) => {
            const usuario = fieldElements.usuarioa ? fieldElements.usuarioa.value.trim() : '';
            const email = fieldElements.mail ? fieldElements.mail.value.trim() : '';

            fetch('validar_usuario_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ usuario, email }),
            })
            .then(response => response.json())
            .then(data => resolve(data))
            .catch(error => reject(error));
        });
    }

    function validarFormulario(event) {
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

        // Validaciones específicas
        if (fieldElements.mail && !validarEmail(fieldElements.mail.value.trim())) {
            showError('mail', 'Formato de email inválido');
            isValid = false;
        }

        // Validación de usuario
        if (fieldElements.usuarioa) {
            const usuario = fieldElements.usuarioa.value.trim();
            if (usuario.length < 4) {
                showError('usuarioa', 'El usuario debe tener al menos 4 caracteres');
                isValid = false;
            }
        }

        // Validación de contraseña
        if (fieldElements.passworda) {
            const password = fieldElements.passworda.value.trim();
            if (password.length < 8) {
                showError('passworda', 'La contraseña debe tener al menos 8 caracteres');
                isValid = false;
            } else if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                showError('passworda', 'La contraseña debe incluir mayúsculas, minúsculas y números');
                isValid = false;
            }
        }

        if (terminos && !terminos.checked) {
            showError('terminos', 'Debe aceptar los términos y condiciones');
            isValid = false;
        }

        if (isValid) {
            validarUsuarioEmail()
                .then(response => {
                    if (response.usuario_existe) {
                        showError('usuarioa', 'El usuario ya está registrado');
                        isValid = false;
                    }
                    if (response.email_existe) {
                        showError('mail', 'El email ya está registrado');
                        isValid = false;
                    }
                    if (isValid) {
                        showNotification('Formulario enviado con éxito', 'success');
                        form.submit();
                    }
                })
                .catch(error => {
                    showNotification('Error al validar usuario y email', 'error');
                });
        }
    }

    function checkAvailability(field) {
        const usuario = fieldElements.usuarioa ? fieldElements.usuarioa.value.trim() : '';
        const email = fieldElements.mail ? fieldElements.mail.value.trim() : '';

        fetch('validar_usuario_email.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ usuario, email }),
        })
        .then(response => response.json())
        .then(data => {
            if (field === 'usuarioa' && data.usuario_existe) {
                showError(field, 'El usuario ya está registrado');
            } else if (field === 'mail' && data.email_existe) {
                showError(field, 'El email ya está registrado');
            } else {
                hideError(field);
            }
        })
        .catch(error => {
            console.error('Error al validar ' + field + ':', error);
        });
    }

    function showError(fieldId, message) {
        const field = document.getElementById(fieldId);
        if (!field) {
            console.error(`Campo no encontrado para mostrar error: ${fieldId}`);
            return;
        }
        let errorElement = field.nextElementSibling;

        if (!errorElement || !errorElement.classList.contains('text-danger')) {
            errorElement = document.createElement('div');
            errorElement.className = 'text-danger';
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

        if (errorElement && errorElement.classList.contains('text-danger')) {
            errorElement.innerText = '';
            errorElement.style.display = 'none';
        }
        field.classList.remove('is-invalid');
    }

    function resetearErrores() {
        fields.forEach(field => hideError(field));
        hideError('terminos');
    }

    function validarEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function showNotification(message, type) {
        const notification = document.createElement('div');
        notification.textContent = message;
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} notification`;
        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    if (form) {
        form.addEventListener('submit', validarFormulario);
    } else {
        console.error('Formulario no encontrado');
    }

    // Añadir eventos blur a los campos de usuario y email
    if (fieldElements.usuarioa) {
        fieldElements.usuarioa.addEventListener('blur', () => checkAvailability('usuarioa'));
    }
    if (fieldElements.mail) {
        fieldElements.mail.addEventListener('blur', () => checkAvailability('mail'));
    }
});