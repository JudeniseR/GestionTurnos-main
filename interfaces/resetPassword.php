<?php
require_once('../Persistencia/conexionBD.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();
$token = $_GET['token'] ?? null;

if (!$token) {
    die("Token inválido.");
}

// Validar token
$stmt = $conn->prepare("SELECT id_usuario, fecha_expiracion, usado FROM recuperacion_password WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->bind_result($id_usuario, $fecha_expiracion, $usado);

if ($stmt->fetch()) {
    if ($usado || strtotime($fecha_expiracion) < time()) {
        die("El enlace ya no es válido o ha expirado.");
    }
} else {
    die("Token no encontrado.");
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <title>Restablecer contraseña | Gestión de turnos</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #f4f6f9;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    form {
      background: #fff;
      padding: 25px;
      border-radius: 10px;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
      width: 320px;
    }

    h2 {
      text-align: center;
      margin-bottom: 20px;
    }

    label {
      display: block;
      margin-bottom: 6px;      
      font-weight: bold;
      color: #333;
    }

    input[type="password"] {
      width: 100%;
      padding: 10px;
      margin-bottom: 15px;      
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 14px;
    }

    button {
      width: 100%;
      padding: 10px;
      background: #007BFF;
      color: #fff;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 15px;
    }

    button:hover {
      background: #0056b3;
    }
  </style>
</head>

<body>
  <div class="form-container">
    <h2>Restablecer contraseña</h2>
    <form action="../Logica/General/procesarReset.php" method="POST">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

      <label for="password">Nueva contraseña:</label>
      <input type="password" name="password" placeholder="Ingresá tu nueva contraseña" required>

      <label for="confirm">Confirmar contraseña:</label>
      <input type="password" name="confirm" placeholder="Repetí tu contraseña" required>

      <button type="submit">Guardar</button>
    </form>
  </div>
</body>

</html>