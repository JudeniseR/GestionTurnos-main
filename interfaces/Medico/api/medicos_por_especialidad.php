<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../../../Persistencia/conexionBD.php');
$conn=ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_esp = (int)($_GET['id_especialidad'] ?? 0);
$out=[];
if($id_esp>0){
  $sql="SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS nombre_completo
        FROM medico_especialidad me
        JOIN medicos m ON m.id_medico = me.id_medico
        JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE me.id_especialidad = ?
        ORDER BY u.apellido, u.nombre";
  $st=$conn->prepare($sql); $st->bind_param('i',$id_esp); $st->execute();
  $rs=$st->get_result();
  while($r=$rs->fetch_assoc()){
    $out[]=['id_medico'=>(int)$r['id_medico'],'nombre_completo'=>$r['nombre_completo']];
  }
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
