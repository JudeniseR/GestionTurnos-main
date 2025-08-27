<?php
// Mostrar errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesionPaciente.php';

$conn = ConexionBD::conectar();

$paciente_id = $_SESSION['paciente_id'] ?? null;
if (!$paciente_id) {
    die("Debe iniciar sesión para ver sus turnos.");
}

// Consulta turnos (igual que antes)
$sql = "
    SELECT 
    t.id,
    t.fecha,
    t.hora,
    t.estado,
    t.estudio_id,
    e.nombre AS nombre_estudio,
    t.medico_id,
    m.nombre AS nombre_medico,
    m.apellido AS apellido_medico,
    s1.nombre AS sede_estudio,
    (
        SELECT s.nombre 
        FROM agenda_medica am
        JOIN sedes s ON am.sede_id = s.id
        WHERE am.id_medico = t.medico_id
          AND am.fecha = t.fecha
        LIMIT 1
    ) AS sede_medico
FROM turnos t
LEFT JOIN estudios e ON t.estudio_id = e.id
LEFT JOIN recursos r ON t.recurso_id = r.id
LEFT JOIN sedes s1 ON r.sede_id = s1.id
LEFT JOIN medicos m ON t.medico_id = m.id_medico
WHERE t.paciente_id = ?
ORDER BY t.fecha DESC, t.hora DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Turnos</title>
</head>
<body>
    <h1>Mis Turnos</h1>

    <?php if (isset($_GET['cancelado']) && $_GET['cancelado'] == 1): ?>
        <p style="color:green;">Turno cancelado correctamente.</p>
    <?php endif; ?>

    <?php if ($result->num_rows === 0): ?>
        <p>No tenés turnos registrados.</p>
    <?php else: ?>
        <table border="1" cellpadding="5">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Hora</th>
                    <th>Estudio / Médico</th>
                    <th>Sede</th>
                    <th>Estado</th>
                    <th>Acciones</th> <!-- Nueva columna para el botón -->
                </tr>
            </thead>
            <tbody>
                <?php while ($turno = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($turno['fecha']); ?></td>
                        <td><?php echo htmlspecialchars($turno['hora']); ?></td>
                        <td>
                            <?php 
                                if ($turno['estudio_id']) {
                                    echo "Estudio: " . htmlspecialchars($turno['nombre_estudio']);
                                } elseif ($turno['medico_id']) {
                                    echo "Médico: " . htmlspecialchars($turno['nombre_medico'] . " " . $turno['apellido_medico']);
                                } else {
                                    echo "Sin asignar";
                                }
                            ?>
                        </td>
                        <td>
                            <?php 
                                if ($turno['estudio_id']) {
                                    echo htmlspecialchars($turno['sede_estudio'] ?? 'Sin sede asignada');
                                } elseif ($turno['medico_id']) {
                                    echo htmlspecialchars($turno['sede_medico'] ?? 'Sin sede asignada');
                                } else {
                                    echo 'Sin sede asignada';
                                }
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($turno['estado']); ?></td>
                        <td>
                            <?php if ($turno['estado'] !== 'cancelado'): ?>
                                <form method="post" action="cancelarTurno.php" style="margin:0;">
                                    <input type="hidden" name="turno_id" value="<?php echo $turno['id']; ?>">
                                    <button type="submit" onclick="return confirm('¿Seguro que querés cancelar este turno?');">Cancelar</button>
                                </form>
                            <?php else: ?>
                                Cancelado
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
