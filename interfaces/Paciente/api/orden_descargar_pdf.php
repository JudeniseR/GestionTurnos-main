<?php
/**
 * ========================================
 * API: Descargar orden como PDF usando FPDF
 * ========================================
 * Ruta: /interfaces/Paciente/api/orden_descargar_pdf.php
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    // ===== SEGURIDAD Y SESIÓN =====
    $rol_requerido = 1; // Paciente
    $verifPath = dirname(__DIR__, 3) . '/Logica/General/verificarSesion.php';
    if (file_exists($verifPath)) { require_once $verifPath; }
    
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    
    $id_paciente = $_SESSION['id_paciente_token'] ?? null;
    if (!$id_paciente) { die('No autorizado'); }

    // ===== CONEXIÓN BD =====
    $conexionPath = dirname(__DIR__, 3) . '/Persistencia/conexionBD.php';
    require_once $conexionPath;
    
    $conn = ConexionBD::conectar();
    $conn->set_charset('utf8mb4');

    // ===== PARÁMETROS =====
    $id_orden = (int)($_GET['id_orden'] ?? 0);
    if ($id_orden <= 0) { die('ID de orden inválido'); }

    // ===== OBTENER DATOS =====
    $sql = "
        SELECT 
            om.*,
            CONCAT(u.apellido, ', ', u.nombre) AS medico_nombre,
            m.matricula AS medico_matricula,
            CONCAT(up.apellido, ', ', up.nombre) AS paciente_nombre,
            p.nro_documento AS paciente_dni,
            p.fecha_nacimiento,
            p.telefono
        FROM ordenes_medicas om
        INNER JOIN medicos m ON m.id_medico = om.id_medico
        LEFT JOIN usuarios u ON u.id_usuario = m.id_usuario
        INNER JOIN pacientes p ON p.id_paciente = om.id_paciente
        LEFT JOIN usuarios up ON up.id_usuario = p.id_usuario
        WHERE om.id_orden = ? AND om.id_paciente = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $id_orden, $id_paciente);
    $stmt->execute();
    $result = $stmt->get_result();
    $orden = $result->fetch_assoc();
    $stmt->close();

    if (!$orden) { die('Orden no encontrada'); }

    // ===== DECODIFICAR ESTUDIOS =====
    $estudios_array = json_decode($orden['estudios_indicados'], true);

    // ===== CARGAR FPDF =====
    require_once dirname(__DIR__, 3) . '/librerias/fpdf/fpdf.php';

    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);

    // ===== HEADER =====
    $pdf->SetTextColor(102,126,234); // color azul
    $pdf->Cell(0,10,"📋 ORDEN MÉDICA",0,1,'C');
    $fecha_emision = date('d/m/Y H:i', strtotime($orden['fecha_emision']));
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,"Orden N° {$id_orden} | {$fecha_emision}",0,1,'C');
    $pdf->Ln(5);

    // ===== DATOS DEL PACIENTE Y MÉDICO =====
    $pdf->SetTextColor(0,0,0);
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(40,8,"PACIENTE:",0,0);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,$orden['paciente_nombre'],0,1);

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(40,8,"DNI:",0,0);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,$orden['paciente_dni'],0,1);

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(40,8,"MÉDICO:",0,0);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,$orden['medico_nombre'],0,1);

    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(40,8,"MATRÍCULA:",0,0);
    $pdf->SetFont('Arial','',12);
    $pdf->Cell(0,8,$orden['medico_matricula'],0,1);

    $pdf->Ln(5);

    // ===== DIAGNÓSTICO =====
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,"🩺 DIAGNÓSTICO",0,1);
    $pdf->SetFont('Arial','',12);
    $pdf->MultiCell(0,8,$orden['diagnostico']);
    $pdf->Ln(2);

    // ===== ESTUDIOS =====
    $pdf->SetFont('Arial','B',12);
    $pdf->Cell(0,8,"🔬 ESTUDIOS INDICADOS",0,1);
    $pdf->SetFont('Arial','',12);
    if (is_array($estudios_array)) {
        foreach ($estudios_array as $estudio) {
            $pdf->Cell(5,8,"",0,0);
            $pdf->Cell(0,8,"- ".($estudio['nombre'] ?? ''),0,1);
        }
    }
    $pdf->Ln(5);

    // ===== OBSERVACIONES =====
    if ($orden['observaciones']) {
        $pdf->SetFont('Arial','B',12);
        $pdf->Cell(0,8,"📝 OBSERVACIONES",0,1);
        $pdf->SetFont('Arial','',12);
        $pdf->MultiCell(0,8,$orden['observaciones']);
        $pdf->Ln(5);
    }

// ===== FIRMA =====
$pdf->Ln(5);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0,8,"Firma Digital",0,1,'C');

$pdf->SetFont('Arial','',12);
$pdf->Cell(0,6,$orden['medico_nombre'],0,1,'C'); // Nombre del médico
$pdf->Cell(0,6,"Matrícula: {$orden['medico_matricula']}",0,1,'C');

// Fecha de firma: usamos la fecha de emisión de la orden
$fecha_firma = date('d/m/Y, h:i a', strtotime($orden['fecha_emision']));
$pdf->Cell(0,6,"Firmado digitalmente el {$fecha_firma}",0,1,'C');

$pdf->Ln(5);



    // ===== FOOTER =====
    $pdf->SetFont('Arial','',8);
    $pdf->SetTextColor(100,100,100);
    $pdf->MultiCell(0,5,"Sistema de Gestión de Turnos Médicos\nDocumento generado electrónicamente | {$fecha_emision}\nEste documento ha sido firmado digitalmente. La autenticidad puede verificarse en el sistema.",0,'C');

    // ===== ENVIAR PDF =====
    $pdf->Output("I","orden_medica_{$id_orden}.pdf");

} catch (Throwable $e) {
    die('Error al generar PDF: ' . $e->getMessage());
}
?>


