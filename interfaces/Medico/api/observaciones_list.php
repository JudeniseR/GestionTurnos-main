<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');
session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode(['ok'=>false]); exit; }

require_once('../../../Persistencia/conexionBD.php');
$conn=ConexionBD::conectar(); $conn->set_charset('utf8mb4');

$id_turno = (int)($_GET['id_turno'] ?? 0);
if($id_turno<=0){ http_response_code(400); echo json_encode(['ok'=>false,'msg'=>'id_turno requerido']); exit; }

$items = [];
try{
  // 1) Intentar tabla observaciones (id_turno, tipo, nota, ts_creado)
  $sqlObs = "
    SELECT 
      DATE_FORMAT(o.ts_creado, '%d/%m/%Y %H:%i') AS fecha,
      o.tipo,
      o.nota
    FROM observaciones o
    WHERE o.id_turno = ?
    ORDER BY o.ts_creado DESC, o.id_observacion DESC
  ";
  if($st=$conn->prepare($sqlObs)){
    $st->bind_param('i',$id_turno);
    if($st->execute()){
      $rs=$st->get_result();
      while($r=$rs->fetch_assoc()){
        $tipo = strtolower($r['tipo'] ?? '');
        $badge = null;
        if($tipo==='estado:confirmado') $badge = ['txt'=>'confirmado','cls'=>'b-conf'];
        elseif($tipo==='estado:atendido') $badge = ['txt'=>'atendido','cls'=>'b-aten'];
        elseif($tipo==='estado:cancelado') $badge = ['txt'=>'cancelado','cls'=>'b-canc'];
        elseif($tipo==='reprogramado')     $badge = ['txt'=>'reprogramado','cls'=>'b-conf'];
        elseif($tipo==='derivado')         $badge = ['txt'=>'derivado','cls'=>'b-conf'];

        $items[] = [
          'fecha'  => $r['fecha'],
          'titulo' => ucfirst($tipo ?: 'nota'),
          'detalle'=> $r['nota'] ?: '',
          'badge'  => $badge
        ];
      }
    }
    $st->close();
  }
}catch(Throwable $e){
  // Si falla por tabla inexistente: seguimos al fallback
}

if(!count($items)){
  // 2) Fallback a observaciones del turno
  $sql="SELECT DATE_FORMAT(t.fecha, '%d/%m/%Y') AS d, t.observaciones FROM turnos t WHERE t.id_turno=?";
  $st=$conn->prepare($sql); $st->bind_param('i',$id_turno); $st->execute();
  if($r=$st->get_result()->fetch_assoc()){
    if(trim((string)$r['observaciones'])!==''){
      $items[] = [
        'fecha'  => $r['d'].' (turno)',
        'titulo' => 'Observación del turno',
        'detalle'=> $r['observaciones'],
        'badge'  => null
      ];
    }
  }
  $st->close();
}

echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE);
