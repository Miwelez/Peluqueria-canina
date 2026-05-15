<?php
session_start();
require_once '../conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../dashboard.php?sec=clientes');
    exit;
}

$accion = $_POST['accion'] ?? '';


// chequea si el cliente está en un centro que el usuario gestione (empleador) o donde trabaje (empleado)
function clienteEsDelUsuario(PDO $pdo, int $cliente_id, int $usuario_id, string $rol): bool {
    if ($rol === 'empleador') {
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
    $stmt->execute([$cliente_id, $usuario_id]);
    return (bool) $stmt->fetch();
}



// --- crear cliente ---

if ($accion === 'crear') {
    $nombre = trim($_POST['nombre_cliente'] ?? '');
    $telefono = trim($_POST['telefono_cliente'] ?? '');
    $centro_id = (int)($_POST['centro_id'] ?? 0);

    if ($nombre === '' || $telefono === '' || $centro_id === 0) {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    // el centro destino tiene que pertenecer al usuario (empleador) o ser uno donde trabaje (empleado)
    if ($_SESSION['rol'] === 'empleador') {
        $stmt = $pdo->prepare('SELECT 1 FROM centros
                               WHERE centro_id = ?
                               AND empleador_id = ?
                               AND activo = 1');
    } else {
        $stmt = $pdo->prepare('
            SELECT 1 FROM centros c
            INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
            WHERE c.centro_id = ? AND uc.usuario_id = ? AND c.activo = 1
        ');
    }
    $stmt->execute([$centro_id, $_SESSION['usuario_id']]);

    if (!$stmt->fetch()) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    $stmt = $pdo->prepare('INSERT INTO clientes (nombre_cliente, telefono_cliente, centro_id)
                           VALUES (?, ?, ?)');
    $stmt->execute([$nombre, $telefono, $centro_id]);

    header('Location: ../dashboard.php?sec=clientes&ok=creado');
    exit;
}


// --- editar cliente ---
// solo nombre y teléfono

if ($accion === 'editar') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $nombre = trim($_POST['nombre_cliente'] ?? '');
    $telefono = trim($_POST['telefono_cliente'] ?? '');

    if ($cliente_id === 0 || $nombre === '' || $telefono === '') {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    if (!clienteEsDelUsuario($pdo, $cliente_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    $stmt = $pdo->prepare('UPDATE clientes SET nombre_cliente = ?, telefono_cliente = ? WHERE cliente_id = ?');
    $stmt->execute([$nombre, $telefono, $cliente_id]);

    header('Location: ../dashboard.php?sec=clientes&ok=cliente_editado');
    exit;
}


// --- borrar cliente ---
// los perros del cliente caen en cascada lógica; los dos UPDATES van en transacción para que se apliquen los dos o ninguno

if ($accion === 'quitar') {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);

    if ($cliente_id === 0) {
        header('Location: ../dashboard.php?sec=clientes&error=campos');
        exit;
    }

    if (!clienteEsDelUsuario($pdo, $cliente_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        header('Location: ../dashboard.php?sec=clientes&error=permiso');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE perros SET activo = 0
                                WHERE cliente_id = ? AND activo = 1');
        $stmt->execute([$cliente_id]);


        $stmt = $pdo->prepare('UPDATE clientes SET activo = 0 WHERE cliente_id = ?');
        $stmt->execute([$cliente_id]);

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header('Location: ../dashboard.php?sec=clientes&error=transaccion');
        exit;
    }

    header('Location: ../dashboard.php?sec=clientes&ok=cliente_borrado');
    exit;
}

header('Location: ../dashboard.php?sec=clientes');
exit;
