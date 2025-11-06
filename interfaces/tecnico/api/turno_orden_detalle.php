<?php
/**
 * ========================================
 * API: Obtener orden médica de un turno (Técnico)
 * ========================================
 * Ruta: /interfaces/tecnico/api/turno_orden_detalle.php
 * 
 * Devuelve la información completa de la orden médica
 * vinculada a un turno de estudio.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $rol_requerido = 4; // Técnico
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_tecnico = $_SESSION['id_tecnico'] ?? null;
    
    if (!$id_tecnico) {
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
    $id_turno = (int)($_GET['id_turno'] ?? 0);

    if ($id_turno <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID de turno inválido']);
        exit;
    }

    // ===== VERIFICAR QUE EL TURNO SEA DEL TÉCNICO =====
    $sqlVerificar = "
        SELECT id_turno 
        FROM turnos 
        WHERE id_turno = ? AND id_tecnico = ?
    ";
    
    $stmtVerif = $conn->prepare($sqlVerificar);
    $stmtVerif->bind_param('ii', $id_turno, $id_tecnico);
    $stmtVerif->execute();
    $resultVerif = $stmtVerif->get_result();
    
    if ($resultVerif->num_rows === 0) {
        echo json_encode(['ok' => false, 'msg' => 'Turno no encontrado o no autorizado']);
        exit;
    }
    $stmtVerif->close();

    // ===== OBTENER DATOS DE LA ORDEN =====
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
            CONCAT(um.apellido, ', ', um.nombre) AS medico_nombre,
            m.matricula AS medico_matricula,
            m.clave_publica,
            CONCAT(up.apellido, ', ', up.nombre) AS paciente_nombre,
            p.nro_documento AS paciente_dni,
            p.fecha_nacimiento,
            p.telefono AS paciente_telefono,
            t.fecha AS fecha_turno,
            t.hora AS hora_turno,
            e.nombre AS estudio_nombre
        FROM turnos t
        LEFT JOIN ordenes_medicas om ON om.id_orden = t.id_orden_medica
        LEFT JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios um ON um.id_usuario = m.id_usuario
        LEFT JOIN pacientes p ON p.id_paciente = om.id_paciente
        LEFT JOIN usuarios up ON up.id_usuario = p.id_usuario
        LEFT JOIN estudios e ON e.id_estudio = t.id_estudio
        WHERE t.id_turno = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_turno);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    if (!$data) {
        echo json_encode(['ok' => false, 'msg' => 'Datos no encontrados']);
        exit;
    }

    // Verificar si tiene orden médica vinculada
    if (!$data['id_orden']) {
        echo json_encode([
            'ok' => true,
            'tiene_orden' => false,
            'msg' => 'Este turno no tiene una orden médica vinculada',
            'turno' => [
                'paciente_nombre' => $data['paciente_nombre'],
                'fecha_turno' => $data['fecha_turno'],
                'hora_turno' => $data['hora_turno'],
                'estudio_nombre' => $data['estudio_nombre']
            ]
        ]);
        exit;
    }

    // Decodificar estudios
    $estudios_array = json_decode($data['estudios_indicados'], true);
    $estudios_nombres = '';
    
    if (is_array($estudios_array)) {
        $nombres = array_map(function($e) {
            return $e['nombre'] ?? '';
        }, $estudios_array);
        $estudios_nombres = implode(', ', array_filter($nombres));
    }

    // ===== RESPUESTA =====
    echo json_encode([
        'ok' => true,
        'tiene_orden' => true,
        'orden' => [
            'id_orden' => (int)$data['id_orden'],
            'diagnostico' => $data['diagnostico'],
            'estudios_indicados' => $data['estudios_indicados'],
            'estudios_nombres' => $estudios_nombres,
            'observaciones' => $data['observaciones'],
            'contenido_hash' => $data['contenido_hash'],
            'firma_digital' => $data['firma_digital'],
            'fecha_emision' => $data['fecha_emision'],
            'estado' => $data['estado']
        ],
        'medico' => [
            'nombre' => $data['medico_nombre'],
            'matricula' => $data['medico_matricula'],
            'clave_publica' => $data['clave_publica']
        ],
        'paciente' => [
            'nombre' => $data['paciente_nombre'],
            'dni' => $data['paciente_dni'],
            'fecha_nacimiento' => $data['fecha_nacimiento'],
            'telefono' => $data['paciente_telefono']
        ],
        'turno' => [
            'fecha' => $data['fecha_turno'],
            'hora' => $data['hora_turno'],
            'estudio' => $data['estudio_nombre']
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
?>