<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/fpdf/fpdf.php');
require_once('../../librerias/phpqrcode/qrlib.php');

// ================= Helpers =================
function vstr($v, $fallback='-') {
    return ($v === null || $v === '') ? $fallback : (string)$v;
}
function titlecase($v) {
    $s = vstr($v, '');
    if ($s === '') return '-';
    return mb_convert_case(mb_strtolower($s, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
}
function latin1($utf) {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$utf);
}

// Conexión
$conn = ConexionBD::conectar();

/**
 * Trae datos del afiliado por token QR
 * - Para TITULARES: nombre/apellido desde usuarios
 * - Para MENORES: nombre/apellido desde afiliados (porque no tienen usuario)
 */
function obtenerDatosAfiliadoPorToken($conn, $token) {
    $sql = "SELECT 
                p.id_paciente,
                p.id_usuario,
                p.id_titular,
                p.nro_documento,
                p.fecha_nacimiento,
                -- Datos del usuario (solo para titulares)
                u.nombre AS nombre_usuario,
                u.apellido AS apellido_usuario,
                -- Datos del afiliado (buscar por el documento del paciente)
                a.nombre AS nombre_afiliado,
                a.apellido AS apellido_afiliado,
                a.numero_afiliado,
                a.tipo_beneficiario,
                a.seccional,
                a.estado
            FROM pacientes p
            LEFT JOIN usuarios u 
                ON p.id_usuario = u.id_usuario
            LEFT JOIN afiliados a 
                ON a.numero_documento = p.nro_documento
            WHERE p.token_qr = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        return null;
    }

    $row = $result->fetch_assoc();
    $stmt->close();

    // DEBUG: Mostrar qué datos se están obteniendo
    //echo "<pre style='background:#f0f0f0;padding:10px;margin:10px;border:1px solid #ccc'>";
    //echo "DEBUG - Datos obtenidos:\n";
    //echo "Documento paciente: " . $row['nro_documento'] . "\n";
    //echo "id_usuario: " . ($row['id_usuario'] ?? 'NULL') . "\n";
    //echo "id_titular: " . ($row['id_titular'] ?? 'NULL') . "\n";
    //echo "nombre_usuario: " . ($row['nombre_usuario'] ?? 'NULL') . "\n";
    //echo "nombre_afiliado: " . ($row['nombre_afiliado'] ?? 'NULL') . "\n";
    //echo "</pre>";

    // Verificar si es menor: no tiene id_usuario pero tiene id_titular
    $es_menor = ($row['id_usuario'] === null && $row['id_titular'] !== null);
    
    //echo "<pre style='background:#ffe;padding:10px;margin:10px;border:1px solid #cc0'>";
    //echo "¿Es menor? " . ($es_menor ? 'SÍ' : 'NO') . "\n";
    //echo "</pre>";
    
    // Decidir qué datos usar
    $nombre = $es_menor ? $row['nombre_afiliado'] : $row['nombre_usuario'];
    $apellido = $es_menor ? $row['apellido_afiliado'] : $row['apellido_usuario'];

    return [
        'id_paciente' => $row['id_paciente'],
        'id_usuario' => $row['id_usuario'],
        'id_titular' => $row['id_titular'],
        'nro_documento' => $row['nro_documento'],
        'fecha_nacimiento' => $row['fecha_nacimiento'],
        'nombre' => $nombre,
        'apellido' => $apellido,
        'numero_afiliado' => $row['numero_afiliado'],
        'tipo_beneficiario' => $row['tipo_beneficiario'],
        'seccional' => $row['seccional'],
        'estado' => $row['estado'],
        'es_menor' => $es_menor
    ];
}

/**
 * Genera y descarga el PDF de la credencial (con QR embebido)
 */
function descargarCredencial($datos, $token) {
    // Construir la URL para el QR
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $urlBase = $protocol . "://" . $host;
    $qrData = $urlBase . "/interfaces/Paciente/verCredencialAfiliado.php?token=" . urlencode($token);

    // Evitar que cualquier warning previo rompa los headers del PDF
    if (ob_get_length()) { @ob_end_clean(); }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    
    $titulo = $datos['es_menor'] 
        ? "Credencial Virtual - Afiliado Menor" 
        : "Credencial Virtual - Titular";
    
    $pdf->Cell(0, 10, latin1($titulo), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, latin1("Nombre: " . titlecase($datos['nombre'] ?? '')), 0, 1);
    $pdf->Cell(0, 10, latin1("Apellido: " . titlecase($datos['apellido'] ?? '')), 0, 1);
    $pdf->Cell(0, 10, latin1("Número de Documento: " . vstr($datos['nro_documento'] ?? '-')), 0, 1);
    $pdf->Cell(0, 10, latin1("Número de Afiliado: " . vstr($datos['numero_afiliado'] ?? '-')), 0, 1);
    $pdf->Cell(0, 10, latin1("Tipo de Beneficiario: " . titlecase($datos['tipo_beneficiario'] ?? '-')), 0, 1);
    $pdf->Cell(0, 10, latin1("Seccional: " . titlecase($datos['seccional'] ?? '-')), 0, 1);
    $pdf->Cell(0, 10, latin1("Estado: " . titlecase($datos['estado'] ?? '-')), 0, 1);

    // --- QR ---
    if (!extension_loaded('gd')) {
        $pdf->Ln(5);
        $pdf->SetTextColor(200,0,0);
        $pdf->Cell(0, 10, latin1("⚠ No se pudo generar el QR (extensión GD no habilitada)."), 0, 1);
        $pdf->SetTextColor(0,0,0);
    } else {
        $qrTemp = sys_get_temp_dir() . '/qr_' . uniqid('', true) . '.png';
        QRcode::png($qrData, $qrTemp, QR_ECLEVEL_L, 4);
        $pdf->Image($qrTemp, $pdf->GetX(), $pdf->GetY() + 10, 40, 40, 'PNG');
        if (file_exists($qrTemp)) { @unlink($qrTemp); }
    }

    if (ob_get_length()) { @ob_end_clean(); }
    
    $nombreArchivo = $datos['es_menor'] 
        ? 'credencial_menor_' . $datos['nro_documento'] . '.pdf'
        : 'credencial_titular_' . $datos['nro_documento'] . '.pdf';
    
    $pdf->Output('D', $nombreArchivo);
    exit;
}

$token = null;

// 1) Si viene por URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
}
// 2) Si no viene, lo tomamos desde sesión (id_paciente_token → token_qr)
elseif (isset($_SESSION['id_paciente_token'])) {
    $id_paciente = $_SESSION['id_paciente_token'];
    $stmt = $conn->prepare("SELECT token_qr FROM pacientes WHERE id_paciente = ?");
    $stmt->bind_param("i", $id_paciente);
    $stmt->execute();
    $result = $stmt->get_result();
    $paciente = $result->fetch_assoc();
    $stmt->close();
    if ($paciente) {
        $token = $paciente['token_qr'];
    }
}

if (!$token) {
    die("❌ No se proporcionó un token válido.");
}

// Buscar datos por token
$datos = obtenerDatosAfiliadoPorToken($conn, $token);
if (!$datos) {
    die("❌ Token inválido o no se encontraron datos del afiliado.");
}

// Verificar que el nombre y apellido no estén vacíos
if (empty($datos['nombre']) || empty($datos['apellido'])) {
    die("❌ Error: Faltan datos del afiliado (nombre/apellido). Verifique que la tabla 'afiliados' tenga estos campos completados.");
}

// Si viene para descargar PDF
if (isset($_GET['descargar']) && $_GET['descargar'] == '1') {
    descargarCredencial($datos, $token);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credencial Virtual <?= $datos['es_menor'] ? 'Afiliado Menor' : 'Titular' ?></title>
    <link rel="stylesheet" href="../../css/credencial.css">
</head>
<body>
    <div class="credencial">
        <!-- Logo -->
        <div class="header">
            <img src="../../assets/img/osuomra_logo.png" alt="OSUOMRA Logo">
            <?php if ($datos['es_menor']): ?>
                <span class="badge-menor">Afiliado Menor</span>
            <?php endif; ?>
        </div>

        <!-- Contenido principal: datos + QR -->
        <div class="contenido">
            <div class="datos">
                <p><strong>Nombre:</strong> <?= htmlspecialchars(titlecase($datos['nombre'] ?? '')) ?></p>
                <p><strong>Apellido:</strong> <?= htmlspecialchars(titlecase($datos['apellido'] ?? '')) ?></p>
                <p><strong>Documento:</strong> <?= htmlspecialchars(vstr($datos['nro_documento'] ?? '-')) ?></p>
                <p><strong>Número de Afiliado:</strong> <?= htmlspecialchars(vstr($datos['numero_afiliado'] ?? '-')) ?></p>
                <p><strong>Tipo de Beneficiario:</strong> <?= htmlspecialchars(titlecase($datos['tipo_beneficiario'] ?? '-')) ?></p>
                <p><strong>Seccional:</strong> <?= htmlspecialchars(titlecase($datos['seccional'] ?? '-')) ?></p>
                <p><strong>Estado:</strong> <?= htmlspecialchars(titlecase($datos['estado'] ?? '-')) ?></p>
                
                <?php if ($datos['fecha_nacimiento']): ?>
                    <p><strong>Fecha de Nacimiento:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($datos['fecha_nacimiento']))) ?></p>
                <?php endif; ?>
                
                <a href="?token=<?= urlencode($token) ?>&descargar=1" class="descargar">
                    ⬇ Descargar Credencial
                </a>
            </div>

            <div class="qr-container">
                <?php 
                    // QR directo en memoria (sin archivos en disco)
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $urlBase = $protocol . "://" . $host;
                    $qrData = $urlBase . "/interfaces/Paciente/verCredencialAfiliado.php?token=" . urlencode($token);

                    if (!extension_loaded('gd')) {
                        echo '<div style="color:#b00">⚠ No se pudo generar el QR (extensión GD no habilitada).</div>';
                    } else {
                        ob_start();
                        QRcode::png($qrData, null, QR_ECLEVEL_L, 4);
                        $qrImage = ob_get_clean();
                        echo '<img src="data:image/png;base64,' . base64_encode($qrImage) . '" alt="QR" class="qr">';
                    }
                ?>
            </div>
        </div>
        
        <?php if ($datos['es_menor']): ?>
            <div class="footer-info">
                <small>⚠ Esta credencial pertenece a un afiliado menor de edad</small>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>