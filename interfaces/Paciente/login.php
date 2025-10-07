<?php   
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Iniciar sesión - Gestión de turnos</title>
  <link rel="stylesheet" href="../../css/style.css"/>
</head>
<body class="login-page">
  <form class="login-card" action="../../Logica/General/iniciarSesion.php" method="POST">
    <h1>INICIAR SESIÓN</h1>

    <div>
      <label for="email">Correo electrónico:</label>
      <input type="text" id="email" name="email" required>
    </div>

    <div>
      <label for="password">Contraseña:</label>
      <input type="password" id="password" name="password" required>
      <a href="../olvidasteContrasenia.html">¿Olvidaste tu contraseña?</a>
    </div>

    <div>
      <button type="submit">INICIAR SESIÓN</button>
    </div>

    <div class="login-card-last-child" >
      ¿No tenés cuenta? <br><a href="./registrarPaciente.php">Regístrate </a> |
      <a href="../../index.php">Volver al inicio</a>
    </div>
  </form>
</body>
</html>
