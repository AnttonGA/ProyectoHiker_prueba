document.addEventListener('DOMContentLoaded', function() {
    checkSession();
});

function checkSession() {
    fetch('javascript/usuario.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.logged_in) {
                    // El usuario ha iniciado sesión
                    document.getElementById('userGreeting').textContent = `Bienvenido, ${data.usuario}`;
                    document.getElementById('userMenu').style.display = 'block';
                    loadUserRoutes();
                    setupEventListeners();
                } else {
                    // El usuario no ha iniciado sesión
                    document.getElementById('loginPrompt').style.display = 'block';
                }
            } else {
                console.error('Error al verificar la sesión:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function setupEventListeners() {
    // Agregar event listeners a las pestañas
    document.querySelectorAll('.nav-link').forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const target = this.getAttribute('data-bs-target').replace('#', '');
            loadContent(target);
        });
    });

    // Event listener para crear nueva lista
    document.querySelector('#createListBtn').addEventListener('click', createNewList);
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRoutes(data.routes, 'userRoutes');
            } else {
                console.error('Error al cargar las rutas:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadFavoriteRoutes() {
    fetch('usuario.php?action=get_favorite_routes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRoutes(data.routes, 'favoriteRoutes');
            } else {
                console.error('Error al cargar las rutas favoritas:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadCompletedRoutes() {
    fetch('usuario.php?action=get_completed_routes')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRoutes(data.routes, 'completedRoutes');
            } else {
                console.error('Error al cargar las rutas completadas:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function loadUserLists() {
    fetch('usuario.php?action=get_user_lists')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayLists(data.lists);
            } else {
                console.error('Error al cargar las listas:', data.message);
            }
        })
        .catch(error => console.error('Error:', error));
}

function displayRoutes(routes, containerId) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    routes.forEach(route => {
        const routeElement = document.createElement('div');
        routeElement.className = 'col-md-4 mb-3';
        routeElement.innerHTML = `
            <div class="card">
                <img src="${route.imagen_path}" class="card-img-top" alt="${route.title}">
                <div class="card-body">
                    <h5 class="card-title">${route.title}</h5>
                    <p class="card-text">${route.description}</p>
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lista creada con éxito');
                loadUserLists();
            } else {
                alert('Error al crear la lista: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
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
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Lista eliminada con éxito');
                loadUserLists();
            } else {
                alert('Error al eliminar la lista: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    }
}

function viewRouteDetails(routeId) {
    // Implementa la lógica para ver los detalles de la ruta
    // Esto podría ser una redirección a una página de detalles o abrir un modal
    console.log('Ver detalles de la ruta:', routeId);
}