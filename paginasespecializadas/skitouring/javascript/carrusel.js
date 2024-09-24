document.addEventListener('DOMContentLoaded', function() {
  function loadCardsAndInitializeCarousel() {
    fetch('javascript/obtener_rutas.php')
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
      })
      .then(text => {
        try {
          const routes = JSON.parse(text);
          const container1 = document.getElementById('cards-container');
          const container2 = document.getElementById('cards-container2');
          
          if (!container1 || !container2) {
            console.error('One or both cards containers not found.');
            return;
          }

          const maxCards = 16;
          const cards = routes.slice(0, maxCards);

          [container1, container2].forEach(container => {
            container.innerHTML = '';

            cards.forEach(route => {
              const card = document.createElement('div');
              card.className = 'swiper-slide';

              const imageUrl = route.imagenes && route.imagenes.length > 0 ? route.imagenes[0] : 'ruta_placeholder.jpg';

              card.innerHTML = `
                <div class="card">
                  <img class="card-img-top" src="${imageUrl}" alt="Image of ${route.title}">
                  <div class="card-body">
                    <h5 class="card-title">${route.title}</h5>
                    <ul class="list-unstyled">
                      <li><i class="fas fa-mountain"></i> ${route.elevation} m</li>
                      <li><i class="far fa-clock"></i> ${route.duration} hours</li>
                      <li><i class="fas fa-signal"></i> ${route.difficulty}</li>
                      <li><i class="fas fa-map-marker-alt"></i> ${route.location}</li>
                      <li><i class="fas fa-hiking"></i> ${route.tipo_ruta}</li>
                    </ul>
                    <a href="ruta.html?id=${route.id}" class="btn btn-primary">More Info</a>
                  </div>
                </div>
              `;

              container.appendChild(card);
            });
          });

          initializeSwiper();
        } catch (e) {
          console.error('Error parsing JSON:', e);
          mostrarNotificacion('Error al cargar las rutas. Por favor, intente de nuevo más tarde.', 'error');
        }
      })
      .catch(error => {
        console.error('Error loading routes:', error);
        mostrarNotificacion('Error al cargar las rutas. Por favor, intente de nuevo más tarde.', 'error');
      });
  }

  function initializeSwiper() {
    const swiperContainers = document.querySelectorAll('.swiper-container');
    
    swiperContainers.forEach((container, index) => {
      new Swiper(container, {
        slidesPerView: 2,
        spaceBetween: 20,
        loop: true,
        pagination: {
          el: `.swiper-pagination-${index + 1}`,
          clickable: true,
        },
        navigation: {
          nextEl: `.swiper-button-next-${index + 1}`,
          prevEl: `.swiper-button-prev-${index + 1}`,
        },
        breakpoints: {
          640: {
            slidesPerView: 2,
          },
          1024: {
            slidesPerView: 4,
          },
        },
      });
    });
  }

  function mostrarNotificacion(mensaje, tipo) {
    const notificacion = document.createElement('div');
    notificacion.textContent = mensaje;
    notificacion.className = `notificacion ${tipo}`;
    document.body.appendChild(notificacion);
    setTimeout(() => {
      notificacion.remove();
    }, 5000);
  }

  loadCardsAndInitializeCarousel();
});