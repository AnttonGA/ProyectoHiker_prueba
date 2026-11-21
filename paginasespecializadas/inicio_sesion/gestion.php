<?php
// La sesión debe iniciarse antes de cualquier salida: login.php y registro_ia.php se incluyen más abajo
require_once __DIR__ . '/../../includes/security.php';
secure_session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro e Inicio de Sesión</title>
  <!-- Enlace a Bootstrap 5.2.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Estilos adicionales -->
  <link href="../estilos2.css" rel="stylesheet">
</head>

<body>
  <!-- Navbar -->
  <div id="menu"></div>

  <!-- Header -->
  <div class="header">
    <div class="overlay">
      <h1>TU CUENTA</h1>
    </div>
  </div>

  <!-- Contenido -->
  <div class="container">


    <!-- Pestañas de navegación -->
    <ul class="nav nav-tabs mb-4">
      <li class="nav-item">
        <a class="nav-link active" id="inicio-sesion-tab" data-bs-toggle="tab" href="#inicio-sesion" role="tab" aria-controls="inicio-sesion" aria-selected="true">Log in</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" id="registro-tab" data-bs-toggle="tab" href="#registro" role="tab" aria-controls="registro" aria-selected="false">Sign up</a>
      </li>
    </ul>

    <!-- Contenido de las pestañas -->
    <div class="tab-content mb-4">
      <!-- Pestaña de Inicio de Sesión -->
      <div class="tab-pane fade show active" id="inicio-sesion" role="tabpanel" aria-labelledby="inicio-sesion-tab">
      <?php include 'login.php'; ?>
      </div>

      <!-- Pestaña de Registro -->
      <div class="tab-pane fade" id="registro" role="tabpanel" aria-labelledby="registro-tab">
      
      <?php include 'registro_ia.php'; ?>
      
      </div>
    </div>

  </div>

  <!-- Footer -->
  <div id="footer"></div>
  <!-- Enlace a Bootstrap 5.2.3 JS (opcional, para funcionalidades avanzadas) -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../../include.js"></script>
  
  
</body>

</html>
