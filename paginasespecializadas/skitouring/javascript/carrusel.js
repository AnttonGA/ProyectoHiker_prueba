document.addEventListener('DOMContentLoaded', function () {

  // Paleta de actividad (misma en mapa.js / lista.js)
  const ACTIVITY_LABEL = {
    hiking:        { label: 'Senderismo',    icon: 'bi-person-walking',   color: '#5F7444' },
    ski_touring:   { label: 'Esquí de travesía', icon: 'bi-snow3',            color: '#3F6B7A' },
    btt:           { label: 'BTT',           icon: 'bi-bicycle',          color: '#B5653A' },
    cycle_touring: { label: 'Cicloturismo',  icon: 'bi-bicycle',          color: '#7A5C7E' },
    snowshoeing:   { label: 'Raquetas',      icon: 'bi-snow',             color: '#5E8F9E' },
    climbing:      { label: 'Escalada',      icon: 'bi-activity',         color: '#A6402C' },
    via_ferrata:   { label: 'Vía ferrata',   icon: 'bi-lightning-charge', color: '#B8862B' },
    kayaking:      { label: 'Kayak',         icon: 'bi-water',            color: '#3E7F74' },
  };

  const DIFFICULTY_LABEL = {
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

  function fmtLocation(ubicacion) {
    if (!ubicacion) return null;
    return Object.prototype.hasOwnProperty.call(LOCATION, ubicacion) ? LOCATION[ubicacion] : String(ubicacion).replace(/_/g, ' ');
  }

  function getDefaultImage(tipo) {
    const map = {
      ski_touring:   'rutas_st/default_images/skitouring.jpg',
      hiking:        'rutas_st/default_images/hiking.jpg',
      cycle_touring: 'rutas_st/default_images/cyclotouring.jpg',
      btt:           'rutas_st/default_images/btt.jpg',
      snowshoeing:   'rutas_st/default_images/skitouring.jpg',
    };
    return map[tipo] || 'rutas_st/default_images/hiking.jpg';
  }

  function esc(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function buildSlide(route) {
    const act   = ACTIVITY_LABEL[route.tipo_ruta] || { label: route.tipo_ruta || 'Ruta', icon: 'bi-map', color: '#7A7466' };
    const diff  = DIFFICULTY_LABEL[(route.dificultad || '').toLowerCase()];
    const img   = (route.imagenes && route.imagenes.length > 0) ? route.imagenes[0] : getDefaultImage(route.tipo_ruta);
    const loc   = fmtLocation(route.ubicacion);
    const dist  = route.distancia_km     ? `${parseFloat(route.distancia_km).toLocaleString('es-ES', { maximumFractionDigits: 1 })} km` : '';
    const elev  = route.elevacion_ganada ? `+${Math.round(route.elevacion_ganada).toLocaleString('es-ES')} m` : '';
    const dur   = route.duracion_horas   ? `${parseFloat(route.duracion_horas).toLocaleString('es-ES', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} h` : '';
    const effort = (route.esfuerzo && route.horas_mide) ? parseInt(route.esfuerzo, 10) : NaN;

    const chips = [
      dist ? `<li class="k-chip"><i class="bi bi-arrows-expand" aria-hidden="true"></i>${dist}</li>` : '',
      elev ? `<li class="k-chip"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>${elev}</li>` : '',
      dur  ? `<li class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i>${dur}</li>` : '',
      diff ? `<li class="k-chip k-chip-diff ${diff.cls}"><span class="k-dot" aria-hidden="true"></span>${diff.label}</li>` : '',
      !isNaN(effort) ? `<li class="k-chip k-chip-moss">Esfuerzo ${effort}/5</li>` : '',
    ].join('');

    return `
      <div class="swiper-slide">
        <a href="ruta.html?id=${encodeURIComponent(route.id)}" class="route-card route-card--compact">
          <div class="route-card__img-wrap">
            <img src="${esc(img)}" alt="" loading="lazy"
                 onerror="this.src='${getDefaultImage(route.tipo_ruta)}'">
            <span class="route-card__activity" style="--act:${act.color};">
              <i class="bi ${act.icon}" aria-hidden="true"></i>${esc(act.label)}
            </span>
          </div>
          <div class="route-card__body">
            ${loc ? `<p class="route-card__location"><i class="bi bi-geo-alt" aria-hidden="true"></i>${esc(loc)}</p>` : ''}
            <h3 class="route-card__title">${esc(route.titulo || 'Ruta sin nombre')}</h3>
            ${chips ? `<ul class="route-card__stats">${chips}</ul>` : ''}
          </div>
        </a>
      </div>`;
  }

  function initCarousel(routes) {
    const container = document.getElementById('cards-container');
    if (!container) return;

    // Take top 16 by popularity (already sorted DESC by PHP)
    const top = routes.slice(0, 16);
    if (top.length === 0) {
      container.innerHTML = '<div class="swiper-slide k-slide-full"><div class="k-empty k-empty--compact"><i class="bi bi-signpost-split k-empty__icon" aria-hidden="true"></i><p class="k-empty__text">Aún no hay rutas sugeridas. Vuelve pronto a echar un vistazo.</p></div></div>';
      return;
    }
    container.innerHTML = top.map(buildSlide).join('');

    new Swiper('#carousel-swiper', {
      slidesPerView: 1.15,
      spaceBetween: 16,
      loop: top.length > 4,
      grabCursor: true,
      pagination: {
        el: '.carousel-pagination',
        clickable: true,
      },
      navigation: {
        nextEl: '.carousel-next',
        prevEl: '.carousel-prev',
      },
      breakpoints: {
        576:  { slidesPerView: 2, spaceBetween: 16 },
        768:  { slidesPerView: 3, spaceBetween: 20 },
        1024: { slidesPerView: 4, spaceBetween: 20 },
        1400: { slidesPerView: 4, spaceBetween: 24 },
      },
    });
  }

  fetch('javascript/obtener_rutas.php')
    .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
    .then(routes => {
      if (!Array.isArray(routes)) throw new Error('Invalid data');
      initCarousel(routes);
    })
    .catch(err => {
      console.error('Carousel load error:', err);
      const c = document.getElementById('cards-container');
      if (c) c.innerHTML = '<div class="swiper-slide k-slide-full"><div class="k-empty k-empty--compact"><i class="bi bi-cloud-slash k-empty__icon" aria-hidden="true"></i><p class="k-empty__text">No se pudieron cargar las sugerencias. Inténtalo de nuevo en un momento.</p></div></div>';
    });
});
