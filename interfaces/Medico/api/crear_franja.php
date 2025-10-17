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
  // Chequeo de superposición:
  // Nueva franja [desde, hasta) se superpone si existe E tal que !(hasta <= E.inicio OR desde >= E.fin)
  $q = $conn->prepare("
    SELECT 1
    FROM agenda
    WHERE id_medico = ? AND fecha = ?
      AND NOT (? <= hora_inicio OR ? >= hora_fin)
    LIMIT 1
  ");
  $q->bind_param('isss', $id_medico, $fecha, $hasta, $desde);
  $q->execute();
  if ($q->get_result()->fetch_row()) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'msg'=>'Se superpone con otra franja']);
    exit;
  }

  // Insertar franja (con o sin columna 'disponible')
  $hasDisponible = ($conn->query("SHOW COLUMNS FROM agenda LIKE 'disponible'")?->num_rows ?? 0) > 0;

  if ($hasDisponible) {
    $st = $conn->prepare("
      INSERT INTO agenda (id_medico, fecha, hora_inicio, hora_fin, disponible)
      VALUES (?, ?, ?, ?, 1)
    ");
  } else {
    $st = $conn->prepare("
      INSERT INTO agenda (id_medico, fecha, hora_inicio, hora_fin)
      VALUES (?, ?, ?, ?)
    ");
  }
  $st->bind_param('isss', $id_medico, $fecha, $desde, $hasta);
  $st->execute();

  $newId = $st->insert_id;

  echo json_encode([
    'ok'        => true,
    'id'        => $newId,   // alias “genérico”
    'id_agenda' => $newId,   // alias que a veces usa el front
    'franja_id' => $newId    // otro alias común
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
