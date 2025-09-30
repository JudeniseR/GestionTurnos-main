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
 * Trae datos visibles de la credencial a partir del token QR.
 * - Nombre/Apellido: desde `usuario` (vía pacientes.id_usuario)
 * - Datos de afiliación: desde `afiliados` (link por documento afiliados.numero_documento = pacientes.nro_documento)
 */
function obtenerDatosPacientePorToken($conn, $token) {
    $sql = "SELECT 
                u.nombre,
                u.apellido,
                a.numero_afiliado,
                a.tipo_beneficiario,
                a.seccional,
                a.estado
            FROM pacientes p
            JOIN usuarios u 
                  ON p.id_usuario = u.id_usuario
            LEFT JOIN afiliados a 
                  ON a.numero_documento = p.nro_documento
            WHERE p.token_qr = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        return null;
    }

    $stmt->bind_result($nombre, $apellido, $numero_afiliado, $tipo_beneficiario, $seccional, $estado);
    $stmt->fetch();
    $stmt->close();

    return compact('nombre', 'apellido', 'numero_afiliado', 'tipo_beneficiario', 'seccional', 'estado');
}

/**
 * Genera y descarga el PDF de la credencial (con QR embebido)
 */
function descargarCredencial($datos, $token) {
    // Construir la URL para el QR
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    $urlBase = $protocol . "://" . $host;
    $qrData = $urlBase . "/interfaces/Paciente/verCredencial.php?token=" . urlencode($token);

    // Evitar que cualquier warning previo rompa los headers del PDF
    if (ob_get_length()) { @ob_end_clean(); }

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, latin1("Credencial Virtual del Paciente"), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, latin1("Nombre: " . titlecase($datos['nombre'] ?? '')), 0, 1);
    $pdf->Cell(0, 10, latin1("Apellido: " . titlecase($datos['apellido'] ?? '')), 0, 1);
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
    $pdf->Output('D', 'credencial_virtual.pdf');
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
$datos = obtenerDatosPacientePorToken($conn, $token);
if (!$datos) {
    die("❌ Token inválido.");
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
    <title>Credencial Virtual</title>
    <link rel="stylesheet" href="../../css/credencial.css">
</head>
<body>
    <div class="credencial">
        <!-- Logo -->
        <div class="header">
            <img src="../../assets/img/osuomra_logo.png" alt="OSUOMRA Logo">
        </div>

        <!-- Contenido principal: datos + QR -->
        <div class="contenido">
            <div class="datos">
                <p><strong>Nombre:</strong> <?= htmlspecialchars(titlecase($datos['nombre'] ?? '')) ?></p>
                <p><strong>Apellido:</strong> <?= htmlspecialchars(titlecase($datos['apellido'] ?? '')) ?></p>
                <p><strong>Número de Afiliado:</strong> <?= htmlspecialchars(vstr($datos['numero_afiliado'] ?? '-')) ?></p>
                <p><strong>Tipo de Beneficiario:</strong> <?= htmlspecialchars(titlecase($datos['tipo_beneficiario'] ?? '-')) ?></p>
                <p><strong>Seccional:</strong> <?= htmlspecialchars(titlecase($datos['seccional'] ?? '-')) ?></p>
                <p><strong>Estado:</strong> <?= htmlspecialchars(titlecase($datos['estado'] ?? '-')) ?></p>
                
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
                    $qrData = $urlBase . "/interfaces/Paciente/verCredencial.php?token=" . urlencode($token);

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
    </div>
</body>
</html>
