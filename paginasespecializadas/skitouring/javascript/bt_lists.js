document.addEventListener('DOMContentLoaded', function() {
    const btnAddToList = document.getElementById('btn-add-to-list');
    const listModal = new bootstrap.Modal(document.getElementById('listModal'));
    const createNewListBtn = document.getElementById('createNewList');

    if (btnAddToList) {
        btnAddToList.addEventListener('click', function() {
            loadUserLists();
            listModal.show();
        });
    }

    function loadUserLists() {
        const urlParams = new URLSearchParams(window.location.search);
        const routeId = urlParams.get('id');

        fetch(`javascript/get_user_lists.php?routeId=${routeId}`, {
            method: 'GET',
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            const existingLists = document.getElementById('existingLists');
            existingLists.innerHTML = '';
            data.lists.forEach(list => {
                addListToDOM(list);
            });
        })
        .catch(error => console.error('Error loading user lists:', error));
    }

    function addListToDOM(list) {
        const existingLists = document.getElementById('existingLists');
        const listItem = document.createElement('div');
        listItem.className = 'form-check mb-2';
        listItem.innerHTML = `
            <input class="form-check-input" type="checkbox" id="list${list.id}" value="${list.id}" ${list.is_route_in_list ? 'checked' : ''}>
            <label class="form-check-label" for="list${list.id}">
                ${list.list_name}
            </label>
            <span class="feedback-text ms-2"></span>
        `;
        existingLists.appendChild(listItem);
    }

    createNewListBtn.addEventListener('click', function() {
        const newListName = document.getElementById('newListName').value.trim();
        if (newListName) {
            createNewListAndAdd(newListName);
        } else {
            showFeedback(this, 'Please enter a list name', false);
        }
    });

    function createNewListAndAdd(listName) {
        const urlParams = new URLSearchParams(window.location.search);
        const routeId = urlParams.get('id');

        createNewListBtn.disabled = true;
        createNewListBtn.classList.add('btn-loading');
        createNewListBtn.textContent = 'Creating...';

        fetch('javascript/bt_lists.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'create', listName: listName, routeId: routeId }),
            credentials: 'include'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showFeedback(createNewListBtn, 'List created!', true);
                addListToDOM({
                    id: data.listId,
                    list_name: listName,
                    is_route_in_list: true
                });
                document.getElementById('newListName').value = '';
            } else {
                throw new Error(data.error || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error creating new list:', error);
            showFeedback(createNewListBtn, error.message, false);
        })
        .finally(() => {
            createNewListBtn.disabled = false;
            createNewListBtn.classList.remove('btn-loading');
            createNewListBtn.textContent = 'Create and Add';
        });
    }

    document.getElementById('existingLists').addEventListener('change', function(e) {
        if (e.target && e.target.type === 'checkbox') {
            const listId = e.target.value;
            const isChecked = e.target.checked;
            const urlParams = new URLSearchParams(window.location.search);
            const routeId = urlParams.get('id');

            const action = isChecked ? 'add' : 'remove';

            fetch('javascript/bt_lists.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: action, listId: listId, routeId: routeId }),
                credentials: 'include'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFeedback(e.target, isChecked ? 'Added!' : 'Removed!', true);
                } else {
                    throw new Error(data.error || 'Unknown error occurred');
                }
            })
            .catch(error => {
                console.error('Error managing list:', error);
                showFeedback(e.target, error.message, false);
                e.target.checked = !isChecked;
            });
        }
    });

    function showFeedback(element, message, isSuccess) {
        const feedbackSpan = element.nextElementSibling || document.createElement('span');
        feedbackSpan.textContent = message;
        feedbackSpan.className = `feedback-text ms-2 ${isSuccess ? 'text-success' : 'text-danger'}`;
        
        if (!element.nextElementSibling) {
            element.parentNode.insertBefore(feedbackSpan, element.nextSibling);
        }

        element.classList.add('animate-feedback');
        setTimeout(() => {
            element.classList.remove('animate-feedback');
        }, 500);

        setTimeout(() => {
            feedbackSpan.textContent = '';
        }, 3000);
    }
});