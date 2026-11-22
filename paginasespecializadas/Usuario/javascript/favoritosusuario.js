/**
 * favoritosusuario.js — Favourites tab
 * Depends on shared helpers from rutasusuario.js (loaded first).
 */

const FAVS_PER_PAGE = 12;
let favsLoaded = false;

function loadFavoriteRoutes(page = 1) {
  renderSkeletons('favoriteRoutes');
  fetch(`javascript/favoritosusuario.php?page=${page}&limit=${FAVS_PER_PAGE}`)
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('favoriteRoutes');
      if (!data.success) {
        el.innerHTML = acctState({
          icon: 'bi-exclamation-circle', tone: 'error',
          title: 'No se pudieron cargar tus favoritas',
          text: data.error ?? 'Recarga la página dentro de un momento.',
        });
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = acctState({
          icon: 'bi-heart',
          title: 'Guarda las rutas que te llaman',
          text: 'Toca el corazón en cualquier ruta y te esperará aquí.',
          ctaHtml: '<a href="../skitouring/skitouring.html" class="btn btn-primary"><i class="bi bi-compass me-1" aria-hidden="true"></i>Explorar rutas</a>',
        });
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r)).join('');
      renderPagination('favoriteRoutes-pagination', page, data.total, FAVS_PER_PAGE, loadFavoriteRoutes);
    })
    .catch(() => {
      document.getElementById('favoriteRoutes').innerHTML = acctState({
        icon: 'bi-wifi-off', tone: 'error',
        title: 'Sin conexión',
        text: 'Comprueba tu conexión y recarga la página.',
      });
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const tab = document.getElementById('favorites-tab');
  if (!tab) return;
  tab.addEventListener('shown.bs.tab', () => {
    if (!favsLoaded) {
      favsLoaded = true;
      loadFavoriteRoutes(1);
    }
  });
});
