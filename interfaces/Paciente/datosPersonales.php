<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

$id_usuario = $_SESSION['id_usuario'] ?? null;

$conn = ConexionBD::conectar();

// Procesar actualización si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $email = $_POST['email'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $tipo_documento = $_POST['tipo_documento'] ?? '';
    $nro_documento = $_POST['nro_documento'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
    $direccion = $_POST['direccion'] ?? '';
    $telefono = $_POST['telefono'] ?? '';

    // Actualizar tabla usuarios
    $stmt = $conn->prepare("UPDATE usuarios SET nombre = ?, apellido = ?, email = ?, genero = ? WHERE id_usuario = ?");
    $stmt->bind_param("ssssi", $nombre, $apellido, $email, $genero, $id_usuario);
    $stmt->execute();

    // Actualizar tabla pacientes
    $stmt = $conn->prepare("UPDATE pacientes SET tipo_documento = ?, nro_documento = ?, fecha_nacimiento = ?, direccion = ?, telefono = ? WHERE id_usuario = ?");
    $stmt->bind_param("sssssi", $tipo_documento, $nro_documento, $fecha_nacimiento, $direccion, $telefono, $id_usuario);
    $stmt->execute();

    $mensaje_exito = "Datos actualizados correctamente.";
}

// Traer datos actuales
$sql = "SELECT u.nombre, u.apellido, u.email, u.genero, 
               p.tipo_documento, p.nro_documento, p.fecha_nacimiento, p.direccion, p.telefono
        FROM usuarios u
        LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $datosPaciente = $result->fetch_assoc();
} else {
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
  <style>
    .btn-editar {
      display: inline-block;
      padding: 10px 20px;
      background-color: #007bff;
      color: white;
      text-decoration: none;
      border-radius: 5px;
      margin-top: 20px;
      cursor: pointer;
    }
    .btn-editar:hover { background-color: #0056b3; }
    .form-container { max-width: 600px; margin: 0 auto; }
    .form-container input { width: 100%; padding: 8px; margin-bottom: 10px; }
    .mensaje-exito { color: green; text-align: center; margin-bottom: 20px; }
  </style>
</head>
<body>

  <?php include('navPac.php'); ?>

  <main class="container">
    <h1>Mis Datos Personales</h1>

    <?php if (isset($mensaje_exito)): ?>
      <p class="mensaje-exito"><?= htmlspecialchars($mensaje_exito) ?></p>
    <?php endif; ?>

    <?php if ($datosPaciente): ?>
      
      <?php if (!isset($_GET['editar'])): ?>
        <!-- Modo visualización -->
        <table>
          <thead>
            <tr>
              <th colspan="2">Datos Personales</th>
            </tr>
          </thead>
          <tbody>
            <tr><td><strong>Nombre</strong></td><td><?= htmlspecialchars($datosPaciente['nombre']) ?></td></tr>
            <tr><td><strong>Apellido</strong></td><td><?= htmlspecialchars($datosPaciente['apellido']) ?></td></tr>
            <tr><td><strong>Email</strong></td><td><?= htmlspecialchars($datosPaciente['email']) ?></td></tr>
            <tr><td><strong>Genero</strong></td><td><?= htmlspecialchars($datosPaciente['genero']) ?></td></tr>
            <tr><td><strong>Tipo de Documento</strong></td><td><?= htmlspecialchars($datosPaciente['tipo_documento']) ?></td></tr>
            <tr><td><strong>Nro de Documento</strong></td><td><?= htmlspecialchars($datosPaciente['nro_documento']) ?></td></tr>
            <tr><td><strong>Fecha de Nacimiento</strong></td><td><?= htmlspecialchars($datosPaciente['fecha_nacimiento']) ?></td></tr>
            <tr><td><strong>Dirección</strong></td><td><?= htmlspecialchars($datosPaciente['direccion']) ?></td></tr>
            <tr><td><strong>Teléfono</strong></td><td><?= htmlspecialchars($datosPaciente['telefono']) ?></td></tr>
          </tbody>
        </table>

        <div style="text-align: center;">
          <a href="editarDatosPersonales.php?editar=1" class="btn-editar">✏️ Editar mis datos</a>
        </div>

      <?php else: ?>
        <!-- Modo edición -->
        <div class="form-container">
          <form method="POST">
            <input type="text" name="nombre" placeholder="Nombre" value="<?= htmlspecialchars($datosPaciente['nombre']) ?>" required>
            <input type="text" name="apellido" placeholder="Apellido" value="<?= htmlspecialchars($datosPaciente['apellido']) ?>" required>
            <input type="email" name="email" placeholder="Email" value="<?= htmlspecialchars($datosPaciente['email']) ?>" required>
            <input type="text" name="genero" placeholder="Genero" value="<?= htmlspecialchars($datosPaciente['genero']) ?>">
            <input type="text" name="tipo_documento" placeholder="Tipo de Documento" value="<?= htmlspecialchars($datosPaciente['tipo_documento']) ?>">
            <input type="text" name="nro_documento" placeholder="Nro de Documento" value="<?= htmlspecialchars($datosPaciente['nro_documento']) ?>">
            <input type="date" name="fecha_nacimiento" placeholder="Fecha de Nacimiento" value="<?= htmlspecialchars($datosPaciente['fecha_nacimiento']) ?>">
            <input type="text" name="direccion" placeholder="Dirección" value="<?= htmlspecialchars($datosPaciente['direccion']) ?>">
            <input type="text" name="telefono" placeholder="Teléfono" value="<?= htmlspecialchars($datosPaciente['telefono']) ?>">

            <button type="submit" name="actualizar" class="btn-editar">💾 Guardar cambios</button>
            <a href="datosPersonales.php" class="btn-editar" style="background-color: grey;">❌ Cancelar</a>
          </form>
        </div>
      <?php endif; ?>

    <?php else: ?>
      <p style="color: white; text-align: center;">No se encontraron datos personales.</p>
    <?php endif; ?>

  </main>

  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>
  <?php include '../footer.php'; ?>

</body>
</html>
