<?php
// obtenerHorariosDia.php - Obtiene horarios disponibles para estudio o médico en una fecha específica
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

ob_start();

try {
    require_once '../../../Persistencia/conexionBD.php';
    
    if (!isset($_POST['tipo']) || !isset($_POST['fecha'])) {
        throw new Exception('Faltan parámetros requeridos');
    }
    
    $tipo = $_POST['tipo'];
    $fecha = $_POST['fecha'];
    
    if (!$fecha) {
        throw new Exception('Fecha inválida');
    }
    
    $conn = ConexionBD::conectar();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $horarios = [];
    
    if ($tipo === 'estudio') {
    if (!isset($_POST['id_estudio'])) {
        throw new Exception('Falta id_estudio para tipo estudio');
    }
    $id_estudio = intval($_POST['id_estudio']);

    // Obtener el primer técnico del estudio (igual que id_recurso para médicos)
    $qRecurso = $conn->prepare("SELECT id_tecnico AS id_recurso FROM tecnico_estudio WHERE id_estudio = ? LIMIT 1");
    $qRecurso->bind_param('i', $id_estudio);
    $qRecurso->execute();
    $resultRec = $qRecurso->get_result();
    $id_recurso = $resultRec->fetch_assoc()['id_recurso'] ?? null;
    $qRecurso->close();

    if (!$id_recurso) {
        ob_end_clean();
        echo json_encode(['success' => true, 'horarios' => []]);
        exit;
    }

    // Consulta para agenda del técnico
    $sql = "
        SELECT a.hora_inicio, a.id_tecnico AS id_recurso, 'tecnico' AS tipo
        FROM agenda a
        LEFT JOIN turnos t 
            ON t.id_estudio = ?
            AND t.fecha = a.fecha
            AND t.hora = a.hora_inicio
            AND t.id_estado IN (1,2,5)
        WHERE a.id_tecnico = ?
        AND a.fecha = ?
        AND a.disponible = 1
        AND t.id_turno IS NULL
        ORDER BY a.hora_inicio
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iis', $id_estudio, $id_recurso, $fecha);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $horarios[] = [
            'hora_inicio' => substr($row['hora_inicio'], 0, 5), // solo hora_inicio
            'id_recurso'  => $row['id_recurso'],
            'tipo'        => $row['tipo']
        ];
    }
    $stmt->close();
}
 elseif ($tipo === 'medico') {
        // Nueva lógica para médicos
        if (!isset($_POST['id_medico'])) {
            throw new Exception('Falta id_medico para tipo medico');
        }
        $id_medico = intval($_POST['id_medico']);
        
        // Obtener id_recurso via medico_recursos
        $qRecurso = $conn->prepare("SELECT id_recurso FROM medico_recursos WHERE id_medico = ? LIMIT 1");
        $qRecurso->bind_param('i', $id_medico);
        $qRecurso->execute();
        $resultRec = $qRecurso->get_result();
        $id_recurso = $resultRec->fetch_assoc()['id_recurso'] ?? null;
        $qRecurso->close();
        
        if (!$id_recurso) {
            ob_end_clean();
            echo json_encode(['success' => true, 'horarios' => []]);
            exit;
        }
        
        // Consulta para médicos
        $sql = "
            SELECT a.hora_inicio, a.hora_fin, a.id_recurso, 'medico' AS tipo
            FROM agenda a
            LEFT JOIN turnos t ON t.id_medico = a.id_medico 
                               AND t.fecha = a.fecha 
                               AND t.hora = a.hora_inicio 
                               AND t.id_estado IN (1,2,5)
            WHERE a.id_recurso = ?
            AND a.fecha = ?
            AND a.disponible = 1
            AND t.id_turno IS NULL
            ORDER BY a.hora_inicio
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('is', $id_recurso, $fecha);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
    $horarios[] = [
        'hora_inicio' => substr($row['hora_inicio'], 0, 5), // Solo devolver hora_inicio
        // 'hora_fin' se puede omitir o mantener para uso interno
        'id_recurso' => $row['id_recurso'],
        'tipo' => $row['tipo']
    ];
}
        $stmt->close();
        
    } else {
        throw new Exception('Tipo inválido: debe ser "estudio" o "medico"');
    }
    
    $conn->close();
    
    ob_end_clean();
    
    echo json_encode([
        'success' => true,
        'horarios' => $horarios
    ]);
    
} catch (Exception $e) {
    ob_end_clean();
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>