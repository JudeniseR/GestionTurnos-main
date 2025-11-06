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
    
    // Obtener técnicos del estudio
    $tecnicos = [];
    $stmtTec = $conn->prepare("SELECT id_tecnico FROM tecnico_estudio WHERE id_estudio = ?");
    $stmtTec->bind_param('i', $id_estudio);
    $stmtTec->execute();
    $resultTec = $stmtTec->get_result();
    while ($row = $resultTec->fetch_assoc()) {
        $tecnicos[] = $row['id_tecnico'];
    }
    $stmtTec->close();

    if (empty($tecnicos)) {
        ob_end_clean();
        echo json_encode(['success' => true, 'horarios' => []]);
        exit;
    }

    // Consulta: horarios disponibles para técnicos que hacen este estudio
    $placeholders = str_repeat('?,', count($tecnicos) - 1) . '?';
    $sql = "
        SELECT a.hora_inicio, a.hora_fin, a.id_tecnico AS id_recurso, 'tecnico' AS tipo
        FROM agenda a
        LEFT JOIN turnos t 
            ON t.id_estudio = ?          -- ✅ usamos id_estudio, no id_tecnico
            AND t.fecha = a.fecha 
            AND t.hora = a.hora_inicio 
            AND t.id_estado IN (1,2,5)
        WHERE a.id_tecnico IN ($placeholders)
        AND a.fecha = ?
        AND a.disponible = 1
        AND t.id_turno IS NULL
        ORDER BY a.hora_inicio
    ";

    // 💡 nota el orden de parámetros: id_estudio, tecnicos[], fecha
    $params = array_merge([$id_estudio], $tecnicos, [$fecha]);
    $types = 'i' . str_repeat('i', count($tecnicos)) . 's';

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $horarios[] = [
            'hora_inicio' => substr($row['hora_inicio'], 0, 5),
            'hora_fin'    => substr($row['hora_fin'], 0, 5),
            'id_recurso'  => $row['id_recurso'],
            'tipo'        => $row['tipo']
        ];
    }
    $stmt->close();
        
    } elseif ($tipo === 'medico') {
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
                'hora_inicio' => substr($row['hora_inicio'], 0, 5),
                'hora_fin' => substr($row['hora_fin'], 0, 5),
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