<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../../../Persistencia/conexionBD.php');
$conn=ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$rs=$conn->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad");
$out=[]; while($r=$rs->fetch_assoc()){
  $out[]=['id_especialidad'=>(int)$r['id_especialidad'],'nombre_especialidad'=>$r['nombre_especialidad']];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
