<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

function norm_h(?string $s): ?string {
  if ($s === null) return null;
  $s = trim(str_replace('.', ':', $s));
  if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $s)) return null;
  [$h,$m,$sec] = array_pad(explode(':', $s), 3, '00');
  $h = str_pad((string)intval($h), 2, '0', STR_PAD_LEFT);
  return "$h:$m:".(strlen($sec)?$sec:'00');
}

function params(): array {
  $d = $_GET + $_POST;
  $raw = file_get_contents('php://input');
  if ($raw) { $j = json_decode($raw, true); if (is_array($j)) $d = $j + $d; }
  return $d;
}

try {
  $p = params();

  // todos los alias de id
  $idRaw = $p['id_agenda'] ?? $p['id'] ?? $p['id_franja'] ?? $p['franja_id'] ?? null;
  $id = (is_numeric($idRaw) ? (int)$idRaw : 0);

  $fecha = $p['fecha'] ?? $p['dia'] ?? null;
  $hi    = norm_h($p['hora_inicio'] ?? $p['desde'] ?? null);
  $hf    = norm_h($p['hora_fin']    ?? $p['hasta'] ?? null);

  // 1) borrar por id (preferido)
  if ($id > 0) {
    $st = $conn->prepare("DELETE FROM agenda WHERE id_agenda=? AND id_medico=?");
    $st->bind_param('ii', $id, $id_medico);
    $st->execute();
    echo json_encode(['ok'=>true,'deleted'=>$st->affected_rows,'by'=>'id']);
    exit;
  }

  // 2) borrar por fecha + horas (fallback)
  if ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && $hi && $hf) {
    $st = $conn->prepare("DELETE FROM agenda WHERE id_medico=? AND fecha=? AND hora_inicio=? AND hora_fin=?");
    $st->bind_param('isss', $id_medico, $fecha, $hi, $hf);
    $st->execute();
    echo json_encode(['ok'=>true,'deleted'=>$st->affected_rows,'by'=>'fecha+horas']);
    exit;
  }

  // nada útil -> no-op para que el front no explote
  echo json_encode(['ok'=>true,'deleted'=>0,'by'=>'noop']);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
