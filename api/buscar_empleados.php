<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'empleador') {
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

// empleados activos por nombre, excluyendo los que ya están en algún centro de este empleador

$stmt = $pdo->prepare("
    SELECT u.usuario_id, u.nombre_usuario, u.email_usuario
    FROM usuarios u
    WHERE u.rol_usuario = 'empleado'
    AND u.activo = 1
    AND u.nombre_usuario LIKE ?
    AND u.usuario_id NOT IN (
        SELECT uc.usuario_id
        FROM usuario_centro uc
        INNER JOIN centros c ON c.centro_id = uc.centro_id
        WHERE c.empleador_id = ?
    )
    ORDER BY u.nombre_usuario
    LIMIT 10
");
$stmt->execute(['%' . $q . '%', $id]);

echo json_encode($stmt->fetchAll());
