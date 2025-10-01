<?php
// interfaces/Medico/api/turnos_crear.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }

require_once('../../Persistencia/conexionBD.php');

try{
  $conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

  $id_medico   = (int)$_SESSION['id_medico'];
  $id_paciente = (int)($_POST['id_paciente'] ?? 0);
  $fecha       = $_POST['fecha'] ?? '';
  $hora        = $_POST['hora']  ?? '';
  $obs         = trim($_POST['observaciones'] ?? '');

  if(!$id_paciente || !preg_match('/^\d{4}-\d{2}-\d{2}$/',$fecha) || !preg_match('/^\d{2}:\d{2}$/',$hora)){
    http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit;
  }

  // evitar choque con otro turno no cancelado
  $sql = "SELECT 1
          FROM turnos t JOIN estado e ON e.id_estado=t.id_estado
          WHERE t.id_medico=? AND t.fecha=? AND t.hora LIKE CONCAT(?,':%')
            AND e.nombre_estado <> 'cancelado' LIMIT 1";
  $st = $conn->prepare($sql);
  $st->bind_param('iss', $id_medico, $fecha, $hora);
  $st->execute(); $st->store_result();
  if($st->num_rows>0){ http_response_code(409); echo json_encode(['ok'=>false,'msg'=>'Conflicto de horario']); exit; }
  $st->close();

  // id_estado pendiente
  $id_estado = null;
  $st = $conn->prepare("SELECT id_estado FROM estado WHERE nombre_estado='pendiente' LIMIT 1");
  $st->execute(); $st->bind_result($id_estado); $st->fetch(); $st->close();
  if(!$id_estado){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'Falta estado pendiente']); exit; }

  $st = $conn->prepare("INSERT INTO turnos (id_paciente,id_medico,fecha,hora,id_estado,observaciones) VALUES (?,?,?,?,?,?)");
  $st->bind_param('iissis', $id_paciente, $id_medico, $fecha, $hora, $id_estado, $obs);
  if(!$st->execute()){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$st->error]); exit; }
  echo json_encode(['ok'=>true,'id_turno'=>$conn->insert_id]);
}catch(Throwable $e){
  http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
