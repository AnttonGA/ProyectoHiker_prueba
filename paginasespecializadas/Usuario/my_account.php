<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: gestion.php");
    exit();
}
$username     = htmlspecialchars($_SESSION['usuario']);
$avatarLetter = strtoupper(mb_substr($username, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account — <?= $username ?></title>
  <link rel="icon" type="image/svg+xml" href="../../favicon.svg">

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.10.0/font/bootstrap-icons.min.css" rel="stylesheet">

  <!-- Shared + page CSS (versioned to bust stale cache) -->
<?php
function cssTag(string $rel): string {
    $abs = __DIR__ . '/' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : time();
    return "  <link href=\"{$rel}?v={$v}\" rel=\"stylesheet\">\n";
}
echo cssTag('../estilos2.css');
echo cssTag('css/my_account.css');
?>
</head>
<body>
  <div id="menu"></div>

  <!-- ── Hero ──────────────────────────────────────────────── -->
  <section class="acct-hero">
    <div class="container-xl">
      <div class="acct-hero__inner">
        <div class="acct-avatar"><?= $avatarLetter ?></div>
        <div class="acct-hero__info">
          <h1 class="acct-hero__name">Welcome back, <?= $username ?>!</h1>
          <p class="acct-hero__sub mb-0">Explore your routes, favourites and more.</p>
        </div>
        <div class="ms-auto">
          <div class="dropdown">
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="dropdown" aria-expanded="false" title="Settings">
              <i class="bi bi-gear-fill me-1"></i>Settings
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
              <li><a class="dropdown-item" href="edit_profile.php">
                <i class="bi bi-person-gear me-2 text-muted"></i>Edit Profile
              </a></li>
              <li><a class="dropdown-item" href="change_password.php">
                <i class="bi bi-key me-2 text-muted"></i>Change Password
              </a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item text-danger" href="../../cerrar_sesion.php">
                <i class="bi bi-box-arrow-right me-2"></i>Log out
              </a></li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Main ──────────────────────────────────────────────── -->
  <main class="container-xl py-4">

    <!-- ── Discovery / onboarding section (hidden until JS populates it) ── -->
    <div id="onboarding-section" class="onboarding-section mb-4"
         data-user="<?= $username ?>" style="display:none"></div>

    <!-- Tab navigation -->
    <ul class="nav nav-tabs acct-tabs mb-4" id="accountTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="routes-tab"
                data-bs-toggle="tab" data-bs-target="#routes"
                type="button" role="tab" aria-selected="true">
          <i class="bi bi-map me-1"></i><span class="acct-tab-label">My Routes</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="favorites-tab"
                data-bs-toggle="tab" data-bs-target="#favorites"
                type="button" role="tab" aria-selected="false">
          <i class="bi bi-heart me-1"></i><span class="acct-tab-label">Favourites</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="completed-tab"
                data-bs-toggle="tab" data-bs-target="#completed"
                type="button" role="tab" aria-selected="false">
          <i class="bi bi-check-circle me-1"></i><span class="acct-tab-label">Completed</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="lists-tab"
                data-bs-toggle="tab" data-bs-target="#lists"
                type="button" role="tab" aria-selected="false">
          <i class="bi bi-bookmark me-1"></i><span class="acct-tab-label">My Lists</span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="perfil-ia-tab"
                data-bs-toggle="tab" data-bs-target="#perfil-ia"
                type="button" role="tab" aria-selected="false">
          <i class="bi bi-robot me-1"></i><span class="acct-tab-label">AI Profile</span>
        </button>
      </li>
    </ul>

    <!-- Tab content -->
    <div class="tab-content" id="accountTabsContent">

      <!-- ── My Routes ─────────────────────── -->
      <div class="tab-pane fade show active" id="routes" role="tabpanel" aria-labelledby="routes-tab">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 align-items-start" id="userRoutes"></div>
        <nav class="mt-3" id="userRoutes-pagination" aria-label="My routes pagination"></nav>
      </div>

      <!-- ── Favourites ────────────────────── -->
      <div class="tab-pane fade" id="favorites" role="tabpanel" aria-labelledby="favorites-tab">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 align-items-start" id="favoriteRoutes"></div>
        <nav class="mt-3" id="favoriteRoutes-pagination" aria-label="Favourites pagination"></nav>
      </div>

      <!-- ── Completed ─────────────────────── -->
      <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 align-items-start" id="completedRoutes"></div>
        <nav class="mt-3" id="completedRoutes-pagination" aria-label="Completed routes pagination"></nav>
      </div>

      <!-- ── My Lists ──────────────────────── -->
      <div class="tab-pane fade" id="lists" role="tabpanel" aria-labelledby="lists-tab">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h5 class="mb-0 fw-semibold">
            <i class="bi bi-bookmark me-2 text-primary"></i>My Lists
          </h5>
          <button class="btn btn-primary btn-sm" id="createListBtn">
            <i class="bi bi-plus-lg me-1"></i>New List
          </button>
        </div>
        <div id="userLists"></div>
      </div>

      <!-- ── AI Profile ────────────────────── -->
      <div class="tab-pane fade" id="perfil-ia" role="tabpanel" aria-labelledby="perfil-ia-tab">
        <div id="perfil-ia-content" class="text-center py-5">
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading…</span>
          </div>
          <p class="mt-2 text-muted">Loading your AI Profile…</p>
        </div>
      </div>

    </div><!-- /tab-content -->
  </main>

  <div id="footer"></div>

  <!-- ══ Modals ════════════════════════════════════════════ -->

  <!-- Create List Modal -->
  <div class="modal fade" id="createListModal" tabindex="-1"
       aria-labelledby="createListModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="createListModalLabel">
            <i class="bi bi-bookmark-plus me-2"></i>New List
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <label for="listName" class="form-label">List name</label>
          <input type="text" class="form-control" id="listName"
                 placeholder="e.g. Weekend routes" maxlength="80" autocomplete="off">
          <div class="invalid-feedback">Please enter a name for the list.</div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="createListSubmit">
            <i class="bi bi-check-lg me-1"></i>Create
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View List Routes Modal -->
  <div class="modal fade" id="listRoutesModal" tabindex="-1"
       aria-labelledby="listRoutesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="listRoutesModalLabel">Routes in list</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="listRoutesContent"></div>
      </div>
    </div>
  </div>

  <!-- Confirm Delete Modal -->
  <div class="modal fade" id="confirmDeleteModal" tabindex="-1"
       aria-labelledby="confirmDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content">
        <div class="modal-header border-0 pb-0">
          <h5 class="modal-title text-danger" id="confirmDeleteLabel">
            <i class="bi bi-trash me-2"></i>Delete List
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body pt-1">
          <p id="confirmDeleteMsg" class="mb-0 text-muted small">
            Are you sure you want to delete this list? This action cannot be undone.
          </p>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteOk">
            <i class="bi bi-trash me-1"></i>Delete
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

  <!-- ══ Scripts ════════════════════════════════════════════ -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<?php
/* Cache-busting: append file mtime as ?v=... so browsers always get
   the latest version after any edit, without manual cache clearing. */
function jsTag(string $rel): string {
    $abs = __DIR__ . '/' . $rel;
    $v   = file_exists($abs) ? filemtime($abs) : time();
    return "  <script src=\"{$rel}?v={$v}\"></script>\n";
}
echo jsTag('javascript/rutasusuario.js');   // loads shared helpers first
echo jsTag('javascript/favoritosusuario.js');
echo jsTag('javascript/completadasusuario.js');
echo jsTag('javascript/user_lists.js');
echo jsTag('javascript/perfil_ia.js');
echo jsTag('javascript/onboarding.js');
?>
  <script src="../../include.js"></script>
</body>
</html>
