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
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-exclamation-triangle"></i>
          <p>${escHtml(data.error ?? 'Error loading completed routes.')}</p>
        </div>`;
        return;
      }
      if (data.message || !data.routes?.length) {
        el.innerHTML = `<div class="col-12 acct-empty">
          <i class="bi bi-check-circle"></i>
          <p>${escHtml(data.message ?? "You haven't completed any routes yet. Go explore!")}</p>
        </div>`;
        return;
      }
      el.innerHTML = data.routes.map(r => buildRouteCard(r, { showCompleted: true })).join('');
      renderPagination('completedRoutes-pagination', page, data.total, COMPLETED_PER_PAGE, loadCompletedRoutes);
    })
    .catch(() => {
      document.getElementById('completedRoutes').innerHTML = `<div class="col-12 acct-empty">
        <i class="bi bi-wifi-off"></i><p>Network error. Please try again.</p>
      </div>`;
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
