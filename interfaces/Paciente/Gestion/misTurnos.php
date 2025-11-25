<?php
// Mostrar errores (solo para desarrollo)
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

require_once '../../../Persistencia/conexionBD.php';
require_once '../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

$paciente_id = $_SESSION['id_paciente_token'] ?? null;

//if (!$paciente_id) {
//    die("Debe iniciar sesión para ver sus turnos.");
//}

/**
 * Consulta de turnos del paciente o de sus afiliados
 */
$sql = "
SELECT 
    t.id_turno AS id,
    t.fecha,
    t.hora,
    es.nombre_estado AS estado,
    t.id_estudio,
    e.nombre AS nombre_estudio,
    t.id_medico,
    um.nombre AS nombre_medico,
    um.apellido AS apellido_medico,
    r.nombre AS recurso,
    s.nombre AS sede,
    a.nombre AS nombre_afiliado,
    a.apellido AS apellido_afiliado
FROM turnos t
LEFT JOIN estudios e   ON t.id_estudio = e.id_estudio
LEFT JOIN medicos m    ON t.id_medico  = m.id_medico
LEFT JOIN usuarios um  ON m.id_usuario = um.id_usuario
LEFT JOIN estados es   ON t.id_estado  = es.id_estado
LEFT JOIN recursos r   ON r.id_recurso = t.id_recurso
LEFT JOIN sedes s      ON s.id_sede    = r.id_sede
LEFT JOIN afiliados a  ON t.id_afiliado = a.id
WHERE t.id_paciente = ? 
   OR t.id_afiliado IN (
       SELECT id FROM afiliados 
       WHERE id_titular = ? 
       AND estado = 'activo'
   )
ORDER BY t.fecha DESC, t.hora DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $paciente_id, $paciente_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Mis turnos | Gestión de turnos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css" />
    <link rel="stylesheet" href="../../../css/misTurnos.css">
    <link rel="stylesheet" href="../../../css/principalPac.css"> 
</head>
<body>
    <?php include('../navPac.php'); ?>

    <div class="container">
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
                        <th>Paciente</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Estudio / Médico / Recurso</th>
                        <th>Sede</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($turno = $result->fetch_assoc()): ?>
                        <?php 
                            $estadoUpper = strtoupper($turno['estado'] ?? ''); 
                            switch($estadoUpper) {
                                case 'CONFIRMADO': $colorEstado = '#28a745'; break;   
                                case 'REPROGRAMADO': $colorEstado = '#fd7e14'; break; 
                                case 'DERIVADO': $colorEstado = '#ffc107'; break;     
                                case 'ATENDIDO': $colorEstado = '#007bff'; break;     
                                case 'CANCELADO': $colorEstado = '#dc3545'; break;    
                                default: $colorEstado = '#000';                        
                            }

                            // Nombre del paciente o afiliado
                            $nombreTurno = $turno['nombre_afiliado'] ?? 'Titular';
                            $apellidoTurno = $turno['apellido_afiliado'] ?? '';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars(trim($nombreTurno . ' ' . $apellidoTurno)); ?></td>
                            <td><?= htmlspecialchars($turno['fecha'] ?? ''); ?></td>
                            <td><?= htmlspecialchars($turno['hora'] ?? ''); ?></td>
                            <td>
                                <?php
                                if (!empty($turno['id_estudio'])) {
                                    echo "Estudio: " . htmlspecialchars($turno['nombre_estudio'] ?? 'No especificado');
                                } elseif (!empty($turno['id_medico'])) {
                                    $nombreCompleto = trim(($turno['nombre_medico'] ?? '') . ' ' . ($turno['apellido_medico'] ?? ''));
                                    echo "Médico: " . htmlspecialchars($nombreCompleto ?: 'No especificado');
                                } elseif (!empty($turno['recurso'])) {
                                    echo "Recurso: " . htmlspecialchars($turno['recurso']);
                                } else {
                                    echo "Sin asignar";
                                }
                                ?>
                            </td>
                            <td><?= htmlspecialchars($turno['sede'] ?? 'Sin sede'); ?></td>
                            <td style="color: <?= $colorEstado ?>;"><?= ucfirst(htmlspecialchars($turno['estado'] ?? '')); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
    <?php include '../../footer.php'; ?>
</body>
</html>
