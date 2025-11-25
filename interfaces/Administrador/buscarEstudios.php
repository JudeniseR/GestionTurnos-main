<?php
require_once('../../Persistencia/conexionBD.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: text/html; charset=utf-8');

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-03:00'");

$q = trim($_GET['q'] ?? '');
$rows = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $stmt = $conn->prepare("SELECT * FROM estudios WHERE nombre LIKE ? ORDER BY nombre");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM estudios ORDER BY nombre");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='4' style='color:#666'>No se encontraron estudios.</td></tr>";
    exit;
}

foreach ($rows as $e) {
    $requiere = $e['requiere_preparacion'] ? 'Sí' : 'No';
    echo "
        <tr>
            <td>{$e['id_estudio']}</td>
            <td>" . htmlspecialchars($e['nombre']) . "</td>
            <td>{$requiere}</td>
            <td style='max-width:350px'>" . htmlspecialchars($e['instrucciones'] ?? '-') . "</td>
            <td style='width:180px'>
                <a class='btn-outline btn-sm' href='abmEstudios.php?action=edit&id={$e['id_estudio']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar este estudio?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_estudio' value='{$e['id_estudio']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
