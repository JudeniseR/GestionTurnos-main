<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../Logica/General/verificarSesion.php');

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cambiar contraseña | Gestión de turnos</title>
  <link rel="stylesheet" href="../css/principalPac.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.css"/>
<!--  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .container {
      border: 1px solid #000;
      padding: 30px;
      width: 350px;
      text-align: center;
    }

    h2 {
      text-align: center;
      font-size: 20px;
      margin-bottom: 20px;
    }

    input[type="email"] {
      width: 80%;
      padding: 8px;
      margin: 15px 0;
      border: 1px solid #000;
    }

    button {
      padding: 8px 15px;
      border: 1px solid #000;
      background-color: #fff;
      cursor: pointer;
      margin-bottom: 15px;
    }

    a {
      display: block;
      font-size: 14px;
      color: #0000ee;
      text-decoration: none;
    }

    a:hover {
      text-decoration: underline;
    }
  </style>
-->

</head>
<body>
  <?php include('Paciente/navPac.php'); ?>

  <div class="form-recuperar">
    <h2>Cambiar Contraseña</h2>
    <p>Introduce tu correo electrónico para recibir el enlace de recuperación de contraseña.</p>

    <form action="../Logica/General/procesarOlvido.php" method="POST">
      <div>
        <label for="email">Correo electrónico:</label>
        <input type="email" name="email" id="email" placeholder="usuario@example.com" required>
      </div>

      <div>
        <button type="submit">Enviar enlace</button>
      </div>
    </form>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui/dist/fancybox.umd.js"></script>

  <!-- FOOTER REUTILIZABLE -->
  <?php include 'footer.php'; ?>
</body>

</html>