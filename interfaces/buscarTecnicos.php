<?php
require_once('../Persistencia/conexionBD.php');
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
            t.id_tecnico,
            r.nombre AS recurso_nombre
        FROM usuarios u
        LEFT JOIN tecnicos t ON t.id_usuario = u.id_usuario
        LEFT JOIN recursos r ON t.id_recurso = r.id_recurso
        WHERE u.id_rol = 4
          AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ?)
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    $stmt->bind_param('sss', $like, $like, $like);
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
            t.id_tecnico,
            r.nombre AS recurso_nombre
        FROM usuarios u
        LEFT JOIN tecnicos t ON t.id_usuario = u.id_usuario
        LEFT JOIN recursos r ON t.id_recurso = r.id_recurso
        WHERE u.id_rol = 4
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='7' style='color:#666'>No se encontraron técnicos.</td></tr>";
    exit;
}

foreach ($rows as $t) {
    $activo = $t['activo'] ? "<span class='badge on'>Activo</span>" : "<span class='badge off'>Inactivo</span>";
    echo "
        <tr>
            <td>{$t['id_usuario']}</td>
            <td>" . htmlspecialchars($t['id_tecnico'] ?? '-') . "</td>
            <td>" . htmlspecialchars($t['apellido'] . ', ' . $t['nombre']) . "</td>
            <td>" . htmlspecialchars($t['email']) . "</td>
            <td>{$activo}</td>
            <td>" . htmlspecialchars($t['fecha_creacion']) . "</td>
            <td>
                <a class='btn-outline btn-sm' href='abmTecnicos.php?action=edit&id={$t['id_usuario']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar este técnico?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_usuario' value='{$t['id_usuario']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
