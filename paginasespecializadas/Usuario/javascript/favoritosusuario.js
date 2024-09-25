$(document).ready(function() {
    loadFavoriteRoutes();
});

function loadFavoriteRoutes() {
    $.ajax({
        url: 'javascript/favoritosusuario.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            const routesContainer = $('#favoriteRoutes');
            routesContainer.empty();
            
            if (response.success) {
                if (response.message) {
                    // No hay rutas favoritas, mostrar mensaje
                    routesContainer.append(`<p class="empty-message">${response.message}</p>`);
                } else if (response.routes && response.routes.length > 0) {
                    // Hay rutas favoritas, mostrarlas
                    response.routes.forEach(function(route) {
                        routesContainer.append(createRouteCard(route, false));
                    });
                } else {
                    // Respuesta exitosa pero sin rutas ni mensaje
                    routesContainer.append('<p class="empty-message">No se encontraron rutas favoritas.</p>');
                }
            } else {
                // Error en la respuesta
                console.error("Error en la respuesta del servidor:", response.error);
                routesContainer.append(`<p class="error-message">Error al cargar las rutas favoritas: ${response.error}</p>`);
            }
        },
        error: function(xhr, status, error) {
            console.error("Error en la solicitud AJAX:", status, error);
            console.log("Respuesta del servidor:", xhr.responseText);
            
            let errorMessage = "Error al cargar las rutas favoritas. ";
            
            try {
                const responseJson = JSON.parse(xhr.responseText);
                if (responseJson.error) {
                    errorMessage += responseJson.error;
                }
            } catch (e) {
                errorMessage += "Respuesta no válida del servidor.";
            }
            
            $('#favoriteRoutes').append(`<p class="error-message">${errorMessage}</p>`);
        }
    });
}

function createRouteCard(route, isUserRoute) {
    const imagePath = route.imagenes && route.imagenes.length > 0 ? route.imagenes[0] : 'ruta_por_defecto.jpg';
    return `
        <div class="col-md-4 route-card">
            <div class="card">
                <img src="${imagePath}" class="card-img-top" alt="${route.title}">
                <div class="card-body">
                    <h5 class="card-title">${route.title}</h5>
                    <p class="card-text">${route.description || 'Sin descripción'}</p>
                    <a href="../skitouring/ruta.html?id=${route.id}" class="btn btn-primary">Ver Ruta</a>
                    ${isUserRoute ? `<a href="edit_route.php?id=${route.id}" class="btn btn-secondary">Editar</a>` : ''}
                </div>
            </div>
        </div>
    `;
}