<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

header('Content-Type: application/json');

$id = $_SESSION['usuario_id'];
$accion = $_POST['accion'] ?? '';
$centro_id = (int)($_POST['centro_id'] ?? 0);

if (!$centro_id) {
    echo json_encode(['ok' => false]);
    exit;
}

if ($accion === 'unirse') {
    // comprobamos primero que el centro existe y está activo
    $stmt = $pdo->prepare('SELECT centro_id FROM centros WHERE centro_id = ? AND activo = 1');
    $stmt->execute([$centro_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['ok' => false]);
        exit;
    }

    // INSERT IGNORE para que no falle si ya estaba unido (UNIQUE en usuario_id+centro_id)
    $stmt = $pdo->prepare('INSERT IGNORE INTO usuario_centro (usuario_id, centro_id) VALUES (?, ?)');
    $stmt->execute([$id, $centro_id]);

} elseif ($accion === 'salir') {
    
    $stmt = $pdo->prepare('DELETE FROM usuario_centro WHERE usuario_id = ? AND centro_id = ?');
    $stmt->execute([$id, $centro_id]);

} else {
    echo json_encode(['ok' => false]);
    exit;
}

echo json_encode(['ok' => true]);
