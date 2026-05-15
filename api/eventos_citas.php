<?php
session_start();
require_once '../conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    echo json_encode([]);
    exit;
}

// FullCalendar manda el rango como ISO 8601 con zona horaria (ej: 2026-05-11T00:00:00+02:00).
// Lo pasamos a DATETIME normal de MySQL para evitar problemas en la comparación.
$startRaw = $_GET['start'] ?? null;
$endRaw = $_GET['end'] ?? null;
$empleado_id_filtro = isset($_GET['empleado_id']) ? (int)$_GET['empleado_id'] : 0;

if (!$startRaw || !$endRaw) {
    echo json_encode([]);
    exit;
}

try {
    $start = (new DateTime($startRaw))->format('Y-m-d H:i:s');
    $end = (new DateTime($endRaw))->format('Y-m-d H:i:s');
} catch (Exception $e) {
    echo json_encode([]);
    exit;
}

// paleta cíclica para distinguir centros en el calendario del empleado
$paletaColores = [
    '#56916C', // verde corporativo
    '#4A6FA5', // azul
    '#C57F49', // naranja
    '#8A4D9C', // morado
    '#C04B57', // rojo
    '#3D8B91', // turquesa
    '#A78739', // mostaza
    '#7D6B9E', // lila
];

// mapeo legible del ENUM de servicio (en BD están sin tildes)
$nombresServicio = [
    'banio' => 'Baño',
    'rapar' => 'Rapar',
    'corte' => 'Corte',
    'stripping' => 'Stripping',
    'deslanado' => 'Deslanado',
];


// --- consulta según rol ---

if ($_SESSION['rol'] === 'empleador') {
    // empleador: todas las citas de sus centros, opcionalmente filtrando por un empleado
    $sql = '
        SELECT vcc.*
        FROM vista_citas_calendario vcc
        INNER JOIN centros c ON c.centro_id = vcc.centro_id
        WHERE c.empleador_id = ?
        AND vcc.fecha_hora >= ?
        AND vcc.fecha_hora < ?
        AND vcc.estado <> "cancelada"
    ';
    $params = [$_SESSION['usuario_id'], $start, $end];

    if ($empleado_id_filtro > 0) {
        // antes de aceptar el filtro verificamos que ese empleado trabaja en algún centro suyo
        $stmt = $pdo->prepare('
            SELECT 1
            FROM usuario_centro uc
            INNER JOIN centros c ON c.centro_id = uc.centro_id
            WHERE uc.usuario_id = ? AND c.empleador_id = ?
            LIMIT 1
        ');
        $stmt->execute([$empleado_id_filtro, $_SESSION['usuario_id']]);
        if ($stmt->fetch()) {
            $sql .= ' AND vcc.usuario_id = ?';
            $params[] = $empleado_id_filtro;
        }
    }

    $sql .= ' ORDER BY vcc.fecha_hora';
} else {
    // empleado: sus citas en todos los centros donde trabaja (calendario unificado)
    $sql = '
        SELECT vcc.*
        FROM vista_citas_calendario vcc
        WHERE vcc.usuario_id = ?
        AND vcc.fecha_hora >= ?
        AND vcc.fecha_hora < ?
        AND vcc.estado <> "cancelada"
        ORDER BY vcc.fecha_hora
    ';
    $params = [$_SESSION['usuario_id'], $start, $end];
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$citas = $stmt->fetchAll();


// --- construcción del JSON que entiende FullCalendar ---

$eventos = [];
foreach ($citas as $c) {
    $colorCentro = $paletaColores[((int)$c['centro_id'] - 1) % count($paletaColores)];

    // FullCalendar quiere start y end como ISO 8601
    $inicio = new DateTime($c['fecha_hora']);
    $fin = clone $inicio;
    $fin->modify('+' . (int)$c['duracion_real'] . ' minutes');

    $eventos[] = [
        'id' => $c['cita_id'],
        'title' => $c['nombre_perro'] . ' — ' . $c['nombre_servicio'],
        'start' => $inicio->format('Y-m-d\TH:i:s'),
        'end' => $fin->format('Y-m-d\TH:i:s'),
        'backgroundColor' => $colorCentro,
        'borderColor' => $colorCentro,
        // datos extra que viajan con el evento y los aprovecha el modal
        'extendedProps' => [
            'centro_id' => (int)$c['centro_id'],
            'nombre_centro' => $c['nombre_centro'],
            'usuario_id' => (int)$c['usuario_id'],
            'nombre_usuario' => $c['nombre_usuario'],
            'cliente_id' => (int)$c['cliente_id'],
            'nombre_cliente' => $c['nombre_cliente'],
            'telefono_cliente' => $c['telefono_cliente'],
            'perro_id' => (int)$c['perro_id'],
            'nombre_perro' => $c['nombre_perro'],
            'nombre_raza' => $c['nombre_raza'],
            'servicio_id' => (int)$c['servicio_id'],
            'nombre_servicio' => $c['nombre_servicio'],
            'nombre_servicio_label' => $nombresServicio[$c['nombre_servicio']] ?? ucfirst($c['nombre_servicio']),
            'duracion_real' => (int)$c['duracion_real'],
            'estado' => $c['estado'],
            'notas' => $c['notas'],
        ],
    ];
}

echo json_encode($eventos);
