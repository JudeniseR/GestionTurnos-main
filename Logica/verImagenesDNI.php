<?php
require_once '../Persistencia/conexionBD.php';
$conn = ConexionBD::conectar();

$sql = "SELECT nombre, apellido, tipo_documento, numero_documento, img_dni FROM pacientes ORDER BY fecha_registro DESC";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<div style="margin-bottom: 20px;">';
        echo '<strong>' . htmlspecialchars($row['nombre']) . ' ' . htmlspecialchars($row['apellido']) . '</strong><br>';
        echo '<em>' . $row['tipo_documento'] . ': ' . $row['numero_documento'] . '</em><br>';
        echo '<img src="data:image/jpeg;base64,' . $row['img_dni'] . '" alt="Imagen DNI" style="max-width:400px; border: 1px solid #ccc; padding: 5px;">';
        echo '</div>';
    }
} else {
    echo "No se encontraron imágenes.";
}

$conn->close();
?>
