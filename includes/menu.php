<?php
// Se carga antes de cualquier salida para poder iniciar la sesión (functions.php inicia la sesión segura)
require_once __DIR__ . '/functions.php';
?>
<style>
  .navbar-nav .nav-link {
    font-size: 18px;
    padding: 10px 15px;
    color: #333;
  }

  .navbar-nav .nav-item.active .nav-link {
    color: #007bff;
  }

  .navbar-toggler {
    border-radius: 4px;
  }

  .navbar-brand img {
    max-height: 40px;
  }

  .navbar {
    margin-top: 20px;
    margin-bottom: 20px;
    background-color: #ffffff;
  }

  .navbar-nav {
    margin-left: auto;
  }
</style>


</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container">
    <!-- Logo/Home -->
    <a class="navbar-brand" href="<?php echo e(root_path('index.php')); ?>">
      <img src="<?php echo e(root_path('logo.svg')); ?>" alt="Hiker Logo" style="max-height: 40px;">
    </a>

    <!-- Navbar toggler for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo e(root_path('paginasespecializadas/skitouring/skitouring.html')); ?>">Search</a>
        </li>
        <!-- PHP para mostrar "Log in" o nombre de usuario dependiendo de la sesión -->
        <?php
        if (isset($_SESSION['usuario'])) {
          // Si la sesión está iniciada, mostrar nombre de usuario (escapado)
          echo '<li class="nav-item"><a class="nav-link" href="' . e(root_path('paginasespecializadas/Usuario/my_account.php')) . '">' . e($_SESSION['usuario']) . '</a></li>';
          echo '<li class="nav-item"><a class="nav-link" href="' . e(root_path('paginasespecializadas/Usuario/subir_ruta.html')) . '"> upload route</a></li>';
        } else {
          // Si la sesión no está iniciada, mostrar "Log in"
          echo '<li class="nav-item"><a class="nav-link" href="' . e(root_path('paginasespecializadas/inicio_sesion/gestion.php')) . '">Log in</a></li>';
        }
        // Opciones adicionales para el rol de administrador
        if (is_admin()) {
          echo '<li class="nav-item"><a class="nav-link" href="' . e(root_path('paginasespecializadas/Admin/admin_dashboard.php')) . '">Admin Dashboard</a></li>';
        }
        if (isset($_SESSION['usuario'])) {
          echo '<li class="nav-item"><a class="nav-link" href="' . e(root_path('cerrar_sesion.php')) . '">Log out</a></li>';
        }
        ?>
      </ul>
    </div>
  </div>
</nav>

