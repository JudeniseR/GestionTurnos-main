<?php
/**
 * ========================================
 * API: Crear y firmar orden médica
 * ========================================
 * Ruta: /interfaces/Medico/api/ordenes_crear.php
 * 
 * Crea una orden médica, genera su hash y la firma
 * digitalmente con la clave privada del médico.
 */

// Iniciar buffer para capturar cualquier output accidental
ob_start();

header('Content-Type: application/json; charset=utf-8');

// Quitar o comentar estas líneas para evitar warnings
// error_reporting(E_ALL);
// ini_set('display_errors', '1');

// Log temporal (ya comentado, pero confirma que esté así)
// file_put_contents('debug.log', 'Inicio de ordenes_crear.php' . PHP_EOL, FILE_APPEND);

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_medico = $_SESSION['id_medico'] ?? null;
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    
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

    // ===== VALIDACIONES =====
    if ($id_paciente <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'Debe seleccionar un paciente']);
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

    // Validar JSON de estudios
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

    // ===== CONSTRUIR CONTENIDO DE LA ORDEN =====
    // El contenido que se va a firmar debe ser determinístico
    $estudios_nombres = array_map(function($e) { 
        return $e['nombre'] ?? ''; 
    }, $estudios_array);

    $contenido = implode('|', [
        $id_paciente,
        $id_medico,
        $diagnostico,
        implode(',', $estudios_nombres),
        $observaciones,
        date('Y-m-d H:i:s') // timestamp para unicidad
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
    
    // Comentar esta línea para evitar warning deprecated
    // openssl_free_key($clave_privada_resource);

    if (!$resultado_firma) {
        echo json_encode([
            'ok' => false, 
            'msg' => 'Error al firmar la orden digitalmente'
        ]);
        exit;
    }

    // Convertir firma a base64 para almacenarla
    $firma_base64 = base64_encode($firma);

    // ===== GUARDAR EN BASE DE DATOS =====
    $conn->begin_transaction();

    try {
        // Insertar orden médica
        $sql_insert = "INSERT INTO ordenes_medicas 
                       (id_paciente, id_medico, diagnostico, estudios_indicados, 
                        observaciones, contenido_hash, firma_digital, estado)
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'activa')";
        
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param(
            'iisssss',
            $id_paciente,
            $id_medico,
            $diagnostico,
            $estudios_json,
            $observaciones,
            $hash,
            $firma_base64
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar la orden en la base de datos');
        }

        $id_orden = $conn->insert_id;
        $stmt->close();

        $conn->commit();

        // ===== RESPUESTA EXITOSA =====
        // Limpiar buffer antes de enviar JSON
        ob_clean();
        echo json_encode([
            'ok' => true,
            'msg' => 'Orden médica firmada y emitida exitosamente',
            'data' => [
                'id_orden' => $id_orden,
                'hash' => $hash,
                'firma_longitud' => strlen($firma_base64)
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }

} catch (Throwable $e) {
    // Limpiar buffer en caso de error
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}

// Limpiar buffer al final
ob_end_flush();
?>