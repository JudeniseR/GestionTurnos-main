<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica - Gestión de Turnos</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<!-- NAVBAR -->
<nav>
    <ul>
        <li><a href="#inicio">Inicio</a></li>
        <li><a href="#turnos">Turnos</a></li>
        <li><a href="#quienes-somos">Quiénes somos</a></li>
        <li><a href="#objetivo">Nuestro objetivo</a></li>
        <li><a href="#consultas">Consultas</a></li>
        <li><a href="login.php" class="btn-login">Iniciar Sesión</a></li>
    </ul>
</nav>

<!-- HERO -->
<section id="inicio" class="hero">
    <h1>Bienvenido a la Clínica Salud+</h1>
    <p>Tu bienestar es nuestra prioridad</p>
    <a href="login.php" class="btn">Reservar Turno</a>
</section>

<!-- TURNOS -->
<section id="turnos" class="info">
    <h2>Gestión de Turnos</h2>
    <p>Puedes solicitar turnos médicos y estudios de manera rápida y sencilla desde nuestra plataforma.</p>
    <div class="cards">
        <div class="card">
            <i class="fa-solid fa-user-md"></i>
            <h3>Turno Médico</h3>
            <p>Solicita turnos con nuestros profesionales especializados.</p>
        </div>
        <div class="card">
            <i class="fa-solid fa-flask"></i>
            <h3>Estudios</h3>
            <p>Gestiona tus estudios de forma rápida y organizada.</p>
        </div>
    </div>
</section>

<!-- QUIÉNES SOMOS -->
<section id="quienes-somos" class="about">
    <h2>Quiénes Somos</h2>
    <p>Somos una clínica comprometida con la salud de nuestros pacientes, brindando atención médica integral y de calidad.</p>
</section>

<!-- OBJETIVO -->
<section id="objetivo" class="about">
    <h2>Nuestro Objetivo</h2>
    <p>Facilitar la gestión de turnos y estudios médicos, mejorando la experiencia de atención al paciente.</p>
</section>

<!-- CONSULTAS -->
<section id="consultas" class="contact">
    <h2>Consultas</h2>
    <p>Si tienes dudas o necesitas ayuda, contáctanos:</p>
    <form action="enviarConsulta.php" method="POST">
        <input type="text" name="nombre" placeholder="Tu nombre" required>
        <input type="email" name="email" placeholder="Tu correo" required>
        <textarea name="mensaje" placeholder="Escribe tu consulta..." required></textarea>
        <button type="submit">Enviar</button>
    </form>
</section>

<!-- FOOTER -->
<footer>
    <p>&copy; 2025 Clínica Salud+ - Todos los derechos reservados</p>
</footer>

</body>
</html>
