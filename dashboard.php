<?php
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

$seccion = $_GET['sec'] ?? 'calendario';
$rol = $_SESSION['rol'];
$nombre = $_SESSION['nombre'];
?>


<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Peluquería Canina — Dashboard</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>


<!-- topbar fija arriba -->

<header class="topbar">
    <div class="topbar-izquierda">
        <span class="topbar-titulo">Dashboard</span>
    </div>
    <div class="topbar-derecha">
        <span class="topbar-nombre"><?= htmlspecialchars(explode(' ', $nombre)[0]) ?></span>
        <a href="perfil.php" class="topbar-icono" title="Mi perfil"><i class="fa-regular fa-circle-user"></i></a>
        <a href="logout.php" class="topbar-icono" title="Cerrar sesión"><i class="fa-solid fa-right-from-bracket"></i></a>
    </div>
</header>

<main class="main-contenido">

    <nav class="tabs">
        <a href="dashboard.php?sec=calendario" class="tab <?= $seccion === 'calendario' ? 'activo' : '' ?>">Calendario</a>
        <a href="dashboard.php?sec=clientes" class="tab <?= $seccion === 'clientes' ? 'activo' : '' ?>">Clientes</a>
        <?php if ($rol === 'empleador'): ?>
            <a href="dashboard.php?sec=empleados" class="tab <?= $seccion === 'empleados' ? 'activo' : '' ?>">Empleados</a>
        <?php endif; ?>
    </nav>

    
    <!-- contenido de la pestaña activa-->

    <div class="seccion-contenido">
        <?php include "dashboard_secciones/{$seccion}.php"; ?>
    </div>

</main>

</body>
</html>
