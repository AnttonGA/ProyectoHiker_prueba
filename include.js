// Carga el menú y el pie comunes en #menu y #footer.
// La ruta base se calcula a partir de la URL de este mismo script (que está en la raíz del
// proyecto), en lugar de contar segmentos de la URL de la página: así funciona igual en local
// (subcarpeta de Laragon/XAMPP) y en el hosting, sea cual sea la profundidad de la página.
(function () {
    const scriptUrl = document.currentScript ? document.currentScript.src : '';
    const basePath = scriptUrl
        ? scriptUrl.substring(0, scriptUrl.lastIndexOf('/') + 1) + 'includes/'
        : 'includes/';

    function loadHTML(elementId, file) {
        const target = document.getElementById(elementId);
        if (!target) {
            return;
        }
        fetch(file, { credentials: 'same-origin' })
            .then(response => {
                if (!response.ok) {
                    throw new Error(response.status + ' ' + response.statusText);
                }
                return response.text();
            })
            .then(html => {
                // Contenido propio del servidor (menu.php escapa los datos del usuario)
                target.innerHTML = html;
            })
            .catch(error => console.error(`Failed to load ${file}: ${error.message}`));
    }

    function init() {
        loadHTML('menu', basePath + 'menu.php');
        loadHTML('footer', basePath + 'footer.php');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
