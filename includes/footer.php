<?php include_once __DIR__ . '/functions.php'; ?>
<style>
  .footer {
    background-color: #fff;
    padding: 20px 0;
    border-top: 1px solid #e9ecef;
    margin-top: 40px;
  }
  .footer .col-md-4 img {
    max-height: 40px;
  }
  .footer .text-end a i {
    font-size: 24px;
    color: #333;
    transition: color .2s;
  }
  .footer .text-end a:hover i { color: #0d6efd; }
</style>

<footer class="footer bg-light py-3">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-4">
        <p class="mb-1"><strong>Address:</strong></p>
        <p class="mb-0 text-muted small">Calle de ejemplo, 123<br>Ciudad, País<br>Tel: +123 456 789</p>
      </div>
      <div class="col-md-4 text-center">
        <a href="<?php echo root_path('index.php'); ?>">
          <img src="<?php echo root_path('logo.svg'); ?>" alt="Hiker Logo" class="img-fluid" style="max-height:40px;">
        </a>
      </div>
      <div class="col-md-4 text-end">
        <a href="https://facebook.com" target="_blank" class="me-2"><i class="bi bi-facebook"></i></a>
        <a href="https://twitter.com" target="_blank" class="me-2"><i class="bi bi-twitter-x"></i></a>
        <a href="https://instagram.com" target="_blank" class="me-2"><i class="bi bi-instagram"></i></a>
        <a href="https://tiktok.com" target="_blank"><i class="bi bi-tiktok"></i></a>
      </div>
    </div>
  </div>
</footer>
