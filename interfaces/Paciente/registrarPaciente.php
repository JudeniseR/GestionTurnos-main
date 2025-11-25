<?php
require_once('../../interfaces/mostrarAlerta.php');
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar nuevo paciente | Gestión de turnos</title> 
    <link rel="stylesheet" href="../../css/style.css"/>   
</head>
<body class="login-page">
    <form class="register-card" action="../../Logica/Paciente/registroPaciente.php" method="POST" enctype="multipart/form-data" onsubmit="return validarEdad();">
        <h2>REGISTRO DE PACIENTE</h2>

         <div>
            <label for="nombre">Nombre:</label>
            <input type="text" id="nombre" name="nombre" required>
        </div>

        <div>
            <label for="apellido">Apellido:</label>
            <input type="text" id="apellido" name="apellido" required>
        </div>

        <div>
            <label>Tipo de documento:</label>
            <label><input type="radio" name="tipo_documento" value="DNI" required> DNI</label>
            <label><input type="radio" name="tipo_documento" value="Pasaporte"> Pasaporte</label>
            <label><input type="radio" name="tipo_documento" value="Otro"> Otro</label>
        </div>

        <div>
            <label for="numero_documento">Número de documento:</label>
            <input type="text" id="numero_documento" name="numero_documento" required>
        </div>

        <div>
            <label for="imagen_dni">Imagen del DNI (frente y dorso):</label>
           <input type="file" id="imagen_dni" name="imagen_dni[]" accept="image/*" multiple required>
        </div>

        <div>
            <label>Género:</label>
            <label><input type="radio" name="genero" value="Masculino" required> Masculino</label>
            <label><input type="radio" name="genero" value="Femenino"> Femenino</label>
            <label><input type="radio" name="genero" value="Otro"> Otro</label>
        </div>

        <div>
            <label for="fecha_nacimiento">Fecha de nacimiento:</label>
            <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>
        </div>

        <div>
            <label for="domicilio">Domicilio:</label>
            <input type="text" id="domicilio" name="domicilio" required>
        </div>

        <div>
            <label for="numero_contacto">Número de contacto:</label>
            <input type="tel" id="numero_contacto" name="numero_contacto" required>
        </div>

        <div>
            <label>Cobertura de salud:</label>
            <label><input type="radio" name="cobertura_salud" value="UOM" checked required> UOM</label>
            <!--
            <label><input type="radio" name="cobertura_salud" value="OSDE"> OSDE</label>
            <label><input type="radio" name="cobertura_salud" value="Swiss Medical"> Swiss Medical</label>
            <label><input type="radio" name="cobertura_salud" value="Galeno"> Galeno</label>
            <label><input type="radio" name="cobertura_salud" value="Otra"> Otra</label>
            -->
        </div>

        <div>
            <label for="numero_afiliado">Número de afiliado:</label>
            <input type="text" id="numero_afiliado" name="numero_afiliado" required>
        </div>

        <div>
            <label for="email">Correo electrónico:</label>
            <input type="email" id="email" name="email" required>
        </div>

        <div>
            <label for="password">Contraseña:</label>
            <input type="password" id="password" name="password" required>
        </div>

        <div>
            <label><input type="checkbox" name="terminos" required> Acepto los términos y condiciones</label>
        </div>

        <div>
            <button type="submit">REGISTRARSE</button>
        </div>

        <div>
            ¿Ya tenes cuenta? <br><a href="login.php">Inicia sesión </a> |
            <a href="../../index.php">Volver al inicio</a>
        </div>        
    </form>


<script>
function validarEdad() {
    const fn = document.getElementById('fecha_nacimiento').value;
    if (!fn) return true;

    const fecha = new Date(fn);
    const hoy = new Date();

    let edad = hoy.getFullYear() - fecha.getFullYear();
    const m = hoy.getMonth() - fecha.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < fecha.getDate())) {
        edad--;
    }

    if (edad < 18) {
        mostrarAlerta('error', '❌ Debe ser mayor de 18 años para registrarse.');
        return false;
    }

    return true;
}
</script>

</body>
</html>