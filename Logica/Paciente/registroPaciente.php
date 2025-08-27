<?php
require_once('../../Persistencia/conexionBD.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();

// Función: Sanitiza input
function clean_input($data) {
    return htmlspecialchars(trim($data));
}

// Función: Obtener datos del formulario
function get_post_data() {
    return [
        'nombre'             => clean_input($_POST['nombre']),
        'apellido'           => clean_input($_POST['apellido']),
        'tipo_documento'     => $_POST['tipo_documento'],
        'numero_documento'   => clean_input($_POST['numero_documento']),
        'genero'             => $_POST['genero'],
        'fecha_nacimiento'   => $_POST['fecha_nacimiento'],
        'domicilio'          => clean_input($_POST['domicilio']),
        'numero_contacto'    => clean_input($_POST['numero_contacto']),
        'cobertura_salud'    => $_POST['cobertura_salud'],
        'numero_afiliado'    => clean_input($_POST['numero_afiliado']),
        'email'              => clean_input($_POST['email']),
        'password'           => $_POST['password'],
        'password_hash'      => password_hash($_POST['password'], PASSWORD_DEFAULT),
    ];
}

// Función: Procesar imagen del DNI
function procesar_imagen_dni() {
    if (isset($_FILES['imagen_dni']) && is_uploaded_file($_FILES['imagen_dni']['tmp_name'])) {
        return base64_encode(file_get_contents($_FILES['imagen_dni']['tmp_name']));
    } else {
        throw new Exception("❌ Error: no se subió correctamente la imagen del DNI.");
    }
}

// Función: Validar si el afiliado existe y está activo
function validar_afiliado($conn, $documento, $afiliado) {
    $stmt = $conn->prepare("SELECT 1 FROM afiliados WHERE numero_documento = ? AND numero_afiliado = ? AND estado = 'activo'");
    $stmt->bind_param("ss", $documento, $afiliado);
    $stmt->execute();
    $stmt->store_result();
    $valid = $stmt->num_rows > 0;
    $stmt->close();
    return $valid;
}

// Función: Obtener ID del afiliado
function obtener_id_afiliado($conn, $documento, $afiliado) {
    $stmt = $conn->prepare("SELECT id FROM afiliados WHERE numero_documento = ? AND numero_afiliado = ?");
    $stmt->bind_param("ss", $documento, $afiliado);
    $stmt->execute();
    $stmt->bind_result($id);
    if ($stmt->fetch()) {
        $stmt->close();
        return $id;
    } else {
        $stmt->close();
        throw new Exception("❌ No se encontró el ID del afiliado.");
    }
}

// Función: Registrar paciente
function registrar_paciente($conn, $data, $imagen_base64, $id_afiliado, $token_qr) {
    $stmt = $conn->prepare("
        INSERT INTO pacientes 
        (nombre, apellido, tipo_documento, numero_documento, img_dni, genero, fecha_nacimiento, domicilio, numero_contacto, cobertura_salud, numero_afiliado, email, password_hash, id_afiliado, token_qr)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "sssssssssssssis",
        $data['nombre'],
        $data['apellido'],
        $data['tipo_documento'],
        $data['numero_documento'],
        $imagen_base64,
        $data['genero'],
        $data['fecha_nacimiento'],
        $data['domicilio'],
        $data['numero_contacto'],
        $data['cobertura_salud'],
        $data['numero_afiliado'],
        $data['email'],
        $data['password_hash'],
        $id_afiliado,
        $token_qr
    );

    $stmt->execute();
    $stmt->close();
}

// Main
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = get_post_data();
        $imagen_dni = procesar_imagen_dni();

        if (!validar_afiliado($conn, $data['numero_documento'], $data['numero_afiliado'])) {
            throw new Exception("❌ No estás registrado como afiliado activo.");
        }

        $id_afiliado = obtener_id_afiliado($conn, $data['numero_documento'], $data['numero_afiliado']);
        $token_qr = bin2hex(random_bytes(16));

        registrar_paciente($conn, $data, $imagen_dni, $id_afiliado, $token_qr);

        echo "<script>alert('✅ Registro exitoso.'); window.location.href = '../../index.php';</script>";
    } catch (mysqli_sql_exception $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'numero_documento') !== false) {
            echo "<script>alert('❌ El número de documento ya está registrado.'); window.history.back();</script>";
        } elseif (strpos($msg, 'email') !== false) {
            echo "<script>alert('❌ El correo electrónico ya está registrado.'); window.history.back();</script>";
        } else {
            echo "<script>alert('❌ Error inesperado: " . addslashes($msg) . "'); window.history.back();</script>";
        }
    } catch (Exception $e) {
        echo "<script>alert('" . addslashes($e->getMessage()) . "'); window.history.back();</script>";
    } finally {
        $conn->close();
    }
}
?>
