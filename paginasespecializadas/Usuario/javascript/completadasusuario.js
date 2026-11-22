/**
 * completadasusuario.js — Completed routes tab
 * Depends on shared helpers from rutasusuario.js (loaded first).
 */

const COMPLETED_PER_PAGE = 12;
let completedLoaded = false;

function loadCompletedRoutes(page = 1) {
  renderSkeletons('completedRoutes');
  fetch(`javascript/completadausuario.php?page=${page}&limit=${COMPLETED_PER_PAGE}`)
    .then(r => r.json())
    .then(data => {
      const el = document.getElementById('completedRoutes');
      if (!data.success) {
        el.innerHTML = acctState({
          icon: 'bi-exclamation-circle', tone: 'error',
          title: 'No se pudieron cargar tus rutas completadas',
          text: data.error ?? 'Recarga la página dentro de un momento.',
        });
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = acctState({
          icon: 'bi-flag',
          title: 'Tu cuaderno de montaña empieza aquí',
          text: 'Después de cada salida, marca la ruta como hecha y ve guardando el registro de dónde has estado.',
          ctaHtml: '<a href="../skitouring/skitouring.html" class="btn btn-primary"><i class="bi bi-compass me-1" aria-hidden="true"></i>Buscar tu próxima ruta</a>',
        });
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r, { showCompleted: true })).join('');
      renderPagination('completedRoutes-pagination', page, data.total, COMPLETED_PER_PAGE, loadCompletedRoutes);
    })
    .catch(() => {
      document.getElementById('completedRoutes').innerHTML = acctState({
        icon: 'bi-wifi-off', tone: 'error',
        title: 'Sin conexión',
        text: 'Comprueba tu conexión y recarga la página.',
      });
    });
}

document.addEventListener('DOMContentLoaded', () => {
  const tab = document.getElementById('completed-tab');
  if (!tab) return;
  tab.addEventListener('shown.bs.tab', () => {
    if (!completedLoaded) {
      completedLoaded = true;
      loadCompletedRoutes(1);
    }
  });
});
