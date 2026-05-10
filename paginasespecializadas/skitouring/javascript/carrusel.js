document.addEventListener('DOMContentLoaded', function () {

  const ACTIVITY_LABEL = {
    hiking:        { label: 'Hiking',       icon: 'bi-person-walking', color: '#28a745' },
    ski_touring:   { label: 'Ski Touring',  icon: 'bi-snow3',          color: '#0d6efd' },
    btt:           { label: 'MTB',          icon: 'bi-bicycle',        color: '#fd7e14' },
    cycle_touring: { label: 'Cycle',        icon: 'bi-bicycle',        color: '#6f42c1' },
    snowshoeing:   { label: 'Snowshoe',     icon: 'bi-snow',           color: '#17a2b8' },
    climbing:      { label: 'Climbing',     icon: 'bi-activity',       color: '#dc3545' },
    via_ferrata:   { label: 'Via Ferrata',  icon: 'bi-lightning-charge', color: '#e83e8c' },
    kayaking:      { label: 'Kayak',        icon: 'bi-water',          color: '#20c997' },
  };

  const DIFFICULTY_LABEL = {
    beginner:     { label: 'Easy',     cls: 'diff--easy' },
    intermediate: { label: 'Moderate', cls: 'diff--moderate' },
    advanced:     { label: 'Hard',     cls: 'diff--hard' },
  };

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
    const act   = ACTIVITY_LABEL[route.tipo_ruta] || { label: route.tipo_ruta, icon: 'bi-map', color: '#6c757d' };
    const diff  = DIFFICULTY_LABEL[(route.dificultad || '').toLowerCase()];
    const img   = (route.imagenes && route.imagenes.length > 0) ? route.imagenes[0] : getDefaultImage(route.tipo_ruta);
    const elev  = route.elevacion_ganada ? `+${Math.round(route.elevacion_ganada).toLocaleString('es-ES')} m` : '';
    const dur   = route.duracion_horas   ? `${parseFloat(route.duracion_horas).toFixed(1)} h` : '';

    return `
      <div class="swiper-slide">
        <a href="ruta.html?id=${route.id}" class="carousel-card">
          <div class="carousel-card__img">
            <img src="${esc(img)}" alt="${esc(route.titulo)}" loading="lazy"
                 onerror="this.src='${getDefaultImage(route.tipo_ruta)}'">
            ${diff ? `<span class="carousel-card__diff ${diff.cls}">${diff.label}</span>` : ''}
            <span class="carousel-card__act" style="background:${act.color};">
              <i class="bi ${act.icon}"></i> ${act.label}
            </span>
          </div>
          <div class="carousel-card__body">
            <p class="carousel-card__title">${esc(route.titulo || 'Sin título')}</p>
            <div class="carousel-card__stats">
              ${elev ? `<span><i class="bi bi-arrow-up-short"></i>${elev}</span>` : ''}
              ${dur  ? `<span><i class="bi bi-clock"></i>${dur}</span>` : ''}
            </div>
          </div>
        </a>
      </div>`;
  }

  function initCarousel(routes) {
    const container = document.getElementById('cards-container');
    if (!container) return;

    // Take top 16 by popularity (already sorted DESC by PHP)
    const top = routes.slice(0, 16);
    container.innerHTML = top.map(buildSlide).join('');

    new Swiper('#carousel-swiper', {
      slidesPerView: 2,
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
        1400: { slidesPerView: 5, spaceBetween: 20 },
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
      if (c) c.innerHTML = '<div class="swiper-slide text-muted small p-3">Could not load suggestions.</div>';
    });
});
