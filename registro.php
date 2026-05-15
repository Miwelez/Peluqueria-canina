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
    <title>Peluquería Canina — Registro</title>
    <link rel="stylesheet" href="assets/css/login_registro.css">
</head>
<body>

<div class="logo-top">
    <img src="assets/img/logo.png" alt="Logo" onerror="this.style.display='none'">
</div>

<div class="login-wrapper">
    <div class="login-container">

        <p class="subtitulo">Crea tu cuenta</p>
        <h1>Regístrate</h1>

        <?php if (isset($_GET['error'])): ?>
            <p class="error">
                <?php if ($_GET['error'] === 'email'): ?>
                    Ese email ya está registrado.
                <?php elseif ($_GET['error'] === 'email_invalido'): ?>
                    El formato del email no es válido.
                <?php elseif ($_GET['error'] === 'password_debil'): ?>
                    La contraseña debe tener al menos 8 caracteres, un número y un carácter especial.
                <?php else: ?>
                    Ha ocurrido un error. Inténtalo de nuevo.
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <!-- toggle empleado/empleador -->
        <div class="rol-toggle">
            <button type="button" id="btn-empleado" onclick="seleccionarRol('empleado')">Peluquero</button>
            <button type="button" id="btn-empleador" onclick="seleccionarRol('empleador')">Peluquería</button>
        </div>

        <form action="procesar_registro.php" method="POST">

            <input type="hidden" id="rol" name="rol" value="empleado">

            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" name="nombre" placeholder="Tu nombre" required>

            <label for="email">Email</label>
            <input type="email" id="email" name="email" placeholder="tu@email.com" required>

            <label for="telefono">Teléfono</label>
            <input type="tel" id="telefono" name="telefono" placeholder="600 000 000" required>

            <label for="password">Contraseña</label>
            <input type="password" id="password" name="password" placeholder="Contraseña"
                   required minlength="8"
                   pattern="^(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,}$"
                   title="Mínimo 8 caracteres, al menos un número y un carácter especial">
            <small class="ayuda-campo">
                Mínimo 8 caracteres, un número y un carácter especial.
            </small>

            <div id="campo-centro">
                <label for="nombre_centro">Nombre del centro</label>
                <input type="text" id="nombre_centro" name="nombre_centro" placeholder="Mi Peluquería Canina">
            </div>

            <button type="submit">Crear cuenta</button>

        </form>

        <p class="link-pie">¿Ya tienes cuenta? <a href="index.php">Inicia sesión</a></p>

    </div>
</div>

<script>
function seleccionarRol(rol) {
    document.getElementById('rol').value = rol;

    // marcamos el botón activo y desmarcamos el otro
    document.getElementById('btn-empleado').classList.remove('activo');
    document.getElementById('btn-empleador').classList.remove('activo');
    document.getElementById('btn-' + rol).classList.add('activo');

    // el campo "nombre del centro" solo aparece si eres empleador
    if (rol === 'empleador') {
        document.getElementById('campo-centro').classList.add('visible');
    } else {
        document.getElementById('campo-centro').classList.remove('visible');
    }
}

// arrancamos con empleado seleccionado por defecto
seleccionarRol('empleado');
</script>

</body>
</html>
