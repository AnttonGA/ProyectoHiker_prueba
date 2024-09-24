document.addEventListener('DOMContentLoaded', function() {
    checkSession();
});

function checkSession() {
    fetch('javascript/usuario.php?action=check_session')
        .then(handleResponse)
        .then(data => {
            if (data.logged_in) {
                document.getElementById('userGreeting').textContent = `Bienvenido, ${data.usuario}`;
                document.getElementById('userMenu').style.display = 'block';
                document.getElementById('loginPrompt').style.display = 'none';
                loadUserRoutes();
                setupEventListeners();
            } else {
                document.getElementById('loginPrompt').style.display = 'block';
                document.getElementById('userMenu').style.display = 'none';
            }
        })
        .catch(handleError);
}

function setupEventListeners() {
    document.querySelectorAll('.nav-link').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('data-bs-target').replace('#', '');
            loadContent(target);
        });
    });

    document.getElementById('createListBtn').addEventListener('click', createNewList);
}

function loadContent(contentType) {
    switch(contentType) {
        case 'routes':
            loadUserRoutes();
            break;
        case 'favorites':
            loadFavoriteRoutes();
            break;
        case 'completed':
            loadCompletedRoutes();
            break;
        case 'lists':
            loadUserLists();
            break;
    }
}

function loadUserRoutes() {
    fetch('usuario.php?action=get_user_routes')
        .then(handleResponse)
        .then(data => displayRoutes(data.routes, 'userRoutes'))
        .catch(handleError);
}

function loadFavoriteRoutes() {
    fetch('usuario.php?action=get_favorite_routes')
        .then(handleResponse)
        .then(data => displayRoutes(data.routes, 'favoriteRoutes'))
        .catch(handleError);
}

function loadCompletedRoutes() {
    fetch('usuario.php?action=get_completed_routes')
        .then(handleResponse)
        .then(data => displayRoutes(data.routes, 'completedRoutes'))
        .catch(handleError);
}

function loadUserLists() {
    fetch('usuario.php?action=get_user_lists')
        .then(handleResponse)
        .then(data => displayLists(data.lists))
        .catch(handleError);
}

function displayRoutes(routes, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    if (routes.length === 0) {
        container.innerHTML = '<p>No hay rutas para mostrar.</p>';
        return;
    }
    routes.forEach(route => {
        const routeElement = document.createElement('div');
        routeElement.className = 'col-md-4 mb-3';
        routeElement.innerHTML = `
            <div class="card">
                <img src="${route.imagen_path}" class="card-img-top" alt="${route.title}">
                <div class="card-body">
                    <h5 class="card-title">${route.title}</h5>
                    <p class="card-text">${route.description.substring(0, 100)}...</p>
                    <a href="#" class="btn btn-primary" onclick="viewRouteDetails(${route.id})">Ver detalles</a>
                </div>
            </div>
        `;
        container.appendChild(routeElement);
    });
}

function displayLists(lists) {
    const container = document.getElementById('userLists');
    container.innerHTML = '';
    if (lists.length === 0) {
        container.innerHTML = '<p>No tienes listas creadas.</p>';
        return;
    }
    lists.forEach(list => {
        const listElement = document.createElement('div');
        listElement.className = 'list-group-item d-flex justify-content-between align-items-center';
        listElement.innerHTML = `
            ${list.list_name}
            <span class="badge bg-primary rounded-pill">${list.route_count} rutas</span>
            <button class="btn btn-sm btn-danger" onclick="deleteList(${list.id})">Eliminar</button>
        `;
        container.appendChild(listElement);
    });
}

function createNewList() {
    const listName = prompt('Ingrese el nombre de la nueva lista:');
    if (listName) {
        const formData = new FormData();
        formData.append('action', 'create_list');
        formData.append('list_name', listName);

        fetch('usuario.php', {
            method: 'POST',
            body: formData
        })
        .then(handleResponse)
        .then(data => {
            alert('Lista creada con éxito');
            loadUserLists();
        })
        .catch(handleError);
    }
}

function deleteList(listId) {
    if (confirm('¿Está seguro de que desea eliminar esta lista?')) {
        const formData = new FormData();
        formData.append('action', 'delete_list');
        formData.append('list_id', listId);

        fetch('usuario.php', {
            method: 'POST',
            body: formData
        })
        .then(handleResponse)
        .then(data => {
            alert('Lista eliminada con éxito');
            loadUserLists();
        })
        .catch(handleError);
    }
}

function viewRouteDetails(routeId) {
    // Implementa la lógica para ver los detalles de la ruta
    // Por ejemplo, redirigir a una página de detalles
    window.location.href = `ruta_detalle.php?id=${routeId}`;
}

function handleResponse(response) {
    if (!response.ok) {
        throw new Error('Network response was not ok');
    }
    return response.json();
}

function handleError(error) {
    console.error('Error:', error);
    alert('Ha ocurrido un error. Por favor, intenta de nuevo más tarde.');
}