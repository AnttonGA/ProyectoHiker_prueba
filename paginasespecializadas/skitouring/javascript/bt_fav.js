document.addEventListener('DOMContentLoaded', function() {
    const btnFavorite = document.getElementById('btn-favorite');
    const urlParams = new URLSearchParams(window.location.search);
    const routeId = urlParams.get('id');

    if (!btnFavorite || !routeId) {
        console.error('Favorite button not found or invalid Route ID');
        return;
    }

    function updateFavoriteStatus(isFavorite) {
        btnFavorite.textContent = isFavorite ? 'Favorited' : 'Add to Favorites';
        btnFavorite.classList.toggle('btn-primary', isFavorite);
        btnFavorite.classList.toggle('btn-outline-primary', !isFavorite);
        btnFavorite.classList.toggle('favorited', isFavorite);
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
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const newFavoriteStatus = !btnFavorite.classList.contains('favorited');
                updateFavoriteStatus(newFavoriteStatus);
                animateButton();
                showFeedback(btnFavorite, newFavoriteStatus ? 'Added to favorites' : 'Removed from favorites', true);
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (error.message.includes('User not logged in')) {
                alert('Please log in to manage favorites.');
                window.location.href = '/paginasespecializadas/inicio_sesion/gestion.php';
            } else {
                showFeedback(btnFavorite, 'Error managing favorite: ' + error.message, false);
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