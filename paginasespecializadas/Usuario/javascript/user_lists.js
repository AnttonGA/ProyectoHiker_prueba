/**
 * user_lists.js — My Lists tab
 * Uses fetch() + Bootstrap modals (no jQuery, no alert/confirm).
 * Depends on escHtml() and showToast() from rutasusuario.js.
 */

/* ── State ───────────────────────────────────────────── */
let pendingDeleteId = null;

/* Static markup for an empty list inside the routes modal */
const LIST_EMPTY_HTML = `<div class="acct-empty acct-empty--compact">
  <span class="acct-empty__icon" aria-hidden="true"><i class="bi bi-bookmark"></i></span>
  <h3 class="acct-empty__title">Esta lista espera sus rutas</h3>
  <p class="acct-empty__text">Añade rutas a esta lista desde la ficha de cualquier ruta.</p>
  <div class="acct-empty__cta"><a href="../skitouring/skitouring.html" class="btn btn-outline-primary btn-sm">Explorar rutas</a></div>
</div>`;

/* ── Bootstrap modal references (lazy init) ─────────── */
function getModal(id) {
  return bootstrap.Modal.getOrCreateInstance(document.getElementById(id));
}

/* ── Load & render lists ─────────────────────────────── */
function loadUserLists() {
  const container = document.getElementById('userLists');
  container.innerHTML = `<div class="acct-loading">
    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    <span>Cargando listas…</span>
  </div>`;

  fetch('javascript/user_lists_handler.php?action=getLists')
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = `<div class="alert alert-warning mb-0">${escHtml(data.error)}</div>`;
        return;
      }
      if (!data.length) {
        container.innerHTML = `<div class="acct-empty">
          <span class="acct-empty__icon" aria-hidden="true"><i class="bi bi-bookmarks"></i></span>
          <h3 class="acct-empty__title">Agrupa las rutas a tu manera</h3>
          <p class="acct-empty__text">Crea una lista para un viaje, una temporada o un grupo de amigos, y ve añadiendo rutas mientras exploras.</p>
          <div class="acct-empty__cta">
            <button type="button" class="btn btn-primary acct-empty-create">
              <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Crear tu primera lista
            </button>
          </div>
        </div>`;
        container.querySelector('.acct-empty-create')
          ?.addEventListener('click', () => document.getElementById('createListBtn')?.click());
        return;
      }
      container.innerHTML = `<div class="acct-lists">` + data.map(list => `
        <div class="acct-list-item" data-list-id="${escHtml(list.id)}">
          <div class="acct-list-icon" aria-hidden="true"><i class="bi bi-bookmark"></i></div>
          <span class="acct-list-name">${escHtml(list.list_name)}</span>
          <div class="acct-list-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary btn-view-list"
                    data-id="${escHtml(list.id)}" data-name="${escHtml(list.list_name)}">
              Abrir
            </button>
            <button type="button" class="btn btn-sm acct-icon-btn btn-delete-list"
                    data-id="${escHtml(list.id)}" data-name="${escHtml(list.list_name)}"
                    aria-label="Borrar la lista ${escHtml(list.list_name)}" title="Borrar lista">
              <i class="bi bi-trash3" aria-hidden="true"></i>
            </button>
          </div>
        </div>`).join('') + `</div>`;

      // Wire up buttons
      container.querySelectorAll('.btn-view-list').forEach(btn => {
        btn.addEventListener('click', () => viewListRoutes(btn.dataset.id, btn.dataset.name));
      });
      container.querySelectorAll('.btn-delete-list').forEach(btn => {
        btn.addEventListener('click', () => confirmDeleteList(btn.dataset.id, btn.dataset.name));
      });
    })
    .catch(() => {
      container.innerHTML = `<div class="alert alert-danger">No se pudieron cargar las listas. Inténtalo de nuevo.</div>`;
    });
}

/* ── Create list ─────────────────────────────────────── */
function createNewList(name) {
  const btn = document.getElementById('createListSubmit');
  btn.disabled = true;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creando…';

  const body = new URLSearchParams({ list_name: name });
  fetch('javascript/user_lists_handler.php?action=createList', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        getModal('createListModal').hide();
        document.getElementById('listName').value = '';
        loadUserLists();
        showToast('Lista creada', 'success');
        document.dispatchEvent(new CustomEvent('kahir:listCreated'));
      } else {
        showToast(data.error ?? 'No se pudo crear la lista. Inténtalo de nuevo.', 'danger');
      }
    })
    .catch(() => showToast('Error de conexión. Inténtalo de nuevo.', 'danger'))
    .finally(() => {
      btn.disabled = false;
      btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Crear';
    });
}

/* ── View list routes ────────────────────────────────── */
function viewListRoutes(listId, listName) {
  const modalBody = document.getElementById('listRoutesContent');
  document.getElementById('listRoutesModalLabel').textContent = `Rutas de «${listName}»`;
  modalBody.innerHTML = `<div class="acct-loading">
    <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
    <span>Cargando…</span>
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
        modalBody.innerHTML = LIST_EMPTY_HTML;
        return;
      }
      modalBody.innerHTML = routes.map(route => `
        <div class="list-route-item" data-route-id="${escHtml(route.id)}">
          <span class="list-route-item__icon" aria-hidden="true"><i class="bi bi-signpost-2"></i></span>
          <a href="../skitouring/ruta.html?id=${encodeURIComponent(route.id)}" class="list-route-item__link">
            <span class="list-route-item__title">${escHtml(route.titulo)}</span>
            ${route.descripcion
              ? `<span class="list-route-item__desc">${escHtml(route.descripcion)}</span>`
              : ''}
          </a>
          <button type="button" class="btn btn-sm acct-icon-btn btn-remove-route flex-shrink-0"
                  data-list-id="${escHtml(listId)}" data-route-id="${escHtml(route.id)}"
                  title="Quitar de la lista" aria-label="Quitar ${escHtml(route.titulo)} de la lista">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </div>`).join('');

      modalBody.querySelectorAll('.btn-remove-route').forEach(btn => {
        btn.addEventListener('click', () => removeRouteFromList(btn.dataset.listId, btn.dataset.routeId, btn));
      });
    })
    .catch(() => {
      modalBody.innerHTML = `<div class="alert alert-danger">No se pudieron cargar las rutas. Inténtalo de nuevo.</div>`;
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
            modal.innerHTML = LIST_EMPTY_HTML;
          }
        }, 250);
      } else {
        showToast(data.error ?? 'No se pudo quitar la ruta. Inténtalo de nuevo.', 'danger');
        btn.disabled = false;
      }
    })
    .catch(() => { showToast('Error de conexión. Inténtalo de nuevo.', 'danger'); btn.disabled = false; });
}

/* ── Delete list (with modal confirm) ───────────────── */
function confirmDeleteList(listId, listName) {
  pendingDeleteId = listId;
  document.getElementById('confirmDeleteMsg').textContent =
    `Se borrará «${listName}». No se puede deshacer.`;
  getModal('confirmDeleteModal').show();
}

function performDeleteList(listId) {
  const body = new URLSearchParams({ list_id: listId });
  fetch('javascript/user_lists_handler.php?action=deleteList', { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        loadUserLists();
        showToast('Lista borrada', 'success');
      } else {
        showToast(data.error ?? 'No se pudo borrar la lista. Inténtalo de nuevo.', 'danger');
      }
    })
    .catch(() => showToast('Error de conexión. Inténtalo de nuevo.', 'danger'));
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
