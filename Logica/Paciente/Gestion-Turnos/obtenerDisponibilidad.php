<?php
// obtenerDisponibilidad.php - UNIFICADO para médicos, estudios y técnicos
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

try {
    require_once '../../../Persistencia/conexionBD.php';
    
    if (!isset($_POST['mes']) || !isset($_POST['anio'])) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    $mes = intval($_POST['mes']);
    $anio = intval($_POST['anio']);
    
    // Validar tipo de disponibilidad
    if (!isset($_POST['tipo']) || !in_array($_POST['tipo'], ['medico', 'estudio', 'tecnico'])) {
        throw new Exception('Tipo no válido');
    }
    
    $tipo = $_POST['tipo']; // 'medico', 'estudio' o 'tecnico'
    
    // Validar datos del mes y año
    if ($mes < 1 || $mes > 12 || $anio < 2020) {
        throw new Exception('Parámetros inválidos');
    }
    
    $conn = ConexionBD::conectar();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    // Calcular primer y último día del mes
    $primerDia = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
    $ultimoDia = date("Y-m-t", strtotime($primerDia));
    
    $diasDisponibles = [];
    
    // Lógica para médicos
    if ($tipo === 'medico') {
        if (!isset($_POST['id_medico'])) {
            throw new Exception('Falta id_medico');
        }
        
        $idMedico = intval($_POST['id_medico']);
        
        if ($idMedico <= 0) {
            throw new Exception('ID de médico inválido');
        }
        
        $sql = "SELECT DISTINCT DATE(a.fecha) as fecha
                FROM agenda a
                WHERE a.id_medico = ? 
                AND a.fecha BETWEEN ? AND ? 
                AND a.disponible = 1
                AND NOT EXISTS (
                    SELECT 1 FROM agenda_bloqueos ab 
                    WHERE ab.id_medico = a.id_medico 
                    AND ab.fecha = a.fecha 
                    AND ab.tipo = 'dia'
                )
                ORDER BY a.fecha";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $idMedico, $primerDia, $ultimoDia);
    } 
    // Lógica para estudios
    else if ($tipo === 'estudio') {
        if (!isset($_POST['id_estudio'])) {
            throw new Exception('Falta id_estudio');
        }
        
        $idEstudio = intval($_POST['id_estudio']);
        
        if ($idEstudio <= 0) {
            throw new Exception('ID de estudio inválido');
        }
        
        // Obtener técnicos que realizan este estudio
        $tecnicos = [];
        $stmtTec = $conn->prepare("SELECT id_tecnico FROM tecnico_estudio WHERE id_estudio = ?");
        $stmtTec->bind_param('i', $idEstudio);
        $stmtTec->execute();
        $resultTec = $stmtTec->get_result();
        while ($row = $resultTec->fetch_assoc()) {
            $tecnicos[] = $row['id_tecnico'];
        }
        $stmtTec->close();
        
        if (empty($tecnicos)) {
            ob_end_clean();
            echo json_encode([
                'success' => true,
                'dias_disponibles' => [],
                'debug' => [
                    'tipo' => $tipo,
                    'mes' => $mes,
                    'anio' => $anio,
                    'total_dias' => 0
                ]
            ]);
            exit;
        }
        
        // Consultar disponibilidad
        $placeholders = str_repeat('?,', count($tecnicos) - 1) . '?';
        $sql = "
            SELECT DISTINCT DATE(a.fecha) as fecha
            FROM agenda a
            WHERE a.id_tecnico IN ($placeholders) 
            AND a.fecha BETWEEN ? AND ? 
            AND a.disponible = 1
            AND NOT EXISTS (
                SELECT 1 FROM agenda_bloqueos ab 
                WHERE ab.id_tecnico = a.id_tecnico 
                AND ab.fecha = a.fecha 
                AND ab.tipo = 'dia'
            )
            AND (
                SELECT COUNT(*) FROM agenda a2 
                WHERE a2.id_tecnico = a.id_tecnico 
                AND a2.fecha = a.fecha 
                AND a2.disponible = 1
            ) > (
                SELECT COUNT(*) FROM turnos t 
                WHERE t.id_estudio = ? 
                AND t.fecha = a.fecha 
                AND t.id_estado IN (1,2,5)
            )
            ORDER BY a.fecha
        ";
        
        $stmt = $conn->prepare($sql);
        $params = array_merge($tecnicos, [$primerDia, $ultimoDia, $idEstudio]);
        $types = str_repeat('i', count($tecnicos)) . 'ssi';
        $stmt->bind_param($types, ...$params);
    } 
    // Lógica para técnicos
    else if ($tipo === 'tecnico') {
        if (!isset($_POST['id_tecnico'])) {
            throw new Exception('Falta id_tecnico');
        }
        
        $idTecnico = intval($_POST['id_tecnico']);
        
        if ($idTecnico <= 0) {
            throw new Exception('ID de técnico inválido');
        }

        $sql = "SELECT DISTINCT DATE(a.fecha) as fecha
                FROM agenda a
                WHERE a.id_tecnico = ? 
                AND a.fecha BETWEEN ? AND ? 
                AND a.disponible = 1
                AND NOT EXISTS (
                    SELECT 1 FROM agenda_bloqueos ab 
                    WHERE ab.id_tecnico = a.id_tecnico 
                    AND ab.fecha = a.fecha 
                    AND ab.tipo = 'dia'
                )
                ORDER BY a.fecha";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $idTecnico, $primerDia, $ultimoDia);
    }
    
    // Ejecutar consulta
    $stmt->execute();
    $result = $stmt->get_result();
    
    $hoy = date('Y-m-d'); // 🔒 Fecha actual para evitar días pasados
    while ($row = $result->fetch_assoc()) {
        $fecha = $row['fecha'];
        if ($fecha >= $hoy) {
            $diasDisponibles[] = $fecha;
        }
    }
    
    $stmt->close();
    $conn->close();
    
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'dias_disponibles' => $diasDisponibles,
        'debug' => [
            'tipo' => $tipo,
            'mes' => $mes,
            'anio' => $anio,
            'total_dias' => count($diasDisponibles)
        ]
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
