<?php
/**
  * ⚠️ [DEPRECATED] API antigua - Verificación de firma digital desde módulo Paciente
 * 
 * Ya no se utiliza. La verificación de firma se realiza ahora en:
 * /interfaces/Tecnico/api/tecnico_orden_verificar.php
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
    $id_orden = (int)($_GET['id_orden'] ?? 0);

    if ($id_orden <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID de orden inválido']);
        exit;
    }

    // ===== VERIFICAR QUE LA ORDEN SEA DEL PACIENTE =====
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
            CONCAT(u.apellido, ', ', u.nombre) AS medico_nombre,
            m.matricula
        FROM ordenes_medicas om
        INNER JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        WHERE om.id_orden = ? AND om.id_paciente = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id_orden, $id_paciente);
    $stmt->execute();
    $result = $stmt->get_result();
    $orden = $result->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        echo json_encode(['ok' => false, 'msg' => 'Orden no encontrada o no autorizada']);
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
            ]
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
            ]
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