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

<?php include 'functions.php'; ?>

</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-light">
  <div class="container">
    <!-- Logo/Home -->
    <a class="navbar-brand" href="<?php echo root_path('index.php'); ?>">
      <img src="<?php echo root_path('logo.png'); ?>" alt="Logo" style="max-height: 40px;">
    </a>

    <!-- Navbar toggler for mobile -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link" href="<?php echo root_path('paginasespecializadas/skitouring/skitouring.html'); ?>">Map</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="<?php echo root_path('paginasespecializadas/huts/huts.html'); ?>">Huts</a>
        </li>
        <!-- PHP para mostrar "Log in" o nombre de usuario dependiendo de la sesión -->
        <?php
        if (isset($_SESSION['usuario'])) {
          // Si la sesión está iniciada, mostrar nombre de usuario y menú desplegable
          echo '
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle" href="#" id="navbarDropdownMenuLink" role="button" data-bs-toggle="dropdown" aria-expanded="false">' . $_SESSION['usuario'] . '</a>
              <ul class="dropdown-menu" aria-labelledby="navbarDropdownMenuLink">
                <li><a class="dropdown-item" href="' . root_path('paginasespecializadas/Usuario/my_account.html') . '">My account</a></li>
                <li><a class="dropdown-item" href="#">Configuration</a></li>
                <li><a class="dropdown-item" href="' . root_path('paginasespecializadas/Usuario/subir_ruta.html') . '">Upload your route</a></li>
                <li><a class="dropdown-item" href="' . root_path('paginasespecializadas/Usuario/my_routes.php') . '">Manage your routes</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="' . root_path('cerrar_sesion.php?return_url=' . urlencode($_SERVER['REQUEST_URI'])) . '">Cerrar sesión</a></li>
              </ul>
            </li>
          ';
          // Mostrar opciones adicionales para el rol de administrador
          if (isset($_SESSION['rol']) && $_SESSION['rol'] == 'A') {
            echo '<li class="nav-item"><a class="nav-link" href="' . root_path('paginasespecializadas/Admin/admin_dashboard.php') . '">Admin Dashboard</a></li>';
          }
        } else {
          // Si la sesión no está iniciada, mostrar "Log in"
          echo '<li class="nav-item"><a class="nav-link" href="' . root_path('paginasespecializadas/inicio_sesion/gestion.php') . '">Log in</a></li>';
        }
        ?>
      </ul>
    </div>
  </div>
</nav>

