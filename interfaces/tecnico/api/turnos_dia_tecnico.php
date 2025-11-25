<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','1'); // para debug
session_start();

// --- Obtener id_tecnico desde sesión o tabla tecnicos ---
$id_tecnico = $_SESSION['id_tecnico'] ?? null;
if (!$id_tecnico && isset($_SESSION['id_usuario'])) {
    require_once('../../../Persistencia/conexionBD.php');
    $conn = ConexionBD::conectar();
    $stmt = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_usuario = ? LIMIT 1");
    $stmt->bind_param('i', $_SESSION['id_usuario']);
    $stmt->execute();
    $stmt->bind_result($id_tecnico);
    $stmt->fetch();
    $stmt->close();
    if ($id_tecnico) $_SESSION['id_tecnico'] = $id_tecnico;
}

if (!$id_tecnico) { 
    http_response_code(401); 
    echo json_encode([]); 
    exit; 
}

// --- Fecha solicitada ---
$fecha = $_GET['fecha'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) { 
    echo json_encode([]); 
    exit; 
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); 
$conn->set_charset('utf8mb4');

try {
    // --- Consulta de turnos para el técnico ---
    $sql = "
        SELECT 
            t.id_turno,
            DATE(t.fecha) AS fecha,
            TIME_FORMAT(t.hora,'%H:%i') AS hora,
            t.observaciones,
            e.nombre_estado AS estado,
            p.id_paciente,
            p.nro_documento AS dni,
            CONCAT(u.apellido, ', ', u.nombre) AS paciente,
            est.nombre AS estudio
        FROM turnos t
        LEFT JOIN estados e    ON e.id_estado = t.id_estado
        LEFT JOIN pacientes p  ON p.id_paciente = t.id_paciente
        LEFT JOIN usuarios u   ON u.id_usuario = p.id_usuario
        LEFT JOIN estudios est ON est.id_estudio = t.id_estudio
        WHERE t.id_recurso = ? AND DATE(t.fecha) = ?
        ORDER BY t.hora ASC
    ";

    $st = $conn->prepare($sql);
    $st->bind_param('is', $id_tecnico, $fecha);
    $st->execute();
    $rs = $st->get_result();

    $out = [];
    while ($r = $rs->fetch_assoc()) {
        $out[] = [
            'id_turno'      => (int)$r['id_turno'],
            'fecha'         => $r['fecha'],
            'hora'          => $r['hora'],
            'paciente'      => $r['paciente'],
            'dni'           => $r['dni'],
            'estado'        => $r['estado'],
            'observaciones' => $r['observaciones'] ?? '',
            'id_paciente'   => (int)$r['id_paciente'],
            'estudio'       => $r['estudio'] ?? '',
        ];
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
