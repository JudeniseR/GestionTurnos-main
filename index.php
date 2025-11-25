<?php
// Iniciar sesión ANTES de leer/escribir $_SESSION
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

$isLogged = isset($_SESSION['id_usuario']) || !empty($_SESSION['usuario']);
$rolId    = isset($_SESSION['rol_id']) ? (int)$_SESSION['rol_id'] : 0;

// Destino de "Mi cuenta" según rol
$dashboard = './interfaces/Paciente/login.php';
if ($isLogged) {
  switch ($rolId) {
    case 1: $dashboard = './interfaces/Paciente/principalPac.php'; break;
    case 2: $dashboard = './interfaces/Medico/principalMed.php'; break;
    case 3: $dashboard = './interfaces/Administrador/principalAdmi.php'; break;
    case 4: $dashboard = './interfaces/tecnico/principalTecnico.php'; break;
    case 5: $dashboard = './interfaces/Administrativo/principalAdministrativo.php'; break;
    default: $dashboard = './interfaces/Paciente/principalPac.php'; break;
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Inicio | Gestión de turnos</title>

  <link rel="icon" type="image/png" sizes="64x64" href="assets/img/favicon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/img/favicon.png">
  <link rel="icon" type="image/png" sizes="16x16" href="assets/img/favicon.png">
  <link rel="apple-touch-icon" href="assets/img/favicon.png">
  <link rel="shortcut icon" href="favicon.ico" type="image/x-icon">

  <meta name="theme-color" content="#0d6efd">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
  <!-- NAVBAR CENTRADA -->
  <nav>
    <ul>
      <li><a href="#inicio">Inicio</a></li>
      <li><a href="#turnos">Turnos</a></li>
      <li><a href="#quienes-somos">Quiénes somos</a></li>
      <li><a href="#objetivo">Nuestro objetivo</a></li>
      <li><a href="#consultas">Consultas</a></li>

      <?php if ($isLogged): ?>
        <li><a href="<?php echo htmlspecialchars($dashboard); ?>" class="btn-login">Mi cuenta</a></li>
      <?php else: ?>
        <li><a href="./interfaces/Paciente/login.php" class="btn-login">Iniciar Sesión</a></li>
      <?php endif; ?>
    </ul>
  </nav>

  <!-- HERO -->
  <section id="inicio" class="hero">
    <h1>Bienvenido al Sistema de gestión de turnos</h1>
    <p>Tu bienestar es nuestra prioridad</p>
    <a href="<?php echo $isLogged ? htmlspecialchars($dashboard) : './interfaces/Paciente/login.php'; ?>" class="btn">
      Reservar Turno
    </a>
  </section>
  
  <!-- CARRUSEL Bootstrap -->
  <section id="carrusel" class="my-5">
    <h2>Nuestras Instalaciones</h2>

    <div id="carouselClinica" class="carousel slide" data-bs-ride="carousel" data-bs-interval="4000" style="max-width: 1000px; margin: 0 auto;">
      <div class="carousel-inner text-center">
        <div class="carousel-item active">
          <img src="assets/img/carrusel/Pasillo.jpg" 
               class="rounded shadow mx-auto d-block" 
               style="height:500px; max-width:900px; width:100%; object-fit:cover;" 
               alt="Clínica 1">
        </div>
        <div class="carousel-item">
          <img src="assets/img/carrusel/Pasillo2.jpg" 
               class="rounded shadow mx-auto d-block" 
               style="height:500px; max-width:900px; width:100%; object-fit:cover;" 
               alt="Clínica 2">
        </div>
        <div class="carousel-item">
          <img src="assets/img/carrusel/Estudio1.jpg" 
               class="rounded shadow mx-auto d-block" 
               style="height:500px; max-width:900px; width:100%; object-fit:cover;" 
               alt="Clínica 3">
        </div>
      </div>
    </div>
  </section>

  <!-- TURNOS -->
  <section id="turnos" class="info section-anchor">
    <h2>Gestión de Turnos</h2>
    <p>Podés solicitar turnos médicos y estudios de manera rápida y sencilla desde nuestra plataforma.</p>
    <div class="cards">
      <div class="card">
        <i class="fa-solid fa-user-md"></i>
        <h3>Turno Médico</h3>
        <p>Solicitá turnos con nuestros profesionales.</p>
      </div>
      <div class="card">
        <i class="fa-solid fa-flask"></i>
        <h3>Estudios</h3>
        <p>Gestioná tus estudios de forma rápida y organizada.</p>
      </div>
    </div>
  </section>

  <!-- QUIÉNES SOMOS -->
  <section id="quienes-somos" class="about section-anchor">
    <h2>Quiénes Somos</h2>
    <p>Somos una clínica comprometida con la salud de nuestros pacientes, brindando atención médica integral y de calidad.</p>
  </section>

  <!-- OBJETIVO -->
  <section id="objetivo" class="about section-anchor">
    <h2>Nuestro Objetivo</h2>
    <p>Facilitar la gestión de turnos y estudios médicos, mejorando la experiencia de atención al paciente.</p>
  </section>

  <!-- CONSULTAS con botón a página de contacto -->
  <section id="consultas" class="contact section-anchor">
    <h2>Consultas</h2>
    <p>Si tenés dudas o necesitás ayuda, contactanos:</p>
    <div class="consultas-cta">
      <a href="interfaces/contacto.php" class="btn btn-contacto">
        <i class="fa-solid fa-envelope"></i> Contactanos
      </a>
    </div>
  </section>

  <!-- FOOTER REUTILIZABLE -->
  <?php include 'interfaces/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>