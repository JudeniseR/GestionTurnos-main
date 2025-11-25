<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


header('Content-Type: application/json');

try {
    require_once('../../../Persistencia/conexionBD.php');

    
    $id_estudio = (int)($_GET['id_estudio'] ?? 0);
    $mes = (int)($_GET['mes'] ?? date('n'));
    $anio = (int)($_GET['anio'] ?? date('Y'));
    
    if (!$id_estudio) {
        echo json_encode(['error' => 'Estudio requerido']);
        exit;
    }
    
    $conn = ConexionBD::conectar();
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $conn->set_charset('utf8mb4');
    
    $desde = sprintf('%04d-%02d-01', $anio, $mes);
    $hasta = date('Y-m-t', strtotime($desde));
    $hoy = date('Y-m-d');
    
    // Obtener ID del estado cancelado
    $id_cancelado = 0;
    $stmt = $conn->query("SELECT id_estado FROM estados WHERE nombre_estado = 'cancelado' LIMIT 1");
    if ($stmt && $row = $stmt->fetch_assoc()) {
        $id_cancelado = (int)$row['id_estado'];
    }
    
    $resultado = [];
    
    for ($dia = 1; $dia <= (int)date('t', strtotime($desde)); $dia++) {
        $fecha = sprintf('%04d-%02d-%02d', $anio, $mes, $dia);
        $estado = 'gris';
        $feriado_desc = null;
        
        // 1. Verificar si es fecha pasada
        if ($fecha < $hoy) {
            $estado = 'pasado';
            $resultado[] = ['dia' => $dia, 'estado' => $estado, 'feriado_desc' => $feriado_desc];
            continue;
        }
        
        // 2. Verificar feriados
        $stmt_fer = $conn->prepare("SELECT motivo FROM feriados WHERE fecha = ? LIMIT 1");
        if ($stmt_fer) {
            $stmt_fer->bind_param('s', $fecha);
            $stmt_fer->execute();
            $fer_result = $stmt_fer->get_result();
            if ($fer_row = $fer_result->fetch_assoc()) {
                $estado = 'azul';
                $feriado_desc = $fer_row['motivo'];
                $stmt_fer->close();
                $resultado[] = ['dia' => $dia, 'estado' => $estado, 'feriado_desc' => $feriado_desc];
                continue;
            }
            $stmt_fer->close();
        }
        
        // 3. Verificar si hay agenda para este estudio
        $stmt_agenda = $conn->prepare("
            SELECT COUNT(*) as cant 
            FROM agenda 
            WHERE id_estudio = ? AND fecha = ? AND disponible = 1
        ");
        
        if (!$stmt_agenda) {
            throw new Exception('Error al preparar consulta de agenda');
        }
        
        $stmt_agenda->bind_param('is', $id_estudio, $fecha);
        $stmt_agenda->execute();
        $agenda_row = $stmt_agenda->get_result()->fetch_assoc();
        $stmt_agenda->close();
        
        if ($agenda_row['cant'] == 0) {
            $estado = 'gris';
            $resultado[] = ['dia' => $dia, 'estado' => $estado, 'feriado_desc' => $feriado_desc];
            continue;
        }
        
        // 4. Verificar disponibilidad real de slots
$stmt_disponibilidad = $conn->prepare("
    SELECT 
        a.id_agenda,
        a.hora_inicio,
        a.hora_fin,
        EXISTS(
            SELECT 1 FROM turnos t
            WHERE t.id_estudio = ?
            AND t.fecha = ?
            AND t.hora BETWEEN a.hora_inicio AND a.hora_fin
            AND t.id_estado != ?
        ) as tiene_turno
    FROM agenda a
    WHERE a.id_estudio = ?
    AND a.fecha = ?
    AND a.disponible = 1
");

if (!$stmt_disponibilidad) {
    throw new Exception('Error al preparar consulta de disponibilidad');
}

$stmt_disponibilidad->bind_param('issis', $id_estudio, $fecha, $id_cancelado, $id_estudio, $fecha);
$stmt_disponibilidad->execute();
$disponibilidad_result = $stmt_disponibilidad->get_result();

$total_slots = 0;
$slots_ocupados = 0;

while ($slot = $disponibilidad_result->fetch_assoc()) {
    $total_slots++;
    if ($slot['tiene_turno']) {
        $slots_ocupados++;
    }
}
$stmt_disponibilidad->close();

// Determinar estado final
if ($total_slots == 0) {
    $estado = 'gris'; // Sin agenda
} elseif ($slots_ocupados >= $total_slots) {
    $estado = 'rojo'; // Totalmente ocupado
} else {
    $estado = 'verde'; // Disponible
}

$resultado[] = ['dia' => $dia, 'estado' => $estado, 'feriado_desc' => $feriado_desc];
    }
    
    echo json_encode($resultado);
    
} catch (Exception $e) {
    // Log del error
    error_log('Error en agenda_estado_estudios.php: ' . $e->getMessage());
    
    // Respuesta de error en formato JSON
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al procesar la solicitud',
        'message' => $e->getMessage()
    ]);
}