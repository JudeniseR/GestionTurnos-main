<?php
/**
 * ========================================
 * API: Generación de claves digitales RSA
 * ========================================
 * Ruta: /interfaces/Medico/api/generar_claves.php
 * 
 * Permite al médico generar su par de claves RSA (pública/privada)
 * confirmando su contraseña como medida de seguridad.
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    // Verificar que sea médico
    $id_medico = $_SESSION['id_medico'] ?? null;
    $id_usuario = $_SESSION['id_usuario'] ?? null;
    
    if (!$id_medico || !$id_usuario) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autorizado. Debe iniciar sesión como médico.']);
        exit;
    }

    // ===== CONEXIÓN BD =====
    $conexionPath = dirname(__DIR__, 3) . '/Persistencia/conexionBD.php';
    if (!file_exists($conexionPath)) {
        throw new RuntimeException("No se encontró el archivo de conexión");
    }
    require_once $conexionPath;
    
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== PROCESAR SOLICITUD =====
    
    // 1️⃣ GET: Verificar si ya tiene claves generadas
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $sql = "SELECT 
                    clave_publica IS NOT NULL AS tiene_claves,
                    fecha_generacion_claves
                FROM medicos 
                WHERE id_medico = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_medico);
        $stmt->execute();
        $stmt->bind_result($tiene_claves, $fecha_generacion);
        $stmt->fetch();
        $stmt->close();
        
        echo json_encode([
            'ok' => true,
            'tiene_claves' => (bool)$tiene_claves,
            'fecha_generacion' => $fecha_generacion
        ]);
        exit;
    }

    // 2️⃣ POST: Generar nuevas claves
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $password_confirmacion = $data['password'] ?? '';
        
        if (empty($password_confirmacion)) {
            echo json_encode(['ok' => false, 'msg' => 'Debe confirmar su contraseña']);
            exit;
        }

        // ===== VERIFICAR CONTRASEÑA =====
        $sql = "SELECT u.password_hash 
                FROM usuarios u
                INNER JOIN medicos m ON m.id_usuario = u.id_usuario
                WHERE m.id_medico = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_medico);
        $stmt->execute();
        $stmt->bind_result($password_hash);
        $stmt->fetch();
        $stmt->close();
        
        if (!password_verify($password_confirmacion, $password_hash)) {
            echo json_encode(['ok' => false, 'msg' => 'Contraseña incorrecta']);
            exit;
        }

        // ===== VERIFICAR SI YA TIENE CLAVES =====
        $sql = "SELECT clave_publica FROM medicos WHERE id_medico = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_medico);
        $stmt->execute();
        $stmt->bind_result($clave_existente);
        $stmt->fetch();
        $stmt->close();
        
        if ($clave_existente) {
            echo json_encode([
                'ok' => false, 
                'msg' => 'Ya generaste tus claves digitales. Por seguridad, no se pueden regenerar.'
            ]);
            exit;
        }

        // ===== GENERAR PAR DE CLAVES RSA =====
        
        // Configuración para generar claves de 2048 bits
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];
        
        // Generar el par de claves
        $res = openssl_pkey_new($config);
        
        if (!$res) {
            throw new Exception('Error al generar el par de claves RSA');
        }
        
        // Extraer la clave privada
        openssl_pkey_export($res, $clave_privada);
        
        // Extraer la clave pública
        $detalles = openssl_pkey_get_details($res);
        $clave_publica = $detalles['key'];
        
        // ===== GUARDAR EN BD =====
        $sql = "UPDATE medicos 
                SET clave_publica = ?,
                    clave_privada = ?,
                    fecha_generacion_claves = CURRENT_TIMESTAMP
                WHERE id_medico = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $clave_publica, $clave_privada, $id_medico);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al guardar las claves en la base de datos');
        }
        $stmt->close();
        
        // ===== RESPUESTA EXITOSA =====
        echo json_encode([
            'ok' => true,
            'msg' => '✅ Claves digitales generadas exitosamente',
            'info' => [
                'algoritmo' => 'RSA',
                'bits' => 2048,
                'fecha' => date('Y-m-d H:i:s')
            ]
        ]);
        exit;
    }

    // Método no permitido
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'Error en el servidor: ' . $e->getMessage()
    ]);
}
?>