<?php include_once __DIR__ . '/functions.php'; ?>
<footer class="k-footer">
  <div class="container">
    <div class="row g-4">
      <div class="col-lg-5">
        <a class="k-footer-brand" href="<?php echo e(root_path('index.php')); ?>">
          <img src="<?php echo e(root_path('assets/img/kahir-mark.svg')); ?>" alt="" width="30" height="30">
          Kahir
        </a>
        <p class="k-footer-note mt-3 mb-0">
          Un kahir es un pequeño montón de piedras que marca el camino en el Pirineo.
          Te ayudamos a encontrar la ruta que encaja con tu nivel, hoy.
        </p>
      </div>
      <div class="col-6 col-lg-2 offset-lg-1">
        <h6>Explora</h6>
        <ul>
          <li><a href="<?php echo e(root_path('paginasespecializadas/skitouring/skitouring.html')); ?>">Rutas</a></li>
          <li><a href="<?php echo e(root_path('paginasespecializadas/huts/huts.html')); ?>">Refugios</a></li>
          <li><a href="<?php echo e(root_path('index.php')); ?>">Asistente</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6>Tu cuenta</h6>
        <ul>
          <li><a href="<?php echo e(root_path('paginasespecializadas/Usuario/my_account.php')); ?>">Mi cuenta</a></li>
          <li><a href="<?php echo e(root_path('paginasespecializadas/Usuario/subir_ruta.html')); ?>">Subir ruta</a></li>
        </ul>
      </div>
      <div class="col-lg-2">
        <h6>Seguridad</h6>
        <p class="k-footer-note mb-0" style="font-size:.85rem">
          Kahir te orienta, pero no sustituye a tu criterio ni al boletín oficial de aludes.
        </p>
      </div>
    </div>
    <div class="k-footer-bottom d-flex flex-wrap justify-content-between gap-2">
      <span>© <?php echo date('Y'); ?> Kahir.ai</span>
      <span>Pirineo · País Vasco</span>
    </div>
  </div>
</footer>
