/**
 * user_lists.js — My Lists tab
 * Uses fetch() + Bootstrap modals (no jQuery, no alert/confirm).
 * Depends on escHtml() and showToast() from rutasusuario.js.
 */

/* ── State ───────────────────────────────────────────── */
let pendingDeleteId = null;

/* ── Bootstrap modal references (lazy init) ─────────── */
function getModal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

/* ── Load & render lists ─────────────────────────────── */
function loadUserLists() {
  const container = document.getElementById('userLists');
  container.innerHTML = `<div class="text-center py-4">
    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    <span class="ms-2 text-muted">Loading lists…</span>
  </div>`;

  fetch('javascript/user_lists_handler.php?action=getLists')
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = `<div class="alert alert-warning">${escHtml(data.error)}</div>`;
        return;
      }
      if (!data.length) {
        container.innerHTML = `<div class="acct-empty">
          <i class="bi bi-bookmark"></i>
          <p>No lists yet. Create one to organise your favourite routes!</p>
        </div>`;
        return;
      }
      container.innerHTML = data.map(list => `
        <div class="acct-list-item" data-list-id="${list.id}">
          <div class="acct-list-icon"><i class="bi bi-bookmark-fill"></i></div>
          <span class="acct-list-name">${escHtml(list.list_name)}</span>
          <div class="d-flex gap-1 flex-shrink-0">
            <button class="btn btn-sm btn-outline-primary btn-view-list"
                    data-id="${list.id}" data-name="${escHtml(list.list_name)}">
              <i class="bi bi-eye me-1"></i>View
            </button>
            <button class="btn btn-sm btn-outline-danger btn-delete-list"
                    data-id="${list.id}" data-name="${escHtml(list.list_name)}">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>`).join('');

      // Wire up buttons
      container.querySelectorAll('.btn-view-list').forEach(btn => {
        btn.addEventListener('click', () => viewListRoutes(btn.dataset.id, btn.dataset.name));
      });
      container.querySelectorAll('.btn-delete-list').forEach(btn => {
        btn.addEventListener('click', () => confirmDeleteList(btn.dataset.id, btn.dataset.name));
      });
    })
    .catch(() => {
      container.innerHTML = `<div class="alert alert-danger">Could not load lists. Please try again.</div>`;
    });
}

/* ── Create list ─────────────────────────────────────── */
function createNewList(name) {
  const btn = document.getElementById('createListSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating…';

  const body = new URLSearchParams({ list_name: name });
  fetch('javascript/user_lists_handler.php?action=createList', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        getModal('createListModal').hide();
        document.getElementById('listName').value = '';
        loadUserLists();
        showToast('List created successfully!', 'success');
        document.dispatchEvent(new CustomEvent('kahir:listCreated'));
      } else {
        showToast(data.error ?? 'Error creating list.', 'danger');
      }
    })
    .catch(() => showToast('Network error. Please try again.', 'danger'))
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Create';
    });
}

/* ── View list routes ────────────────────────────────── */
function viewListRoutes(listId, listName) {
  const modalBody = document.getElementById('listRoutesContent');
  document.getElementById('listRoutesModalLabel').textContent = `Routes in "${listName}"`;
  modalBody.innerHTML = `<div class="text-center py-3">
    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    <span class="ms-2 text-muted">Loading…</span>
  </div>`;
  getModal('listRoutesModal').show();

  fetch(`javascript/user_lists_handler.php?action=getListRoutes&list_id=${encodeURIComponent(listId)}`)
    .then(r => r.json())
    .then(routes => {
      if (routes.error) {
        modalBody.innerHTML = `<div class="alert alert-warning">${escHtml(routes.error)}</div>`;
        return;
      }
      if (!routes.length) {
        modalBody.innerHTML = `<p class="text-muted p-3 mb-0">No routes in this list yet.</p>`;
        return;
      }
      modalBody.innerHTML = routes.map(route => `
        <div class="list-route-item" data-route-id="${route.id}">
          <a href="../skitouring/ruta.html?id=${route.id}" class="text-decoration-none flex-grow-1 min-width-0">
            <div class="list-route-item__title">${escHtml(route.titulo)}</div>
            ${route.descripcion
              ? `<div class="list-route-item__desc">${escHtml(route.descripcion)}</div>`
              : ''}
          </a>
          <button class="btn btn-sm btn-outline-danger btn-remove-route flex-shrink-0"
                  data-list-id="${listId}" data-route-id="${route.id}" title="Remove from list">
            <i class="bi bi-trash"></i>
          </button>
        </div>`).join('');

      modalBody.querySelectorAll('.btn-remove-route').forEach(btn => {
        btn.addEventListener('click', () => removeRouteFromList(btn.dataset.listId, btn.dataset.routeId, btn));
      });
    })
    .catch(() => {
      modalBody.innerHTML = `<div class="alert alert-danger">Could not load routes.</div>`;
    });
}

/* ── Remove route from list ─────────────────────────── */
function removeRouteFromList(listId, routeId, btn) {
  btn.disabled = true;
  const body = new URLSearchParams({ list_id: listId, route_id: routeId });
  fetch('javascript/user_lists_handler.php?action=removeRouteFromList', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        const item = btn.closest('.list-route-item');
        item.style.transition = 'opacity .25s';
        item.style.opacity = '0';
        setTimeout(() => {
          item.remove();
          const modal = document.getElementById('listRoutesContent');
          if (!modal.querySelector('.list-route-item')) {
            modal.innerHTML = `<p class="text-muted p-3 mb-0">No routes in this list yet.</p>`;
          }
        }, 250);
      } else {
        showToast(data.error ?? 'Error removing route.', 'danger');
        btn.disabled = false;
      }
    })
    .catch(() => { showToast('Network error.', 'danger'); btn.disabled = false; });
}

/* ── Delete list (with modal confirm) ───────────────── */
function confirmDeleteList(listId, listName) {
  pendingDeleteId = listId;
  document.getElementById('confirmDeleteMsg').textContent =
    `Are you sure you want to delete "${listName}"? This action cannot be undone.`;
  getModal('confirmDeleteModal').show();
}

function performDeleteList(listId) {
  const body = new URLSearchParams({ list_id: listId });
  fetch('javascript/user_lists_handler.php?action=deleteList', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        loadUserLists();
        showToast('List deleted.', 'success');
      } else {
        showToast(data.error ?? 'Error deleting list.', 'danger');
      }
    })
    .catch(() => showToast('Network error.', 'danger'));
}

/* ── Event listeners ─────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {

  // Open create modal
  document.getElementById('createListBtn')?.addEventListener('click', () => {
    document.getElementById('listName').value = '';
    document.getElementById('listName').classList.remove('is-invalid');
    getModal('createListModal').show();
  });

  // Submit create
  document.getElementById('createListSubmit')?.addEventListener('click', () => {
    const input = document.getElementById('listName');
    const name  = input.value.trim();
    if (!name) {
      input.classList.add('is-invalid');
      input.focus();
      return;
    }
    input.classList.remove('is-invalid');
    createNewList(name);
  });

  // Also allow Enter in the input
  document.getElementById('listName')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') document.getElementById('createListSubmit').click();
  });

  // Confirm delete OK button
  document.getElementById('confirmDeleteOk')?.addEventListener('click', () => {
    if (pendingDeleteId) {
      const id = pendingDeleteId;
      pendingDeleteId = null;
      getModal('confirmDeleteModal').hide();
      performDeleteList(id);
    }
  });

  // Load lists when tab is shown (lazy)
  const listsTab = document.getElementById('lists-tab');
  if (listsTab) {
    let loaded = false;
    listsTab.addEventListener('shown.bs.tab', () => {
      if (!loaded) { loaded = true; loadUserLists(); }
    });
  }
});
