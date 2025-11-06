<?php
/**
 * ========================================
 * API: Obtener órdenes médicas disponibles
 * ========================================
 * Ruta: /Logica/Paciente/Gestion-Turnos/obtenerOrdenesDisponibles.php
 * 
 * Devuelve las órdenes médicas ACTIVAS del paciente
 * que contengan el estudio solicitado.
 * 
 * NOTA: Verificación de firma removida (no necesaria).
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 2) . '/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_paciente = $_SESSION['id_paciente_token'] ?? null;
    
    if (!$id_paciente) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
        exit;
    }

    // ===== CONEXIÓN BD =====
    $conexionPath = dirname(__DIR__, 2) . '/../Persistencia/conexionBD.php';
    require_once $conexionPath;
    
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== PARÁMETROS =====
    $id_estudio = (int)($_GET['id_estudio'] ?? 0);

    if ($id_estudio <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID de estudio inválido']);
        exit;
    }

    // ===== BUSCAR ÓRDENES ACTIVAS =====
    $sql = "
        SELECT 
            om.id_orden,
            om.diagnostico,
            om.estudios_indicados,
            om.fecha_emision,
            CONCAT(u.apellido, ', ', u.nombre) AS medico_nombre,
            m.matricula AS medico_matricula
        FROM ordenes_medicas om
        INNER JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE om.id_paciente = ?
          AND om.estado = 'activa'
        ORDER BY om.fecha_emision DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_paciente);
    $stmt->execute();
    $result = $stmt->get_result();

    $ordenes_validas = [];

    // ===== FILTRAR POR ESTUDIO (SIN VERIFICAR FIRMA) =====
    while ($row = $result->fetch_assoc()) {
        // Decodificar estudios
        $estudios_array = json_decode($row['estudios_indicados'], true);
        
        if (!is_array($estudios_array)) {
            error_log("Advertencia: estudios_indicados no es array válido para orden {$row['id_orden']}");
            continue;
        }

        // Verificar si contiene el estudio solicitado
        $contiene_estudio = false;
        foreach ($estudios_array as $estudio) {
            if ((int)($estudio['id'] ?? 0) === $id_estudio) {
                $contiene_estudio = true;
                break;
            }
        }

        if (!$contiene_estudio) continue;

        // Extraer nombres de estudios
        $estudios_nombres = array_map(function($e) {
            return $e['nombre'] ?? '';
        }, $estudios_array);

        $ordenes_validas[] = [
            'id_orden' => (int)$row['id_orden'],
            'medico_nombre' => $row['medico_nombre'],
            'medico_matricula' => $row['medico_matricula'],
            'diagnostico' => $row['diagnostico'],
            'estudios_nombres' => implode(', ', $estudios_nombres),
            'fecha_emision' => $row['fecha_emision'],
            'firma_verificada' => false  // Indicador de que no se verificó
        ];
    }

    $stmt->close();

    // Log para depurar
    error_log("Órdenes válidas encontradas para estudio {$id_estudio}: " . count($ordenes_validas));

    // ===== RESPUESTA =====
    echo json_encode([
        'ok' => true,
        'ordenes' => $ordenes_validas,
        'total' => count($ordenes_validas)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log("Error en obtenerOrdenesDisponibles.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>
