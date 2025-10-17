<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

$id_medico = $_SESSION['id_medico'] ?? null;
$anio = (int)($_GET['anio'] ?? date('Y'));
$mes  = (int)($_GET['mes']  ?? date('n'));

try {
  if(!$id_medico || $anio < 2000 || $mes < 1 || $mes > 12){
    echo json_encode([]); exit;
  }
  require_once('../../../Persistencia/conexionBD.php');
  $conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

  $desde = sprintf('%04d-%02d-01', $anio, $mes);
  $hasta = date('Y-m-t', strtotime($desde));

  $byDay = [];

  // días con franjas (no filtramos por 'disponible')
  $st = $conn->prepare("SELECT DATE(fecha) f, COUNT(*) c FROM agenda WHERE id_medico=? AND DATE(fecha) BETWEEN ? AND ? GROUP BY DATE(fecha)");
  $st->bind_param('iss', $id_medico, $desde, $hasta); $st->execute();
  $r = $st->get_result();
  while($x=$r->fetch_assoc()){ $byDay[$x['f']] = ['slots'=>(int)$x['c'], 'ocupados'=>0, 'bloqDia'=>0]; }
  $st->close();

  // turnos (no cancelados)
  if ($conn->query("SHOW TABLES LIKE 'estado'")->num_rows) {
    $sql = "SELECT DATE(t.fecha) f, COUNT(*) usados
              FROM turnos t
              JOIN estado e ON e.id_estado=t.id_estado
             WHERE t.id_medico=? AND DATE(t.fecha) BETWEEN ? AND ? AND e.nombre_estado<>'cancelado'
          GROUP BY DATE(t.fecha)";
  } else {
    $sql = "SELECT DATE(t.fecha) f, COUNT(*) usados
              FROM turnos t
             WHERE t.id_medico=? AND DATE(t.fecha) BETWEEN ? AND ? AND (t.id_estado IS NULL OR t.id_estado<>4)
          GROUP BY DATE(t.fecha)";
  }
  $st = $conn->prepare($sql);
  $st->bind_param('iss', $id_medico, $desde, $hasta); $st->execute();
  $r = $st->get_result();
  while($x=$r->fetch_assoc()){
    $f=$x['f']; if(!isset($byDay[$f])) $byDay[$f]=['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
    $byDay[$f]['ocupados'] = (int)$x['usados'];
  }
  $st->close();

  // bloqueos de día
  if ($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $st = $conn->prepare("SELECT fecha f, COUNT(*) c FROM agenda_bloqueos WHERE id_medico=? AND fecha BETWEEN ? AND ? AND tipo='dia' GROUP BY fecha");
    $st->bind_param('iss', $id_medico, $desde, $hasta); $st->execute();
    $r = $st->get_result();
    while($x=$r->fetch_assoc()){
      $f=$x['f']; if(!isset($byDay[$f])) $byDay[$f]=['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
      $byDay[$f]['bloqDia'] += (int)$x['c'];
    }
    $st->close();
  }

  // feriados
  if ($conn->query("SHOW TABLES LIKE 'feriados'")->num_rows) {
    $st = $conn->prepare("SELECT fecha f FROM feriados WHERE fecha BETWEEN ? AND ?");
    $st->bind_param('ss', $desde, $hasta); $st->execute();
    $r = $st->get_result();
    while($x=$r->fetch_assoc()){
      $f=$x['f']; if(!isset($byDay[$f])) $byDay[$f]=['slots'=>0,'ocupados'=>0,'bloqDia'=>0];
      $byDay[$f]['bloqDia'] += 1;
    }
    $st->close();
  }

  // construir calendario
  $ultimo = (int)date('t', strtotime($desde));
  $out = [];
  for($d=1;$d<=$ultimo;$d++){
    $f = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
    $info = $byDay[$f] ?? ['slots'=>0,'ocupados'=>0,'bloqDia'=>0];

    $estado = 'verde';
    if ($info['bloqDia']>0)                $estado = 'rojo';
    elseif ($info['slots']>0 && $info['ocupados'] >= $info['slots']) $estado = 'rojo';
    // si no hay franjas ni bloqueos, lo consideramos 'verde' (se puede cargar)

    $out[] = ['dia'=>$d, 'estado'=>$estado,
              'libres'=>max(0, ($info['slots']??0) - ($info['ocupados']??0)),
              'ocupados'=>$info['ocupados']??0];
  }
  echo json_encode($out);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>$e->getMessage()]);
}
