<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

$id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : ($_SESSION['id_medico'] ?? null);
$anio = (int)($_GET['anio'] ?? date('Y'));
$mes  = (int)($_GET['mes']  ?? date('n'));

try {
  if(!$id_medico || $anio < 2000 || $mes < 1 || $mes > 12){ echo json_encode([]); exit; }
  require_once('../../../Persistencia/conexionBD.php');
  $conn = ConexionBD::conectar(); $conn->set_charset('utf8mb4');

  $desde = sprintf('%04d-%02d-01', $anio, $mes);
  $hasta = date('Y-m-t', strtotime($desde));
  $hoy   = date('Y-m-d');

  $by = [];

  // Conteo franjas por día
  $st = $conn->prepare("SELECT DATE(fecha) f, COUNT(*) c FROM agenda WHERE id_medico=? AND DATE(fecha) BETWEEN ? AND ? GROUP BY DATE(fecha)");
  $st->bind_param('iss',$id_medico,$desde,$hasta);
  $st->execute();
  $r = $st->get_result();
  while($x=$r->fetch_assoc()) {
    $by[$x['f']] = ['franjas'=>(int)$x['c'],'ocup'=>0,'bloq'=>0,'fer'=>0];
  }
  $st->close();

  // Conteo turnos ocupados por día
  $sql = $conn->query("SHOW TABLES LIKE 'estado'")->num_rows 
    ? "SELECT DATE(t.fecha) f, COUNT(*) c
         FROM turnos t JOIN estados e ON e.id_estado=t.id_estado
        WHERE t.id_medico=? AND DATE(t.fecha) BETWEEN ? AND ? AND e.nombre_estado<>'cancelado'
     GROUP BY DATE(t.fecha)"
    : "SELECT DATE(t.fecha) f, COUNT(*) c
         FROM turnos t
        WHERE t.id_medico=? AND DATE(t.fecha) BETWEEN ? AND ? AND (t.id_estado IS NULL OR t.id_estado<>4)
     GROUP BY DATE(t.fecha)";
  $st = $conn->prepare($sql);
  $st->bind_param('iss',$id_medico,$desde,$hasta);
  $st->execute();
  $r = $st->get_result();
  while($x=$r->fetch_assoc()) {
    $f=$x['f'];
    $by[$f] = ($by[$f] ?? ['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]);
    $by[$f]['ocup'] = (int)$x['c'];
  }
  $st->close();

  // Bloqueos de día
  if ($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
    $st = $conn->prepare("SELECT fecha f FROM agenda_bloqueos WHERE id_medico=? AND fecha BETWEEN ? AND ? AND tipo='dia' AND (activo=1 OR activo IS NULL)");
    $st->bind_param('iss',$id_medico,$desde,$hasta);
    $st->execute();
    $r = $st->get_result();
    while($x=$r->fetch_assoc()) {
      $f = $x['f'];
      $by[$f] = ($by[$f] ?? ['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]);
      $by[$f]['bloq'] = 1;
    }
    $st->close();
  }

  // Feriados
  if ($conn->query("SHOW TABLES LIKE 'feriados'")->num_rows) {
    $st = $conn->prepare("SELECT fecha f FROM feriados WHERE fecha BETWEEN ? AND ?");
    $st->bind_param('ss',$desde,$hasta);
    $st->execute();
    $r = $st->get_result();
    while($x=$r->fetch_assoc()) {
      $f = $x['f'];
      $by[$f] = ($by[$f] ?? ['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]);
      $by[$f]['fer'] = 1;
    }
    $st->close();
  }

  $ultimo = (int)date('t', strtotime($desde));
  $out = [];

  for($d=1; $d <= $ultimo; $d++) {
    $f = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
    $info = $by[$f] ?? ['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0];

  if ($f < $hoy) {
  $estado = 'gris';
} elseif ($info['fer']) {
  $estado = 'azul';  // Feriados en azul
} elseif ($info['bloq']) {
  $estado = 'rojo';  // Días bloqueados en rojo
} elseif ($info['franjas'] > 0) {
  $estado = 'verde';  // Días con franjas en verde
} else {
  $estado = 'gris';  // Sin franjas = gris (SIN AGENDA)
}


    $out[] = [
      'dia' => $d,
      'estado' => $estado,
      'libres' => max(0, ($info['franjas'] ?? 0) - ($info['ocup'] ?? 0)),
      'ocupados' => $info['ocup'] ?? 0
    ];
  }

  echo json_encode($out);
} catch(Throwable $e){
  http_response_code(500);
  echo json_encode(['error'=>true,'msg'=>$e->getMessage()]);
}
