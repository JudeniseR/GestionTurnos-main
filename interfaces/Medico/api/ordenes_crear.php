<?php
/**
 * ========================================
 * API: Crear y firmar orden médica
 * ========================================
 */

// ===== DEPURACIÓN ACTIVA =====
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_log("=== INICIO ordenes_crear.php ===");

header('Content-Type: application/json; charset=utf-8');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_medico = $_SESSION['id_medico'] ?? null;
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    
    error_log("ID Médico: $id_medico");
    
    if (!$id_medico || !$id_usuario) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
        exit;
    }

    // ===== SOLO POST =====
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
        exit;
    }

    // ===== CONEXIÓN BD =====
    $conexionPath = dirname(__DIR__, 3) . '/Persistencia/conexionBD.php';
    require_once $conexionPath;
    
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== RECIBIR DATOS =====
    $id_paciente = (int)($_POST['id_paciente'] ?? 0);
    $diagnostico = trim($_POST['diagnostico'] ?? '');
    $estudios_json = trim($_POST['estudios_indicados'] ?? '');
    $observaciones = trim($_POST['observaciones'] ?? '');

    error_log("ID Paciente recibido: $id_paciente");
    error_log("Diagnóstico: $diagnostico");
    error_log("Estudios JSON: $estudios_json");

    // ===== VALIDACIONES BÁSICAS =====
    if ($id_paciente <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Debe seleccionar un paciente o beneficiario']);
        exit;
    }

    if (empty($diagnostico)) {
        echo json_encode(['ok' => false, 'msg' => 'El diagnóstico es obligatorio']);
        exit;
    }

    if (empty($estudios_json)) {
        echo json_encode(['ok' => false, 'msg' => 'Debe indicar al menos un estudio']);
        exit;
    }

    $estudios_array = json_decode($estudios_json, true);
    if (!is_array($estudios_array) || count($estudios_array) === 0) {
        echo json_encode(['ok' => false, 'msg' => 'Formato de estudios inválido']);
        exit;
    }

    // ===== VERIFICAR CLAVES DEL MÉDICO =====
    $sql = "SELECT clave_privada FROM medicos WHERE id_medico = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $id_medico);
    $stmt->execute();
    $stmt->bind_result($clave_privada);
    $stmt->fetch();
    $stmt->close();

    if (!$clave_privada) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'No tiene claves digitales generadas. Debe generarlas primero.'
        ]);
        exit;
    }

    // ===== VALIDAR ENTIDAD (PACIENTE O AFILIADO) =====
    $id_afiliado = null;
    $id_titular = null;
    $tipo_entidad = null;

    error_log("Buscando entidad con ID: $id_paciente");

    // Primero verificar si es un paciente
    $sql_pac = "SELECT p.id_paciente FROM pacientes p WHERE p.id_paciente = ?";
    $stmt = $conn->prepare($sql_pac);
    $stmt->bind_param('i', $id_paciente);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Es un paciente titular
        $tipo_entidad = 'paciente';
        $id_afiliado = null;
        $id_titular = null;
        error_log("Entidad identificada: PACIENTE");
    } else {
        // No es paciente, verificar si es afiliado
        $stmt->close();
        
        $sql_af = "SELECT id, id_titular, tipo_beneficiario 
                   FROM afiliados 
                   WHERE id = ? AND estado = 'activo'";
        $stmt = $conn->prepare($sql_af);
        $stmt->bind_param('i', $id_paciente);
        $stmt->execute();
        $stmt->bind_result($id_af, $id_tit, $tipo_benef);
        
        if ($stmt->fetch()) {
            $tipo_entidad = $tipo_benef;
            $id_afiliado = $id_af;
            $id_titular = $id_tit;
            
            error_log("Entidad identificada: AFILIADO ($tipo_benef) - Titular: $id_titular");
            
            // Validar que los menores tengan titular
            if (in_array($tipo_benef, ['hijo menor', 'hijo mayor']) && !$id_titular) {
                $stmt->close();
                echo json_encode(['ok' => false, 'msg' => 'Afiliado menor sin titular asociado']);
                exit;
            }
        } else {
            $stmt->close();
            echo json_encode(['ok' => false, 'msg' => 'Paciente o afiliado no encontrado']);
            exit;
        }
    }
    $stmt->close();

    // ===== CONSTRUIR CONTENIDO DE LA ORDEN =====
    $estudios_nombres = array_map(fn($e) => $e['nombre'] ?? '', $estudios_array);
    $contenido = implode('|', [
        $id_paciente,
        $id_medico,
        $diagnostico,
        implode(',', $estudios_nombres),
        $observaciones,
        date('Y-m-d H:i:s')
    ]);

    // ===== GENERAR HASH SHA256 =====
    $hash = hash('sha256', $contenido);

    // ===== FIRMAR CON CLAVE PRIVADA =====
    $clave_privada_resource = openssl_pkey_get_private($clave_privada);
    if (!$clave_privada_resource) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'Error al cargar la clave privada. Contacte al administrador.'
        ]);
        exit;
    }

    $firma = '';
    $resultado_firma = openssl_sign($hash, $firma, $clave_privada_resource, OPENSSL_ALGO_SHA256);
    if (!$resultado_firma) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'Error al firmar la orden digitalmente'
        ]);
        exit;
    }
    $firma_base64 = base64_encode($firma);

    // ===== GUARDAR EN BASE DE DATOS (VERSIÓN SIMPLIFICADA) =====
    $conn->begin_transaction();
    
    try {
        // Usar valores NULL correctamente en SQL
        $sql_insert = "INSERT INTO ordenes_medicas 
                       (id_paciente, id_afiliado, id_titular, id_medico, diagnostico, 
                        estudios_indicados, observaciones, contenido_hash, firma_digital, estado)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'activa')";

        $stmt = $conn->prepare($sql_insert);
        
        if (!$stmt) {
            throw new Exception("Error en prepare: " . $conn->error);
        }

        // Asignar valores (NULL para campos vacíos)
        $val_id_paciente = ($tipo_entidad === 'paciente') ? $id_paciente : null;
        $val_id_afiliado = ($tipo_entidad !== 'paciente') ? $id_afiliado : null;
        $val_id_titular = ($tipo_entidad !== 'paciente') ? $id_titular : null;

        error_log("Valores a insertar: id_pac=$val_id_paciente, id_afil=$val_id_afiliado, id_tit=$val_id_titular");

        $stmt->bind_param(
            'iiiisssss',
            $val_id_paciente,
            $val_id_afiliado,
            $val_id_titular,
            $id_medico,
            $diagnostico,
            $estudios_json,
            $observaciones,
            $hash,
            $firma_base64
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al insertar: ' . $stmt->error);
        }

        $id_orden = $conn->insert_id;
        $stmt->close();
        $conn->commit();

        error_log("Orden creada exitosamente: ID $id_orden");

        echo json_encode([
            'ok' => true,
            'msg' => 'Orden médica firmada y emitida exitosamente',
            'data' => [
                'id_orden' => $id_orden,
                'tipo_entidad' => $tipo_entidad,
                'es_afiliado' => ($tipo_entidad !== 'paciente')
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        error_log("ERROR en transacción: " . $e->getMessage());
        throw $e;
    }

} catch (Throwable $e) {
    error_log("ERROR GENERAL: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error en el servidor: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>