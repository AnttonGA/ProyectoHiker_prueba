<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bootstrap Navbar Example</title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-rbsA2VBKQhggwzxH7pPCaAqO46MgnOM80zW1RWuH61DGLwZJEdK2Kadq2F9CUG65" crossorigin="anonymous">
  <link href="estilo.css" rel="stylesheet">
</head>
<?php include 'includes/functions.php'; ?>
<body>
  <!-- Navbar -->
  <nav id="menu"></nav>

  <div class="container mt-3 ">
    <div class="row">
      <div class="col-md-6 col-sm-12 p-1">
        <a href="<?php echo root_path('paginasespecializadas/skitouring/skitouring.html?id=1'); ?>" class="d-block image-container full-height" style="height: 250px;">
          <img src="imagenes/hiking.jpg" alt="Image 1" class="image" >
          <div class="overlay-text text-center text-white">ADVENTURES NEAR ME</div>
        </a>
      </div>
      <div class="col-md-6 col-sm-12 p-1">
        <a href="<?php echo root_path('paginasespecializadas/huts/huts.html'); ?>" class="d-block image-container full-height" style="height: 250px;">
          <img src="imagenes/skitouring.jpg" alt="Image 2" class="image">
          <div class="overlay-text text-center text-white">HUTS</div>
        </a>
      </div>
    </div>
    <div class="row">
      <div class="col-md-6 col-sm-12 p-1">
        <a href="<?php echo root_path('#'); ?>" class="d-block image-container full-height" style="height: 250px;">
          <img src="imagenes/GR_route.jpg" alt="Image 3" class="image">
          <div class="overlay-text text-center text-white">HOUSING</div>
        </a>
      </div>
      <div class="col-md-6 col-sm-12 p-1">
        <a href="<?php echo root_path('#'); ?>" class="d-block image-container full-height" style="height: 250px;">
          <img src="imagenes/cycletouring.jpg" alt="Image 4" class="image">
          <div class="overlay-text text-center text-white">GEAR SHOPS</div>
        </a>
      </div>
    </div>
  </div>
  
  <div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <!-- Texto para incentivar el uso del buscador -->
      <p class="lead">Descubre nuevas aventuras y experiencias. ¿Qué estás buscando hoy? ¡Te ayudamos!</p>
      <!-- Formulario de búsqueda -->
      <form class="form-inline">
        <div class="input-group w-100">
          <!-- Campo de entrada de texto -->
          <input type="text" class="form-control" placeholder="Buscar..." aria-label="Buscar" aria-describedby="button-addon2">
          <!-- Botón de búsqueda -->
          <div class="input-group-append">
            <button class="btn btn-primary" type="submit" id="button-addon2">Buscar</button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

  

  <!-- Footer -->
  <nav id="footer"></nav>

  <!-- Bootstrap JS and dependencies -->
  <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.min.js"></script>
  <script src="include.js"></script>
</body>
</html>
