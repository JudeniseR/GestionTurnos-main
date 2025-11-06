<?php

require_once('../../Persistencia/conexionBD.php');
require_once('../General/envioNotif.php');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// --- Helpers ---
function clean_input($s){ return htmlspecialchars(trim($s ?? '')); }

function get_post_data() {
    return [
        'nombre'           => clean_input($_POST['nombre']),
        'apellido'         => clean_input($_POST['apellido']),
        'genero'           => clean_input($_POST['genero']),               
        'tipo_documento'   => clean_input($_POST['tipo_documento']),
        'numero_documento' => clean_input($_POST['numero_documento']),
        'fecha_nacimiento' => clean_input($_POST['fecha_nacimiento']),
        'domicilio'        => clean_input($_POST['domicilio']),
        'numero_contacto'  => clean_input($_POST['numero_contacto']),
        'cobertura_salud'  => clean_input($_POST['cobertura_salud']),
        'numero_afiliado'  => clean_input($_POST['numero_afiliado']),
        'email'            => strtolower(clean_input($_POST['email'])),
        'password'         => $_POST['password'] ?? '',
        'estado_civil'     => clean_input($_POST['estado_civil']),
    ];
}

function leer_img_dni_base64() {
    if (isset($_FILES['imagen_dni']) && is_uploaded_file($_FILES['imagen_dni']['tmp_name'])) {
        return base64_encode(file_get_contents($_FILES['imagen_dni']['tmp_name']));
    }
    return null; 
}

function validar_afiliado(mysqli $conn, $documento, $afiliado) {
    $sql = "SELECT 1 FROM afiliados
            WHERE numero_documento = ? AND numero_afiliado = ? AND estado='activo' LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("ss", $documento, $afiliado);
    $st->execute(); $st->store_result();
    $ok = $st->num_rows > 0;
    $st->close();
    return $ok;
}

/* ================= MAIN ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../interfaces/Paciente/registrarPaciente.php');
    exit;
}

// Si hay body pero $_POST vacío
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $enviado = (int)$_SERVER['CONTENT_LENGTH'];
    exit("❌ El formulario ($enviado bytes) supera el límite. Aumentá post_max_size/upload_max_filesize o subí una imagen más chica.");
}

try {
    $d = get_post_data();

    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('❌ Email inválido.');
    }

    $img_dni_b64 = leer_img_dni_base64(); 
    if (!$img_dni_b64) {
        throw new Exception('❌ Debe subir una imagen del DNI.');
    }

    if (!validar_afiliado($conn, $d['numero_documento'], $d['numero_afiliado'])) {
        throw new Exception('❌ No estás registrado como afiliado activo.');
    }

    $conn->begin_transaction();

    // Email único
    $st = $conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $st->bind_param("s", $d['email']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El correo ya está registrado.'); }
    $st->close();

    // Documento único
    $st = $conn->prepare("SELECT 1 FROM pacientes WHERE nro_documento=? LIMIT 1");
    $st->bind_param("s", $d['numero_documento']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El número de documento ya está registrado.'); }
    $st->close();

    // Token QR
    $token_qr = bin2hex(random_bytes(16));

    // --- Llamada al procedimiento almacenado ---
    $activo = 1;
    $st = $conn->prepare("CALL insertar_usuario_paciente(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $st->bind_param(
        "ssssisssssssss",
        $d['nombre'], $d['apellido'], $d['email'], password_hash($d['password'], PASSWORD_DEFAULT),
        $activo,
        $d['genero'], $d['tipo_documento'], $d['numero_documento'],
        $d['fecha_nacimiento'], $d['domicilio'], $d['numero_contacto'],
        $d['estado_civil'], $token_qr, $img_dni_b64
    );
    $st->execute();
    $st->close();

    $conn->commit();

    // Notificación
    $datosCorreo = [
        'email'    => $d['email'],
        'nombre'   => $d['nombre'],
        'apellido' => $d['apellido']
    ];
    enviarNotificacion('registro',$datosCorreo);

    echo "<script>alert('✅ Registro exitoso.'); window.location.href='../../interfaces/Paciente/login.php';</script>";

} catch (Throwable $e) {
    if ($conn->errno) { $conn->rollback(); }
    $msg = $e->getMessage();
    echo "<script>alert('".addslashes($msg)."'); window.history.back();</script>";
} finally {
    $conn->close();
}
