<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']);
  exit;
}
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

/**
 * Normaliza hora a HH:MM:SS aceptando "H:MM", "HH:MM", "HH:MM:SS" y puntos.
 */
function norm_hora(?string $s): ?string {
  if ($s === null) return null;
  $s = trim(str_replace('.', ':', $s));
  if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) return null;
  [$h,$m,$sec] = array_pad(explode(':', $s), 3, '00');
  $h = str_pad((string)intval($h), 2, '0', STR_PAD_LEFT);
  return sprintf('%s:%s:%s', $h, $m, strlen($sec)?$sec:'00');
}

// Acepta ambos nombres usados por el front.
$fecha = $_POST['fecha'] ?? $_POST['dia'] ?? null;
$desde = $_POST['desde'] ?? $_POST['hora_inicio'] ?? null;
$hasta = $_POST['hasta'] ?? $_POST['hora_fin']    ?? null;

$fecha = is_string($fecha) ? trim($fecha) : '';
$desde = norm_hora($desde);
$hasta = norm_hora($hasta);

// Validaciones básicas
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
  // Obtener id_recurso basado en id_medico usando la tabla intermedia
  $qRecurso = $conn->prepare("
    SELECT mr.id_recurso
    FROM medico_recursos mr
    JOIN recursos r ON mr.id_recurso = r.id_recurso
    WHERE mr.id_medico = ? AND r.tipo = 'medico'
    LIMIT 1
  ");
  $qRecurso->bind_param('i', $id_medico);
  $qRecurso->execute();
  $result = $qRecurso->get_result();
  $id_recurso = $result->fetch_assoc()['id_recurso'] ?? null;

  if (!$id_recurso) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'No se encontró un recurso asociado al médico']);
    exit;
  }

  // Chequeo de superposición (ahora incluye id_recurso)
  $q = $conn->prepare("
    SELECT 1
    FROM agenda
    WHERE id_medico = ? AND fecha = ? AND id_recurso = ?
      AND NOT (? <= hora_inicio OR ? >= hora_fin)
    LIMIT 1
  ");
  $q->bind_param('issss', $id_medico, $fecha, $id_recurso, $hasta, $desde);
  $q->execute();
  if ($q->get_result()->fetch_row()) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'msg'=>'Se superpone con otra franja']);
    exit;
  }

  // Generar slots de 30 minutos
  $slots = [];
  $current = strtotime($desde);
  $end = strtotime($hasta);

  while ($current < $end) {
    $slot_inicio = date('H:i:s', $current);
    $slot_fin = date('H:i:s', $current + 1800); // +30 minutos (1800 segundos)
    
    $slots[] = [
      'inicio' => $slot_inicio,
      'fin' => $slot_fin
    ];
    
    $current += 1800;
  }

  if (empty($slots)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'msg'=>'No se generaron slots válidos en el rango horario']);
    exit;
  }

  // Preparar statement para inserción múltiple
  $st = $conn->prepare("
    INSERT INTO agenda (id_medico, id_recurso, fecha, hora_inicio, hora_fin, disponible)
    VALUES (?, ?, ?, ?, ?, 1)
  ");

  $insertedIds = [];
  
  // Insertar cada slot
  foreach ($slots as $slot) {
    $st->bind_param('iisss', $id_medico, $id_recurso, $fecha, $slot['inicio'], $slot['fin']);
    $st->execute();
    $insertedIds[] = $st->insert_id;
  }
  
  $st->close();

  echo json_encode([
    'ok'           => true,
    'id'           => $insertedIds[0],      // Primer ID insertado (para compatibilidad)
    'id_agenda'    => $insertedIds[0],
    'franja_id'    => $insertedIds[0],
    'slots_creados' => count($insertedIds),
    'ids'          => $insertedIds          // Array con todos los IDs creados
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}