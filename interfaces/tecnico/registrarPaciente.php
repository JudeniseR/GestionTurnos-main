<?php
/**
 * interfaces/tecnico/registrarPaciente.php
 * Registra un paciente con TODOS los campos de la tabla `pacientes`:
 * id_usuario, tipo_documento, nro_documento, fecha_nacimiento, direccion,
 * telefono, email, estado_civil, token_qr
 *
 * Requiere sesión de TÉCNICO (rol_id = 4).
 * Si no existe un usuario paciente con ese email, lo crea (rol=1) con password temporal.
 *
 * POST OBLIGATORIO:
 *  nombre, apellido, email,
 *  tipo_documento, nro_documento,
 *  fecha_nacimiento (YYYY-MM-DD),
 *  direccion, telefono, estado_civil
 *
 * RESPUESTA JSON:
 *  { success:true, id_paciente, id_usuario, token_qr, password_temp? }
 */

error_reporting(E_ALL); ini_set('display_errors', 1);
header('Content-Type: application/json; charset=UTF-8');
session_start();

require_once '../../Persistencia/conexionBD.php';

// --- Guardia de sesión: técnico ---
if (!isset($_SESSION['id_usuario']) || (int)($_SESSION['rol_id'] ?? 0) !== 4) {
  http_response_code(401);
  echo json_encode(['success' => false, 'error' => 'NO_SESSION']);
  exit;
}

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// --- Helpers ---
function norm($v){ return trim((string)$v); }
function onlyDigits($v){ return preg_replace('/\D+/', '', (string)$v); }

// --- Inputs obligatorios (usuario + pacientes) ---
$nombre          = norm($_POST['nombre']           ?? '');
$apellido        = norm($_POST['apellido']         ?? '');
$email           = strtolower(norm($_POST['email'] ?? ''));

$tipo_documento  = norm($_POST['tipo_documento']   ?? '');
$nro_documento   = onlyDigits($_POST['nro_documento'] ?? '');
$fecha_nac       = norm($_POST['fecha_nacimiento'] ?? ''); // YYYY-MM-DD
$direccion       = norm($_POST['direccion']        ?? '');
$telefono        = norm($_POST['telefono']         ?? '');
$estado_civil    = norm($_POST['estado_civil']     ?? '');

// --- Validaciones duras ---
$errors = [];
if ($nombre === '')                                 $errors[] = 'Nombre es obligatorio';
if ($apellido === '')                               $errors[] = 'Apellido es obligatorio';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido';
if ($tipo_documento === '')                         $errors[] = 'Tipo de documento es obligatorio';
if ($nro_documento === '')                          $errors[] = 'Número de documento es obligatorio';
if ($fecha_nac === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nac)) $errors[] = 'Fecha de nacimiento inválida (YYYY-MM-DD)';
if ($direccion === '')                              $errors[] = 'Dirección es obligatoria';
if ($telefono === '')                               $errors[] = 'Teléfono es obligatorio';
if ($estado_civil === '')                           $errors[] = 'Estado civil es obligatorio';

if ($errors) {
  echo json_encode(['success' => false, 'error' => implode('. ', $errors)]);
  exit;
}

try {
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  $conn->begin_transaction();

  // 1) DNI único en pacientes
  $stmt = $conn->prepare("SELECT 1 FROM pacientes WHERE nro_documento = ? LIMIT 1");
  $stmt->bind_param('s', $nro_documento);
  $stmt->execute();
  if ($stmt->get_result()->fetch_row()) {
    throw new Exception('El DNI ya está registrado como paciente');
  }

  // 2) Usuario por email (rol paciente=1)
  $stmt = $conn->prepare("SELECT id_usuario, id_rol FROM usuario WHERE email = ? LIMIT 1");
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $u = $stmt->get_result()->fetch_assoc();

  $id_usuario = null;
  $password_temp = null;

  if ($u) {
    if ((int)$u['id_rol'] !== 1) {
      throw new Exception('El email ya existe y NO corresponde a un usuario paciente');
    }
    $id_usuario = (int)$u['id_usuario'];

    // (Opcional) actualizar nombre/apellido del usuario
    $stmt = $conn->prepare("UPDATE usuario SET nombre=?, apellido=? WHERE id_usuario=?");
    $stmt->bind_param('ssi', $nombre, $apellido, $id_usuario);
    $stmt->execute();
  } else {
    // Crear usuario paciente
    $password_temp = substr($nro_documento, -4) . rand(100, 999);
    $hash = password_hash($password_temp, PASSWORD_DEFAULT);

    // Ajusta si tu tabla usuario tiene más/menos columnas
    $stmt = $conn->prepare(
      "INSERT INTO usuario (nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni)
       VALUES (?, ?, ?, ?, 1, 1, '', '')"
    );
    $stmt->bind_param('ssss', $nombre, $apellido, $email, $hash);
    $stmt->execute();
    $id_usuario = $stmt->insert_id;
  }

  // 3) Generar token QR único
  $token_qr = hash('md5', $email . '|' . $nro_documento . '|' . microtime(true));

  // 4) Insertar TODOS los campos en `pacientes`
  $stmt = $conn->prepare(
    "INSERT INTO pacientes
       (id_usuario, tipo_documento, nro_documento, fecha_nacimiento, direccion, telefono, email, estado_civil, token_qr)
     VALUES (?,?,?,?,?,?,?,?,?)"
  );
  $stmt->bind_param(
    'issssssss',
    $id_usuario,
    $tipo_documento,
    $nro_documento,
    $fecha_nac,
    $direccion,
    $telefono,
    $email,
    $estado_civil,
    $token_qr
  );
  $stmt->execute();
  $id_paciente = $stmt->insert_id;

  $conn->commit();

  $out = [
    'success'     => true,
    'id_paciente' => $id_paciente,
    'id_usuario'  => $id_usuario,
    'token_qr'    => $token_qr
  ];
  if ($password_temp) $out['password_temp'] = $password_temp;

  echo json_encode($out, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
  if ($conn->errno) { $conn->rollback(); }
  echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
