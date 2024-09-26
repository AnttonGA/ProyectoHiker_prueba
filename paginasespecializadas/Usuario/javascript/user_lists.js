$(document).ready(function() {
    loadUserLists();

    // Manejar el clic en el botón de crear nueva lista
    $('#createListBtn').click(function() {
        $('#createListModal').modal('show');
    });

    // Manejar el envío del formulario de creación de lista
    $('#createListForm').submit(function(e) {
        e.preventDefault();
        const listName = $('#listName').val().trim();
        if (listName) {
            createNewList(listName);
        } else {
            alert("Por favor, ingrese un nombre para la lista.");
        }
    });

    function loadUserLists() {
        $.ajax({
            url: 'javascript/user_lists_handler.php?action=getLists',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                const listsContainer = $('#userLists');
                listsContainer.empty();

                if (response.length === 0) {
                    listsContainer.append('<p class="text-muted">No tienes listas creadas aún.</p>');
                } else {
                    const listGroup = $('<div class="list-group">');
                    response.forEach(function(list) {
                        const listItem = $('<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">')
                            .append($('<h5 class="mb-1">').text(list.list_name))
                            .append($('<div class="btn-group" role="group">')
                                .append($('<button class="btn btn-primary btn-sm view-routes">').text('Ver Rutas')
                                    .click(function(e) {
                                        e.preventDefault();
                                        viewListRoutes(list.id, list.list_name);
                                    }))
                                .append($('<button class="btn btn-danger btn-sm delete-list">').html('<i class="fas fa-trash"></i>')
                                    .click(function(e) {
                                        e.preventDefault();
                                        deleteList(list.id, list.list_name);
                                    }))
                            );
                        listGroup.append(listItem);
                    });
                    listsContainer.append(listGroup);
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al cargar las listas:", error);
                $('#userLists').append('<p class="text-danger">Error al cargar las listas. Por favor, intenta de nuevo más tarde.</p>');
            }
        });
    }

    function createNewList(listName) {
        $.ajax({
            url: 'javascript/user_lists_handler.php?action=createList',
            type: 'POST',
            data: { list_name: listName },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#listName').val(''); // Limpiar el campo de entrada
                    $('#createListModal').modal('hide'); // Cerrar el modal
                    loadUserLists(); // Recargar las listas
                    alert('Lista creada con éxito.');
                } else {
                    console.error('Error al crear la lista:', response.error);
                    alert('Error al crear la lista: ' + (response.error || 'Ocurrió un error desconocido.'));
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al crear la lista:", error);
                alert('Error al crear la lista. Por favor, intenta de nuevo.');
            }
        });
    }

    function viewListRoutes(listId, listName) {
        $.ajax({
            url: 'javascript/user_lists_handler.php?action=getListRoutes&list_id=' + listId,
            type: 'GET',
            dataType: 'json',
            success: function(routes) {
                const modalBody = $('#listRoutesContent');
                modalBody.empty();
                $('#listRoutesModalLabel').text('Rutas en la lista: ' + listName);

                if (routes.length === 0) {
                    modalBody.append('<p class="text-muted">No hay rutas en esta lista.</p>');
                } else {
                    const routesList = $('<div class="list-group">');
                    routes.forEach(function(route) {
                        const routeItem = $('<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">')
                            .append($('<div>')
                                .append($('<h6 class="mb-1">').text(route.title))
                                .append($('<small class="text-muted">').text(route.description)))
                            .append($('<button class="btn btn-danger btn-sm remove-route">')
                                .html('<i class="fas fa-trash"></i>')
                                .click(function() {
                                    removeRouteFromList(listId, route.id, $(this).closest('.list-group-item'));
                                }));
                        routesList.append(routeItem);
                    });
                    modalBody.append(routesList);
                }

                $('#listRoutesModal').modal('show');
            },
            error: function(xhr, status, error) {
                console.error("Error al cargar las rutas de la lista:", error);
                alert('Error al cargar las rutas de la lista. Por favor, intenta de nuevo.');
            }
        });
    }

    function removeRouteFromList(listId, routeId, listItem) {
        $.ajax({
            url: 'javascript/user_lists_handler.php?action=removeRouteFromList',
            type: 'POST',
            data: { list_id: listId, route_id: routeId },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    listItem.fadeOut(300, function() {
                        $(this).remove();
                        if ($('#listRoutesContent .list-group-item').length === 0) {
                            $('#listRoutesContent').append('<p class="text-muted">No hay rutas en esta lista.</p>');
                        }
                    });
                } else {
                    console.error('Error al quitar la ruta de la lista:', response.error);
                    alert('Error al quitar la ruta de la lista.');
                }
            },
            error: function(xhr, status, error) {
                console.error("Error al quitar la ruta de la lista:", error);
                alert('Error al quitar la ruta de la lista. Por favor, intenta de nuevo.');
            }
        });
    }

    function deleteList(listId, listName) {
        if (confirm(`¿Estás seguro de que quieres eliminar la lista "${listName}"? Esta acción no se puede deshacer.`)) {
            $.ajax({
                url: 'javascript/user_lists_handler.php?action=deleteList',
                type: 'POST',
                data: { list_id: listId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadUserLists(); // Recargar las listas después de eliminar
                        alert('Lista eliminada con éxito.');
                    } else {
                        console.error('Error al eliminar la lista:', response.error);
                        alert('Error al eliminar la lista: ' + (response.error || 'Ocurrió un error desconocido.'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Error al eliminar la lista:", error);
                    alert('Error al eliminar la lista. Por favor, intenta de nuevo.');
                }
            });
        }
    }
});