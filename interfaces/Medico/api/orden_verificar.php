<?php
/**
 * ========================================
 * API: Verificar firma digital de orden
 * ========================================
 * Ruta: /interfaces/Medico/api/orden_verificar.php
 * 
 * Verifica la autenticidad de una orden médica
 * usando la clave pública del médico emisor.
 * 
 * PRINCIPIOS DE SEGURIDAD:
 * - Autenticidad: Confirma que la orden fue emitida por el médico
 * - Integridad: Detecta cualquier modificación del contenido
 * - No repudio: El médico no puede negar haber emitido la orden
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

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

    // ===== OBTENER DATOS DE LA ORDEN =====
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
        WHERE om.id_orden = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_orden);
    $stmt->execute();
    $result = $stmt->get_result();
    $orden = $result->fetch_assoc();
    $stmt->close();

    if (!$orden) {
        echo json_encode(['ok' => false, 'msg' => 'Orden no encontrada']);
        exit;
    }

    // ===== VERIFICAR QUE EXISTA CLAVE PÚBLICA =====
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

    // ===== RECONSTRUIR CONTENIDO ORIGINAL =====
    // Debe coincidir exactamente con cómo se generó en ordenes_crear.php
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

    // ===== GENERAR HASH DEL CONTENIDO RECONSTRUIDO =====
    $hash_reconstruido = hash('sha256', $contenido_reconstruido);

    // ===== VERIFICAR INTEGRIDAD (Hash coincide) =====
    $integridad_ok = ($hash_reconstruido === $orden['contenido_hash']);

    if (!$integridad_ok) {
        echo json_encode([
            'ok' => true,
            'valida' => false,
            'msg' => '⚠️ La orden ha sido modificada después de su emisión',
            'verificacion' => [
                'integridad' => false,
                'autenticidad' => null,
                'hash_almacenado' => $orden['contenido_hash'],
                'hash_recalculado' => $hash_reconstruido
            ],
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'matricula' => $orden['matricula'],
                'fecha_emision' => $orden['fecha_emision']
            ]
        ]);
        exit;
    }

    // ===== VERIFICAR AUTENTICIDAD (Firma digital válida) =====
    $firma_binaria = base64_decode($orden['firma_digital']);
    $clave_publica_resource = openssl_pkey_get_public($orden['clave_publica']);

    if (!$clave_publica_resource) {
        echo json_encode([
            'ok' => false,
            'msg' => 'Error al cargar la clave pública del médico'
        ]);
        exit;
    }

    // openssl_verify retorna:
    // 1 = firma válida
    // 0 = firma inválida
    // -1 = error
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
        // ✅ Orden válida
        echo json_encode([
            'ok' => true,
            'valida' => true,
            'msg' => '✅ Orden médica válida y auténtica',
            'verificacion' => [
                'integridad' => true,
                'autenticidad' => true,
                'hash' => $orden['contenido_hash']
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
        // ❌ Firma inválida
        echo json_encode([
            'ok' => true,
            'valida' => false,
            'msg' => '❌ Firma digital inválida',
            'verificacion' => [
                'integridad' => true,
                'autenticidad' => false,
                'resultado_openssl' => $resultado_verificacion
            ],
            'detalles' => [
                'medico' => $orden['medico_nombre'],
                'fecha_emision' => $orden['fecha_emision']
            ],
            'explicacion' => [
                'problema' => 'La firma no corresponde a la clave pública del médico',
                'posibles_causas' => [
                    'La orden fue firmada con otra clave privada',
                    'Las claves del médico fueron regeneradas',
                    'La firma fue alterada'
                ]
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