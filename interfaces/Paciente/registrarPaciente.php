<?php
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recibir datos
    $nombre            = $_POST['nombre'];
    $apellido          = $_POST['apellido'];
    $tipo_documento    = $_POST['tipo_documento'];
    $numero_documento  = $_POST['numero_documento'];
    $genero            = $_POST['genero'];
    $fecha_nacimiento  = $_POST['fecha_nacimiento'];
    $domicilio         = $_POST['domicilio'];
    $numero_contacto   = $_POST['numero_contacto'];
    $cobertura_salud   = $_POST['cobertura_salud'];
    $numero_afiliado   = $_POST['numero_afiliado'];
    $email             = $_POST['email'];
    $password          = $_POST['password'];

    // 🔒 Encriptar contraseña
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    // Subir imagen del DNI
    $imagenPath = null;
    if (isset($_FILES['imagen_dni']) && $_FILES['imagen_dni']['error'] === UPLOAD_ERR_OK) {
        $carpeta = "../../uploads/dni/";
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0777, true);
        }
        $nombreArchivo = uniqid() . "_" . basename($_FILES['imagen_dni']['name']);
        $imagenPath = $carpeta . $nombreArchivo;
        move_uploaded_file($_FILES['imagen_dni']['tmp_name'], $imagenPath);
    }

    // Guardar paciente en la tabla pacientes
    $stmtPaciente = $conn->prepare("INSERT INTO pacientes 
        (nombre, apellido, tipo_documento, numero_documento, genero, fecha_nacimiento, domicilio, numero_contacto, cobertura_salud, numero_afiliado, imagen_dni) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmtPaciente->bind_param(
        "sssssssssss", 
        $nombre, $apellido, $tipo_documento, $numero_documento, $genero, $fecha_nacimiento,
        $domicilio, $numero_contacto, $cobertura_salud, $numero_afiliado, $imagenPath
    );

    if (!$stmtPaciente->execute()) {
        echo "<script>alert('❌ Error al registrar paciente.'); window.history.back();</script>";
        exit();
    }

    // ID del paciente recién creado
    $id_paciente = $stmtPaciente->insert_id;

    // Guardar usuario en la tabla usuarios (con rol = paciente)
    $rol = "paciente";
    $stmtUsuario = $conn->prepare("INSERT INTO usuarios (email, password, rol, id_paciente) VALUES (?, ?, ?, ?)");
    $stmtUsuario->bind_param("sssi", $email, $passwordHash, $rol, $id_paciente);

    if ($stmtUsuario->execute()) {
        echo "<script>alert('✅ Registro exitoso. Ahora puedes iniciar sesión.'); window.location='../../interfaces/Paciente/login.php';</script>";
    } else {
        echo "<script>alert('❌ Error al registrar usuario.'); window.history.back();</script>";
    }

    // Cerrar conexiones
    $stmtPaciente->close();
    $stmtUsuario->close();
    $conn->close();
}
?>
