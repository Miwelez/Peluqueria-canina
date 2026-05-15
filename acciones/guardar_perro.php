<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?sec=clientes');
    exit;
}

$accion = $_POST['accion'] ?? '';

// el perro está en un cliente, el cliente en un centro, y el centro lo usa el usuario.

function perroEsDelUsuario(PDO $pdo, int $perro_id, int $usuario_id, string $rol): bool {
    if ($rol === 'empleador') {
        $stmt = $pdo->prepare('
            SELECT 1 FROM perros p
            INNER JOIN clientes cl ON cl.cliente_id = p.cliente_id
            INNER JOIN centros c ON c.centro_id = cl.centro_id
            WHERE p.perro_id = ? AND c.empleador_id = ? AND p.activo = 1
        ');
    } else {
        $stmt = $pdo->prepare('
            SELECT 1 FROM perros p
            INNER JOIN clientes cl ON cl.cliente_id = p.cliente_id
            INNER JOIN centros c ON c.centro_id = cl.centro_id
            INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
            WHERE p.perro_id = ? AND uc.usuario_id = ? AND p.activo = 1
        ');
    }
    $stmt->execute([$perro_id, $usuario_id]);
    return (bool) $stmt->fetch();
}


// --- crear perro ---

if ($accion === 'crear') {
    $nombre = trim($_POST['nombre_perro'] ?? '');
    $raza_id = (int)($_POST['raza_id'] ?? 0);
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);

    if ($nombre === '' || $raza_id === 0 || $cliente_id === 0) {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    // antes de meter el perro nos aseguramos que el cliente es del usuario
    if ($_SESSION['rol'] === 'empleador') {
        $stmt = $pdo->prepare('
            SELECT 1 FROM clientes cl
            INNER JOIN centros c ON c.centro_id = cl.centro_id
            WHERE cl.cliente_id = ? AND c.empleador_id = ? AND cl.activo = 1
        ');
    } else {
        $stmt = $pdo->prepare('
            SELECT 1 FROM clientes cl
            INNER JOIN centros c ON c.centro_id = cl.centro_id
            INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
            WHERE cl.cliente_id = ? AND uc.usuario_id = ? AND cl.activo = 1
        ');
    }
    $stmt->execute([$cliente_id, $_SESSION['usuario_id']]);

    if (!$stmt->fetch()) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    // comprobamos también que la raza enviada exista en el catálogo
    $stmt = $pdo->prepare('SELECT 1 FROM razas WHERE raza_id = ?');
    $stmt->execute([$raza_id]);
    if (!$stmt->fetch()) {
        header('Location: ../dashboard.php?sec=clientes&error=raza');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO perros (nombre_perro, raza_id, cliente_id)
                           VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $raza_id, $cliente_id]);

    header('Location: ../dashboard.php?sec=clientes&ok=perro_creado');
    exit;
}



// --- editar perro ---

if ($accion === 'editar') {
    $perro_id = (int)($_POST['perro_id'] ?? 0);
    $nombre = trim($_POST['nombre_perro'] ?? '');
    $raza_id = (int)($_POST['raza_id'] ?? 0);

    if ($perro_id === 0 || $nombre === '' || $raza_id === 0) {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    if (!perroEsDelUsuario($pdo, $perro_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM razas WHERE raza_id = ?');
    $stmt->execute([$raza_id]);
    if (!$stmt->fetch()) {
        header('Location: ../dashboard.php?sec=clientes&error=raza');
        exit;
    }

    $stmt = $pdo->prepare('UPDATE perros SET nombre_perro = ?, raza_id = ? WHERE perro_id = ?');
    $stmt->execute([$nombre, $raza_id, $perro_id]);

    header('Location: ../dashboard.php?sec=clientes&ok=perro_editado');
    exit;
}


// --- borrar perro ---


if ($accion === 'quitar') {
    $perro_id = (int)($_POST['perro_id'] ?? 0);

    if ($perro_id === 0) {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    if (!perroEsDelUsuario($pdo, $perro_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    $stmt = $pdo->prepare('UPDATE perros SET activo = 0 WHERE perro_id = ?');
    $stmt->execute([$perro_id]);

    header('Location: ../dashboard.php?sec=clientes&ok=perro_borrado');
    exit;
}

header('Location: ../dashboard.php?sec=clientes');
exit;
