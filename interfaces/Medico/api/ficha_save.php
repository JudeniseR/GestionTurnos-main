<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
$id_medico = (int)$_SESSION['id_medico'];

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_turno = (int)($_POST['id_turno'] ?? 0);
$notas    = trim((string)($_POST['notas'] ?? ''));

if($id_turno<=0){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'Datos inválidos']); exit; }

try{
  // coloca estado 'atendido' (si existe) y guarda notas
  $sql = "UPDATE turnos 
             SET observaciones=?, 
                 id_estado = COALESCE((SELECT id_estado FROM estados WHERE nombre_estado='atendido' LIMIT 1), id_estado)
           WHERE id_turno=? AND id_medico=?";
  $q = $conn->prepare($sql);
  $q->bind_param('sii',$notas,$id_turno,$id_medico);
  $q->execute();
  if($q->affected_rows===0){ http_response_code(404); echo json_encode(['ok'=>false,'msg'=>'Turno no encontrado']); exit; }
  echo json_encode(['ok'=>true]);
}catch(Throwable $e){ http_response_code(500); echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
