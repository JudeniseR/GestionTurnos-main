<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

$id_usuario = $_SESSION['id_usuario'] ?? null;

// Consulta para traer datos del paciente
$sql = "SELECT u.nombre, u.apellido, u.email, p.tipo_documento, p.nro_documento, 
               p.fecha_nacimiento, p.direccion, p.telefono, u.genero
        FROM usuarios u
        LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?";

$conn = ConexionBD::conectar();
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $datosPaciente = $result->fetch_assoc();
} else {
    // Datos no encontrados
    $datosPaciente = null;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Datos Personales</title>
  <link rel="stylesheet" href="../../css/principalPac.css">
  <link rel="stylesheet" href="../../css/misTurnos.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
</head>
<body>

  <?php include('navPac.php'); ?>

  <main class="container">
    <h1>Mis Datos Personales</h1>

    <?php if ($datosPaciente): ?>
      <table>
  <thead>
    <tr>
      <th colspan="2">Datos Personales</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Nombre</strong></td>
      <td><?= htmlspecialchars($datosPaciente['nombre']) ?></td>
    </tr>
    <tr>
      <td><strong>Apellido</strong></td>
      <td><?= htmlspecialchars($datosPaciente['apellido']) ?></td>
    </tr>
    <tr>
      <td><strong>Email</strong></td>
      <td><?= htmlspecialchars($datosPaciente['email']) ?></td>
    </tr>
    <tr>
      <td><strong>Tipo de Documento</strong></td>
      <td><?= htmlspecialchars($datosPaciente['tipo_documento']) ?></td>
    </tr>
    <tr>
      <td><strong>Nro de Documento</strong></td>
      <td><?= htmlspecialchars($datosPaciente['nro_documento']) ?></td>
    </tr>
    <tr>
      <td><strong>Fecha de Nacimiento</strong></td>
      <td><?= htmlspecialchars($datosPaciente['fecha_nacimiento']) ?></td>
    </tr>
    <tr>
      <td><strong>Dirección</strong></td>
      <td><?= htmlspecialchars($datosPaciente['direccion']) ?></td>
    </tr>
    <tr>
      <td><strong>Teléfono</strong></td>
      <td><?= htmlspecialchars($datosPaciente['telefono']) ?></td>
    </tr>
    <tr>
      <td><strong>Genero</strong></td>
      <td><?= htmlspecialchars($datosPaciente['genero']) ?></td>
    </tr>
  </tbody>
</table>

    <?php else: ?>
      <p style="color: white; text-align: center;">No se encontraron datos personales.</p>
    <?php endif; ?>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
</body>
</html>
