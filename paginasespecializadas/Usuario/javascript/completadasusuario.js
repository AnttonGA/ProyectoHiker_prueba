$(document).ready(function() {
    loadCompletedRoutes();
});

function loadCompletedRoutes() {
    $.ajax({
        url: 'javascript/completadausuario.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            const routesContainer = $('#completedRoutes');
            routesContainer.empty();
            
            if (response.success) {
                if (response.message) {
                    // No hay rutas completadas, mostrar mensaje
                    routesContainer.append(`<p class="empty-message">${response.message}</p>`);
                } else if (response.routes && response.routes.length > 0) {
                    // Hay rutas completadas, mostrarlas
                    response.routes.forEach(function(route) {
                        routesContainer.append(createRouteCard(route));
                    });
                } else {
                    // Respuesta exitosa pero sin rutas ni mensaje
                    routesContainer.append('<p class="empty-message">No has completado ninguna ruta aún.</p>');
                }
            } else {
                // Error en la respuesta
                console.error("Error en la respuesta del servidor:", response.error);
                routesContainer.append(`<p class="error-message">Error al cargar las rutas completadas: ${response.error}</p>`);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error en la solicitud AJAX:", status, error);
            console.log("Respuesta del servidor:", xhr.responseText);
            
            let errorMessage = "Error al cargar las rutas completadas. ";
            
            try {
                const responseJson = JSON.parse(xhr.responseText);
                if (responseJson.error) {
                    errorMessage += responseJson.error;
                }
            } catch (e) {
                errorMessage += "Respuesta no válida del servidor.";
            }
            
            $('#completedRoutes').append(`<p class="error-message">${errorMessage}</p>`);
        }
    });
}

function createRouteCard(route) {
    const imagePath = route.imagenes && route.imagenes.length > 0 ? route.imagenes[0] : 'ruta_por_defecto.jpg';
    return `
        <div class="col-md-4 route-card">
            <div class="card">
                <img src="${imagePath}" class="card-img-top" alt="${route.title}">
                <div class="card-body">
                    <h5 class="card-title">${route.title}</h5>
                    <p class="card-text">${route.description || 'Sin descripción'}</p>
                    <a href="../skitouring/ruta.html?id=${route.id}" class="btn btn-primary">Ver Ruta</a>
                    ${createStatsButtons(route)}
                </div>
            </div>
        </div>
    `;
}

function createStatsButtons(route) {
    return `
        <div class="mt-2">
            <button class="btn btn-sm btn-outline-primary" title="Vistas">
                <i class="fas fa-eye"></i> ${route.view_count || 0}
            </button>
            <button class="btn btn-sm btn-outline-success" title="Descargas">
                <i class="fas fa-download"></i> ${route.download_count || 0}
            </button>
            <button class="btn btn-sm btn-outline-danger" title="Favoritos">
                <i class="fas fa-heart"></i> ${route.favorite_count || 0}
            </button>
            <button class="btn btn-sm btn-outline-info" title="Completadas">
                <i class="fas fa-check-circle"></i> ${route.complete_count || 0}
            </button>
        </div>
    `;
}