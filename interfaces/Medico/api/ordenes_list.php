<?php
/**
 * ========================================
 * API: Listar órdenes médicas
 * ========================================
 * Ruta: /interfaces/Medico/api/ordenes_list.php
 * 
 * Lista las órdenes médicas emitidas por el médico
 * con filtros por estado y búsqueda.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_medico = $_SESSION['id_medico'] ?? null;
    
    if (!$id_medico) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
        exit;
    }

    // ===== CONEXIÓN BD =====
    $conexionPath = dirname(__DIR__, 3) . '/Persistencia/conexionBD.php';
    require_once $conexionPath;
    
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== PARÁMETROS =====
    $estado = trim($_GET['estado'] ?? 'activa'); // activa, utilizada, cancelada, historial
    $busqueda = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    // ===== CONSTRUIR QUERY =====
    $where = ["om.id_medico = ?"];
    $types = "i";
    $params = [$id_medico];

    // Filtro por estado
    if ($estado !== 'historial') {
        $where[] = "om.estado = ?";
        $types .= "s";
        $params[] = $estado;
    }

    // Filtro por búsqueda (paciente o DNI)
    if ($busqueda !== '') {
        $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR a.nombre LIKE ? OR a.apellido LIKE ? OR p.nro_documento LIKE ? OR a.numero_documento LIKE ?)";
        $types .= "ssssss";
        $like = "%{$busqueda}%";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = "WHERE " . implode(" AND ", $where);

    // ===== QUERY PRINCIPAL =====
    $sql = "
        SELECT 
            om.id_orden,
            om.id_paciente,
            om.id_afiliado,
            om.diagnostico,
            om.estudios_indicados,
            om.observaciones,
            om.contenido_hash,
            om.firma_digital,
            om.fecha_emision,
            om.estado,
            CASE
                WHEN om.id_afiliado IS NOT NULL AND a.id IS NOT NULL THEN CONCAT(a.apellido, ', ', a.nombre)
                WHEN om.id_paciente IS NOT NULL AND u.id_usuario IS NOT NULL THEN CONCAT(u.apellido, ', ', u.nombre)
                ELSE 'Sin datos'
            END AS paciente_nombre,
            CASE
                WHEN om.id_afiliado IS NOT NULL AND a.id IS NOT NULL THEN a.numero_documento
                WHEN om.id_paciente IS NOT NULL AND p.nro_documento IS NOT NULL THEN p.nro_documento
                ELSE NULL
            END AS paciente_dni
        FROM ordenes_medicas om
        LEFT JOIN pacientes p ON p.id_paciente = om.id_paciente
        LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
        LEFT JOIN afiliados a ON a.id = om.id_afiliado
        $whereSql
        ORDER BY om.fecha_emision DESC
        LIMIT ? OFFSET ?
    ";

    $stmt = $conn->prepare($sql);
    $types .= "ii";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        // Decodificar estudios JSON
        $estudios_array = json_decode($row['estudios_indicados'], true);
        $estudios_nombres = '';
        
        if (is_array($estudios_array)) {
            $nombres = array_map(function($e) {
                return $e['nombre'] ?? '';
            }, $estudios_array);
            $estudios_nombres = implode(', ', array_filter($nombres));
        }

        $items[] = [
            'id_orden' => (int)$row['id_orden'],
            'id_paciente' => (int)$row['id_paciente'],
            'paciente_nombre' => $row['paciente_nombre'],
            'paciente_dni' => $row['paciente_dni'],
            'diagnostico' => $row['diagnostico'],
            'estudios_indicados' => $row['estudios_indicados'],
            'estudios_nombres' => $estudios_nombres,
            'observaciones' => $row['observaciones'],
            'contenido_hash' => $row['contenido_hash'],
            'firma_digital' => $row['firma_digital'],
            'fecha_emision' => $row['fecha_emision'],
            'estado' => $row['estado']
        ];
    }

    $stmt->close();

    // ===== CONTAR TOTAL =====
    $sqlCount = "
        SELECT COUNT(*) as total
        FROM ordenes_medicas om
        LEFT JOIN pacientes p ON p.id_paciente = om.id_paciente
        LEFT JOIN usuarios u ON u.id_usuario = p.id_usuario
        LEFT JOIN afiliados a ON a.id = om.id_afiliado
        $whereSql
    ";

    $stmtCount = $conn->prepare($sqlCount);
    // Remover los últimos 2 parámetros (LIMIT y OFFSET)
    $paramsCount = array_slice($params, 0, -2);
    $typesCount = substr($types, 0, -2);
    $stmtCount->bind_param($typesCount, ...$paramsCount);
    $stmtCount->execute();
    $stmtCount->bind_result($total);
    $stmtCount->fetch();
    $stmtCount->close();

    // ===== RESPUESTA =====
    echo json_encode([
        'ok' => true,
        'items' => $items,
        'total' => (int)$total,
        'page' => $page,
        'per_page' => $perPage
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>