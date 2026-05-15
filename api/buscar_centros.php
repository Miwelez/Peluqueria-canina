<?php
session_start();
require_once '../conexion.php';

// si no hay usuario id, error 401 y json vacio
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$id = $_SESSION['usuario_id'];

// centros activos cuyo nombre concida y que el empleado no tenga ya en su listado
$stmt = $pdo->prepare('
    SELECT c.centro_id, c.nombre_centro, c.localizacion_centro
    FROM centros c
    WHERE c.activo = 1
    AND c.nombre_centro LIKE ?
    AND c.centro_id NOT IN (
        SELECT uc.centro_id FROM usuario_centro uc WHERE uc.usuario_id = ?
    )
    ORDER BY c.nombre_centro
    LIMIT 10
');

$stmt->execute(['%' . $q . '%', $id]);

echo json_encode($stmt->fetchAll());