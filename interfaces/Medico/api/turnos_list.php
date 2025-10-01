<?php
// interfaces/Medico/api/turnos_list.php
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
session_start();

if (!isset($_SESSION['id_medico'])) { http_response_code(401); echo json_encode([]); exit; }

require_once('../../Persistencia/conexionBD.php');

function fail($code, $msg){ http_response_code($code); echo json_encode(['ok'=>false,'msg'=>$msg]); exit; }

try{
  $conn = ConexionBD::conectar();
  $conn->set_charset('utf8mb4');

  $id_medico = (int)$_SESSION['id_medico'];

  $desde  = $_GET['desde']  ?? date('Y-m-d');
  $hasta  = $_GET['hasta']  ?? date('Y-m-d', strtotime('+30 days'));
  $estado = $_GET['estado'] ?? '';
  $q      = trim($_GET['q'] ?? '');

  // SQL mínimo: turnos + estado. Nada de tablas de pacientes/usuarios.
  $sql = "SELECT 
            t.id_turno,
            t.id_paciente,
            t.fecha,
            DATE_FORMAT(t.fecha, '%d/%m/%Y') AS fecha_fmt,
            t.hora,
            t.observaciones,
            e.nombre_estado AS estado
          FROM turnos t
          JOIN estado e ON e.id_estado = t.id_estado
          WHERE t.id_medico = ?
            AND t.fecha BETWEEN ? AND ?";

  $types = 'iss';
  $params = [$id_medico, $desde, $hasta];

  if ($estado !== '') {
    $sql .= " AND t.id_estado = ?";
    $types .= 'i';
    $params[] = (int)$estado;
  }

  // búsqueda: por id_turno / id_paciente / observaciones
  if ($q !== '') {
    $sql .= " AND (t.observaciones LIKE ? OR CAST(t.id_paciente AS CHAR) LIKE ? OR CAST(t.id_turno AS CHAR) LIKE ?)";
    $like = "%$q%";
    $types .= 'sss';
    array_push($params, $like, $like, $like);
  }

  $sql .= " ORDER BY t.fecha, t.hora";

  $st = $conn->prepare($sql);
  if(!$st){ fail(500, 'prepare: '.$conn->error); }
  if(!$st->bind_param($types, ...$params)){ fail(500, 'bind: '.$st->error); }
  if(!$st->execute()){ fail(500, 'exec: '.$st->error); }

  $res = $st->get_result();
  $out = [];
  while($r = $res->fetch_assoc()){
    // Campos que el front espera:
    $r['paciente'] = 'Paciente #'.$r['id_paciente']; // sin JOIN a pacientes
    $r['dni'] = null;
    $out[] = $r;
  }
  echo json_encode($out);
}catch(Throwable $e){
  fail(500, 'throw: '.$e->getMessage());
}
