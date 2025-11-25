<!-- Archivo para que el buscador sea dinamico en abmMedicos.php -->
<?php
require_once('../Persistencia/conexionBD.php');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

header('Content-Type: text/html; charset=utf-8');

// ===== Conexión =====
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone = '-03:00'");

// ===== Parámetro de búsqueda =====
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
            m.matricula,
            GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') AS especialidades
        FROM usuarios u
        LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
        LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
        LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
        WHERE u.id_rol = 2
          AND (u.nombre LIKE ? OR u.apellido LIKE ? OR u.email LIKE ? OR m.matricula LIKE ?)
        GROUP BY u.id_usuario
        ORDER BY u.apellido, u.nombre
        LIMIT 100
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
            m.matricula,
            GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') AS especialidades
        FROM usuarios u
        LEFT JOIN medicos m ON m.id_usuario = u.id_usuario
        LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
        LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
        WHERE u.id_rol = 2
        GROUP BY u.id_usuario
        ORDER BY u.apellido, u.nombre
        LIMIT 100
    ");
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
}

if (empty($rows)) {
    echo "<tr><td colspan='7' style='color:#666'>No se encontraron médicos.</td></tr>";
    exit;
}

foreach ($rows as $m) {
    $activo = $m['activo'] ? "<span class='badge on'>Activo</span>" : "<span class='badge off'>Inactivo</span>";
    echo "
        <tr>
            <td>{$m['id_usuario']}</td>
            <td>" . htmlspecialchars($m['apellido'] . ', ' . $m['nombre']) . "</td>
            <td>" . htmlspecialchars($m['email']) . "</td>
            <td>" . htmlspecialchars($m['matricula'] ?? '-') . "</td>
            <td><small>" . htmlspecialchars($m['especialidades'] ?? 'Sin especialidad') . "</small></td>
            <td>{$activo}</td>
            <td>
                <a class='btn-outline btn-sm' href='abmMedicos.php?action=edit&id={$m['id_usuario']}'><i class='fa fa-pen'></i> Modificar</a>
                <form style='display:inline' method='post' onsubmit='return confirm(\"¿Eliminar este médico?\")'>
                    <input type='hidden' name='form_action' value='delete'/>
                    <input type='hidden' name='id_usuario' value='{$m['id_usuario']}'/>
                    <button class='btn-danger btn-sm' type='submit'><i class='fa fa-trash'></i> Eliminar</button>
                </form>
            </td>
        </tr>
    ";
}
?>
