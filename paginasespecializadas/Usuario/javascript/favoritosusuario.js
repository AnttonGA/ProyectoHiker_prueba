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
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-exclamation-triangle"></i>
          <p>${escHtml(data.error ?? 'Error loading favourites.')}</p>
        </div>`;
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-heart"></i>
          <p>${escHtml(data.message ?? 'No favourite routes yet. Star a route to see it here!')}</p>
        </div>`;
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r)).join('');
      renderPagination('favoriteRoutes-pagination', page, data.total, FAVS_PER_PAGE, loadFavoriteRoutes);
    })
    .catch(() => {
      document.getElementById('favoriteRoutes').innerHTML = `<div class="col-12 acct-empty">
        <i class="bi bi-wifi-off"></i><p>Network error. Please try again.</p>
      </div>`;
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
