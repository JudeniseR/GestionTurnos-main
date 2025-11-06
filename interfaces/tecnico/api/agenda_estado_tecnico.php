<?php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 1);
session_start();

// Conexión
require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// Buscar id_tecnico desde sesión o desde tabla tecnicos
$id_tecnico = $_SESSION['id_tecnico'] ?? null;
if (!$id_tecnico && isset($_SESSION['id_usuario'])) {
    $stmt = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['id_usuario']);
    $stmt->execute();
    $stmt->bind_result($id_tecnico);
    $stmt->fetch();
    $stmt->close();
    if ($id_tecnico) $_SESSION['id_tecnico'] = $id_tecnico;
}

// Obtener el id_recurso asociado al técnico
$id_recurso = null;
if ($id_tecnico) {
    $stmt = $conn->prepare("SELECT id_recurso FROM recursos WHERE id_tecnico = ? LIMIT 1");
    $stmt->bind_param('i', $id_tecnico);
    $stmt->execute();
    $stmt->bind_result($id_recurso);
    $stmt->fetch();
    $stmt->close();
}

$anio = (int)($_GET['anio'] ?? date('Y'));
$mes  = (int)($_GET['mes']  ?? date('n'));

try {
    if(!$id_tecnico || !$id_recurso || $anio < 2000 || $mes < 1 || $mes > 12){ 
        echo json_encode([]); 
        exit; 
    }

    $desde = sprintf('%04d-%02d-01', $anio, $mes);
    $hasta = date('Y-m-t', strtotime($desde));
    $hoy   = date('Y-m-d');

    $by = [];

    // Franjas del técnico en agenda
    $st = $conn->prepare("SELECT DATE(fecha) f, COUNT(*) c FROM agenda WHERE id_tecnico=? AND DATE(fecha) BETWEEN ? AND ? GROUP BY DATE(fecha)");
    $st->bind_param('iss', $id_tecnico, $desde, $hasta); 
    $st->execute();
    $r = $st->get_result(); 
    while($x = $r->fetch_assoc()){ 
        $by[$x['f']] = ['franjas'=>(int)$x['c'],'ocup'=>0,'bloq'=>0,'fer'=>0]; 
    } 
    $st->close();

    // Turnos ocupados (estados activos: pendiente=1, confirmado=2, reprogramado=5)
    $sql="SELECT DATE(t.fecha) f, COUNT(*) c
          FROM turnos t
          WHERE t.id_recurso=? AND DATE(t.fecha) BETWEEN ? AND ? AND t.id_estado IN (1,2,5)
          GROUP BY DATE(t.fecha)";
    $st=$conn->prepare($sql); 
    $st->bind_param('iss', $id_recurso, $desde, $hasta); 
    $st->execute();
    $r=$st->get_result(); 
    while($x=$r->fetch_assoc()){ 
        $f=$x['f']; 
        $by[$f] = ($by[$f]??['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]); 
        $by[$f]['ocup']=(int)$x['c']; 
    } 
    $st->close();

    // Bloqueos de día
    if ($conn->query("SHOW TABLES LIKE 'agenda_bloqueos'")->num_rows) {
        $st=$conn->prepare("SELECT fecha f FROM agenda_bloqueos WHERE id_tecnico=? AND fecha BETWEEN ? AND ? AND tipo='dia' AND (activo=1 OR activo IS NULL)");
        $st->bind_param('iss', $id_tecnico, $desde, $hasta); 
        $st->execute();
        $r=$st->get_result(); 
        while($x=$r->fetch_assoc()){ 
            $f=$x['f'];
            $by[$f]=($by[$f]??['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]); 
            $by[$f]['bloq']=1; 
        } 
        $st->close();
    }

    // Feriados
    if ($conn->query("SHOW TABLES LIKE 'feriados'")->num_rows) {
        $st=$conn->prepare("SELECT fecha f, descripcion FROM feriados WHERE fecha BETWEEN ? AND ?");
        $st->bind_param('ss', $desde, $hasta); 
        $st->execute();
        $r=$st->get_result(); 
        while($x=$r->fetch_assoc()){ 
            $f=$x['f']; 
            $by[$f]=($by[$f]??['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0]); 
            $by[$f]['fer']=1;
            $by[$f]['feriado_desc']=$x['descripcion']??''; 
        } 
        $st->close();
    }

    $ultimo = (int)date('t', strtotime($desde));
    $out = [];
    for($d=1;$d<=$ultimo;$d++){
        $f = sprintf('%04d-%02d-%02d', $anio, $mes, $d);
        $info = $by[$f] ?? ['franjas'=>0,'ocup'=>0,'bloq'=>0,'fer'=>0];

        if ($f < $hoy) $estado='gris';
        elseif ($info['bloq']||$info['fer']) $estado='rojo';
        else $estado='verde';

        $item = [
            'dia'=>$d,
            'estado'=>$estado,
            'libres'=>max(0,($info['franjas']??0)-($info['ocup']??0)),
            'ocupados'=>$info['ocup']??0
        ];

        if($info['fer'] ?? 0) {
            $item['feriado'] = true;
            $item['feriado_desc'] = $info['feriado_desc'] ?? '';
        }

        $out[] = $item;
    }
    echo json_encode($out);

} catch(Throwable $e){
    http_response_code(500); 
    echo json_encode(['error'=>true,'msg'=>$e->getMessage()]);
}
