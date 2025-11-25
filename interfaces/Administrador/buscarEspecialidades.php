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
    $stmt = $conn->prepare("SELECT * FROM especialidades WHERE nombre_especialidad LIKE ? ORDER BY nombre_especialidad");
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("SELECT * FROM especialidades ORDER BY nombre_especialidad");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='3' style='color:#666'>No se encontraron especialidades.</td></tr>";
    exit;
}

foreach ($rows as $e) {
    echo "
        <tr>
            <td>{$e['id_especialidad']}</td>
            <td>" . htmlspecialchars($e['nombre_especialidad']) . "</td>
            <td style='width:180px'>
                <a class='btn-outline btn-sm' href='abmEspecialidades.php?action=edit&id={$e['id_especialidad']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar esta especialidad?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_especialidad' value='{$e['id_especialidad']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
