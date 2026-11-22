<?php
// Se carga antes de cualquier salida para poder iniciar la sesión (functions.php inicia la sesión segura).
// include.js inserta este HTML en #menu de cada página y marca el enlace activo.
require_once __DIR__ . '/functions.php';

$logueado = isset($_SESSION['usuario']);
$nombre   = $logueado ? (string) $_SESSION['usuario'] : '';
$inicial  = $logueado ? mb_substr($nombre, 0, 1, 'UTF-8') : '';
?>
<nav class="navbar navbar-expand-lg k-nav" aria-label="Navegación principal">
  <div class="container">
    <a class="navbar-brand" href="<?php echo e(root_path('index.php')); ?>">
      <img src="<?php echo e(root_path('assets/img/kahir-mark.svg')); ?>" alt="" class="k-brand-mark" width="30" height="30">
      Kahir
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#kahirNav"
            aria-controls="kahirNav" aria-expanded="false" aria-label="Abrir menú">
      <i class="bi bi-list fs-4" aria-hidden="true"></i>
    </button>

    <div class="collapse navbar-collapse" id="kahirNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo e(root_path('paginasespecializadas/skitouring/skitouring.html')); ?>">
            <i class="bi bi-map me-1" aria-hidden="true"></i>Rutas
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo e(root_path('paginasespecializadas/huts/huts.html')); ?>">
            <i class="bi bi-house-door me-1" aria-hidden="true"></i>Refugios
          </a>
        </li>
        <?php if ($logueado): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo e(root_path('index.php')); ?>">
              <i class="bi bi-chat-dots me-1" aria-hidden="true"></i>Asistente
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo e(root_path('paginasespecializadas/Usuario/subir_ruta.html')); ?>">
              <i class="bi bi-upload me-1" aria-hidden="true"></i>Subir ruta
            </a>
          </li>
          <?php if (is_admin()): ?>
            <li class="nav-item">
              <a class="nav-link" href="<?php echo e(root_path('paginasespecializadas/Admin/admin_dashboard.php')); ?>">
                <i class="bi bi-shield-check me-1" aria-hidden="true"></i>Admin
              </a>
            </li>
          <?php endif; ?>
          <li class="nav-item ms-lg-2">
            <a class="nav-link k-nav-user" href="<?php echo e(root_path('paginasespecializadas/Usuario/my_account.php')); ?>">
              <span class="k-avatar" aria-hidden="true"><?php echo e($inicial); ?></span><?php echo e($nombre); ?>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo e(root_path('cerrar_sesion.php')); ?>" title="Salir">
              <i class="bi bi-box-arrow-right" aria-hidden="true"></i><span class="d-lg-none ms-1">Salir</span>
            </a>
          </li>
        <?php else: ?>
          <li class="nav-item ms-lg-2">
            <a class="nav-link k-nav-cta" href="<?php echo e(root_path('paginasespecializadas/inicio_sesion/gestion.php')); ?>">Entrar</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
