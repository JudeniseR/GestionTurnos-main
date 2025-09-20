<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/fpdf/fpdf.php');
require_once('../../librerias/phpqrcode/qrlib.php');

// Conexión
$conn = ConexionBD::conectar();

function obtenerDatosPacientePorToken($conn, $token) {
    $stmt = $conn->prepare("SELECT p.nombre, p.apellido, p.numero_afiliado, 
                                   a.tipo_beneficiario, a.seccional, a.estado 
                            FROM pacientes p
                            JOIN afiliados a ON p.id_afiliado = a.id 
                            WHERE p.token_qr = ?");
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

function descargarCredencial($datos) {
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, mb_convert_encoding("Credencial Virtual del Paciente", 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, mb_convert_encoding("Nombre: " . ucwords(strtolower($datos['nombre'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Apellido: " . ucwords(strtolower($datos['apellido'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Número de Afiliado: {$datos['numero_afiliado']}", 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Tipo de Beneficiario: " . ucwords(strtolower($datos['tipo_beneficiario'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Seccional: " . ucwords(strtolower($datos['seccional'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Estado: " . ucwords(strtolower($datos['estado'])), 'ISO-8859-1', 'UTF-8'), 0, 1);

    $pdf->Output('D', 'credencial_virtual.pdf');
    exit;
}

/** 🔹 Resolución del token */
$token = null;

// Si viene por URL
if (isset($_GET['token'])) {
    $token = $_GET['token'];
}
// Si no viene, tratamos de sacarlo de sesión
elseif (isset($_SESSION['id_paciente_token'])) {
    $id_paciente = $_SESSION['id_paciente_token'];
    $stmt = $conn->prepare("SELECT token_qr FROM pacientes WHERE id = ?");
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

// Buscar datos
$datos = obtenerDatosPacientePorToken($conn, $token);
if (!$datos) {
    die("❌ Token inválido.");
}

// 🔹 Si viene para descargar PDF
if (isset($_GET['descargar']) && $_GET['descargar'] == '1') {
    descargarCredencial($datos);
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
                <p><strong>Nombre:</strong> <?= ucwords(strtolower($datos['nombre'])) ?></p>
                <p><strong>Apellido:</strong> <?= ucwords(strtolower($datos['apellido'])) ?></p>
                <p><strong>Número de Afiliado:</strong> <?= $datos['numero_afiliado'] ?></p>
                <p><strong>Tipo de Beneficiario:</strong> <?= ucwords(strtolower($datos['tipo_beneficiario'])) ?></p>
                <p><strong>Seccional:</strong> <?= ucwords(strtolower($datos['seccional'])) ?></p>
                <p><strong>Estado:</strong> <?= ucwords(strtolower($datos['estado'])) ?></p>
                
                <a href="?token=<?= urlencode($token) ?>&descargar=1" class="descargar">
                    ⬇ Descargar Credencial
                </a>
            </div>

            <div class="qr-container">
                <?php 
                    // Construcción dinámica de la URL
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $host = $_SERVER['HTTP_HOST'];
                    $urlBase = $protocol . "://" . $host;

                    // Ruta temporal para el QR
                    $qrTemp = sys_get_temp_dir() . "/temp_qr.png";
                    
                    // Información que contendrá el QR
                    $qrData = $urlBase . "/interfaces/Paciente/verCredencial.php?token=" . urlencode($token);

                    // Generar QR en archivo temporal
                    QRcode::png($qrData, $qrTemp, QR_ECLEVEL_L, 4);

                    // Mostrar QR como imagen embebida en base64
                    echo '<img src="data:image/png;base64,' . base64_encode(file_get_contents($qrTemp)) . '" alt="QR" class="qr">';
                ?>
            </div>
        </div>
    </div>
</body>
</html>