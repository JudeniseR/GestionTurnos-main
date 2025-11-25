<?php
// Mostrar errores para desarrollo
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';
require_once '../../../Logica/General/envioNotif.php';

$conn = ConexionBD::conectar();

// Validar sesión
$paciente_id = $_SESSION['id_paciente_token'] ?? null;
if (!$paciente_id) {
    die("Debe iniciar sesión.");
}

// Validar entrada
if (!isset($_POST['turno_id'])) {
    die("ID de turno no recibido.");
}

$turno_id = intval($_POST['turno_id']);

// Verificar que el turno existe, pertenece al paciente y obtener información
// IMPORTANTE: Agregamos t.id_medico y t.id_recurso del turno
$sql_info = "
    SELECT 
        t.id_turno,
        t.id_medico,
        t.id_recurso,
        t.id_estudio,
        t.fecha,
        t.hora,
        t.copago,        
        u_p.nombre       AS paciente_nombre,
        u_p.apellido     AS paciente_apellido,
        u_p.email        AS paciente_email,        
        u_m.nombre       AS medico_nombre,
        u_m.apellido     AS medico_apellido,
        u_m.email        AS medico_email,        
        esp.nombre_especialidad AS especialidad,        
        e.nombre AS nombre_estudio,
        r.nombre     AS recurso_nombre,
        s.nombre     AS sede_nombre,
        s.direccion  AS sede_direccion        
    FROM turnos t        
        JOIN pacientes p       ON t.id_paciente = p.id_paciente
        JOIN usuarios u_p      ON p.id_usuario = u_p.id_usuario
        LEFT JOIN medicos m    ON t.id_medico = m.id_medico
        LEFT JOIN usuarios u_m ON m.id_usuario = u_m.id_usuario
        LEFT JOIN medico_especialidad me ON m.id_medico = me.id_medico
        LEFT JOIN especialidades esp     ON me.id_especialidad = esp.id_especialidad
        LEFT JOIN estudios e   ON t.id_estudio = e.id_estudio
        LEFT JOIN recursos r   ON t.id_recurso = r.id_recurso
        LEFT JOIN sedes s      ON r.id_sede = s.id_sede
    WHERE 
        t.id_turno = ? 
        AND t.id_paciente = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql_info);
$stmt->bind_param("ii", $turno_id, $paciente_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("El turno no existe o no pertenece al paciente.");
}

$turno = $result->fetch_assoc();
$id_medico = $turno['id_medico'];
$id_recurso = $turno['id_recurso'];
$id_estudio = $turno['id_estudio'];
$fecha = $turno['fecha'];
$hora = $turno['hora'];

// Cancelar el turno (id_estado = 4)
$sql_cancelar = "UPDATE turnos SET id_estado = 4 WHERE id_turno = ?";
$stmt = $conn->prepare($sql_cancelar);
$stmt->bind_param("i", $turno_id);
$okTurno = $stmt->execute();

// Liberar el turno en la tabla agenda
// La lógica depende de si es turno médico o de estudio/recurso
$okAgenda = false;

if (!empty($id_medico)) {
    // Es un turno MÉDICO - liberar por id_medico
    $sql_liberar = "UPDATE agenda 
                    SET disponible = 1 
                    WHERE id_medico = ? 
                    AND fecha = ? 
                    AND hora_inicio = ?";
    $stmt = $conn->prepare($sql_liberar);
    $stmt->bind_param("iss", $id_medico, $fecha, $hora);
    $okAgenda = $stmt->execute();
    
} elseif (!empty($id_recurso)) {
    // Es un turno de ESTUDIO/RECURSO - liberar por id_recurso
    $sql_liberar = "UPDATE agenda 
                    SET disponible = 1 
                    WHERE id_recurso = ? 
                    AND fecha = ? 
                    AND hora_inicio = ?";
    $stmt = $conn->prepare($sql_liberar);
    $stmt->bind_param("iss", $id_recurso, $fecha, $hora);
    $okAgenda = $stmt->execute();
}

if ($okTurno && $okAgenda) {
    // Enviar notificación de cancelación
    $datosCorreo = [
        'nombre'       => $turno['paciente_nombre'],
        'apellido'     => $turno['paciente_apellido'],
        'email'        => $turno['paciente_email'],
        'fecha'        => $turno['fecha'],
        'hora'         => $turno['hora'],
        'tipo'         => !empty($turno['id_estudio']) ? 'estudio' : 'medico',
        'detalle'      => !empty($turno['id_estudio'])
                            ? $turno['nombre_estudio']
                            : $turno['especialidad'],
        'profesional'  => !empty($turno['id_estudio'])
                            ? $turno['recurso_nombre']
                            : $turno['medico_nombre'].' '.$turno['medico_apellido'],
        'direccion'    => $turno['sede_direccion']
    ];

    enviarNotificacion('cancelar_turno', $datosCorreo);    

    header("Location: ../../../interfaces/Paciente/Gestion/misTurnos.php?cancelado=1");
    exit;
} else {
    echo "Error al cancelar y/o liberar el turno.";
    // Para debug, puedes agregar:
    echo "<br>Turno cancelado: " . ($okTurno ? "SÍ" : "NO");
    echo "<br>Agenda liberada: " . ($okAgenda ? "SÍ" : "NO");
    echo "<br>ID Médico: " . ($id_medico ?? 'NULL');
    echo "<br>ID Recurso: " . ($id_recurso ?? 'NULL');
}

?>