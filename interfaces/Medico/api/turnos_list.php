<?php
// interfaces/Medico/api/turnos_list.php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Seguridad / sesión
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    // Conexión
    $conexionPath = dirname(__DIR__, 3) . '/Persistencia/conexionBD.php';
    if (!file_exists($conexionPath)) {
        throw new RuntimeException("No se encontró el conector: $conexionPath");
    }
    require_once $conexionPath;

    if (!class_exists('ConexionBD') || !method_exists('ConexionBD','conectar')) {
        throw new RuntimeException('No está definida ConexionBD::conectar()');
    }
    $conn = ConexionBD::conectar(); // mysqli
    $conn->set_charset('utf8mb4');

    // Médico en sesión
    $id_medico = $_SESSION['id_medico'] ?? null;
    if (!$id_medico) {
        http_response_code(401);
        echo json_encode(['ok'=>false,'msg'=>'No autorizado (falta id_medico en sesión)']);
        exit;
    }

    // Parámetros
    $estadoTxt = strtolower(trim($_GET['estado'] ?? 'confirmado')); // pendiente|confirmado|atendido|cancelado|reprogramado
    $q         = trim($_GET['q'] ?? '');
    $desde     = trim($_GET['desde'] ?? '');
    $hasta     = trim($_GET['hasta'] ?? '');
    $page      = max(1, (int)($_GET['page'] ?? 1));
    $perPage   = min(50, max(1, (int)($_GET['per_page'] ?? 10)));

    // Mapear estado -> id_estado
    $stmt = $conn->prepare("SELECT id_estado FROM estados WHERE LOWER(nombre_estado)=LOWER(?) LIMIT 1");
    $stmt->bind_param('s', $estadoTxt);
    $stmt->execute();
    $stmt->bind_result($id_estado);
    $stmt->fetch();
    $stmt->close();

    // WHERE dinámico
    $where = ["t.id_medico = ?"];
    $types = "i";
    $vals  = [$id_medico];

    if ($id_estado) {
        $where[] = "t.id_estado = ?";
        $types  .= "i";
        $vals[]  = $id_estado;
    }

    if ($desde !== '' && $hasta !== '') {
        $where[] = "t.fecha BETWEEN ? AND ?";
        $types  .= "ss";
        $vals[]  = $desde;
        $vals[]  = $hasta;
    } elseif ($desde !== '') {
        $where[] = "t.fecha >= ?";
        $types  .= "s";
        $vals[]  = $desde;
    } elseif ($hasta !== '') {
        $where[] = "t.fecha <= ?";
        $types  .= "s";
        $vals[]  = $hasta;
    }

    if ($q !== '') {
        $where[] = "(u.apellido LIKE ? OR u.nombre LIKE ? OR a.apellido LIKE ? OR a.nombre LIKE ? OR p.nro_documento LIKE ? OR a.numero_documento LIKE ? OR t.fecha LIKE ?)";
        $types  .= "sssssss";
        $like    = "%{$q}%";
        $vals[]  = $like; $vals[] = $like; $vals[] = $like; $vals[] = $like; $vals[] = $like; $vals[] = $like; $vals[] = $like;
    }

    $whereSql = $where ? ("WHERE ".implode(" AND ", $where)) : "";

    // COUNT total
    $sqlCount = "
        SELECT COUNT(*)
        FROM turnos t
        INNER JOIN estados    e ON e.id_estado   = t.id_estado
        LEFT  JOIN pacientes p ON p.id_paciente = t.id_paciente
        LEFT  JOIN usuarios   u ON u.id_usuario  = p.id_usuario
        LEFT  JOIN afiliados  a ON a.id = t.id_afiliado
        $whereSql
    ";
    $stmt = $conn->prepare($sqlCount);
    $stmt->bind_param($types, ...$vals);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();

    // DATA con LIMIT
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT 
            t.id_turno,
            t.id_paciente,
            CASE
                WHEN t.id_afiliado IS NOT NULL AND a.id IS NOT NULL THEN CONCAT(a.apellido, ', ', a.nombre)
                WHEN t.id_paciente IS NOT NULL AND u.id_usuario IS NOT NULL THEN CONCAT(u.apellido, ', ', u.nombre)
                ELSE 'Sin datos'
            END AS paciente,
            CASE
                WHEN t.id_afiliado IS NOT NULL AND a.id IS NOT NULL THEN a.numero_documento
                WHEN t.id_paciente IS NOT NULL AND u.id_usuario IS NOT NULL THEN p.nro_documento
                ELSE NULL
            END AS dni,
            t.fecha,
            DATE_FORMAT(t.fecha, '%d/%m/%Y') AS fecha_fmt,
            t.hora,
            LOWER(e.nombre_estado) AS estado,
            COALESCE(t.observaciones, '') AS observaciones,
            COALESCE(t.reprogramado, 0) AS reprogramado,
            t.id_estado
        FROM turnos t
        INNER JOIN estados    e ON e.id_estado   = t.id_estado
        LEFT  JOIN pacientes p ON p.id_paciente = t.id_paciente
        LEFT  JOIN usuarios   u ON u.id_usuario  = p.id_usuario
        LEFT  JOIN afiliados  a ON a.id = t.id_afiliado
        $whereSql
        ORDER BY t.fecha ASC, t.hora ASC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    $types2 = $types . "ii";
    $vals2  = array_merge($vals, [$perPage, $offset]);
    $stmt->bind_param($types2, ...$vals2);
    $stmt->execute();
    $stmt->bind_result($id_turno, $id_paciente, $paciente, $dni, $fecha, $fecha_fmt, $hora, $estado, $observaciones, $reprogramado, $id_estado_row);

    $items = [];
    while ($stmt->fetch()) {
        $items[] = [
            'id_turno'      => $id_turno,
            'id_paciente'   => $id_paciente,
            'paciente'      => $paciente,
            'dni'           => $dni,
            'fecha'         => $fecha,
            'fecha_fmt'     => $fecha_fmt,
            'hora'          => $hora,
            'estado'        => $estado,
            'observaciones' => $observaciones,
            'reprogramado'  => (int)$reprogramado,
            'id_estado'     => (int)$id_estado_row,
        ];
    }
    $stmt->close();

    echo json_encode([
        'ok'       => true,
        'page'     => $page,
        'per_page' => $perPage,
        'total'    => (int)$total,
        'items'    => $items
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage(), 'where'=>'turnos_list.php']);
}
