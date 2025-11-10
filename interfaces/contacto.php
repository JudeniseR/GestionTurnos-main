<?php
declare(strict_types=1);
session_start();

$isLogged = isset($_SESSION['id_usuario']) || !empty($_SESSION['usuario']);
$rolId    = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;

$dashboard = './interfaces/Paciente/login.php';
if ($isLogged) {
  switch ($rolId) {
    case 1: $dashboard = './interfaces/Paciente/principalPac.php'; break;
    case 2: $dashboard = './interfaces/Medico/principalMed.php'; break;
    case 3: $dashboard = './interfaces/Administrador/principalAdmi.php'; break;
    case 4: $dashboard = './interfaces/tecnico/panelTecnico.php'; break;
    default: $dashboard = './interfaces/Paciente/principalPac.php'; break;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contacto - Sistema de Gestión de Turnos</title>
  
  <link rel="icon" type="image/png" href="assets/img/favicon.png">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <!-- NAVBAR -->
  <nav>
    <ul>
      <li><a href="/index.php">Inicio</a></li>
      <li><a href="/index.php#turnos">Turnos</a></li>
      <li><a href="/index.php#quienes-somos">Quiénes somos</a></li>
      <li><a href="/index.php#objetivo">Nuestro objetivo</a></li>
      <li><a href="/index.php#consultas">Consultas</a></li>

      <?php if ($isLogged): ?>
        <li><a href="<?php echo htmlspecialchars($dashboard); ?>" class="btn-login">Mi cuenta</a></li>
      <?php else: ?>
        <li><a href="/interfaces/Paciente/login.php" class="btn-login">Iniciar Sesión</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <!-- SECCIÓN DE CONTACTO -->
  <section class="contact section-anchor" style="margin-top: 80px; min-height: calc(100vh - 200px);">
    <h2>Contactanos</h2>
    <p style="max-width: 600px;">Estamos aquí para ayudarte. Envianos tu consulta y te responderemos a la brevedad.</p>
    
    <form action="enviarConsulta.php" method="POST" class="contact-form">
      <input type="text" name="nombre" placeholder="Tu nombre completo" required>
      
      <input type="email" name="email" placeholder="Tu correo electrónico" required>
      
      <input type="tel" name="telefono" placeholder="Teléfono (opcional)">
      
      <select name="asunto" required>
        <option value="">Seleccioná un tema</option>
        <option value="consulta">Consulta general</option>
        <option value="turno">Problema con turno</option>
        <option value="sugerencia">Sugerencia</option>
        <option value="reclamo">Reclamo</option>
        <option value="otro">Otro</option>
      </select>
      
      <textarea name="mensaje" placeholder="Escribí tu consulta..." required></textarea>
      
      <button type="submit" class="btn">
        <i class="fa-solid fa-paper-plane"></i> Enviar Mensaje
      </button>
    </form>

    <?php if (isset($_GET['success']) && $_GET['success'] == '1'): ?>
    <div class="mensaje-exito">
      <h3>¡Mensaje enviado con éxito!</h3>
      <p>Gracias por contactarnos. Te responderemos pronto.</p>
    </div>
    <?php endif; ?>
  </section>

  <!-- FOOTER REUTILIZABLE -->
  <?php include 'footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>