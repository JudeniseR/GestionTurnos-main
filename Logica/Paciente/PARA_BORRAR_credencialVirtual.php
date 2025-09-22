<?php
// MOSTRAR ERRORES
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Evitar iniciar sesión más de una vez
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Incluir archivos necesarios
require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/fpdf/fpdf.php');
require_once('../../librerias/phpqrcode/qrlib.php');

// Conexión a la base de datos
$conn = ConexionBD::conectar();

// Función para obtener los datos del paciente por ID
function obtenerDatosPaciente($conn, $id_paciente) {
    $stmt = $conn->prepare("SELECT p.nombre, p.apellido, p.numero_afiliado, a.tipo_beneficiario, a.seccional, a.estado 
                            FROM pacientes p
                            JOIN afiliados a ON p.id_afiliado = a.id 
                            WHERE p.id = ?");
    if (!$stmt) {
        throw new Exception("Error en la consulta de datos: " . $conn->error);
    }

    $stmt->bind_param("i", $id_paciente);
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

// Función para obtener los datos del paciente por token
function obtenerDatosPacientePorToken($conn, $token) {
    $stmt = $conn->prepare("SELECT p.nombre, p.apellido, p.numero_afiliado, a.tipo_beneficiario, a.seccional, a.estado 
                            FROM pacientes p
                            JOIN afiliados a ON p.id_afiliado = a.id 
                            WHERE p.token_qr = ?"); // Usamos el campo token_qr aquí
    if (!$stmt) {
        throw new Exception("Error en la consulta de datos: " . $conn->error);
    }

    $stmt->bind_param("s", $token); // Usamos 's' para pasar el token como string
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows !== 1) {
        $stmt->close();
        return null; // Si no se encuentra el token, retornamos null
    }

    $stmt->bind_result($nombre, $apellido, $numero_afiliado, $tipo_beneficiario, $seccional, $estado);
    $stmt->fetch();
    $stmt->close();

    return compact('nombre', 'apellido', 'numero_afiliado', 'tipo_beneficiario', 'seccional', 'estado');
}

// Función para mostrar los datos del paciente
function mostrarDatosPaciente($datos) {
    echo "<p><strong>Nombre:</strong> " . ucwords(strtolower($datos['nombre'])) . "</p>";
    echo "<p><strong>Apellido:</strong> " . ucwords(strtolower($datos['apellido'])) . "</p>";
    echo "<p><strong>Número de Afiliado:</strong> {$datos['numero_afiliado']}</p>";
    echo "<p><strong>Tipo de Beneficiario:</strong> " . ucwords(strtolower($datos['tipo_beneficiario'])) . "</p>";
    echo "<p><strong>Seccional:</strong> " . ucwords(strtolower($datos['seccional'])) . "</p>";
    echo "<p><strong>Estado:</strong> " . ucwords(strtolower($datos['estado'])) . "</p>";
}

function descargarCredencial($conn, $token) {
    // Obtener los datos del paciente
    $datos = obtenerDatosPacientePorToken($conn, $token);
    if (!$datos) {
        die("❌ No se encontraron los datos del paciente.");
    }

    // Crear el PDF
    require_once('../../librerias/fpdf/fpdf.php');
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Credencial Virtual del Paciente', 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 12);

    // Añadir datos del paciente al PDF
    $pdf->Cell(0, 10, mb_convert_encoding("Nombre: " . ucwords(strtolower($datos['nombre'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Apellido: " . ucwords(strtolower($datos['apellido'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Número de Afiliado: {$datos['numero_afiliado']}", 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Tipo de Beneficiario: " . ucwords(strtolower($datos['tipo_beneficiario'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Seccional: " . ucwords(strtolower($datos['seccional'])), 'ISO-8859-1', 'UTF-8'), 0, 1);
    $pdf->Cell(0, 10, mb_convert_encoding("Estado: " . ucwords(strtolower($datos['estado'])), 'ISO-8859-1', 'UTF-8'), 0, 1);

    $pdf->Ln(10);

    // Salida del PDF
    $pdf->Output('D', 'credencial_virtual.pdf');
    exit;
}

if (isset($_GET['descargar']) && $_GET['descargar'] == '1' && isset($_GET['token'])) {
    descargarCredencial($conn, $_GET['token']);
}

?>

