document.addEventListener('DOMContentLoaded', function() {
    const btnCompleted = document.getElementById('btn-completed');

    if (btnCompleted) {
        btnCompleted.addEventListener('click', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const routeId = urlParams.get('id');

            if (!routeId) {
                console.error('Route ID not found');
                return;
            }

            toggleCompleted(routeId, this);
        });

        // Verificar el estado inicial de completado
        checkCompletedStatus();
    }

    function toggleCompleted(routeId, button) {
        button.disabled = true;
        button.classList.add('btn-loading');

        fetch('javascript/interaction_tracker.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify([{ type: 'complete', routeId: routeId }]),
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('No se pudo conectar. Inténtalo de nuevo.');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const isCompleted = !button.classList.contains('btn-success');
                updateButtonState(button, isCompleted);
                showFeedback(button, isCompleted ? 'Marcada como hecha' : 'Marcada como pendiente', true);
            } else {
                throw new Error(data.error || 'No se pudo actualizar la ruta. Inténtalo de nuevo.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showFeedback(button, error.message, false);
        })
        .finally(() => {
            button.disabled = false;
            button.classList.remove('btn-loading');
        });
    }

    function updateButtonState(button, isCompleted) {
        // Avisar al panel de valoración (route_feedback.js)
        document.dispatchEvent(new CustomEvent('kahir:completed', { detail: { isCompleted: !!isCompleted } }));
        if (isCompleted) {
            setButtonContent(button, 'bi-check-circle-fill', 'Completada');
            button.classList.remove('btn-outline-success');
            button.classList.add('btn-success');
        } else {
            setButtonContent(button, 'bi-check-circle', 'Marcar como hecha');
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-success');
        }
        button.setAttribute('aria-pressed', isCompleted ? 'true' : 'false');
    }

    // Cambia el texto sin perder el icono
    function setButtonContent(button, iconClass, text) {
        const icon = document.createElement('i');
        icon.className = `bi ${iconClass}`;
        icon.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'k-act__label';
        label.textContent = text;
        button.replaceChildren(icon, label);
    }

    function checkCompletedStatus() {
        const urlParams = new URLSearchParams(window.location.search);
        const routeId = urlParams.get('id');

        fetch('javascript/interaction_tracker.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check', routeId }),
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateButtonState(btnCompleted, data.isCompleted);
            }
        })
        .catch(error => console.error('Error checking completed status:', error));
    }

    function showFeedback(element, message, isSuccess) {
        const feedbackSpan = document.createElement('span');
        feedbackSpan.textContent = message;
        feedbackSpan.className = `feedback-text ms-2 ${isSuccess ? 'text-success' : 'text-danger'}`;
        
        element.parentNode.insertBefore(feedbackSpan, element.nextSibling);

        element.classList.add('animate-feedback');
        setTimeout(() => {
            element.classList.remove('animate-feedback');
        }, 500);

        setTimeout(() => {
            feedbackSpan.remove();
        }, 3000);
    }
});