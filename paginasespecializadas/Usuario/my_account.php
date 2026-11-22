<?php
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: ../inicio_sesion/gestion.php");
    exit();
}
$usernameRaw  = (string) $_SESSION['usuario'];
$username     = e($usernameRaw);
$avatarLetter = e(mb_strtoupper(mb_substr($usernameRaw, 0, 1)));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <title>Mi cuenta — <?= $username ?></title>
  <link rel="icon" type="image/svg+xml" href="../../favicon.svg">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Shared + page CSS (versioned to bust stale cache) -->
<?php
function cssTag(string $rel): string {
    $abs = __DIR__ . '/' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : time();
    return '  <link href="' . e($rel . '?v=' . $v) . "\" rel=\"stylesheet\">\n";
}
echo cssTag('../estilos2.css');
echo cssTag('css/my_account.css');
?>
  <!-- Sistema de diseño Kahir (después del CSS de la página) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link href="../../assets/css/kahir.css?v=20261122" rel="stylesheet">
</head>
<body>
  <div id="menu"></div>

  <!-- ── Cabecera personal ─────────────────────────────────── -->
  <header class="acct-hero">
    <div class="container-xl">
      <div class="acct-hero__inner">
        <div class="acct-avatar" aria-hidden="true"><?= $avatarLetter ?></div>
        <div class="acct-hero__info">
          <p class="acct-eyebrow mb-1">Mi cuenta</p>
          <h1 class="acct-hero__name">Hola de nuevo, <?= $username ?></h1>
          <p class="acct-hero__sub mb-0">Tus rutas, tus favoritas y tus planes de montaña, todo en un mismo sitio.</p>
        </div>
        <div class="acct-hero__actions">
          <a href="subir_ruta.html" class="btn btn-primary btn-sm">
            <i class="bi bi-upload me-1" aria-hidden="true"></i>Subir ruta
          </a>
          <div class="dropdown">
            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-gear me-1" aria-hidden="true"></i>Ajustes
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="edit_profile.php">
                <i class="bi bi-person-gear me-2 text-muted" aria-hidden="true"></i>Editar perfil
              </a></li>
              <li><a class="dropdown-item" href="change_password.php">
                <i class="bi bi-key me-2 text-muted" aria-hidden="true"></i>Cambiar contraseña
              </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="../../cerrar_sesion.php">
                <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Salir
              </a></li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Resumen (lo rellena perfil_ia.js; queda oculto si falla) -->
      <dl class="acct-stats" id="acct-stats" hidden>
        <div class="acct-stat">
          <dt>Rutas compartidas</dt>
          <dd data-stat="routes">0</dd>
        </div>
        <div class="acct-stat">
          <dt>Favoritas</dt>
          <dd data-stat="favorites">0</dd>
        </div>
        <div class="acct-stat">
          <dt>Completadas</dt>
          <dd data-stat="completed">0</dd>
        </div>
      </dl>
    </div>
  </header>

  <!-- ── Main ──────────────────────────────────────────────── -->
  <main class="container-xl acct-main">

    <!-- ── Actividades declaradas (Fase 1; opcional, lo rellena actividades_usuario.js) ── -->
    <div id="activities-card" class="acct-block" hidden></div>

    <!-- ── Discovery / onboarding section (hidden until JS populates it) ── -->
    <section id="onboarding-section" class="onboarding-section acct-block"
             aria-label="Primeros pasos"
             data-user="<?= $username ?>" style="display:none"></section>

    <!-- Tab navigation -->
    <div class="acct-tabs-wrap">
      <ul class="nav nav-tabs acct-tabs" id="accountTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="routes-tab"
                  data-bs-toggle="tab" data-bs-target="#routes"
                  type="button" role="tab" aria-controls="routes" aria-selected="true">
            <i class="bi bi-signpost-split" aria-hidden="true"></i><span class="acct-tab-label">Mis rutas</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="favorites-tab"
                  data-bs-toggle="tab" data-bs-target="#favorites"
                  type="button" role="tab" aria-controls="favorites" aria-selected="false">
            <i class="bi bi-heart" aria-hidden="true"></i><span class="acct-tab-label">Favoritas</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="completed-tab"
                  data-bs-toggle="tab" data-bs-target="#completed"
                  type="button" role="tab" aria-controls="completed" aria-selected="false">
            <i class="bi bi-flag" aria-hidden="true"></i><span class="acct-tab-label">Completadas</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="lists-tab"
                  data-bs-toggle="tab" data-bs-target="#lists"
                  type="button" role="tab" aria-controls="lists" aria-selected="false">
            <i class="bi bi-bookmarks" aria-hidden="true"></i><span class="acct-tab-label">Mis listas</span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="perfil-ia-tab"
                  data-bs-toggle="tab" data-bs-target="#perfil-ia"
                  type="button" role="tab" aria-controls="perfil-ia" aria-selected="false">
            <i class="bi bi-person-badge" aria-hidden="true"></i><span class="acct-tab-label">Perfil IA</span>
          </button>
        </li>
      </ul>
    </div>

    <!-- Tab content -->
    <div class="tab-content" id="accountTabsContent">

      <!-- ── My Routes ─────────────────────── -->
      <div class="tab-pane fade show active" id="routes" role="tabpanel" aria-labelledby="routes-tab" tabindex="0">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4" id="userRoutes"></div>
        <nav class="acct-pagination" id="userRoutes-pagination" aria-label="Paginación de mis rutas"></nav>
      </div>

      <!-- ── Favourites ────────────────────── -->
      <div class="tab-pane fade" id="favorites" role="tabpanel" aria-labelledby="favorites-tab" tabindex="0">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4" id="favoriteRoutes"></div>
        <nav class="acct-pagination" id="favoriteRoutes-pagination" aria-label="Paginación de favoritas"></nav>
      </div>

      <!-- ── Completed ─────────────────────── -->
      <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab" tabindex="0">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-4" id="completedRoutes"></div>
        <nav class="acct-pagination" id="completedRoutes-pagination" aria-label="Paginación de completadas"></nav>
      </div>

      <!-- ── My Lists ──────────────────────── -->
      <div class="tab-pane fade" id="lists" role="tabpanel" aria-labelledby="lists-tab" tabindex="0">
        <div class="acct-section-head">
          <div>
            <h2 class="acct-section-title">Mis listas</h2>
            <p class="acct-section-sub">Colecciones de rutas para viajes, temporadas o salidas con amigos.</p>
          </div>
          <button type="button" class="btn btn-primary btn-sm" id="createListBtn">
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>Nueva lista
          </button>
        </div>
        <div id="userLists"></div>
      </div>

      <!-- ── AI Profile ────────────────────── -->
      <div class="tab-pane fade" id="perfil-ia" role="tabpanel" aria-labelledby="perfil-ia-tab" tabindex="0">
        <div id="perfil-ia-content">
          <div class="acct-loading">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
              <span class="visually-hidden">Cargando…</span>
            </div>
            <span>Cargando tu perfil IA…</span>
          </div>
        </div>
      </div>

    </div><!-- /tab-content -->
  </main>

  <div id="footer"></div>

  <!-- ══ Modals ════════════════════════════════════════════ -->

  <!-- Create List Modal -->
  <div class="modal fade acct-modal" id="createListModal" tabindex="-1"
       aria-labelledby="createListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="createListModalLabel">Nueva lista</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <label for="listName" class="form-label">Nombre de la lista</label>
          <input type="text" class="form-control" id="listName"
                 placeholder="Ej: Rutas de fin de semana" maxlength="80" autocomplete="off"
                 aria-describedby="listNameHelp">
          <div class="invalid-feedback">Ponle un nombre a la lista.</div>
          <div class="form-text" id="listNameHelp">Hasta 80 caracteres. Puedes añadirle rutas desde la ficha de cualquier ruta.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="createListSubmit">
            <i class="bi bi-check-lg me-1"></i>Crear
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View List Routes Modal -->
  <div class="modal fade acct-modal" id="listRoutesModal" tabindex="-1"
       aria-labelledby="listRoutesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title fs-5" id="listRoutesModalLabel">Rutas de la lista</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body" id="listRoutesContent"></div>
      </div>
    </div>
  </div>

  <!-- Confirm Delete Modal -->
  <div class="modal fade acct-modal" id="confirmDeleteModal" tabindex="-1"
       aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-body acct-confirm">
          <span class="acct-confirm__icon" aria-hidden="true"><i class="bi bi-trash3"></i></span>
          <h2 class="modal-title fs-5" id="confirmDeleteLabel">¿Borrar esta lista?</h2>
          <p id="confirmDeleteMsg" class="acct-confirm__text">
            Se borrará la lista. No se puede deshacer.
          </p>
          <div class="acct-confirm__actions">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Conservar lista</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteOk">Borrar lista</button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

  <!-- ══ Scripts ════════════════════════════════════════════ -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
/* Cache-busting: append file mtime as ?v=... so browsers always get
   the latest version after any edit, without manual cache clearing. */
function jsTag(string $rel): string {
    $abs = __DIR__ . '/' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : time();
    return '  <script src="' . e($rel . '?v=' . $v) . "\"></script>\n";
}
// csrf.js primero: añade X-CSRF-Token a los fetch() POST de los scripts de la página
echo "  <script src=\"../../csrf.js\"></script>\n";
echo jsTag('javascript/rutasusuario.js');   // loads shared helpers first
echo jsTag('javascript/favoritosusuario.js');
echo jsTag('javascript/completadasusuario.js');
echo jsTag('javascript/user_lists.js');
echo jsTag('javascript/perfil_ia.js');
echo jsTag('javascript/onboarding.js');
echo jsTag('javascript/actividades_usuario.js');
?>
  <script src="../../include.js"></script>
</body>
</html>
