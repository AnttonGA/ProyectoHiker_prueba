
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Base de datos 01</title>
    
</head>
<style>
    #message {
        margin-bottom: 15px;
    }
    #message p {
        padding: 10px;
        border-radius: 5px;
        margin: 0;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    .error {
        color: #dc3545;
    }
    .warning {
        
        color: #dc3545;
        
    }
</style>
<body>
<div id="container">
    <div id="message">
        <?php
        // personalizar error
        if (isset($_GET['msg'])){
            $errores = ['error Nombre y usuario 0 ','Error EN DB 1 ','vacio 2'];
            echo "<p>".$errores[$_GET['msg']]."</p>";
        }
        ?>
    </div>
    <h2 class="mb-4">Log In</h2>
    <form id="loginForm" name="form1" action="acceso.php" method="POST" onsubmit="return false;">
        <div class="mb-3">
            <label for="usuario" class="form-label">User:</label>
            <input type="text" name="usuario" class="form-control" id="usuario">
        </div>
        <div id="message"></div>
        <div class="mb-3">
            <label for="password" class="form-label">Password:</label>
            <input type="password" name="password" class="form-control" id="password">
        </div>
        <div id="message"></div>
        <div class="mb-3">
            <a href="forget_password.php" target="_blank">¿Did you forget your password?</a>
        </div>
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="mantenerseLogeado">
            <label class="form-check-label" for="mantenerseLogeado">keep me logged</label>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
</div>

<script src="login.js"></script>
</body>
</html>