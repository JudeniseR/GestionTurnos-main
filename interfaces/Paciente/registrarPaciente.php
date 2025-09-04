<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar usuario - Gestión de turnos</title>
</head>
<body>
    <form action="../../Logica/Paciente/registroPaciente.php" method="POST" enctype="multipart/form-data">
        <h2>Registro de Usuario</h2>

        <label for="nombre">Nombre:</label>
        <input type="text" id="nombre" name="nombre" required>

        <label for="apellido">Apellido:</label>
        <input type="text" id="apellido" name="apellido" required>

        <label>Tipo de documento:</label>
        <div>
            <label><input type="radio" name="tipo_documento" value="DNI" required> DNI</label>
            <label><input type="radio" name="tipo_documento" value="Pasaporte"> Pasaporte</label>
            <label><input type="radio" name="tipo_documento" value="Otro"> Otro</label>
        </div>

        <label for="numero_documento">Número de documento:</label>
        <input type="text" id="numero_documento" name="numero_documento" required>

        <label for="imagen_dni">Imagen del DNI (frente y dorso):</label>
        <input type="file" id="imagen_dni" name="imagen_dni" accept="image/*" required>

        <label>Género:</label>
        <div>
            <label><input type="radio" name="genero" value="Masculino" required> Masculino</label>
            <label><input type="radio" name="genero" value="Femenino"> Femenino</label>
            <label><input type="radio" name="genero" value="Otro"> Otro</label>
        </div>

        <label for="fecha_nacimiento">Fecha de nacimiento:</label>
        <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" required>

        <label for="domicilio">Domicilio:</label>
        <input type="text" id="domicilio" name="domicilio" required>

        <label for="numero_contacto">Número de contacto:</label>
        <input type="tel" id="numero_contacto" name="numero_contacto" required>

        
        <label>Cobertura de salud:</label>
        <div class="radio-group">
            <label><input type="radio" name="cobertura_salud" value="UOM" checked required> UOM</label>
            <label><input type="radio" name="cobertura_salud" value="OSDE"> OSDE</label>
            <label><input type="radio" name="cobertura_salud" value="Swiss Medical"> Swiss Medical</label>
            <label><input type="radio" name="cobertura_salud" value="Galeno"> Galeno</label>
            <label><input type="radio" name="cobertura_salud" value="Otra"> Otra</label>
        </div>

        <label for="numero_afiliado">Número de afiliado:</label>
        <input type="text" id="numero_afiliado" name="numero_afiliado" required>

        <label for="email">Correo electrónico:</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required>

        <label>
            <input type="checkbox" name="terminos" required> Acepto los términos y condiciones
        </label>

        <div>
            <button type="submit">Registrarse</button>
            ¿Ya tienes cuenta? <a href="../../index.php">Inicia sesión</a>
        </div>
  </form>
</body>
</html>