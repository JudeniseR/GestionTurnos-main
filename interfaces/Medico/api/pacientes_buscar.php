<?php
header('Content-Type: application/json; charset=utf-8');
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$q = trim($_GET['q'] ?? '');
if($q===''){ echo json_encode([]); exit; }

$like = "%$q%";
$sql = "SELECT p.id_paciente, u.apellido, u.nombre, p.nro_documento AS dni
        FROM pacientes p
        LEFT JOIN usuario u ON u.id_usuario=p.id_usuario
        WHERE u.apellido LIKE ? OR u.nombre LIKE ? OR CONCAT(u.apellido,' ',u.nombre) LIKE ? OR p.nro_documento LIKE ?
        ORDER BY u.apellido, u.nombre LIMIT 20";
$st=$conn->prepare($sql);
$st->bind_param('ssss',$like,$like,$like,$like);
$st->execute();
$res=$st->get_result();
$out=[];
while($r=$res->fetch_assoc()){
  $r['paciente'] = trim($r['apellido'].' '.$r['nombre']);
  unset($r['apellido'],$r['nombre']);
  $out[]=$r;
}
echo json_encode($out);
