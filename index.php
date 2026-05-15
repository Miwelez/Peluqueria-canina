<?php
session_start();

if (isset($_SESSION['usuario_id'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peluquería Canina — Acceso</title>
    <link rel="stylesheet" href="assets/css/login_registro.css">
</head>
<body>

<div class="logo-top">
    <!-- Sustituir por <img src="assets/img/logo.png" alt="Logo"> cuando esté disponible -->
    <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
</div>

<div class="login-wrapper">
    <div class="login-container">

        <p class="subtitulo">Introduce tus datos</p>
        <h1>Bienvenido</h1>

        <?php if (isset($_GET['error'])): ?>
            <p class="error">Email o contraseña incorrectos.</p>
        <?php endif; ?>

        <form action="procesar_login.php" method="POST" autocomplete="off">

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="tu@email.com" required autofocus autocomplete="off">

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Contraseña" required autocomplete="new-password">

            <button type="submit">Entrar</button>

        </form>

        <p class="link-pie">¿No tienes cuenta? <a href="registro.php">Regístrate</a></p>

    </div>
</div>

</body>
</html>
