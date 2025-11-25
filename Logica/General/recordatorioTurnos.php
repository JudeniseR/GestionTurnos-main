<?php
// Mostrar errores para depuración
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('/opt/bitnami/apache/htdocs/Persistencia/conexionBD.php');
require_once('/opt/bitnami/apache/htdocs/Logica/General/envioNotif.php');

$conn = ConexionBD::conectar();

// Obtener fecha/hora actual y +48hs
$ahora = date('Y-m-d H:i:s');
$hasta48h = date('Y-m-d H:i:s', strtotime('+48 hours'));

// Consulta: turnos pendientes dentro de las próximas 48hs
$sql = "
    SELECT 
        t.id_turno, t.fecha, t.hora, t.id_medico, t.id_estudio,
        up.nombre AS paciente_nombre, up.apellido AS paciente_apellido, up.email AS paciente_email,
        um.nombre AS medico_nombre, um.apellido AS medico_apellido,
        esp.nombre_especialidad AS especialidad,
        e.nombre AS estudio_nombre,
        r.nombre AS recurso_nombre,
        s.direccion AS sede_direccion
    FROM turnos t
    JOIN pacientes p ON p.id_paciente = t.id_paciente
    JOIN usuarios up ON up.id_usuario = p.id_usuario
    LEFT JOIN medicos m ON m.id_medico = t.id_medico
    LEFT JOIN usuarios um ON um.id_usuario = m.id_usuario
    LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
    LEFT JOIN especialidades esp ON esp.id_especialidad = me.id_especialidad
    LEFT JOIN estudios e ON e.id_estudio = t.id_estudio
    JOIN recursos r ON r.id_recurso = t.id_recurso
    JOIN sedes s ON s.id_sede = r.id_sede
    WHERE t.id_estado = 1
      AND CONCAT(t.fecha, ' ', t.hora) BETWEEN ? AND ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $ahora, $hasta48h);
$stmt->execute();
$result = $stmt->get_result();


if ($result->num_rows === 0) {
    error_log("[RecordatorioTurnos] No hay turnos para enviar.");
    exit;
}

while ($turno = $result->fetch_assoc()) {

    // Preparar datos para el correo
    $datosCorreo = [
        'nombre'       => $turno['paciente_nombre'],
        'apellido'     => $turno['paciente_apellido'],
        'email'        => $turno['paciente_email'],
        'fecha'        => $turno['fecha'],
        'hora'         => $turno['hora'],
        'tipo'         => !empty($turno['id_estudio']) ? 'estudio' : 'médico',
        'detalle'      => !empty($turno['id_estudio'])
                            ? $turno['estudio_nombre']
                            : $turno['especialidad'],
        'profesional'  => !empty($turno['id_estudio'])
                            ? $turno['recurso_nombre']
                            : $turno['medico_nombre'].' '.$turno['medico_apellido'],
        'direccion'    => $turno['sede_direccion']
    ];

    enviarNotificacion('recordatorioTurno', $datosCorreo);
}

error_log("[RecordatorioTurnos] Recordatorios enviados: {$result->num_rows}");