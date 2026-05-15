<?php
session_start();
require_once 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: perfil.php');
    exit;
}

$id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

$stmt = $pdo->prepare('UPDATE usuarios SET nombre_usuario = ?, telefono_usuario = ? WHERE usuario_id = ?');
$stmt->execute([$nombre, $telefono, $id]);

// hay que reflejar el nombre nuevo en la sesión también, si no se queda colgado el viejo en la topbar
$_SESSION['nombre'] = $nombre;

if ($rol === 'empleador') {
    $nombre_centro = trim($_POST['nombre_centro'] ?? '');
    $localizacion = trim($_POST['localizacion'] ?? '');

    $stmt = $pdo->prepare('UPDATE centros SET nombre_centro = ?, localizacion_centro = ? WHERE empleador_id = ?');
    $stmt->execute([$nombre_centro, $localizacion, $id]);
}

// si el usuario rellenó algo del bloque de contraseña, intentamos cambiarla
$password_actual = $_POST['password_actual'] ?? '';
$password_nueva = $_POST['password_nueva'] ?? '';
$password_confirmar = $_POST['password_confirmar'] ?? '';

if ($password_actual !== '' || $password_nueva !== '' || $password_confirmar !== '') {
    $stmt = $pdo->prepare('SELECT password_usuario FROM usuarios WHERE usuario_id = ?');
    $stmt->execute([$id]);
    $fila = $stmt->fetch();

    if (!password_verify($password_actual, $fila['password_usuario'])) {
        header('Location: perfil.php?error=password_actual');
        exit;
    }

    if ($password_nueva !== $password_confirmar) {
        header('Location: perfil.php?error=password_match');
        exit;
    }

    // misma política de contraseñas que en el registro: 8+ caracteres, número y carácter especial
    if (!preg_match('/^(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,}$/', $password_nueva)) {
        header('Location: perfil.php?error=password_debil');
        exit;
    }

    $hash = password_hash($password_nueva, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare('UPDATE usuarios SET password_usuario = ? WHERE usuario_id = ?');
    $stmt->execute([$hash, $id]);
}

header('Location: perfil.php?ok=1');
exit;
