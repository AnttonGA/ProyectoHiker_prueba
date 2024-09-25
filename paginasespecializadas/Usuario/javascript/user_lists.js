$(document).ready(function() {
    loadUserLists();

    $('#createListBtn').click(function() {
        const listName = prompt("Ingrese el nombre de la nueva lista:");
        if (listName) {
            createNewList(listName);
        }
    });

    function loadUserLists() {
        $.ajax({
            url: 'paginasespecializadas/skitouring/user_lists_handler.php?action=getLists',
            type: 'GET',
            dataType: 'json',
            success: function(lists) {
                const listsContainer = $('#userLists');
                listsContainer.empty();

                lists.forEach(function(list) {
                    const listItem = $('<div class="list-item mb-3">')
                        .append($('<h3>').text(list.list_name))
                        .append($('<button class="btn btn-primary btn-sm view-routes">').text('Ver Rutas')
                            .click(function() {
                                viewListRoutes(list.id, list.list_name);
                            }));
                    listsContainer.append(listItem);
                });
            },
            error: function(xhr, status, error) {
                console.error("Error al cargar las listas:", error);
            }
        });
    }

    function viewListRoutes(listId, listName) {
        $.ajax({
            url: 'paginasespecializadas/skitouring/user_lists_handler.php?action=getListRoutes&list_id=' + listId,
            type: 'GET',
            dataType: 'json',
            success: function(routes) {
                const modalBody = $('#listRoutesContent');
                modalBody.empty();
                $('#listRoutesModalLabel').text('Rutas en la lista: ' + listName);

                if (routes.length === 0) {
                    modalBody.append('<p>No hay rutas en esta lista.</p>');
                } else {
                    const routesList = $('<ul class="list-group">');
                    routes.forEach(function(route) {
                        const routeItem = $('<li class="list-group-item d-flex justify-content-between align-items-center">')
                            .append($('<span>').text(route.title))
                            .append($('<button class="btn btn-danger btn-sm remove-route">')
                                .text('Quitar')
                                .click(function() {
                                    removeRouteFromList(listId, route.id, $(this).parent());
                                }));
                        routesList.append(routeItem);
                    });
                    modalBody.append(routesList);
                }

                $('#listRoutesModal').modal('show');
            },
            error: function(xhr, status, error) {
                console.error("Error al cargar las rutas de la lista:", error);
            }
        });
    }

    function removeRouteFromList(listId, routeId, listItem) {
        $.ajax({
            url: 'paginasespecializadas/skitouring/user_lists_handler.php?action=removeRouteFromList',
            type: 'POST',
            data: { list_id: listId, route_id: routeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    listItem.remove();
                    if ($('#listRoutesContent .list-group-item').length === 0) {
                        $('#listRoutesContent').append('<p>No hay rutas en esta lista.</p>');
                    }
                } else {
                    alert('Error al quitar la ruta de la lista.');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al quitar la ruta de la lista:", error);
            }
        });
    }

    function createNewList(listName) {
        $.ajax({
            url: 'paginasespecializadas/skitouring/user_lists_handler.php?action=createList',
            type: 'POST',
            data: { list_name: listName },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    loadUserLists();
                } else {
                    alert('Error al crear la lista.');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al crear la lista:", error);
            }
        });
    }
});