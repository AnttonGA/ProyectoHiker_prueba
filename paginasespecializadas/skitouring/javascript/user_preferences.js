// update_preferences.js
document.addEventListener('DOMContentLoaded', function() {
    let interactionData = {
        timeOnSite: 0,
        pageViews: 0,
        // Agrega más propiedades según sea necesario
    };

    let startTime = Date.now();

    function updateInteractionData() {
        interactionData.timeOnSite = Date.now() - startTime;
        interactionData.pageViews++;
        // Actualiza otras propiedades según sea necesario
    }

    function sendDataToServer() {
        updateInteractionData();
        
        // Usamos sendBeacon para asegurar que los datos se envíen incluso si la página se está cerrando.
        // sendBeacon no admite cabeceras: el token CSRF va en el cuerpo (lo comprueba el servidor).
        const csrfToken = typeof window.kahirCsrfToken === 'function' ? window.kahirCsrfToken() : '';
        navigator.sendBeacon('javascript/user_preferences.php', JSON.stringify({
            interactionData: interactionData,
            csrf_token: csrfToken
        }));
    }

    // Evento que se dispara cuando la página se está descargando
    window.addEventListener('beforeunload', sendDataToServer);

    // También enviamos datos periódicamente
    setInterval(sendDataToServer, 5 * 60 * 1000); // Cada 5 minutos

    // Incrementar pageViews cuando se carga la página
    updateInteractionData();
});