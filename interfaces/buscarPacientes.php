<!-- Archivo para que el buscador sea dinamico en abmPacientes.php -->
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
            p.nro_documento
        FROM pacientes p
        JOIN usuarios u ON u.id_usuario = p.id_usuario
        WHERE u.id_rol = 1
          AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR p.nro_documento LIKE ?)
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
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
            p.nro_documento
        FROM pacientes p
        JOIN usuarios u ON u.id_usuario = p.id_usuario
        WHERE u.id_rol = 1
        ORDER BY u.apellido, u.nombre
        LIMIT 200
    ");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='7' style='color:#666'>No se encontraron pacientes.</td></tr>";
    exit;
}

foreach ($rows as $p) {
    $activo = $p['activo'] ? "<span class='badge on'>Activo</span>" : "<span class='badge off'>Inactivo</span>";
    echo "
        <tr>
            <td>{$p['id_usuario']}</td>
            <td>" . htmlspecialchars($p['apellido'] . ', ' . $p['nombre']) . "</td>
            <td>" . htmlspecialchars($p['email']) . "</td>
            <td>" . htmlspecialchars($p['nro_documento'] ?? '-') . "</td>
            <td>{$activo}</td>
            <td>" . htmlspecialchars($p['fecha_creacion']) . "</td>
            <td>
                <a class='btn-outline btn-sm' href='abmPacientes.php?action=edit&id={$p['id_usuario']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar este paciente?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_usuario' value='{$p['id_usuario']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
