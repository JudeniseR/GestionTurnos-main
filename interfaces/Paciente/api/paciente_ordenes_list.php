<?php
/**
 * ========================================
 * API: Listar órdenes del paciente
 * ========================================
 * Ruta: /interfaces/Paciente/api/paciente_ordenes_list.php
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $rol_requerido = 1; // Paciente
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_paciente = $_SESSION['id_paciente_token'] ?? null;
    
    if (!$id_paciente) {
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
    $busqueda = trim($_GET['q'] ?? '');

    // ===== CONSTRUIR QUERY =====
    $where = ["om.id_paciente = ?"];
    $types = "i";
    $params = [$id_paciente];

    // Filtro por búsqueda (médico o diagnóstico)
    if ($busqueda !== '') {
        $where[] = "(u.nombre LIKE ? OR u.apellido LIKE ? OR om.diagnostico LIKE ? OR om.estudios_indicados LIKE ?)";
        $types .= "ssss";
        $like = "%{$busqueda}%";
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
            om.diagnostico,
            om.estudios_indicados,
            om.observaciones,
            om.contenido_hash,
            om.firma_digital,
            om.fecha_emision,
            om.estado,

            -- 👇 DATOS DEL MÉDICO (para el sello)
            CONCAT(u.apellido, ', ', u.nombre) AS medico_nombre,
            m.matricula AS medico_matricula,
            u.genero AS medico_genero  -- opcional, si querés usar “Dr.” o “Dra.” dinámico
            -- Si luego agregás un campo especialidad en medicos, podés incluirlo así:
            -- , m.especialidad AS medico_especialidad
        FROM ordenes_medicas om
        INNER JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        $whereSql
        ORDER BY om.fecha_emision DESC
    ";

    $stmt = $conn->prepare($sql);
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

        // 👇 Formatear el prefijo Dr./Dra. según género
        $prefijo = 'Dr.';
        if (!empty($row['medico_genero']) && stripos($row['medico_genero'], 'fem') !== false) {
            $prefijo = 'Dra.';
        }

        $items[] = [
            'id_orden' => (int)$row['id_orden'],
            'medico_nombre' => "{$prefijo} {$row['medico_nombre']}",
            'medico_matricula' => $row['medico_matricula'],
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

    // ===== RESPUESTA =====
    echo json_encode([
        'ok' => true,
        'items' => $items,
        'total' => count($items)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>
