<?php
session_start();
require_once 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$nombre = trim($_POST['nombre'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$password = $_POST['password'] ?? '';
$rol = $_POST['rol'] ?? 'empleado';
$centro = trim($_POST['nombre_centro'] ?? '');

// validamos el formato del email antes de pegarle a la BD
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: registro.php?error=email_invalido');
    exit;
}

// y la contraseña tiene que cumplir los requisitos mínimos de seguridad
// (8+ caracteres, al menos un dígito, al menos un carácter no alfanumérico no espacio)
if (!preg_match('/^(?=.*\d)(?=.*[^A-Za-z0-9\s]).{8,}$/', $password)) {
    header('Location: registro.php?error=password_debil');
    exit;
}

// comprobamos que el email no esté ya registrado
$stmt = $pdo->prepare('SELECT usuario_id FROM usuarios WHERE email_usuario = ?');
$stmt->execute([$email]);
if ($stmt->fetch()) {
    header('Location: registro.php?error=email');
    exit;
}

// hashear la contraseña antes de meterla en la BD
$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('INSERT INTO usuarios (nombre_usuario, email_usuario, password_usuario, telefono_usuario, rol_usuario)
                       VALUES (?, ?, ?, ?, ?)');
$stmt->execute([$nombre, $email, $hash, $telefono, $rol]);
$usuario_nuevo_id = $pdo->lastInsertId(); // guardamos el id que se ha creado tras el insert

// si es empleador creamos también su centro y lo vinculamos
if ($rol === 'empleador' && $centro !== '') {
    $stmt = $pdo->prepare('INSERT INTO centros (nombre_centro, empleador_id, localizacion_centro) VALUES (?, ?, ?)');
    $stmt->execute([$centro, $usuario_nuevo_id, '']); // la localización se rellena luego desde perfil.php

    $centro_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare('INSERT INTO usuario_centro (usuario_id, centro_id) VALUES (?, ?)');
    $stmt->execute([$usuario_nuevo_id, $centro_id]);
}

// auto-login después del registro para que no tenga que escribir las credenciales otra vez
$_SESSION['usuario_id'] = $usuario_nuevo_id;
$_SESSION['nombre'] = $nombre;
$_SESSION['rol'] = $rol;

header('Location: dashboard.php');
exit;
