<?php

require_once('../../Persistencia/conexionBD.php');
require_once('../General/envioNotif.php');
require_once('../../interfaces/mostrarAlerta.php');

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
        'estado_civil'     => clean_input($_POST['estado_civil'] ?? ''),
    ];
}

function leer_img_dni_base64_multiple() {
    if (!isset($_FILES['imagen_dni']) || !isset($_FILES['imagen_dni']['tmp_name'])) {
        return null;
    }

    $imagenes = $_FILES['imagen_dni'];

    // Validar máximo 2 archivos
    if (count($imagenes['tmp_name']) > 2) {
        throw new Exception("❌ Solo puede cargar hasta 2 imágenes (frente y dorso).");
    }

    $imagenes_b64 = [];

    // Procesar cada imagen
    for ($i = 0; $i < count($imagenes['tmp_name']); $i++) {
        if (!is_uploaded_file($imagenes['tmp_name'][$i])) continue;

        $tmp_path = $imagenes['tmp_name'][$i];
        $tipo_mime = mime_content_type($tmp_path);

        if (!in_array($tipo_mime, ['image/jpeg','image/jpg','image/png','image/gif'])) {
            throw new Exception("❌ Uno de los archivos no es una imagen válida (JPG, PNG, GIF).");
        }

        switch ($tipo_mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $img_resource = imagecreatefromjpeg($tmp_path);
                break;
            case 'image/png':
                $img_resource = imagecreatefrompng($tmp_path);
                break;
            case 'image/gif':
                $img_resource = imagecreatefromgif($tmp_path);
                break;
        }

        if (!$img_resource) {
            throw new Exception("❌ Error procesando imagen.");
        }

        $ancho_orig = imagesx($img_resource);
        $alto_orig = imagesy($img_resource);
        $max_ancho = 1920;

        if ($ancho_orig > $max_ancho) {
            $ratio = $max_ancho / $ancho_orig;
            $nuevo_ancho = $max_ancho;
            $nuevo_alto = (int) ($alto_orig * $ratio);

            $img_redim = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
            imagecopyresampled($img_redim, $img_resource, 0, 0, 0, 0,
                                $nuevo_ancho, $nuevo_alto,
                                $ancho_orig, $alto_orig);

            imagedestroy($img_resource);
            $img_resource = $img_redim;
        }

        ob_start();
        imagejpeg($img_resource, null, 85);
        $img_data = ob_get_clean();
        imagedestroy($img_resource);

        $imagenes_b64[] = base64_encode($img_data);
    }

    return json_encode($imagenes_b64); // ← Guardamos JSON
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

function obtener_afiliados_menores(mysqli $conn, $documento_titular) {
    $sql = "SELECT id FROM afiliados WHERE numero_documento = ? AND tipo_beneficiario = 'titular' LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param("s", $documento_titular);
    $st->execute();
    $result = $st->get_result();
    $row = $result->fetch_assoc();
    $st->close();
    
    if (!$row) return [];
    
    $id_titular_afiliado = $row['id'];
    
    $sql = "SELECT 
                numero_documento,
                nombre,
                apellido,
                fecha_nacimiento,
                tipo_beneficiario
            FROM afiliados
            WHERE id_titular = ? 
            AND tipo_beneficiario = 'hijo menor'
            AND estado = 'activo'
            AND fecha_nacimiento IS NOT NULL";
    
    $st = $conn->prepare($sql);
    $st->bind_param("i", $id_titular_afiliado);
    $st->execute();
    $result = $st->get_result();
    
    $menores = [];
    while ($row = $result->fetch_assoc()) {
        $fecha_nac = new DateTime($row['fecha_nacimiento']);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac)->y;
        
        if ($edad < 18) {
            $menores[] = $row;
        }
    }
    
    $st->close();
    return $menores;
}

function calcular_tipo_documento_menor($fecha_nacimiento) {
    $fecha_nac = new DateTime($fecha_nacimiento);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
    
    return 'DNI';
}

/* ================= MAIN ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../interfaces/Paciente/registrarPaciente.php');
    exit;
}

if (empty($_POST) && !empty($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0) {
    $enviado = (int)$_SERVER['CONTENT_LENGTH'];
    exit("❌ El formulario ($enviado bytes) supera el límite.");
}

try {

    $d = get_post_data();

    /* === VALIDACIÓN DE MAYORÍA DE EDAD === */
    $fecha_nac = new DateTime($d['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;

    if ($edad < 18) {
        throw new Exception('❌ Solo se pueden registrar pacientes mayores de edad.');
    }
    /* ===================================== */

    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('❌ Email inválido.');
    }

    $img_dni_b64 = leer_img_dni_base64_multiple();
    if (!$img_dni_b64) {
        throw new Exception('❌ Debe subir una imagen del DNI.');
    }

    if (!validar_afiliado($conn, $d['numero_documento'], $d['numero_afiliado'])) {
        throw new Exception('❌ No estás registrado como afiliado activo.');
    }

    $afiliados_menores = obtener_afiliados_menores($conn, $d['numero_documento']);

    $conn->begin_transaction();

    $st = $conn->prepare("SELECT 1 FROM usuarios WHERE email=? LIMIT 1");
    $st->bind_param("s", $d['email']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El correo ya está registrado.'); }
    $st->close();

    $st = $conn->prepare("SELECT 1 FROM pacientes WHERE nro_documento=? LIMIT 1");
    $st->bind_param("s", $d['numero_documento']);
    $st->execute(); $st->store_result();
    if ($st->num_rows > 0) { $st->close(); throw new Exception('❌ El número de documento ya está registrado.'); }
    $st->close();

    $token_qr = bin2hex(random_bytes(16));
    $activo = 1;
    $password_hashed = password_hash($d['password'], PASSWORD_DEFAULT);
    
    $st = $conn->prepare("CALL insertar_usuario_paciente(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, @id_paciente)");
    $st->bind_param(
        "ssssisssssssss",
        $d['nombre'], 
        $d['apellido'], 
        $d['email'], 
        $password_hashed,
        $activo,
        $d['genero'], 
        $d['tipo_documento'], 
        $d['numero_documento'],
        $d['fecha_nacimiento'], 
        $d['domicilio'], 
        $d['numero_contacto'],
        $d['estado_civil'], 
        $token_qr, 
        $img_dni_b64
    );
    $st->execute();
    $st->close();
    
    $result = $conn->query("SELECT @id_paciente as id_paciente");
    $row = $result->fetch_assoc();
    $id_paciente_titular = $row['id_paciente'];
    
    if (!$id_paciente_titular) {
        throw new Exception('❌ Error al obtener el ID del paciente registrado.');
    }

    $menores_registrados = 0;
    foreach ($afiliados_menores as $menor) {

        $st = $conn->prepare("SELECT 1 FROM pacientes WHERE nro_documento=? LIMIT 1");
        $st->bind_param("s", $menor['numero_documento']);
        $st->execute(); $st->store_result();
        
        if ($st->num_rows > 0) {
            $st->close();
            continue;
        }
        $st->close();
        
        $token_qr_menor = bin2hex(random_bytes(16));
        $tipo_doc_menor = calcular_tipo_documento_menor($menor['fecha_nacimiento']);
        
        $st = $conn->prepare("CALL insertar_paciente_afiliado_menor(?, ?, ?, ?, ?, ?, ?)");
        $st->bind_param(
            "issssss",
            $id_paciente_titular,
            $tipo_doc_menor,
            $menor['numero_documento'],
            $menor['nombre'],
            $menor['apellido'],
            $menor['fecha_nacimiento'],
            $token_qr_menor
        );
        $st->execute();
        $st->close();
        
        $menores_registrados++;
    }

    $conn->commit();

    $datosCorreo = [
        'email'    => $d['email'],
        'nombre'   => $d['nombre'],
        'apellido' => $d['apellido'],
        'menores_registrados' => $menores_registrados
    ];
    enviarNotificacion('registro', $datosCorreo);

    $mensaje = '✅ Registro exitoso.';
    //if ($menores_registrados > 0) {
    //    $mensaje .= " Se registraron $menores_registrados afiliado(s) menor(es) asociado(s).";
    //}

    // Llamada a la función mostrarAlerta
    mostrarAlerta('success', $mensaje);

} catch (Throwable $e) {
    if ($conn->errno) { $conn->rollback(); }
    $msg = $e->getMessage();
    echo "<script>alert('".addslashes($msg)."'); window.history.back();</script>";
} finally {
    $conn->close();
}
