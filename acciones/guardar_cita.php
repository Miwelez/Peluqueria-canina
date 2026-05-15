<?php
session_start();
require_once '../conexion.php';

header('Content-Type: application/json');

function respuesta(bool $ok, string $error = '', array $extra = []): void {
    echo json_encode(array_merge(['ok' => $ok, 'error' => $error], $extra));
    exit;
}

if (!isset($_SESSION['usuario_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    respuesta(false, 'No autorizado.');
}

$accion = $_POST['accion'] ?? '';


// --- helpers de validación de propiedad ---

function usuarioGestionaCentro(PDO $pdo, int $centro_id, int $usuario_id, string $rol): bool {
    if ($rol === 'empleador') {
        $stmt = $pdo->prepare('SELECT 1 FROM centros WHERE centro_id = ? AND empleador_id = ? AND activo = 1');
    } else {
        // empleado: tiene que estar vinculado al centro vía la tabla pivote
        $stmt = $pdo->prepare('
            SELECT 1
            FROM centros c
            INNER JOIN usuario_centro uc ON uc.centro_id = c.centro_id
            WHERE c.centro_id = ? AND uc.usuario_id = ? AND c.activo = 1
        ');
    }
    $stmt->execute([$centro_id, $usuario_id]);
    return (bool) $stmt->fetch();
}

function perroPerteneceACentro(PDO $pdo, int $perro_id, int $centro_id): bool {
    $stmt = $pdo->prepare('
        SELECT 1
        FROM perros p
        INNER JOIN clientes cl ON cl.cliente_id = p.cliente_id
        WHERE p.perro_id = ? AND cl.centro_id = ? AND p.activo = 1 AND cl.activo = 1
    ');
    $stmt->execute([$perro_id, $centro_id]);
    return (bool) $stmt->fetch();
}

function empleadoTrabajaEnCentro(PDO $pdo, int $usuario_id, int $centro_id): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM usuario_centro WHERE usuario_id = ? AND centro_id = ?');
    $stmt->execute([$usuario_id, $centro_id]);
    return (bool) $stmt->fetch();
}

// chequea si el empleado tiene otra cita activa que pise el rango [fecha, fecha+duracion).
// excluir_cita_id sirve para que al editar no choque consigo misma.
function existeSolapamiento(PDO $pdo, int $empleado_id, string $fecha_hora, int $duracion_min, int $excluir_cita_id = 0): bool {
    $stmt = $pdo->prepare("
        SELECT 1 FROM citas
        WHERE usuario_id = ?
        AND estado <> 'cancelada'
        AND cita_id <> ?
        AND fecha_hora < (? + INTERVAL ? MINUTE)
        AND DATE_ADD(fecha_hora, INTERVAL duracion_real MINUTE) > ?
        LIMIT 1
    ");
    $stmt->execute([$empleado_id, $excluir_cita_id, $fecha_hora, $duracion_min, $fecha_hora]);
    return (bool) $stmt->fetch();
}

function usuarioGestionaCita(PDO $pdo, int $cita_id, int $usuario_id, string $rol): bool {
    if ($rol === 'empleador') {
        $stmt = $pdo->prepare('
            SELECT 1 FROM citas ci
            INNER JOIN centros c ON c.centro_id = ci.centro_id
            WHERE ci.cita_id = ? AND c.empleador_id = ?
        ');
    } else {
        $stmt = $pdo->prepare('SELECT 1 FROM citas WHERE cita_id = ? AND usuario_id = ?'); // empleado solo toca lo suyo
    }
    $stmt->execute([$cita_id, $usuario_id]);
    return (bool) $stmt->fetch();
}


// --- crear cita ---

if ($accion === 'crear') {

    $centro_id = (int)($_POST['centro_id'] ?? 0);
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $perro_id = (int)($_POST['perro_id'] ?? 0);
    $servicio_id = (int)($_POST['servicio_id'] ?? 0);
    $usuario_id = (int)($_POST['usuario_id'] ?? 0); // empleado al que se asigna la cita
    $fecha_hora = trim($_POST['fecha_hora'] ?? '');
    $duracion_in = trim($_POST['duracion_real'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    if ($centro_id === 0 || $perro_id === 0 || $servicio_id === 0 || $usuario_id === 0 || $fecha_hora === '') {
        respuesta(false, 'Faltan campos por rellenar.');
    }

    if (!usuarioGestionaCentro($pdo, $centro_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        respuesta(false, 'No tienes permiso para crear citas en ese centro.');
    }

    // un empleado no puede colocarle una cita a otro
    if ($_SESSION['rol'] === 'empleado' && $usuario_id !== (int)$_SESSION['usuario_id']) {
        respuesta(false, 'Como empleado solo puedes crear citas para ti.');
    }

    if (!empleadoTrabajaEnCentro($pdo, $usuario_id, $centro_id)) {
        // ojo: el empleador es dueño del centro pero no figura en usuario_centro, ese caso lo dejamos pasar
        $stmt = $pdo->prepare('SELECT empleador_id FROM centros WHERE centro_id = ?');
        $stmt->execute([$centro_id]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['empleador_id'] !== $usuario_id) {
            respuesta(false, 'El empleado asignado no trabaja en ese centro.');
        }
    }

    if (!perroPerteneceACentro($pdo, $perro_id, $centro_id)) {
        respuesta(false, 'El perro no pertenece a un cliente de ese centro.');
    }

    $stmt = $pdo->prepare('SELECT 1 FROM servicios WHERE servicio_id = ? AND activo = 1');
    $stmt->execute([$servicio_id]);
    if (!$stmt->fetch()) {
        respuesta(false, 'Servicio no válido.');
    }

    // el input datetime-local manda "YYYY-MM-DDTHH:MM", lo pasamos a formato DATETIME de MySQL
    $fecha_hora = str_replace('T', ' ', $fecha_hora);
    if (strlen($fecha_hora) === 16) {
        $fecha_hora .= ':00';
    }

    // si el usuario no pasó duración la sacamos de duracion_servicio (cruce raza×servicio).
    // necesitamos resolverla antes del INSERT para poder validar solapes.
    if ($duracion_in === '') {
        $stmt = $pdo->prepare('
            SELECT ds.duracion
            FROM duracion_servicio ds
            INNER JOIN perros p ON p.raza_id = ds.raza_id
            WHERE p.perro_id = ? AND ds.servicio_id = ?
            LIMIT 1
        ');
        $stmt->execute([$perro_id, $servicio_id]);
        $row = $stmt->fetch();
        if (!$row) {
            respuesta(false, 'No se encuentra duración estimada para esa raza y servicio. Indícala manualmente.');
        }
        $duracion = (int)$row['duracion'];
    } else {
        $duracion = (int)$duracion_in;
    }

    if (existeSolapamiento($pdo, $usuario_id, $fecha_hora, $duracion)) {
        respuesta(false, 'Ese empleado ya tiene otra cita que se solapa con esa franja horaria.');
    }

    $stmt = $pdo->prepare('
        INSERT INTO citas (usuario_id, centro_id, perro_id, servicio_id, fecha_hora, duracion_real, notas, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, "pendiente")
    ');
    $stmt->execute([$usuario_id, $centro_id, $perro_id, $servicio_id, $fecha_hora, $duracion, $notas !== '' ? $notas : null]);

    respuesta(true, '', ['cita_id' => (int)$pdo->lastInsertId()]);
}


// --- editar cita ---

if ($accion === 'editar') {

    $cita_id = (int)($_POST['cita_id'] ?? 0);
    $centro_id = (int)($_POST['centro_id'] ?? 0);
    $perro_id = (int)($_POST['perro_id'] ?? 0);
    $servicio_id = (int)($_POST['servicio_id'] ?? 0);
    $usuario_id = (int)($_POST['usuario_id'] ?? 0);
    $fecha_hora = trim($_POST['fecha_hora'] ?? '');
    $duracion_in = trim($_POST['duracion_real'] ?? '');
    $notas = trim($_POST['notas'] ?? '');

    if ($cita_id === 0 || $centro_id === 0 || $perro_id === 0 || $servicio_id === 0 || $usuario_id === 0 || $fecha_hora === '') {
        respuesta(false, 'Faltan campos por rellenar.');
    }

    if (!usuarioGestionaCita($pdo, $cita_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        respuesta(false, 'No tienes permiso para editar esta cita.');
    }
    if (!usuarioGestionaCentro($pdo, $centro_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        respuesta(false, 'No tienes permiso sobre ese centro.');
    }
    if ($_SESSION['rol'] === 'empleado' && $usuario_id !== (int)$_SESSION['usuario_id']) {
        respuesta(false, 'Como empleado solo puedes asignarte citas a ti.');
    }

    if (!empleadoTrabajaEnCentro($pdo, $usuario_id, $centro_id)) {
        $stmt = $pdo->prepare('SELECT empleador_id FROM centros WHERE centro_id = ?');
        $stmt->execute([$centro_id]);
        $row = $stmt->fetch();
        if (!$row || (int)$row['empleador_id'] !== $usuario_id) {
            respuesta(false, 'El empleado asignado no trabaja en ese centro.');
        }
    }

    if (!perroPerteneceACentro($pdo, $perro_id, $centro_id)) {
        respuesta(false, 'El perro no pertenece a un cliente de ese centro.');
    }

    $fecha_hora = str_replace('T', ' ', $fecha_hora);
    if (strlen($fecha_hora) === 16) {
        $fecha_hora .= ':00';
    }

    // el trigger de duración solo se dispara en INSERT; en UPDATE lo resolvemos aquí a mano
    if ($duracion_in === '') {
        $stmt = $pdo->prepare('
            SELECT ds.duracion
            FROM duracion_servicio ds
            INNER JOIN perros p ON p.raza_id = ds.raza_id
            WHERE p.perro_id = ? AND ds.servicio_id = ?
            LIMIT 1
        ');
        $stmt->execute([$perro_id, $servicio_id]);
        $row = $stmt->fetch();
        $duracion = $row ? (int)$row['duracion'] : null;
    } else {
        $duracion = (int)$duracion_in;
    }

    if ($duracion !== null && existeSolapamiento($pdo, $usuario_id, $fecha_hora, $duracion, $cita_id)) {
        respuesta(false, 'Ese empleado ya tiene otra cita que se solapa con esa franja horaria.');
    }

    $stmt = $pdo->prepare('
        UPDATE citas
        SET usuario_id = ?, centro_id = ?, perro_id = ?, servicio_id = ?,
            fecha_hora = ?, duracion_real = ?, notas = ?
        WHERE cita_id = ?
    ');
    $stmt->execute([$usuario_id, $centro_id, $perro_id, $servicio_id, $fecha_hora, $duracion, $notas !== '' ? $notas : null, $cita_id]);

    respuesta(true);
}


// --- mover/redimensionar (drag & drop desde el calendario) ---
// solo cambia fecha y duración, lo demás se conserva

if ($accion === 'mover') {
    $cita_id = (int)($_POST['cita_id'] ?? 0);
    $fecha_hora = trim($_POST['fecha_hora'] ?? '');
    $duracion = (int)($_POST['duracion_real'] ?? 0);

    if ($cita_id === 0 || $fecha_hora === '' || $duracion <= 0) {
        respuesta(false, 'Datos incompletos.');
    }
    if (!usuarioGestionaCita($pdo, $cita_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        respuesta(false, 'No tienes permiso sobre esa cita.');
    }

    // necesitamos saber a qué empleado está asignada la cita para validar solapamientos
    $stmt = $pdo->prepare('SELECT usuario_id FROM citas WHERE cita_id = ?');
    $stmt->execute([$cita_id]);
    $row = $stmt->fetch();
    if (!$row) {
        respuesta(false, 'Cita no encontrada.');
    }

    if (existeSolapamiento($pdo, (int)$row['usuario_id'], $fecha_hora, $duracion, $cita_id)) {
        respuesta(false, 'Esa franja se solapa con otra cita del mismo empleado.');
    }

    $stmt = $pdo->prepare('UPDATE citas SET fecha_hora = ?, duracion_real = ? WHERE cita_id = ?');
    $stmt->execute([$fecha_hora, $duracion, $cita_id]);

    respuesta(true);
}


// --- cancelar cita ---
// no se borra, se cambia el estado a "cancelada" para mantener histórico

if ($accion === 'cancelar') {
    $cita_id = (int)($_POST['cita_id'] ?? 0);
    if ($cita_id === 0) {
        respuesta(false, 'Falta el identificador de la cita.');
    }
    if (!usuarioGestionaCita($pdo, $cita_id, $_SESSION['usuario_id'], $_SESSION['rol'])) {
        respuesta(false, 'No tienes permiso para cancelar esta cita.');
    }

    $stmt = $pdo->prepare("UPDATE citas SET estado = 'cancelada' WHERE cita_id = ?");
    $stmt->execute([$cita_id]);

    respuesta(true);
}

respuesta(false, 'Acción no reconocida.');
