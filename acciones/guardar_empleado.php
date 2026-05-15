<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'empleador' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?sec=empleados');
    exit;
}

$accion = $_POST['accion'] ?? '';
$usuario_id = (int)($_POST['usuario_id'] ?? 0);
$centro_id = (int)($_POST['centro_id'] ?? 0);

if ($accion === 'añadir' && $usuario_id) {
    // de momento asumimos que el empleador tiene un solo centro y cogemos ese
    $stmt = $pdo->prepare('SELECT centro_id FROM centros
                           WHERE empleador_id = ? AND activo = 1 LIMIT 1');

    $stmt->execute([$_SESSION['usuario_id']]);
    $centro = $stmt->fetch();

    if ($centro) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO usuario_centro (usuario_id, centro_id)
                               VALUES (?, ?)');

        $stmt->execute([$usuario_id, $centro['centro_id']]);
    }
}

if ($accion === 'quitar' && $usuario_id && $centro_id) {
    // antes de borrar nos aseguramos que el centro es de este empleador (si no, alguien podría manipular el POST)
    
    $stmt = $pdo->prepare('SELECT centro_id FROM centros
                           WHERE centro_id = ? AND empleador_id = ?');
    $stmt->execute([$centro_id, $_SESSION['usuario_id']]);



    if ($stmt->fetch()) {
        $stmt = $pdo->prepare('DELETE FROM usuario_centro WHERE usuario_id = ? AND centro_id = ?');
        $stmt->execute([$usuario_id, $centro_id]);
    }
}

header('Location: ../dashboard.php?sec=empleados');
exit;
