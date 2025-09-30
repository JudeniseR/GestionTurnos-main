<?php
error_reporting(E_ALL); ini_set('display_errors',1);
header('Content-Type: application/json; charset=UTF-8');
session_start();
require_once '../../Persistencia/conexionBD.php';

if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id']??0)!==4) {
  http_response_code(401); echo json_encode(['error'=>'NO_SESSION']); exit;
}
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_medico = (int)($_POST['id_medico']??0);
if($id_medico<=0){ echo json_encode([]); exit; }

$stmt=$conn->prepare("SELECT fecha, hora_inicio, hora_fin
                      FROM agenda
                      WHERE id_medico=? AND disponible=1 AND fecha>=CURDATE()
                      ORDER BY fecha, hora_inicio");
$stmt->bind_param('i',$id_medico);
$stmt->execute();
$res=$stmt->get_result();

$out=[];
while($r=$res->fetch_assoc()){
  $f=$r['fecha'];
  if(!isset($out[$f])) $out[$f]=[];
  $out[$f][]= ['inicio'=>$r['hora_inicio'],'fin'=>$r['hora_fin']];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
