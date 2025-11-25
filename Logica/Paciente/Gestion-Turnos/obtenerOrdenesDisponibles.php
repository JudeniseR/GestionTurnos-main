<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SESIÓN =====
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $id_paciente_sesion = $_SESSION['id_paciente_token'] ?? null;

    if (!$id_paciente_sesion) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'msg' => 'No autorizado']);
        exit;
    }

    // ===== BD =====
    require_once dirname(__DIR__, 2) . '/../Persistencia/conexionBD.php';
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== Parámetros =====
    $id_estudio = (int)($_GET['id_estudio'] ?? 0);
    $id_beneficiario = (int)($_GET['id_beneficiario'] ?? 0);

    if ($id_estudio <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID de estudio inválido']);
        exit;
    }

    // ===== Obtener DNI del titular =====
    $sql = "SELECT nro_documento FROM pacientes WHERE id_paciente = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_paciente_sesion);
    $stmt->execute();
    $stmt->bind_result($nro_doc_titular);
    $stmt->fetch();
    $stmt->close();

    if (!$nro_doc_titular) {
        echo json_encode(['ok' => false, 'msg' => 'Paciente no encontrado']);
        exit;
    }

    // ===== Buscar ID afiliado del titular =====
    $sql = "SELECT id FROM afiliados WHERE numero_documento = ? AND tipo_beneficiario = 'titular' LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nro_doc_titular);
    $stmt->execute();
    $stmt->bind_result($id_afiliado_titular);
    $stmt->fetch();
    $stmt->close();

    // ======================================================
    //        CONSULTA DE ÓRDENES → CORREGIDA AQUÍ
    // ======================================================

    if ($id_beneficiario > 0) {

        // AFILIADO MENOR
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
            WHERE om.estado = 'activa'
              AND om.id_afiliado = ?
            ORDER BY om.fecha_emision DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_beneficiario);

    } else {

        // TITULAR → BUSCAR POR TODAS LAS POSIBILIDADES REALES
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
            WHERE om.estado = 'activa'
              AND (
                    om.id_paciente = ?           -- caso habitual del titular
                    " . ($id_afiliado_titular ? "OR om.id_afiliado = $id_afiliado_titular" : "") . "
                    " . ($id_afiliado_titular ? "OR om.id_titular  = $id_afiliado_titular" : "") . "
                  )
            ORDER BY om.fecha_emision DESC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_paciente_sesion);
    }

    // Ejecutar
    $stmt->execute();
    $result = $stmt->get_result();

    // ===== Filtrar por estudio =====
    $ordenes_validas = [];

    while ($row = $result->fetch_assoc()) {

        $estudios = json_decode($row['estudios_indicados'], true);
        if (!is_array($estudios)) continue;

        $incluye = false;
        foreach ($estudios as $e) {
            if ((int)$e['id'] === $id_estudio) {
                $incluye = true;
                break;
            }
        }
        if (!$incluye) continue;

        $ordenes_validas[] = [
            'id_orden' => (int)$row['id_orden'],
            'diagnostico' => $row['diagnostico'],
            'fecha_emision' => $row['fecha_emision'],
            'medico_nombre' => $row['medico_nombre'],
            'medico_matricula' => $row['medico_matricula'],
            'firma_verificada' => true
        ];
    }

    echo json_encode([
        'ok' => true,
        'ordenes' => $ordenes_validas,
        'total' => count($ordenes_validas)
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
}
?>
