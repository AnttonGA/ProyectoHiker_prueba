/**
 * rutasusuario.js — My Routes tab
 * NOTE: shared helpers (buildRouteCard, escHtml, renderSkeletons,
 * renderPagination, showToast, DIFF_*, ACT_LABEL) are defined here
 * and reused by favoritosusuario.js / completadasusuario.js.
 */

/* ── Shared dictionaries ─────────────────────────────── */
const DIFF_LABEL = { easy: 'Easy', moderate: 'Moderate', hard: 'Hard' };
const DIFF_CLASS = { easy: 'diff--easy', moderate: 'diff--moderate', hard: 'diff--hard' };
const ACT_LABEL  = {
  hiking:        'Hiking',
  ski_touring:   'Ski Touring',
  snowshoeing:   'Snowshoeing',
  climbing:      'Climbing',
  via_ferrata:   'Via Ferrata',
  cycle_touring: 'Cycling',
  mtb:           'MTB',
};

/* ── Shared helpers ──────────────────────────────────── */
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str ?? '';
  return d.innerHTML;
}

function formatDate(iso) {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return iso; }
}

/**
 * Builds a route card using the shared .route-card CSS system.
 * @param {object} route      Route data from API
 * @param {object} [opts]     { isOwner, showCompleted }
 */
function buildRouteCard(route, opts = {}) {
  const { isOwner = false, showCompleted = false } = opts;
  const img     = route.imagenes?.[0] ?? '';
  const diffCls = DIFF_CLASS[route.dificultad] ?? 'diff--easy';
  const diffLbl = DIFF_LABEL[route.dificultad] ?? (route.dificultad ?? '—');
  const actLbl  = ACT_LABEL[route.tipo_ruta]   ?? (route.tipo_ruta  ?? '—');
  const elev    = route.desnivel_positivo ? `${Math.round(route.desnivel_positivo)} m`       : '—';
  const dist    = route.distancia_km      ? `${parseFloat(route.distancia_km).toFixed(1)} km` : '—';
  const dur     = route.duracion_horas    ? `${parseFloat(route.duracion_horas).toFixed(1)} h` : '—';

  const completedBadge = (showCompleted && route.fecha_completada)
    ? `<p class="completed-date mt-1 mb-0">
         <i class="bi bi-check-circle-fill"></i> Completed on ${formatDate(route.fecha_completada)}
       </p>`
    : '';

  /* Stats + optional Edit live OUTSIDE the <a> so nesting is valid */
  const meta = `
    <div class="route-card-meta mt-2">
      <span class="badge bg-light text-muted border"><i class="bi bi-eye"></i> ${route.view_count ?? 0}</span>
      <span class="badge bg-light text-muted border"><i class="bi bi-download"></i> ${route.download_count ?? 0}</span>
      <span class="badge bg-light text-muted border"><i class="bi bi-heart"></i> ${route.favorite_count ?? 0}</span>
      <span class="badge bg-light text-muted border"><i class="bi bi-check-circle"></i> ${route.complete_count ?? 0}</span>
      ${isOwner
        ? `<a href="edit_route.php?id=${route.id}" class="badge bg-primary-subtle text-primary border ms-auto text-decoration-none">
             <i class="bi bi-pencil"></i> Edit
           </a>`
        : ''}
    </div>`;

  /* No wrapper div — route-card + meta are direct children of .col */
  return `
    <div class="col">
      <a href="../skitouring/ruta.html?id=${route.id}" class="route-card">
        <div class="route-card__img-wrap">
          <img src="${img}" alt="${escHtml(route.titulo)}" loading="lazy"
               onerror="this.style.visibility='hidden'">
          <span class="route-card__diff ${diffCls}">${diffLbl}</span>
          <span class="route-card__activity">${actLbl}</span>
        </div>
        <div class="route-card__body">
          <h3 class="route-card__title">${escHtml(route.titulo)}</h3>
          <div class="route-card__stats">
            <span class="route-card__stat"><i class="bi bi-arrow-up-short"></i>${elev}</span>
            <span class="route-card__stat"><i class="bi bi-arrows-expand"></i>${dist}</span>
            <span class="route-card__stat"><i class="bi bi-clock"></i>${dur}</span>
          </div>
        </div>
      </a>
      ${meta}
      ${completedBadge}
    </div>`;
}

/** Renders N skeleton cards inside a container */
function renderSkeletons(containerId, count = 6) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = Array.from({ length: count }, () =>
    `<div class="col"><div class="skeleton skeleton-card"></div></div>`
  ).join('');
}

/**
 * Smart pagination — shows at most 7 buttons:
 * [«] [1] [...] [p-2][p-1][p][p+1][p+2] [...] [last] [»]
 */
function renderPagination(containerId, currentPage, total, perPage, loadFn) {
  const nav = document.getElementById(containerId);
  if (!nav) return;
  const totalPages = Math.ceil(total / perPage);
  if (totalPages <= 1) { nav.innerHTML = ''; return; }

  const DELTA = 2; // pages shown on each side of current

  // Build the raw set of page numbers to show
  const pages = new Set();
  pages.add(1);
  pages.add(totalPages);
  for (let i = Math.max(1, currentPage - DELTA); i <= Math.min(totalPages, currentPage + DELTA); i++) {
    pages.add(i);
  }
  const sorted = [...pages].sort((a, b) => a - b);

  // Build HTML items
  let items = '';
  let prev = 0;
  for (const p of sorted) {
    if (p - prev > 1) {
      // Gap → insert ellipsis
      items += `<li class="page-item disabled"><span class="page-link px-2">…</span></li>`;
    }
    items += `<li class="page-item ${p === currentPage ? 'active' : ''}">
      <button class="page-link" data-page="${p}">${p}</button>
    </li>`;
    prev = p;
  }

  nav.innerHTML = `
    <ul class="pagination pagination-sm justify-content-center flex-wrap mb-0">
      <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
        <button class="page-link" data-page="${currentPage - 1}" aria-label="Previous">&laquo;</button>
      </li>
      ${items}
      <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <button class="page-link" data-page="${currentPage + 1}" aria-label="Next">&raquo;</button>
      </li>
    </ul>`;

  nav.querySelectorAll('.page-link[data-page]').forEach(btn => {
    btn.addEventListener('click', () => {
      const p = parseInt(btn.dataset.page, 10);
      if (p > 0 && p <= totalPages) {
        loadFn(p);
        // Scroll back to top of the tab
        nav.closest('.tab-pane')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}

/** Shows a Bootstrap toast notification */
function showToast(message, type = 'success') {
  const container = document.getElementById('toastContainer');
  if (!container) return;
  const id   = 'toast-' + Date.now();
  const icon = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
  const bg   = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
  container.insertAdjacentHTML('beforeend', `
    <div id="${id}" class="toast align-items-center ${bg} border-0" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          <i class="bi ${icon} me-2"></i>${escHtml(message)}
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>`);
  const toastEl = document.getElementById(id);
  const toast   = new bootstrap.Toast(toastEl, { autohide: true, delay: 3500 });
  toast.show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ── My Routes ───────────────────────────────────────── */
const ROUTES_PER_PAGE = 12;

function loadUserRoutes(page = 1) {
  renderSkeletons('userRoutes');
  fetch(`javascript/rutasusuario.php?page=${page}&limit=${ROUTES_PER_PAGE}`)
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('userRoutes');
      if (!data.success) {
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-exclamation-triangle"></i>
          <p>${escHtml(data.error ?? 'Error loading routes.')}</p>
        </div>`;
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-map"></i>
          <p>${escHtml(data.message ?? "You haven't uploaded any routes yet.")}</p>
        </div>`;
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r, { isOwner: true })).join('');
      renderPagination('userRoutes-pagination', page, data.total, ROUTES_PER_PAGE, loadUserRoutes);
    })
    .catch(() => {
      document.getElementById('userRoutes').innerHTML = `<div class="col-12 acct-empty">
        <i class="bi bi-wifi-off"></i><p>Network error. Please try again.</p>
      </div>`;
    });
}

document.addEventListener('DOMContentLoaded', () => loadUserRoutes(1));
