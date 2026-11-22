document.addEventListener('DOMContentLoaded', function() {
    const btnFavorite = document.getElementById('btn-favorite');
    const urlParams = new URLSearchParams(window.location.search);
    const routeId = urlParams.get('id');

    if (!btnFavorite || !routeId) {
        console.error('Favorite button not found or invalid Route ID');
        return;
    }

    function updateFavoriteStatus(isFavorite) {
        // Conserva el icono: corazón relleno (óxido, ver ruta.css) cuando es favorito
        const icon = document.createElement('i');
        icon.className = isFavorite ? 'bi bi-heart-fill' : 'bi bi-heart';
        icon.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.className = 'k-act__label';
        label.textContent = isFavorite ? 'En favoritas' : 'Favorita';
        btnFavorite.replaceChildren(icon, label);
        btnFavorite.classList.toggle('favorited', isFavorite);
        btnFavorite.setAttribute('aria-pressed', isFavorite ? 'true' : 'false');
    }

    function animateButton() {
        btnFavorite.classList.add('animate-favorite');
        setTimeout(() => btnFavorite.classList.remove('animate-favorite'), 300);
    }

    function handleFavoriteAction() {
        btnFavorite.disabled = true;

        fetch('javascript/interaction_tracker.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify([{ type: 'favorite', routeId: routeId }]),
            credentials: 'include'
        })
        .then(response => {
            if (!response.ok) throw new Error('No se pudo guardar en favoritas. Inténtalo de nuevo.');
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const newFavoriteStatus = !btnFavorite.classList.contains('favorited');
                updateFavoriteStatus(newFavoriteStatus);
                animateButton();
                showFeedback(btnFavorite, newFavoriteStatus ? 'Añadida a favoritas' : 'Quitada de favoritas', true);
            } else {
                throw new Error(data.error || 'No se pudo guardar en favoritas. Inténtalo de nuevo.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (error.message.includes('Entra en tu cuenta')) {
                alert('Entra en tu cuenta para guardar favoritas.');
                window.location.href = '/paginasespecializadas/inicio_sesion/gestion.php';
            } else {
                showFeedback(btnFavorite, error.message || 'No se pudo guardar en favoritas. Inténtalo de nuevo.', false);
            }
        })
        .finally(() => {
            btnFavorite.disabled = false;
        });
    }

    btnFavorite.addEventListener('click', handleFavoriteAction);

    // Check initial favorite status
    fetch('javascript/interaction_tracker.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'check', routeId }),
        credentials: 'include'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateFavoriteStatus(data.isFavorite);
        }
    })
    .catch(error => console.error('Error checking favorite status:', error));

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