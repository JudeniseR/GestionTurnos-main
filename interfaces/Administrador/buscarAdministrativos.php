<?php
// buscaAdministrativos.php
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
    $stmt = $conn->prepare("
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email,
            u.activo,
            u.fecha_creacion,
            a.dni,
            a.telefono,
            a.direccion
        FROM usuarios u
        LEFT JOIN administrativos a ON a.id_usuario = u.id_usuario
        WHERE u.id_rol = 5
          AND (
              u.nombre LIKE ? OR 
              u.apellido LIKE ? OR 
              u.email LIKE ? OR 
              a.dni LIKE ? OR 
              a.telefono LIKE ?
          )
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    $stmt->bind_param('sssss', $like, $like, $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query("
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.email,
            u.activo,
            u.fecha_creacion,
            a.dni,
            a.telefono,
            a.direccion
        FROM usuarios u
        LEFT JOIN administrativos a ON a.id_usuario = u.id_usuario
        WHERE u.id_rol = 5
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='8' style='color:#666'>No se encontraron administrativos.</td></tr>";
    exit;
}

foreach ($rows as $a) {
    $activo = $a['activo'] ? "<span class='badge on'>Activo</span>" : "<span class='badge off'>Inactivo</span>";
    echo "
        <tr>
            <td>{$a['id_usuario']}</td>
            <td>" . htmlspecialchars($a['apellido'] . ', ' . $a['nombre']) . "</td>
            <td>" . htmlspecialchars($a['email']) . "</td>
            <td>" . htmlspecialchars($a['dni'] ?? '-') . "</td>
            <td>" . htmlspecialchars($a['telefono'] ?? '-') . "</td>
            <td>" . htmlspecialchars($a['direccion'] ?? '-') . "</td>
            <td>{$activo}</td>
            <td>
                <a class='btn-outline btn-sm' href='abmAdministrativos.php?action=edit&id={$a['id_usuario']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar este administrativo?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_usuario' value='{$a['id_usuario']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
