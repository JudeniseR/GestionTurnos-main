<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once('../../Persistencia/conexionBD.php');
require_once('../../librerias/PHPMailer/src/PHPMailer.php');
require_once('../../librerias/PHPMailer/src/SMTP.php');
require_once('../../librerias/PHPMailer/src/Exception.php');

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
        'genero'           => clean_input($_POST['genero']),                 // <- NUEVO en usuario
        'tipo_documento'   => clean_input($_POST['tipo_documento']),
        'numero_documento' => clean_input($_POST['numero_documento']),
        'fecha_nacimiento' => clean_input($_POST['fecha_nacimiento']),
        'domicilio'        => clean_input($_POST['domicilio']),
        'numero_contacto'  => clean_input($_POST['numero_contacto']),
        'cobertura_salud'  => clean_input($_POST['cobertura_salud']),
        'numero_afiliado'  => clean_input($_POST['numero_afiliado']),
        'email'            => strtolower(clean_input($_POST['email'])),
        'password_hash'    => password_hash($_POST['password'], PASSWORD_DEFAULT),
    ];
}

function leer_img_dni_base64() {
    if (isset($_FILES['imagen_dni']) && is_uploaded_file($_FILES['imagen_dni']['tmp_name'])) {
        // (Opcional) validar mime: image/png, image/jpeg, etc.
        return base64_encode(file_get_contents($_FILES['imagen_dni']['tmp_name']));
    }
    return null; // si la hiciste obligatoria en el form, nunca debería ser null
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

/* ========= usuario: ahora con genero + img_dni ========= */
function crear_usuario(mysqli $conn, array $d, ?string $img_dni_b64): int {
    $sql = "INSERT INTO usuarios
            (nombre, apellido, genero, img_dni, email, password_hash, id_rol, activo, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW())";
    $st = $conn->prepare($sql);
    $st->bind_param("ssssss",
        $d['nombre'], $d['apellido'], $d['genero'], $img_dni_b64, $d['email'], $d['password_hash']
    );
    $st->execute();
    $id = $st->insert_id;
    $st->close();
    return $id;
}

/* ========= pacientes: SIN genero ni img_dni ========= */
function crear_paciente(mysqli $conn, array $d, int $id_usuario, string $token_qr): int {
    $sql = "INSERT INTO pacientes
            (id_usuario, tipo_documento, nro_documento, fecha_nacimiento, direccion, telefono, email, token_qr)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $st = $conn->prepare($sql);
    $st->bind_param("isssssss",
        $id_usuario, $d['tipo_documento'], $d['numero_documento'], $d['fecha_nacimiento'],
        $d['domicilio'], $d['numero_contacto'], $d['email'], $token_qr
    );
    $st->execute();
    $id = $st->insert_id;
    $st->close();
    return $id;
}

function enviar_mail_registro($email, $nombre, $apellido) {
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username   = 'xxjavicaixx@gmail.com';
        $mail->Password   = 'ycejgbxqrhueamqf';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->ContentType = 'text/html; charset=UTF-8';
        $mail->setFrom('xxjavicaixx@gmail.com', 'no-responder-gestion-turnos');
        $mail->addAddress($email, $nombre . ' ' . $apellido);
        $mail->isHTML(true);
        $mail->Subject = "Registro exitoso en Sistema de Gestión Turnos";

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $url_login = $protocol . "://" . $host . "/interfaces/Paciente/login.php";

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;padding:20px;background:#f9f9f9'>
          <div style='max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden'>
            <div style='background:#1976d2;color:#fff;padding:20px;text-align:center'>
              <h2 style='margin:0'>Bienvenido/a, {$nombre} {$apellido} 🎉</h2>
            </div>
            <div style='padding:20px;color:#333'>
              <p>Tu registro fue exitoso. Ya podés iniciar sesión.</p>
              <div style='text-align:center;margin:30px 0'>
                <a href='{$url_login}' style='background:#4CAF50;color:#fff;padding:10px 20px;border-radius:5px;text-decoration:none'>Iniciar sesión</a>
              </div>
            </div>
            <div style='background:#f5f5f5;color:#777;padding:10px;text-align:center;font-size:12px'>
              © ".date('Y')." Sistema de Gestión Turnos
            </div>
          </div>
        </div>";
        $mail->send();
    } catch (Exception $e) {
        error_log("Email registro: {$mail->ErrorInfo}");
    }
}

/* ================= MAIN ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../interfaces/Paciente/registrarPaciente.php');
    exit;
}

// Si hay body pero $_POST vacío, probablemente superaste post_max_size
if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $enviado = (int)$_SERVER['CONTENT_LENGTH'];
    exit("❌ El formulario ($enviado bytes) supera el límite. Aumentá post_max_size/upload_max_filesize o subí una imagen más chica.");
}

try {
    //Datos
    $d = get_post_data();
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('❌ Email inválido.');
    }
    $img_dni_b64 = leer_img_dni_base64(); // se guardará en usuario.img_dni

    //Validar afiliación
    if (!validar_afiliado($conn, $d['numero_documento'], $d['numero_afiliado'])) {
        throw new Exception('❌ No estás registrado como afiliado activo.');
    }

    //Transacción usuario→paciente
    $conn->begin_transaction();

    // Email único
    $st = $conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $st->bind_param("s", $d['email']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El correo ya está registrado.'); }
    $st->close();

    //(Opcional) Documento único en pacientes
    $st = $conn->prepare("SELECT 1 FROM pacientes WHERE nro_documento=? LIMIT 1");
    $st->bind_param("s", $d['numero_documento']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El número de documento ya está registrado.'); }
    $st->close();

    //Crear usuario (con genero + img_dni)
    $id_usuario = crear_usuario($conn, $d, $img_dni_b64);

    //Crear paciente
    $token_qr = bin2hex(random_bytes(16));
    $id_paciente = crear_paciente($conn, $d, $id_usuario, $token_qr);

    $conn->commit();

    //Correo
    enviar_mail_registro($d['email'], $d['nombre'], $d['apellido']);

    echo "<script>alert('✅ Registro exitoso.'); window.location.href='../../interfaces/Paciente/login.php';</script>";

} catch (Throwable $e) {
    if ($conn->errno) { $conn->rollback(); }
    $msg = $e->getMessage();
    echo "<script>alert('".addslashes($msg)."'); window.history.back();</script>";
} finally {
    $conn->close();
}
