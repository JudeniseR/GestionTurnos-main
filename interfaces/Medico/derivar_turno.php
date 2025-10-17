<?php
// Seguridad básica
$rol_requerido = 2;
require_once('../../Logica/General/verificarSesion.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Conexión (tu clase)
require_once('../../Persistencia/conexionBD.php');

$id_medico_login = $_SESSION['id_medico'] ?? 0;
$id_turno = isset($_GET['id_turno']) ? (int)$_GET['id_turno'] : 0;
if ($id_turno <= 0) { http_response_code(400); echo "Falta id_turno"; exit; }

/** Obtiene datos del turno + paciente para cabecera */
function getTurno($id_turno) {
    $cn = ConexionBD::conectar();
    $sql = "
      SELECT t.*, 
             p.id_paciente, p.nro_documento AS dni,
             u.nombre AS nombre_pac, u.apellido AS ape_pac
      FROM turnos t
      INNER JOIN pacientes p ON p.id_paciente = t.id_paciente
      INNER JOIN usuario   u ON u.id_usuario = p.id_usuario
      WHERE t.id_turno = ?";
    $st = $cn->prepare($sql);
    $st->bind_param("i", $id_turno);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    $st->close(); $cn->close();
    return $res ?: [];
}

/** Lista todos los médicos con sus especialidades (excepto el médico logueado) */
function listarMedicos($excluir_id_medico = 0) {
    $cn = ConexionBD::conectar();
    $sql = "
      SELECT m.id_medico,
             u.apellido, u.nombre,
             GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') AS especialidades
      FROM medicos m
      INNER JOIN usuario u ON u.id_usuario = m.id_usuario
      LEFT JOIN medico_especialidad me ON me.id_medico = m.id_medico
      LEFT JOIN especialidades e ON e.id_especialidad = me.id_especialidad
      WHERE (? = 0 OR m.id_medico <> ?)
      GROUP BY m.id_medico, u.apellido, u.nombre
      ORDER BY u.apellido, u.nombre";
    $st = $cn->prepare($sql);
    $st->bind_param("ii", $excluir_id_medico, $excluir_id_medico);
    $st->execute();
    $res = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close(); $cn->close();
    return $res;
}

/** Inserta la derivación (notificación + nuevo turno pendiente) */
function registrarDerivacion($turno_origen, $id_medico_origen, $id_medico_destino, $motivo) {
    $cn = ConexionBD::conectar();
    $cn->set_charset('utf8mb4');
    $cn->begin_transaction();

    try {
        // Datos base del turno original
        $id_turno = $turno_origen['id_turno'];
        $id_paciente = $turno_origen['id_paciente'];
        $obs_original = $turno_origen['observaciones'] ?? '';

        // Registrar notificación
        $msg = "[Derivación] desde médico #$id_medico_origen hacia médico #$id_medico_destino. Motivo: $motivo";
        $sqlNotif = "INSERT INTO notificaciones (id_turno, id_paciente, mensaje, estado)
                     VALUES (?, ?, ?, 'pendiente')";
        $st = $cn->prepare($sqlNotif);
        $st->bind_param("iis", $id_turno, $id_paciente, $msg);
        $st->execute();
        $st->close();

        // Crear nuevo turno pendiente para el médico destino
        $sqlTurno = "INSERT INTO turnos (
                        id_paciente, id_medico, id_estado, id_estudio, 
                        fecha, hora, copago, observaciones, id_recurso, reprogramado
                     )
                     VALUES (?, ?, 1, NULL, NULL, NULL, 0.00, CONCAT('[DERIVADO] ', ?, '\nMotivo: ', ?), NULL, 0)";
        $st2 = $cn->prepare($sqlTurno);
        $st2->bind_param("iiss", $id_paciente, $id_medico_destino, $obs_original, $motivo);
        $st2->execute();
        $st2->close();

        $cn->commit();
        $cn->close();
        return true;

    } catch (Exception $e) {
        $cn->rollback();
        $cn->close();
        error_log("Error derivar turno: " . $e->getMessage());
        return false;
    }
}

// POST: enviar derivación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $destino = (int)($_POST['id_medico_dest'] ?? 0);
    $motivo  = trim($_POST['motivo'] ?? '');
    $tk = getTurno($id_turno);
    if ($destino > 0 && $tk) {
        $ok = registrarDerivacion($tk, (int)($_SESSION['id_medico'] ?? 0), $destino, $motivo);
        header("Location: turnos.php?ok=" . ($ok ? 'derivado' : 'error'));
        exit;
    }
}

// Datos para render
$turno   = getTurno($id_turno);
$medicos = listarMedicos($id_medico_login);
$pacienteCompleto = trim(($turno['ape_pac'] ?? '').', '.($turno['nombre_pac'] ?? ''));
$fecha = $turno['fecha'] ?? '';
$hora  = $turno['hora']  ?? '';
$dni   = $turno['dni']   ?? '-';
$obs   = $turno['observaciones'] ?: 'Sin ficha registrada.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Derivar paciente</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
  body{font-family:Inter,Arial,sans-serif;background:#eaf2fc;margin:0}
  .wrap{max-width:1050px;margin:28px auto;padding:0 14px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 18px;box-shadow:0 8px 20px rgba(16,24,40,.06);margin-bottom:16px}
  .row{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
  .muted{color:#6b7280;font-size:14px}
  textarea{width:100%;min-height:90px;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
  select, input[type=text]{border:1px solid #e5e7eb;border-radius:10px;padding:8px 10px}
  .btn{display:inline-flex;gap:8px;align-items:center;border:none;border-radius:10px;padding:10px 14px;font-weight:700;cursor:pointer}
  .btn-primary{background:#1e88e5;color:#fff}
  .btn-outline{background:#fff;border:1px solid #d1d5db}
  .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h2 style="margin:0"><i class="fa-solid fa-share-nodes"></i> Derivar paciente</h2>
    <a class="btn btn-outline" href="turnos.php"><i class="fa-solid fa-arrow-left"></i> Volver a Turnos</a>
  </div>

  <div class="card">
    <div class="row">
      <div><strong>Paciente:</strong> <?= htmlspecialchars($pacienteCompleto) ?> (DNI <?= htmlspecialchars($dni) ?>)</div>
    </div>
    <div class="row"><div><strong>Turno origen:</strong> <?= htmlspecialchars($fecha.' '.$hora) ?></div></div>
    <div style="margin-top:10px">
      <div class="muted" style="margin-bottom:6px">Ficha médica (última):</div>
      <textarea readonly><?= "[Ficha]\n".$obs ?></textarea>
    </div>
  </div>

  <form method="post" class="card">
    <h3 style="margin-top:0">Seleccionar médico destino</h3>
    <?php if(!$medicos): ?>
      <div class="muted">No se encontraron médicos para derivar.</div>
    <?php else: ?>
      <div class="row" style="gap:10px;margin-bottom:10px">
        <select name="id_medico_dest" required>
          <option value="">-- Elegí un médico --</option>
          <?php foreach($medicos as $m): 
            $label = trim(mb_strtoupper($m['apellido']).", ".$m['nombre'])." — ".($m['especialidades'] ?: "Sin especialidad");
          ?>
            <option value="<?= (int)$m['id_medico'] ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:12px">
        <div class="muted" style="margin-bottom:6px">Motivo de derivación</div>
        <textarea name="motivo" placeholder="Breve motivo para el colega..." required></textarea>
      </div>
      <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar derivación</button>
    <?php endif; ?>
  </form>
</div>
</body>
</html>
