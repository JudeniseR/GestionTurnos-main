<?php
// interfaces/Medico/api/turnos_reprogramar.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

require_once('../../Persistencia/conexionBD.php');

try{
  $conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

  $id_medico = (int)$_SESSION['id_medico'];
  $id_turno  = (int)($_POST['id_turno'] ?? 0);
  $fecha     = $_POST['fecha'] ?? '';
  $hora      = $_POST['hora']  ?? '';

  if(!$id_turno || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha) || !preg_match('/^\d{2}:\d{2}$/',$hora)){
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
  }

  // Conflicto con otros turnos (no cancelados)
  $sql = "SELECT 1
          FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
          WHERE t.id_medico=? AND t.fecha=? AND t.hora LIKE CONCAT(?,':%')
            AND e.nombre_estado <> 'cancelado' AND t.id_turno <> ? LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('issi', $id_medico, $fecha, $hora, $id_turno);
  $st->execute(); $st->store_result();
  if($st->num_rows>0){ http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Conflicto de horario']); exit; }
  $st->close();

  $st = $conn->prepare("UPDATE turnos SET fecha=?, hora=? WHERE id_turno=? AND id_medico=?");
  $st->bind_param('ssii', $fecha, $hora, $id_turno, $id_medico);
  if(!$st->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$st->error]); exit; }
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
