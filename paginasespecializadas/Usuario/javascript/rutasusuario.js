/**
 * rutasusuario.js — My Routes tab
 * NOTE: shared helpers (buildRouteCard, escHtml, renderSkeletons,
 * renderPagination, showToast, DIFF_*, ACT_LABEL) are defined here
 * and reused by favoritosusuario.js / completadasusuario.js.
 */

/* ── Shared dictionaries ─────────────────────────────── */
// Difficulty values arrive as Beginner/Intermediate/Advanced/Expert (schema);
// the legacy easy/moderate/hard keys are kept for older rows.
const DIFF_LABEL = {
  beginner: 'Fácil', intermediate: 'Moderada', advanced: 'Difícil', expert: 'Experta',
  easy: 'Fácil', moderate: 'Moderada', hard: 'Difícil',
};
const DIFF_CLASS = {
  beginner: 'diff--easy', intermediate: 'diff--moderate', advanced: 'diff--hard', expert: 'diff--hard',
  easy: 'diff--easy', moderate: 'diff--moderate', hard: 'diff--hard',
};
const ACT_LABEL  = {
  hiking:        'Senderismo',
  ski_touring:   'Esquí de travesía',
  snowshoeing:   'Raquetas',
  climbing:      'Escalada',
  via_ferrata:   'Vía ferrata',
  cycle_touring: 'Cicloturismo',
  mtb:           'BTT',
  btt:           'BTT',
  kayaking:      'Kayak',
};
const ACT_ICON = {
  hiking: 'bi-person-walking', ski_touring: 'bi-snow3', snowshoeing: 'bi-snow',
  climbing: 'bi-triangle', via_ferrata: 'bi-link-45deg', cycle_touring: 'bi-bicycle',
  mtb: 'bi-bicycle', btt: 'bi-bicycle', kayaking: 'bi-water',
};
const ACT_IMG = {
  hiking: 'hiking.jpg', ski_touring: 'skitouring.jpg', snowshoeing: 'skitouring.jpg',
  climbing: 'hiking.jpg', via_ferrata: 'hiking.jpg', cycle_touring: 'cyclotouring.jpg',
  mtb: 'btt.jpg', btt: 'btt.jpg', kayaking: 'kayaking.jpg',
};
const LOC_LABEL = {
  Western_Pyrenees: 'Pirineo occidental',
  Central_Pyrenees: 'Pirineo central',
  Eastern_Pyrenees: 'Pirineo oriental',
};

/* ── Shared helpers ──────────────────────────────────── */
// Escapa también comillas: se usa dentro de atributos (alt, data-name...)
function escHtml(str) {
  return String(str ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

// Solo URLs http(s) o relativas para src/href
function safeUrl(url) {
  const u = String(url ?? '').trim();
  if (!u) return '';
  try {
    const p = new URL(u, window.location.href);
    return (p.protocol === 'http:' || p.protocol === 'https:') ? u : '';
  } catch { return ''; }
}

function formatDate(iso) {
  if (!iso) return '';
  try {
    return new Date(iso).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
  } catch { return iso; }
}

/**
 * Empty / error state shared by every tab.
 * title/text are plain strings (escaped here); ctaHtml is trusted static markup.
 */
function acctState({ icon = 'bi-compass', title = '', text = '', ctaHtml = '', tone = '' } = {}) {
  return `
    <div class="col-12">
      <div class="acct-empty ${tone ? 'acct-empty--' + tone : ''}">
        <span class="acct-empty__icon" aria-hidden="true"><i class="bi ${icon}"></i></span>
        ${title ? `<h3 class="acct-empty__title">${escHtml(title)}</h3>` : ''}
        ${text ? `<p class="acct-empty__text">${escHtml(text)}</p>` : ''}
        ${ctaHtml ? `<div class="acct-empty__cta">${ctaHtml}</div>` : ''}
      </div>
    </div>`;
}

/**
 * Builds a route card using the shared .route-card CSS system
 * (restyled for the account page in css/my_account.css).
 * @param {object} route      Route data from API
 * @param {object} [opts]     { isOwner, showCompleted }
 */
function buildRouteCard(route, opts = {}) {
  const { isOwner = false, showCompleted = false } = opts;
  const tipo     = String(route.tipo_ruta ?? '');
  const diffKey  = String(route.dificultad ?? '').toLowerCase();
  const fallback = `../skitouring/rutas_st/default_images/${ACT_IMG[tipo] ?? 'hiking.jpg'}`;
  const img      = safeUrl(route.imagenes?.[0] ?? '') || fallback;
  const rid      = encodeURIComponent(route.id ?? '');
  const num      = v => (Number.isFinite(Number(v)) ? Number(v) : 0);
  const diffCls  = DIFF_CLASS[diffKey] ?? '';
  const diffLbl  = DIFF_LABEL[diffKey] ?? (route.dificultad ?? '');
  const actLbl   = ACT_LABEL[tipo] ?? (tipo || 'Ruta');
  const actIcon  = ACT_ICON[tipo] ?? 'bi-signpost-2';
  const locRaw   = route.ubicacion ?? '';
  const locLbl   = locRaw === 'Other' ? '' : (LOC_LABEL[locRaw] ?? locRaw);
  const elevRaw  = route.elevacion_ganada ?? route.desnivel_positivo;
  const elev     = Number(elevRaw) > 0              ? `+${Math.round(elevRaw).toLocaleString('es-ES')} m`                       : '';
  const dist     = Number(route.distancia_km) > 0   ? `${parseFloat(route.distancia_km).toLocaleString('es-ES', { maximumFractionDigits: 1 })} km` : '';
  const dur      = Number(route.duracion_horas) > 0 ? `${parseFloat(route.duracion_horas).toLocaleString('es-ES', { maximumFractionDigits: 1 })} h` : '';
  const effort   = Number.parseInt(route.esfuerzo, 10);

  const chips = [
    dist ? `<span class="k-chip"><i class="bi bi-arrows-expand" aria-hidden="true"></i>${dist}</span>` : '',
    elev ? `<span class="k-chip"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>${elev}</span>` : '',
    dur  ? `<span class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i>${dur}</span>` : '',
    (effort >= 1 && effort <= 5)
      ? `<span class="k-chip k-chip-moss" title="Esfuerzo (MIDE) en una escala de 1 a 5">Esfuerzo ${effort}/5</span>` : '',
  ].join('');

  const completedBadge = (showCompleted && route.fecha_completada)
    ? `<p class="completed-date mb-0">
         <i class="bi bi-check-circle-fill" aria-hidden="true"></i> Completada el ${escHtml(formatDate(route.fecha_completada))}
       </p>`
    : '';

  /* Stats + optional Edit live OUTSIDE the <a> so nesting is valid */
  const meta = `
    <div class="route-card-meta">
      <span class="route-card-meta__stat" title="Visitas"><i class="bi bi-eye" aria-hidden="true"></i>${num(route.view_count)}<span class="visually-hidden"> visitas</span></span>
      <span class="route-card-meta__stat" title="Descargas"><i class="bi bi-download" aria-hidden="true"></i>${num(route.download_count)}<span class="visually-hidden"> descargas</span></span>
      <span class="route-card-meta__stat" title="Favoritas"><i class="bi bi-heart" aria-hidden="true"></i>${num(route.favorite_count)}<span class="visually-hidden"> veces en favoritas</span></span>
      <span class="route-card-meta__stat" title="Veces completada"><i class="bi bi-check2-circle" aria-hidden="true"></i>${num(route.complete_count)}<span class="visually-hidden"> veces completada</span></span>
      ${isOwner
        ? `<a href="edit_route.php?id=${rid}" class="route-card-meta__edit">
             <i class="bi bi-pencil" aria-hidden="true"></i> Editar
           </a>`
        : ''}
    </div>`;

  return `
    <div class="col">
      <article class="acct-route">
        <a href="../skitouring/ruta.html?id=${rid}" class="route-card">
          <div class="route-card__img-wrap">
            <img src="${escHtml(img)}" alt="" loading="lazy"
                 onerror="this.onerror=null;this.src='${escHtml(fallback)}'">
            ${diffLbl ? `<span class="route-card__diff ${diffCls}">${escHtml(diffLbl)}</span>` : ''}
            <span class="route-card__activity"><i class="bi ${actIcon}" aria-hidden="true"></i>${escHtml(actLbl)}</span>
          </div>
          <div class="route-card__body">
            <h3 class="route-card__title">${escHtml(route.titulo)}</h3>
            ${locLbl ? `<p class="route-card__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>${escHtml(locLbl)}</p>` : ''}
            ${chips ? `<div class="route-card__stats">${chips}</div>` : ''}
            ${completedBadge}
          </div>
        </a>
        ${meta}
      </article>
    </div>`;
}

/** Renders N skeleton cards inside a container */
function renderSkeletons(containerId, count = 6) {
  const el = document.getElementById(containerId);
  if (!el) return;
  el.innerHTML = Array.from({ length: count }, () =>
    `<div class="col" aria-hidden="true">
       <div class="acct-skel">
         <div class="skeleton acct-skel__img"></div>
         <div class="acct-skel__body">
           <div class="skeleton acct-skel__line"></div>
           <div class="skeleton acct-skel__line acct-skel__line--short"></div>
         </div>
       </div>
     </div>`
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
        <button class="page-link" data-page="${currentPage - 1}" aria-label="Anterior">&laquo;</button>
      </li>
      ${items}
      <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
        <button class="page-link" data-page="${currentPage + 1}" aria-label="Siguiente">&raquo;</button>
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
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Cerrar"></button>
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
        el.innerHTML = acctState({
          icon: 'bi-exclamation-circle', tone: 'error',
          title: 'No se pudieron cargar tus rutas',
          text: data.error ?? 'Recarga la página dentro de un momento.',
        });
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = acctState({
          icon: 'bi-signpost-split',
          title: 'Comparte tu primera ruta',
          text: 'Sube un track GPX y unas notas para que otros puedan seguir tus pasos.',
          ctaHtml: '<a href="subir_ruta.html" class="btn btn-primary"><i class="bi bi-upload me-1" aria-hidden="true"></i>Subir ruta</a>',
        });
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r, { isOwner: true })).join('');
      renderPagination('userRoutes-pagination', page, data.total, ROUTES_PER_PAGE, loadUserRoutes);
    })
    .catch(() => {
      document.getElementById('userRoutes').innerHTML = acctState({
        icon: 'bi-wifi-off', tone: 'error',
        title: 'Sin conexión',
        text: 'Comprueba tu conexión y recarga la página.',
      });
    });
}

document.addEventListener('DOMContentLoaded', () => loadUserRoutes(1));
