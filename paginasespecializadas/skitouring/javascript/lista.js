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
    ski_touring:   { label: 'Esquí de travesía', icon: 'bi-snow3',            color: '#3F6B7A' },
    hiking:        { label: 'Senderismo',    icon: 'bi-person-walking',   color: '#5F7444' },
    cycle_touring: { label: 'Cicloturismo',  icon: 'bi-bicycle',          color: '#7A5C7E' },
    btt:           { label: 'BTT',           icon: 'bi-bicycle',          color: '#B5653A' },
    snowshoeing:   { label: 'Raquetas',      icon: 'bi-snow',             color: '#5E8F9E' },
    climbing:      { label: 'Escalada',      icon: 'bi-activity',         color: '#A6402C' },
    via_ferrata:   { label: 'Vía ferrata',   icon: 'bi-lightning-charge', color: '#B8862B' },
    kayaking:      { label: 'Kayak',         icon: 'bi-water',            color: '#3E7F74' },
  };

  const DIFFICULTY = {
    beginner:     { label: 'Fácil',    cls: 'diff--easy' },
    intermediate: { label: 'Moderada', cls: 'diff--moderate' },
    advanced:     { label: 'Difícil',  cls: 'diff--hard' },
  };

  const LOCATION = {
    Western_Pyrenees: 'Pirineo occidental',
    Central_Pyrenees: 'Pirineo central',
    Eastern_Pyrenees: 'Pirineo oriental',
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
    if (!ubicacion) return null;
    return LOCATION.hasOwnProperty(ubicacion) ? LOCATION[ubicacion] : String(ubicacion).replace(/_/g, ' ');
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
      <div class="col" aria-hidden="true">
        <div class="route-card-skeleton">
          <div class="skeleton-img"></div>
          <div class="skeleton-body">
            <div class="skeleton-line w-40"></div>
            <div class="skeleton-line w-80"></div>
            <div class="skeleton-line w-60"></div>
          </div>
        </div>
      </div>`).join('');
  }

  // ── Construir tarjeta ────────────────────────────────────────
  function buildCard(route) {
    const defaultImg = getDefaultImage(route.tipo_ruta);
    const imgSrc     = route.imagenes?.length > 0 ? route.imagenes[0] : defaultImg;
    const act        = ACTIVITY[route.tipo_ruta] ?? { label: route.tipo_ruta || 'Ruta', icon: 'bi-map', color: '#7A7466' };
    const diff       = DIFFICULTY[route.dificultad?.toLowerCase()];
    const loc        = fmtLocation(route.ubicacion);

    const elev = route.elevacion_ganada ? `+${fmtNum(route.elevacion_ganada)} m` : null;
    const dist = route.distancia_km     ? `${fmtNum(route.distancia_km, 1)} km`  : null;
    const dur  = route.duracion_horas   ? `${fmtNum(route.duracion_horas, 1)} h` : null;
    const effort = (route.esfuerzo && route.horas_mide) ? parseInt(route.esfuerzo, 10) : NaN;

    const statsHtml = [
      dist ? `<li class="k-chip"><i class="bi bi-arrows-expand" aria-hidden="true"></i>${dist}</li>` : '',
      elev ? `<li class="k-chip"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>${elev}</li>` : '',
      dur  ? `<li class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i>${dur}</li>` : '',
      diff ? `<li class="k-chip k-chip-diff ${diff.cls}"><span class="k-dot" aria-hidden="true"></span>${diff.label}</li>` : '',
      !isNaN(effort) ? `<li class="k-chip k-chip-moss">Esfuerzo ${effort}/5</li>` : '',
    ].join('');

    return `
      <div class="col">
        <a href="ruta.html?id=${encodeURIComponent(route.id)}" class="route-card">
          <div class="route-card__img-wrap">
            <img src="${esc(imgSrc)}" alt=""
                 loading="lazy" onerror="this.src='${defaultImg}'">
            <span class="route-card__activity" style="--act:${act.color};">
              <i class="bi ${act.icon}" aria-hidden="true"></i>${esc(act.label)}
            </span>
          </div>
          <div class="route-card__body">
            ${loc ? `<p class="route-card__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>${esc(loc)}</p>` : ''}
            <h3 class="route-card__title">${esc(route.titulo || 'Ruta sin nombre')}</h3>
            ${statsHtml ? `<ul class="route-card__stats">${statsHtml}</ul>` : ''}
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
    routesList.innerHTML = slice.length
      ? slice.map(buildCard).join('')
      : `
        <div class="col-12">
          <div class="k-empty">
            <i class="bi bi-signpost-split k-empty__icon" aria-hidden="true"></i>
            <h3 class="k-empty__title">Ninguna ruta encaja con estos filtros</h3>
            <p class="k-empty__text">Prueba con más distancia o duración, elige otra actividad o quita los filtros.</p>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-action="clear-filters">Quitar filtros</button>
          </div>
        </div>`;

    // Contador
    if (countLabel) {
      countLabel.textContent = filtered.length === allRoutes.length
        ? `${filtered.length.toLocaleString('es-ES')} ${filtered.length === 1 ? 'ruta' : 'rutas'}`
        : `Mostrando ${slice.length.toLocaleString('es-ES')} de ${filtered.length.toLocaleString('es-ES')} ${filtered.length === 1 ? 'ruta' : 'rutas'}`;
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

  // Botón "Clear filters" del estado vacío
  routesList?.addEventListener('click', e => {
    if (e.target.closest('[data-action="clear-filters"]')) clearBtn?.click();
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
        <div class="col-12">
          <div class="k-empty k-empty--error" role="alert">
            <i class="bi bi-cloud-slash k-empty__icon" aria-hidden="true"></i>
            <h3 class="k-empty__title">No se pudieron cargar las rutas</h3>
            <p class="k-empty__text">Recarga la página en un momento. (${esc(err.message)})</p>
          </div>
        </div>`;
    }
  }

  loadRoutes();
});
