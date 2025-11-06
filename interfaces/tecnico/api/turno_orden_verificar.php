<?php
/**
 * ========================================
 * API: Verificar firma de orden médica (Técnico)
 * ========================================
 * Ruta: /interfaces/tecnico/api/turno_orden_verificar.php
 * 
 * Verifica la autenticidad e integridad de la orden médica
 * vinculada a un turno antes de realizar el estudio.
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

    // ===== OBTENER ORDEN Y DATOS =====
    $sql = "
        SELECT 
            om.id_orden,
            om.id_paciente,
            om.id_medico,
            om.diagnostico,
            om.estudios_indicados,
            om.observaciones,
            om.contenido_hash,
            om.firma_digital,
            om.fecha_emision,
            m.clave_publica,
            CONCAT(um.apellido, ', ', um.nombre) AS medico_nombre,
            m.matricula
        FROM turnos t
        INNER JOIN ordenes_medicas om ON om.id_orden = t.id_orden_medica
        INNER JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios um ON um.id_usuario = m.id_usuario
        WHERE t.id_turno = ? AND t.id_tecnico = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id_turno, $id_tecnico);
    $stmt->execute();
    $result = $stmt->get_result();
    $orden = $result->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        echo json_encode(['ok' => false, 'msg' => 'Orden no encontrada o turno no autorizado']);
        exit;
    }

    // ===== VERIFICAR CLAVE PÚBLICA =====
    if (!$orden['clave_publica']) {
        echo json_encode([
            'ok' => false,
            'valida' => false,
            'msg' => 'El médico emisor no tiene clave pública registrada',
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'fecha_emision' => $orden['fecha_emision']
            ]
        ]);
        exit;
    }

    // ===== RECONSTRUIR CONTENIDO =====
    $estudios_array = json_decode($orden['estudios_indicados'], true);
    $estudios_nombres = array_map(function($e) {
        return $e['nombre'] ?? '';
    }, $estudios_array);

    $contenido_reconstruido = implode('|', [
        $orden['id_paciente'],
        $orden['id_medico'],
        $orden['diagnostico'],
        implode(',', $estudios_nombres),
        $orden['observaciones'],
        $orden['fecha_emision']
    ]);

    // ===== GENERAR HASH =====
    $hash_reconstruido = hash('sha256', $contenido_reconstruido);

    // ===== VERIFICAR INTEGRIDAD =====
    $integridad_ok = ($hash_reconstruido === $orden['contenido_hash']);

    if (!$integridad_ok) {
        echo json_encode([
            'ok' => true,
            'valida' => false,
            'msg' => '⚠️ La orden ha sido modificada después de su emisión',
            'verificacion' => [
                'integridad' => false,
                'autenticidad' => null
            ],
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'matricula' => $orden['matricula'],
                'fecha_emision' => $orden['fecha_emision']
            ],
            'recomendacion' => 'NO REALIZAR EL ESTUDIO. Contactar al médico emisor.'
        ]);
        exit;
    }

    // ===== VERIFICAR AUTENTICIDAD =====
    $firma_binaria = base64_decode($orden['firma_digital']);
    $clave_publica_resource = openssl_pkey_get_public($orden['clave_publica']);

    if (!$clave_publica_resource) {
        echo json_encode([
            'ok' => false,
            'msg' => 'Error al cargar la clave pública del médico'
        ]);
        exit;
    }

    $resultado_verificacion = openssl_verify(
        $orden['contenido_hash'],
        $firma_binaria,
        $clave_publica_resource,
        OPENSSL_ALGO_SHA256
    );

    openssl_free_key($clave_publica_resource);

    $autenticidad_ok = ($resultado_verificacion === 1);

    // ===== RESPUESTA =====
    if ($autenticidad_ok) {
        echo json_encode([
            'ok' => true,
            'valida' => true,
            'msg' => '✅ Orden médica válida y auténtica',
            'verificacion' => [
                'integridad' => true,
                'autenticidad' => true
            ],
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'matricula' => $orden['matricula'],
                'fecha_emision' => $orden['fecha_emision'],
                'diagnostico' => $orden['diagnostico']
            ],
            'recomendacion' => 'PUEDE REALIZAR EL ESTUDIO de forma segura.',
            'explicacion' => [
                'integridad' => 'El contenido de la orden no ha sido modificado',
                'autenticidad' => 'La firma digital confirma que fue emitida por el médico indicado',
                'no_repudio' => 'El médico no puede negar haber emitido esta orden'
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'ok' => true,
            'valida' => false,
            'msg' => '❌ Firma digital inválida',
            'verificacion' => [
                'integridad' => true,
                'autenticidad' => false
            ],
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'fecha_emision' => $orden['fecha_emision']
            ],
            'recomendacion' => 'NO REALIZAR EL ESTUDIO. Verificar con el médico emisor.'
        ], JSON_UNESCAPED_UNICODE);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error al verificar la orden: ' . $e->getMessage()
    ]);
}
?>