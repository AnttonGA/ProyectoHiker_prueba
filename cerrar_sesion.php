<?php
session_start();
session_destroy(); // Destruye todas las variables de sesión

include 'includes/functions.php';

// Redirige al usuario a la página principal o a donde desees
header("Location: " . root_path('index.php'));
exit;
?>
