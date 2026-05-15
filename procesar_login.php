<?php
session_start();
require_once 'conexion.php';

// si no viene por POST, lo mandamos al login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

$stmt = $pdo->prepare('SELECT * FROM usuarios
                       WHERE email_usuario = ? AND activo = 1');
$stmt->execute([$email]);
$usuario = $stmt->fetch();

if (!$usuario) {
    header('Location: index.php?error=1');
    exit;
}

if (!password_verify($password, $usuario['password_usuario'])) {
    header('Location: index.php?error=1');
    exit;
}

// todo correcto, guardamos en la sesión los datos que vamos a necesitar
$_SESSION['usuario_id'] = $usuario['usuario_id'];
$_SESSION['nombre'] = $usuario['nombre_usuario'];
$_SESSION['rol'] = $usuario['rol_usuario'];

header('Location: dashboard.php');
exit;
