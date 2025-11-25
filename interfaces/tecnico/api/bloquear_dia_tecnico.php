<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

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
  echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

$fecha  = $_POST['fecha']  ?? null;
$motivo = trim((string)($_POST['motivo'] ?? ''));

if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'msg'=>'Fecha inválida']); exit;
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

try {
  // ¿Ya existe bloqueo de día?
  $st = $conn->prepare("SELECT id_bloqueo FROM agenda_bloqueos 
                         WHERE id_tecnico=? AND fecha=? AND tipo='dia' LIMIT 1");
  $st->bind_param('is', $id_tecnico, $fecha);
  $st->execute(); $res=$st->get_result(); $row=$res->fetch_assoc();
  $st->close();

  if ($row) {
    if ($motivo !== '') {
      $up = $conn->prepare("UPDATE agenda_bloqueos SET motivo=? 
                             WHERE id_bloqueo=?");
      $up->bind_param('si', $motivo, $row['id_bloqueo']);
      $up->execute(); $up->close();
    }
    echo json_encode(['ok'=>true,'id_bloqueo'=>(int)$row['id_bloqueo'],'updated'=>($motivo!=='' ? 1:0)]);
    exit;
  }

  // Insertar bloqueo de día (hora NULL)
  $ins = $conn->prepare("INSERT INTO agenda_bloqueos (id_tecnico, fecha, hora, tipo, motivo)
                         VALUES (?, ?, NULL, 'dia', ?)");
  $ins->bind_param('iss', $id_tecnico, $fecha, $motivo);
  $ins->execute();
  $id = $ins->insert_id;
  $ins->close();

  echo json_encode(['ok'=>true,'id_bloqueo'=>$id]);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}