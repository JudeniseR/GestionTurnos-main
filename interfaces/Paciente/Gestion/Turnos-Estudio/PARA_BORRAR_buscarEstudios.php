<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';

// Conexión a la base de datos
$conn = ConexionBD::conectar();
$tipoEstudioId = $_POST['tipoEstudio'] ?? null;
$sedeId = $_POST['sede'] ?? null;

if (!$tipoEstudioId || !$sedeId) {
    echo "Faltan datos.";
    exit;
}

// Buscar estudios del tipo seleccionado que tienen recursos en esa sede
$sql = "
    SELECT DISTINCT e.id, e.nombre 
    FROM estudios e
    JOIN agenda_estudios a ON a.estudio_id = e.id
    JOIN recursos r ON a.recurso_id = r.id
    WHERE e.tipo_estudio_id = ? AND r.sede_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $tipoEstudioId, $sedeId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Elegir Estudio</title>
</head>
<body>
    <h2>Estudios disponibles</h2>

    <?php if ($result->num_rows > 0): ?>
        <form action="verDisponibilidad.php" method="POST">
            <input type="hidden" name="sedeId" value="<?= $sedeId ?>">
            <label for="estudio">Seleccione un estudio:</label>
            <select name="estudioId" id="estudio" required>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <option value="<?= $row['id'] ?>"><?= $row['nombre'] ?></option>
                <?php endwhile; ?>
            </select>

            <button type="submit">Ver disponibilidad</button>
        </form>
    <?php else: ?>
        <p>No se encontraron estudios para ese tipo en la sede seleccionada.</p>
    <?php endif; ?>
</body>
</html>

