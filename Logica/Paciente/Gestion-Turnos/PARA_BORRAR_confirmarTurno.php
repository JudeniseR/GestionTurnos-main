<?php
// Mostrar errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../Persistencia/conexionBD.php';

// Conexión a la base de datos
$conn = ConexionBD::conectar();
session_start();

$paciente_id = $_SESSION['paciente_id'] ?? null;
if (!$paciente_id) {
    die("Debe estar logueado para confirmar un turno.");
}

// Recibir datos del formulario
$estudioId = $_POST['estudioId'] ?? null;
$sedeId = $_POST['sedeId'] ?? null;
$fecha = $_POST['fecha'] ?? null;
$hora_inicio = $_POST['hora'] ?? null;

if (!$estudioId || !$sedeId || !$fecha || !$hora_inicio) {
    die("Faltan datos para confirmar el turno.");
}

// Validar archivo de orden médica
if (!isset($_FILES['orden']) || $_FILES['orden']['error'] !== UPLOAD_ERR_OK) {
    die("Debe subir la imagen de la orden médica.");
}

// Convertir la imagen a base64
$imagen = file_get_contents($_FILES['orden']['tmp_name']);
$base64 = base64_encode($imagen);

//Validar que el horario esté disponible en agenda
$sqlCheckAgenda = "
    SELECT a.id, a.recurso_id
    FROM agenda a
    JOIN recursos r ON a.recurso_id = r.id
    WHERE a.estudio_id = ? AND r.sede_id = ? AND a.fecha = ? AND a.hora_inicio = ? AND a.disponible = TRUE
    LIMIT 1
";
$stmt = $conn->prepare($sqlCheckAgenda);
$stmt->bind_param("iiss", $estudioId, $sedeId, $fecha, $hora_inicio);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    die("El turno seleccionado ya no está disponible.");
}

$rowAgenda = $res->fetch_assoc();
$agendaId = $rowAgenda['id'];
$recursoId = $rowAgenda['recurso_id'];

//Insertar orden médica en ordenes_estudios
$sqlInsertOrden = "
    INSERT INTO ordenes_estudios (paciente_id, estudio_id, fecha_emision, medico_derivante, archivo_orden)
    VALUES (?, ?, ?, ?, ?)
";
$stmt2 = $conn->prepare($sqlInsertOrden);
$fechaEmision = date('Y-m-d');
$medicoDerivante = "Dr. Juan Pérez"; // Puedes adaptarlo si lo recibís por input

$stmt2->bind_param("iisss", $paciente_id, $estudioId, $fechaEmision, $medicoDerivante, $base64);
if (!$stmt2->execute()) {
    die("Error al guardar la orden médica.");
}

$ordenId = $conn->insert_id;

//Insertar turno en tabla turnos
$sqlInsertTurno = "
    INSERT INTO turnos (paciente_id, estudio_id, recurso_id, fecha, hora, orden_estudio_id, observaciones)
    VALUES (?, ?, ?, ?, ?, ?, '')
";

$stmt3 = $conn->prepare($sqlInsertTurno);
$stmt3->bind_param("iiissi", $paciente_id, $estudioId, $recursoId, $fecha, $hora_inicio, $ordenId);

if (!$stmt3->execute()) {
    die("Error al guardar el turno.");
}

$turnoId = $conn->insert_id; // <- ID del turno recién creado


//Marcar turno como ocupado en agenda
$sqlUpdateAgenda = "UPDATE agenda SET disponible = FALSE WHERE id = ?";
$stmt4 = $conn->prepare($sqlUpdateAgenda);
$stmt4->bind_param("i", $agendaId);
$stmt4->execute();

//Confirmación 
echo "✅ Turno confirmado correctamente. Se envió un email con los detalles."; // TODO: agregar envío de mail
// Llamar al envío del correo de confirmación
require_once '../../General/envioNotificacion.php'; //VERIFICAR RUTA ME PARECE QUE FALTA PACIENTE


enviarNotificacionTurno($conn, $turnoId); // Esta función la definimos ahora

?>
