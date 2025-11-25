<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if (!isset($_SESSION['id_medico'])) { 
    http_response_code(401); 
    echo json_encode([]); 
    exit; 
}

require_once('../../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar(); 
$conn->set_charset('utf8mb4');

$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20; 
$offset = ($page - 1) * $limit;

try {
    if ($q === '') { echo json_encode([]); exit; }

    $like = '%'.$conn->real_escape_string($q).'%';

    // ===== Buscar pacientes activos =====
    $sql_pacientes = "
        SELECT p.id_paciente AS id,
               CONCAT(u.apellido, ', ', u.nombre) AS nombre_completo,
               p.nro_documento AS dni,
               p.telefono,
               'titular' AS tipo,
               NULL AS id_titular
        FROM pacientes p
        LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
        WHERE u.nombre LIKE ? OR u.apellido LIKE ? OR p.nro_documento LIKE ?
    ";

    // ===== Buscar beneficiarios desde afiliados =====
    $sql_beneficiarios = "
        SELECT a.id AS id,
               CONCAT(a.apellido, ', ', a.nombre) AS nombre_completo,
               a.numero_documento AS dni,
               NULL AS telefono,
               a.tipo_beneficiario AS tipo,
               a.id_titular AS id_titular
        FROM afiliados a
        WHERE (a.nombre LIKE ? OR a.apellido LIKE ? OR a.numero_documento LIKE ?)
          AND a.id_titular IS NOT NULL
    ";

    // ===== Unión y paginación =====
    $sql = "($sql_pacientes) UNION ALL ($sql_beneficiarios)
            ORDER BY nombre_completo
            LIMIT $limit OFFSET $offset";

    $st = $conn->prepare($sql);
    $st->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
    $st->execute();
    $rs = $st->get_result();

    $out = [];
    while($r = $rs->fetch_assoc()){
        $out[] = [
            'id' => (int)$r['id'],
            'nombre_completo' => $r['nombre_completo'] ?? '',
            'dni' => $r['dni'] ?? '',
            'telefono' => $r['telefono'] ?? '',
            'tipo' => $r['tipo'],
            'id_titular' => $r['id_titular'] ? (int)$r['id_titular'] : null
        ];
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
