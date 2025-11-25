<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','1');

session_start();

$id_tecnico = $_SESSION['id_tecnico'] ?? null;
if (!$id_tecnico && isset($_SESSION['id_usuario'])) {
    require_once('../../../Persistencia/conexionBD.php');
    $conn = ConexionBD::conectar();
    $stmt = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['id_usuario']);
    $stmt->execute();
    $stmt->bind_result($id_tecnico);
    $stmt->fetch();
    $stmt->close();
    if ($id_tecnico) $_SESSION['id_tecnico'] = $id_tecnico;
}

if (!$id_tecnico) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
    exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

function norm_hora(?string $s): ?string {
    if ($s === null) return null;
    $s = trim(str_replace('.', ':', $s));
    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) return null;
    [$h,$m,$sec] = array_pad(explode(':', $s), 3, '00');
    $h = str_pad((string)intval($h), 2, '0', STR_PAD_LEFT);
    return sprintf('%s:%s:%s', $h, $m, strlen($sec) ? $sec : '00');
}

// Parámetros
$fecha = $_POST['fecha'] ?? $_POST['dia'] ?? null;
$desde = $_POST['desde'] ?? $_POST['hora_inicio'] ?? null;
$hasta = $_POST['hasta'] ?? $_POST['hora_fin'] ?? null;

$fecha = is_string($fecha) ? trim($fecha) : '';
$desde = norm_hora($desde);
$hasta = norm_hora($hasta);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !$desde || !$hasta) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']);
    exit;
}

if (strcmp($desde, $hasta) >= 0) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'Rango horario inválido']);
    exit;
}

try {
    // 🔥 PASO 1: Obtener id_recurso del técnico (si existe)
    $stmtTecnico = $conn->prepare("
        SELECT id_recurso 
        FROM tecnicos 
        WHERE id_tecnico = ?
        LIMIT 1
    ");
    $stmtTecnico->bind_param('i', $id_tecnico);
    $stmtTecnico->execute();
    $stmtTecnico->bind_result($id_recurso);
    $hasFetch = $stmtTecnico->fetch();
    $stmtTecnico->close();

    // DEBUG: Log para verificar
    error_log("DEBUG crear_franja - id_tecnico: {$id_tecnico}, id_recurso obtenido: " . var_export($id_recurso, true) . ", fetch result: " . var_export($hasFetch, true));

    // Si id_recurso es NULL, usar id_tecnico como fallback
    if (!$id_recurso) {
        $id_recurso = $id_tecnico;
        error_log("DEBUG crear_franja - Usando id_tecnico como fallback: {$id_recurso}");
    }

    // Obtener todos los estudios que realiza el técnico
    $stmtEstudios = $conn->prepare("
        SELECT id_estudio 
        FROM tecnico_estudio 
        WHERE id_tecnico = ?
    ");
    $stmtEstudios->bind_param('i', $id_tecnico);
    $stmtEstudios->execute();
    $resultEstudios = $stmtEstudios->get_result();
    
    $estudios = [];
    while ($row = $resultEstudios->fetch_assoc()) {
        $estudios[] = (int)$row['id_estudio'];
    }
    $stmtEstudios->close();

    // DEBUG: Log de estudios obtenidos
    error_log("DEBUG crear_franja - Estudios obtenidos: " . json_encode($estudios));

    // Validar que el técnico tenga estudios asignados
    if (empty($estudios)) {
        http_response_code(400);
        echo json_encode(['ok'=>false,'msg'=>'El técnico no tiene estudios asignados. Contacte al administrador.']);
        exit;
    }

    // 🔥 PASO 2: Chequeo de superposición (solo una vez, no por estudio)
    $q = $conn->prepare("
        SELECT 1
        FROM agenda
        WHERE id_tecnico = ? AND fecha = ?
          AND NOT (? <= hora_inicio OR ? >= hora_fin)
        LIMIT 1
    ");
    $q->bind_param('isss', $id_tecnico, $fecha, $hasta, $desde);
    $q->execute();
    if ($q->get_result()->fetch_row()) {
        http_response_code(409);
        echo json_encode(['ok'=>false,'msg'=>'Se superpone con otra franja existente']);
        exit;
    }
    $q->close();

    // 🔥 PASO 3: Verificar si la tabla tiene el campo 'disponible'
    $hasDisponible = ($conn->query("SHOW COLUMNS FROM agenda LIKE 'disponible'")?->num_rows ?? 0) > 0;

// 🔥 PASO 4: Generar slots de 30 minutos
$slots = [];
$current = strtotime($desde);
$end = strtotime($hasta);

while ($current < $end) {
    $slot_inicio = date('H:i:s', $current);
    $slot_fin = date('H:i:s', $current + 1800); // +30 minutos
    $slots[] = ['inicio' => $slot_inicio, 'fin' => $slot_fin];
    $current += 1800;
}

if (empty($slots)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'No se generaron slots válidos en el rango horario']);
    exit;
}

// 🔥 PASO 5: Insertar slots para cada estudio
$insertedIds = [];

if ($hasDisponible) {
    $st = $conn->prepare("
        INSERT INTO agenda (id_tecnico, id_recurso, fecha, hora_inicio, hora_fin, disponible, id_estudio)
        VALUES (?, ?, ?, ?, ?, 1, ?)
    ");
} else {
    $st = $conn->prepare("
        INSERT INTO agenda (id_tecnico, id_recurso, fecha, hora_inicio, hora_fin, id_estudio)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
}

// Insertar cada slot para cada estudio
foreach ($estudios as $id_estudio) {
    foreach ($slots as $slot) {
        $st->bind_param('iisssi', $id_tecnico, $id_recurso, $fecha, $slot['inicio'], $slot['fin'], $id_estudio);
        $st->execute();
        $insertedIds[] = $st->insert_id;
    }
}
$st->close();

// 🔥 PASO 6: Respuesta exitosa
echo json_encode([
  'ok'           => true,
  'id'           => $insertedIds[0],
  'id_agenda'    => $insertedIds[0],
  'franja_id'    => $insertedIds[0],
  'slots_creados' => count($insertedIds),
  'ids'          => $insertedIds
]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}