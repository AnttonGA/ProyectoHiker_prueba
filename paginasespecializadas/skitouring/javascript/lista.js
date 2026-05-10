document.addEventListener('DOMContentLoaded', function () {

  // ── Configuración ────────────────────────────────────────────
  const PAGE_SIZE = 24;   // tarjetas por "cargar más"
  let visibleCount = PAGE_SIZE;
  let allRoutes    = [];
  let filtered     = [];

  // ── DOM ──────────────────────────────────────────────────────
  const routesList  = document.getElementById('routes-list');
  const loadMoreBtn = document.getElementById('btn-load-more');
  const loadMoreWrap= document.getElementById('load-more-wrap');
  const countLabel  = document.getElementById('routes-count');
  const searchInput = document.getElementById('search-input');
  const filterForm  = document.getElementById('filter-form');
  const clearBtn    = document.getElementById('btn-clear-filters');

  // ── Diccionarios ─────────────────────────────────────────────
  const ACTIVITY = {
    ski_touring:   { label: 'Ski de travesía', icon: 'bi-snow3' },
    hiking:        { label: 'Senderismo',       icon: 'bi-person-walking' },
    cycle_touring: { label: 'Cicloturismo',     icon: 'bi-bicycle' },
    btt:           { label: 'MTB',              icon: 'bi-bicycle' },
    snowshoeing:   { label: 'Raquetas de nieve',icon: 'bi-snow' },
    climbing:      { label: 'Escalada',         icon: 'bi-activity' },
    via_ferrata:   { label: 'Vía Ferrata',      icon: 'bi-lightning-charge' },
    kayaking:      { label: 'Kayak',            icon: 'bi-water' },
  };

  const DIFFICULTY = {
    beginner:     { label: 'Fácil',    cls: 'diff--easy' },
    intermediate: { label: 'Moderado', cls: 'diff--moderate' },
    advanced:     { label: 'Difícil',  cls: 'diff--hard' },
  };

  const LOCATION = {
    Western_Pyrenees: 'Pirineos Occ.',
    Central_Pyrenees: 'Pirineos Cent.',
    Eastern_Pyrenees: 'Pirineos Orient.',
    Other: null,
  };

  // ── Helpers ──────────────────────────────────────────────────
  function esc(str) {
    return String(str ?? '')
      .replace(/&/g,'&amp;').replace(/</g,'&lt;')
      .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtNum(n, decimals = 0) {
    const v = parseFloat(n);
    if (isNaN(v)) return null;
    return v.toLocaleString('es-ES', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
  }

  function fmtLocation(ubicacion) {
    return LOCATION.hasOwnProperty(ubicacion) ? LOCATION[ubicacion] : ubicacion;
  }

  function getDefaultImage(tipo) {
    const map = {
      ski_touring:   'rutas_st/default_images/skitouring.jpg',
      hiking:        'rutas_st/default_images/hiking.jpg',
      cycle_touring: 'rutas_st/default_images/cyclotouring.jpg',
      btt:           'rutas_st/default_images/btt.jpg',
      snowshoeing:   'rutas_st/default_images/skitouring.jpg',
      climbing:      'rutas_st/default_images/hiking.jpg',
      via_ferrata:   'rutas_st/default_images/hiking.jpg',
      kayaking:      'rutas_st/default_images/kayaking.jpg',
    };
    return map[tipo] || 'rutas_st/default_images/hiking.jpg';
  }

  // ── Skeleton loader ──────────────────────────────────────────
  function showSkeletons(n = 8) {
    routesList.innerHTML = Array.from({ length: n }, () => `
      <div class="col">
        <div class="route-card-skeleton">
          <div class="skeleton-img"></div>
          <div class="skeleton-body">
            <div class="skeleton-line w-80"></div>
            <div class="skeleton-line w-60"></div>
            <div class="skeleton-line w-40"></div>
          </div>
        </div>
      </div>`).join('');
  }

  // ── Construir tarjeta ────────────────────────────────────────
  function buildCard(route) {
    const defaultImg = getDefaultImage(route.tipo_ruta);
    const imgSrc     = route.imagenes?.length > 0 ? route.imagenes[0] : defaultImg;
    const act        = ACTIVITY[route.tipo_ruta] ?? { label: route.tipo_ruta, icon: 'bi-map' };
    const diff       = DIFFICULTY[route.dificultad?.toLowerCase()];
    const loc        = fmtLocation(route.ubicacion);

    const elev = route.elevacion_ganada ? `+${fmtNum(route.elevacion_ganada)} m` : null;
    const dist = route.distancia_km     ? `${fmtNum(route.distancia_km, 1)} km`  : null;
    const dur  = route.duracion_horas   ? `${fmtNum(route.duracion_horas, 1)} h` : null;

    const statsHtml = [
      elev ? `<span class="route-card__stat"><i class="bi bi-arrow-up-short"></i>${elev}</span>` : '',
      dist ? `<span class="route-card__stat"><i class="bi bi-arrows-expand"></i>${dist}</span>` : '',
      dur  ? `<span class="route-card__stat"><i class="bi bi-clock"></i>${dur}</span>`  : '',
    ].join('');

    return `
      <div class="col">
        <a href="ruta.html?id=${route.id}" class="route-card">
          <div class="route-card__img-wrap">
            <img src="${esc(imgSrc)}" alt="${esc(route.titulo)}"
                 loading="lazy" onerror="this.src='${defaultImg}'">
            ${diff ? `<span class="route-card__diff ${diff.cls}">${diff.label}</span>` : ''}
            <span class="route-card__activity">
              <i class="bi ${act.icon}"></i> ${esc(act.label)}
            </span>
          </div>
          <div class="route-card__body">
            <h5 class="route-card__title">${esc(route.titulo || 'Sin título')}</h5>
            ${statsHtml ? `<div class="route-card__stats">${statsHtml}</div>` : ''}
            ${loc ? `<p class="route-card__location"><i class="bi bi-geo-alt"></i> ${esc(loc)}</p>` : ''}
            <div class="route-card__cta">Ver ruta <i class="bi bi-arrow-right"></i></div>
          </div>
        </a>
      </div>`;
  }

  // ── Renderizar lista ─────────────────────────────────────────
  function render(reset = false) {
    if (reset) {
      visibleCount  = PAGE_SIZE;
      routesList.innerHTML = '';
    }

    const slice = filtered.slice(0, visibleCount);
    routesList.innerHTML = slice.map(buildCard).join('');

    // Contador
    if (countLabel) {
      countLabel.textContent = `Mostrando ${slice.length} de ${filtered.length} rutas`;
    }

    // Load more
    if (loadMoreWrap) {
      loadMoreWrap.style.display = visibleCount < filtered.length ? 'block' : 'none';
    }
  }

  // ── Filtrar ──────────────────────────────────────────────────
  function applyFilters() {
    const text       = (searchInput?.value ?? '').trim().toLowerCase();
    const diff       = document.getElementById('filter-difficulty')?.value?.toLowerCase() ?? 'all';
    const minElev    = parseFloat(document.getElementById('filter-min-elevation')?.value) || 0;
    const maxDist    = parseFloat(document.getElementById('filter-max-distance')?.value)  || Infinity;
    const maxDur     = parseFloat(document.getElementById('filter-max-duration')?.value)  || Infinity;
    const activities = Array.from(
      document.querySelectorAll('#activityFilter input:checked')
    ).map(i => i.value);

    filtered = allRoutes.filter(r => {
      if (text && !((r.titulo || '').toLowerCase().includes(text) ||
                    (r.ubicacion || '').toLowerCase().includes(text))) return false;
      if (diff !== 'all' && (r.dificultad || '').toLowerCase() !== diff) return false;
      if (minElev > 0 && (parseFloat(r.elevacion_ganada) || 0) < minElev) return false;
      if (maxDist < Infinity && (parseFloat(r.distancia_km) || 0) > maxDist) return false;
      if (maxDur < Infinity && (parseFloat(r.duracion_horas) || 0) > maxDur) return false;
      if (activities.length && !activities.includes(r.tipo_ruta)) return false;
      return true;
    });

    render(true);
  }

  // ── Eventos ──────────────────────────────────────────────────
  // Búsqueda en tiempo real
  searchInput?.addEventListener('input', applyFilters);

  // Formulario de filtros
  filterForm?.addEventListener('submit', e => { e.preventDefault(); applyFilters(); });

  // Cambios en selects/checkboxes también aplican al instante
  filterForm?.addEventListener('change', applyFilters);

  // Limpiar filtros
  clearBtn?.addEventListener('click', () => {
    filterForm?.reset();
    if (searchInput) searchInput.value = '';
    applyFilters();
  });

  // Load more
  loadMoreBtn?.addEventListener('click', () => {
    visibleCount += PAGE_SIZE;
    render(false);
  });

  // ── Cargar rutas ─────────────────────────────────────────────
  async function loadRoutes() {
    showSkeletons(8);
    try {
      const res  = await fetch('javascript/obtener_rutas.php');
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const text = await res.text();
      if (!text.trim()) throw new Error('Respuesta vacía del servidor');
      allRoutes = JSON.parse(text);
      if (!Array.isArray(allRoutes)) throw new Error('Formato de datos incorrecto');
      filtered = [...allRoutes];
      render(true);
    } catch (err) {
      console.error('Error cargando rutas:', err);
      routesList.innerHTML = `
        <div class="col-12 text-center py-5 text-muted">
          <i class="bi bi-exclamation-triangle fs-2 d-block mb-2"></i>
          Error cargando rutas: ${esc(err.message)}
        </div>`;
    }
  }

  loadRoutes();
});
