<?php
/* ========= SEGURIDAD Y CONEXIÓN ========= */
$rol_requerido = 2;
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }

$id_medico = $_SESSION['id_medico'] ?? null;
$nombre    = $_SESSION['nombre']     ?? '';
$apellido  = $_SESSION['apellido']   ?? '';
$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));

/* ========= UTIL EMAIL (placeholder; luego migrar a PHPMailer) ========= */
function notificarPaciente($email, $asunto, $mensajeHtml) {
  if(!$email) return false;
  $headers  = "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=UTF-8\r\n";
  $headers .= "From: GestionTurnos <no-reply@localhost>\r\n";
  return @mail($email, $asunto, $mensajeHtml, $headers);
}

/* ========= HELPERS BACKEND ========= */
/* Solo permitido si faltan <= 24h y > 0 para el turno */
function dentroVentanaHasta24h($fecha, $hora) {
  try {
    if(!$fecha) return false;
    $turno = new DateTime($fecha.' '.$hora);
    $ahora = new DateTime();
    $diffS = $turno->getTimestamp() - $ahora->getTimestamp();
    if ($diffS <= 0) return false;
    $horas = $diffS / 3600;
    return ($horas <= 24);
  } catch(Throwable $e) { return false; }
}

/* ========= ACCIONES POST ========= */

/* Confirmar PENDIENTE -> CONFIRMADO */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='aceptar') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

  $id_turno=(int)($_POST['id_turno']??0);
  if($id_turno<=0){ echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

  try{
    $cn=ConexionBD::conectar(); $cn->set_charset('utf8mb4');
    $st=$cn->prepare("UPDATE turnos SET id_estado=2 WHERE id_turno=? AND id_medico=? AND id_estado=1");
    $st->bind_param('ii',$id_turno,$id_medico); $st->execute();
    echo json_encode($st->affected_rows>0?['ok'=>true,'msg'=>'Turno aceptado y confirmado']:['ok'=>false,'msg'=>'No se pudo aceptar el turno']);
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
  exit;
}

/* Atender CONFIRMADO -> ATENDIDO (ficha) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='atender') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

  $id_turno=(int)($_POST['id_turno']??0);
  $diag = trim($_POST['diagnostico'] ?? '');
  $ind  = trim($_POST['indicaciones'] ?? '');
  $not  = trim($_POST['notas'] ?? '');
  if($id_turno<=0){ echo json_encode(['ok'=>false,'msg'=>'ID invalido']); exit; }

  $stamp=date('d/m/Y H:i');
  $ficha="----\n[Ficha médica $stamp]\n";
  if($diag!=='') $ficha.="Diagnóstico: $diag\n";
  if($ind!=='')  $ficha.="Indicaciones: $ind\n";
  if($not!=='')  $ficha.="Notas: $not\n";

  try{
    $cn=ConexionBD::conectar(); $cn->set_charset('utf8mb4');
    $sql="UPDATE turnos
            SET id_estado=3,
                observaciones=TRIM(CONCAT(IFNULL(observaciones,''), '\n', ?))
          WHERE id_turno=? AND id_medico=? AND id_estado=2";
    $st=$cn->prepare($sql);
    $st->bind_param('sii',$ficha,$id_turno,$id_medico); $st->execute();
    echo json_encode($st->affected_rows>0?['ok'=>true,'msg'=>'Atención guardada']:['ok'=>false,'msg'=>'No se pudo atender']);
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
  exit;
}

/* Cancelar (solo dentro de las 24h previas) + e-mail */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='cancelar') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

  $id_turno=(int)($_POST['id_turno']??0);
  $motivo  = trim($_POST['motivo'] ?? '');
  if($id_turno<=0){ echo json_encode(['ok'=>false,'msg'=>'ID invalido']); exit; }

  try{
    $cn=ConexionBD::conectar(); $cn->set_charset('utf8mb4');
    $q=$cn->prepare("SELECT t.fecha, t.hora, p.email,
                            CONCAT(u.apellido, ', ', u.nombre) AS paciente
                       FROM turnos t
                       JOIN pacientes p ON p.id_paciente = t.id_paciente
                       JOIN usuarios  u ON u.id_usuario  = p.id_usuario
                      WHERE t.id_turno=? AND t.id_medico=? AND t.id_estado IN (1,2,5)");
    $q->bind_param('ii',$id_turno,$id_medico); $q->execute();
    $info=$q->get_result()->fetch_assoc();
    if(!$info){ echo json_encode(['ok'=>false,'msg'=>'Turno no válido']); exit; }

    if(!dentroVentanaHasta24h($info['fecha'],$info['hora'])){
      echo json_encode(['ok'=>false,'msg'=>'Solo se puede cancelar dentro de las 24 horas previas al turno']); exit;
    }

    $obs="----\n[Cancelación ".date('d/m/Y H:i')."]\n".($motivo? "Motivo: $motivo\n":'');
    $up=$cn->prepare("UPDATE turnos
                         SET id_estado=4,
                             observaciones=TRIM(CONCAT(IFNULL(observaciones,''), '\n', ?))
                       WHERE id_turno=? AND id_medico=?");
    $up->bind_param('sii',$obs,$id_turno,$id_medico); $up->execute();

    if($up->affected_rows>0){
      $html="<p>Hola {$info['paciente']},</p>
             <p>Tu turno del <strong>{$info['fecha']} {$info['hora']}</strong> fue <strong>cancelado</strong>.</p>".
             ($motivo? "<p><em>Motivo: ".htmlspecialchars($motivo)."</em></p>":'').
             "<p>Podés solicitar una nueva fecha desde el sistema.</p>";
      notificarPaciente($info['email']??'', 'Cancelación de turno', $html);
      echo json_encode(['ok'=>true,'msg'=>'Turno cancelado y paciente notificado']);
    }else{
      echo json_encode(['ok'=>false,'msg'=>'No se pudo cancelar']);
    }
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
  exit;
}

/* Reprogramar (solo dentro de las 24h previas si está vigente) => estado=5 */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='reprogramar') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

  $id_turno=(int)($_POST['id_turno']??0);
  $nf = trim($_POST['nueva_fecha'] ?? '');
  $nh = trim($_POST['nueva_hora']  ?? '');
  if($id_turno<=0 || !$nf || !$nh){ echo json_encode(['ok'=>false,'msg'=>'Datos incompletos']); exit; }

  try{
    $cn=ConexionBD::conectar(); 
    $cn->set_charset('utf8mb4');

    /* Solo estados vigentes (NO desde cancelado) */
    $q=$cn->prepare(
      "SELECT t.fecha, t.hora, t.id_estado,
              p.email, CONCAT(u.apellido, ', ', u.nombre) AS paciente
         FROM turnos t
         JOIN pacientes p ON p.id_paciente = t.id_paciente
         JOIN usuarios  u ON u.id_usuario  = p.id_usuario
        WHERE t.id_turno=? AND t.id_medico=? AND t.id_estado IN (1,2,5)"
    );
    $q->bind_param('ii',$id_turno,$id_medico); 
    $q->execute();
    $info=$q->get_result()->fetch_assoc();
    if(!$info){ echo json_encode(['ok'=>false,'msg'=>'Turno no válido']); exit; }

    if(!dentroVentanaHasta24h($info['fecha'],$info['hora'])){
      echo json_encode(['ok'=>false,'msg'=>'Solo se puede reprogramar dentro de las 24 horas previas al turno']); 
      exit;
    }

   /* Registrar la reprogramación */
$obs="----\n[Reprogramación ".date('d/m/Y H:i')."]\n".
     "Original: {$info['fecha']} {$info['hora']}\n".
     "Nueva fecha: $nf $nh\n";

$chk=$cn->prepare("SELECT COUNT(*) c 
                 FROM turnos 
                WHERE id_medico=? AND fecha=? AND hora=? 
                  AND id_turno<>? AND id_estado IN (1,2,3,5)");
$chk->bind_param('issi',$id_medico,$nf,$nh,$id_turno);
$chk->execute();
if(($chk->get_result()->fetch_assoc()['c'] ?? 0) > 0){
  echo json_encode(['ok'=>false,'msg'=>'Ese horario ya está ocupado en tu agenda']); 
  exit;
}

$up=$cn->prepare(
  "UPDATE turnos
      SET id_estado=5, fecha=?, hora=?,
          reprogramado=1,
          observaciones=TRIM(CONCAT(IFNULL(observaciones,''), '\n', ?))
    WHERE id_turno=? AND id_medico=?"
);
$up->bind_param('sssii',$nf,$nh,$obs,$id_turno,$id_medico); 
$up->execute();


    if($up->affected_rows>0){
      $html="<p>Hola {$info['paciente']},</p>
             <p>Tu turno fue <strong>reprogramado</strong>.</p>
             <p><strong>Nueva fecha:</strong> {$nf} {$nh}</p>";
      notificarPaciente($info['email']??'', 'Reprogramación de turno', $html);
      echo json_encode(['ok'=>true,'msg'=>'Turno reprogramado y paciente notificado']);
    }else{
      echo json_encode(['ok'=>false,'msg'=>'No se pudo reprogramar']);
    }
  }catch(Throwable $e){ 
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); 
  }
  exit;
}

/* Rechazar derivación (pendiente -> cancelado) */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='rechazar_derivacion') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }
  $id_turno=(int)($_POST['id_turno']??0);
  if($id_turno<=0){ echo json_encode(['ok'=>false,'msg'=>'ID invalido']); exit; }

  try{
    $cn=ConexionBD::conectar(); $cn->set_charset('utf8mb4');
    $obs="----\n[Cancelación ".date('d/m/Y H:i')."]\nMotivo: Derivación rechazada\n";
    $up=$cn->prepare("UPDATE turnos
                         SET id_estado=4,
                             observaciones=TRIM(CONCAT(IFNULL(observaciones,''), '\n', ?))
                      WHERE id_turno=? AND id_medico=? AND id_estado=1");
    $up->bind_param('sii',$obs,$id_turno,$id_medico); $up->execute();
    echo json_encode($up->affected_rows>0?['ok'=>true,'msg'=>'Derivación rechazada']:['ok'=>false,'msg'=>'No se pudo rechazar']);
  }catch(Throwable $e){ echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]); }
  exit;
}

/* Aceptar derivación: set fecha/hora (si hay disponibilidad) y confirmar */
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__action']??'')==='aceptar_derivacion') {
  header('Content-Type: application/json; charset=utf-8');
  if(!$id_medico){ echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

  $id_turno=(int)($_POST['id_turno']??0);
  $fecha=trim($_POST['fecha']??'');
  $hora =trim($_POST['hora']??'');

  if($id_turno<=0 || !$fecha || !$hora){
    echo json_encode(['ok'=>false,'msg'=>'Completá fecha y hora']); exit;
  }

  try{
    $cn=ConexionBD::conectar();
    $cn->set_charset('utf8mb4');
    $cn->begin_transaction();

    // Verifico que el turno sea del médico logueado y esté PENDIENTE (1)
    $q=$cn->prepare("SELECT id_paciente FROM turnos WHERE id_turno=? AND id_medico=? AND id_estado=1 FOR UPDATE");
    $q->bind_param('ii',$id_turno,$id_medico);
    $q->execute();
    $row=$q->get_result()->fetch_assoc();
    if(!$row){ throw new Exception('Turno no válido o ya gestionado'); }
    $id_paciente=(int)$row['id_paciente'];

    // Validar disponibilidad
    $chk=$cn->prepare("SELECT COUNT(*) AS c
                         FROM turnos
                        WHERE id_medico=? AND fecha=? AND hora=? 
                          AND id_estado IN (1,2,3,5)  
                          AND id_turno<>?");
    $chk->bind_param('issi',$id_medico,$fecha,$hora,$id_turno);
    $chk->execute();
    $c = ($chk->get_result()->fetch_assoc()['c'] ?? 0);
    if($c>0){ throw new Exception('Ese horario ya está ocupado en tu agenda. Elige otro.'); }

    // Actualizo el turno: CONFIRMADO (2)
    $u=$cn->prepare("UPDATE turnos SET fecha=?, hora=?, id_estado=2 WHERE id_turno=?");
    $u->bind_param('ssi',$fecha,$hora,$id_turno);
    $u->execute();

    // Marcar notificaciones (opcional)
    @$cn->query("UPDATE notificaciones 
                 SET estado='procesada' 
                 WHERE id_paciente=".$id_paciente." 
                   AND estado='pendiente' 
                   AND mensaje LIKE '[Derivación]%'");

    $cn->commit();
    echo json_encode(['ok'=>true,'msg'=>'Derivación aceptada y turno confirmado']);
  }catch(Throwable $e){
    if(isset($cn)) $cn->rollback();
    echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
  }
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestión de Turnos - <?= htmlspecialchars($displayRight) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --primary:#1976d2; --success:#2e7d32; --danger:#c62828;
  --bg:#EAF4FF; --card:#fff; --muted:#6b7280;
  --radius:12px; --shadow:0 3px 8px rgba(0,0,0,.08);
}
*{box-sizing:border-box}
body{margin:0;font-family:'Inter',sans-serif;background:var(--bg);color:#0f172a}

/* Navbar */
.navbar{background:var(--card);box-shadow:var(--shadow);
  display:flex;justify-content:space-between;align-items:center;
  padding:10px 20px;position:sticky;top:0;z-index:100;}
.navbar a{text-decoration:none;color:#111;font-weight:600;margin-right:10px;padding:6px 10px;border-radius:8px}
.navbar a:hover{background:#e3f2fd;color:var(--primary)}
.chip{background:#e8f0fe;padding:8px 10px;border-radius:8px;color:#1976d2;font-weight:600}

/* Sub-navbar */
.subnav{background:#fff;display:flex;align-items:center;gap:12px;
  padding:10px 20px;border-bottom:1px solid #e0e0e0;position:sticky;top:56px;z-index:99;flex-wrap:wrap;}
.tab{background:#f8fafc;border:none;border-radius:8px;padding:8px 12px;font-weight:600;cursor:pointer;
  display:flex;align-items:center;gap:6px;color:#555;transition:0.25s;}
.tab:hover{background:#e3f2fd;color:var(--primary)}
.tab.active{background:var(--primary);color:#fff}
.search-input{margin-left:auto;display:flex;align-items:center;gap:6px}
.search-input input{border:1px solid #ddd;border-radius:8px;padding:8px;width:260px;}

/* Cards */
.container{max-width:1200px;margin:20px auto;padding:0 16px;display:flex;flex-direction:column;gap:20px}
.card{background:var(--card);padding:16px;border-radius:var(--radius);box-shadow:var(--shadow);}
.section-title{display:flex;align-items:center;gap:8px;margin:0 0 10px 0}
.section-title .pill{font-size:12px;padding:4px 8px;border:1px solid #e5e7eb;border-radius:999px;color:#555;background:#f8fafc}

/* Items */
.turno-item{border:1px solid #e0e0e0;border-left:5px solid var(--primary);
  padding:12px;border-radius:var(--radius);margin-bottom:10px;transition:.2s;
  display:flex;justify-content:space-between;align-items:center;gap:12px;}
.turno-item:hover{transform:scale(1.01);box-shadow:0 2px 8px rgba(0,0,0,0.08)}
.turno-main{display:flex;flex-direction:column}
.turno-nombre{font-weight:800}
.turno-fecha-hora{font-size:16px;font-weight:700;letter-spacing:.2px;color:#0d47a1;display:flex;align-items:center;gap:8px;}
.turno-fecha-hora i{opacity:.9}
.muted{color:var(--muted);font-size:13px}
.badge{padding:4px 8px;border-radius:8px;font-size:12px;font-weight:600;display:inline-block;margin-top:5px}
.pendiente{background:#fff8e1;color:#9a6a00;}
.confirmado{background:#e3f2fd;color:#0d47a1;}
.cancelado{background:#ffebee;color:#b71c1c;}
.atendido{background:#e8f5e9;color:#1b5e20;}
.reprogramado{background:#ede7f6;color:#4527a0;}
.vencido{background:#ffe9e9;color:#b91c1c;border:1px solid #fecaca}
.note{font-size:12px;color:#6b7280;margin-top:6px}

/* Botones inline */
.btn-inline{border:none;border-radius:8px;padding:8px 12px;font-weight:700;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.btn-outline{background:#fff;border:1px solid #ddd}
.btn-danger{background:var(--danger);color:#fff}
.btn-disabled{opacity:.5;cursor:not-allowed}

/* Modal */
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;
       background:rgba(0,0,0,0.4);justify-content:center;align-items:center;z-index:200;}
.modal-content{background:#fff;padding:20px;border-radius:12px;max-width:720px;width:95%;
               box-shadow:var(--shadow);animation:fadeIn .2s;max-height:90vh;overflow:auto}
@keyframes fadeIn{from{opacity:0;transform:scale(.98)}to{opacity:1;transform:scale(1)}}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:10px}
.close{cursor:pointer;font-size:22px;color:#aaa}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.form-grid .full{grid-column:1 / -1}
label{font-size:13px;color:#374151;font-weight:700;margin-bottom:4px;display:block}
input[type="text"], input[type="date"], input[type="time"], textarea, select{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:10px;font:inherit;background:#fafafa}
textarea{min-height:90px;resize:vertical}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
</style>
</head>
<body>

<!-- nav -->
    <?php include('navMedico.php'); ?> 



<div class="subnav">
  <button class="tab active" data-estado="confirmado"><i class="fa-solid fa-calendar-check"></i> Confirmados</button>
  <button class="tab" data-estado="pendiente"><i class="fa-solid fa-bell"></i> Pendientes</button>
  <button class="tab" data-estado="reprogramado"><i class="fa-solid fa-rotate"></i> Reprogramados</button>
  <button class="tab" data-estado="cancelado"><i class="fa-solid fa-ban"></i> Cancelados</button>
  <button class="tab" data-estado="vencido"><i class="fa-solid fa-triangle-exclamation"></i> Vencidos</button>
  <button class="tab" data-estado="atendido"><i class="fa-solid fa-user-check"></i> Atendidos</button>
  <div class="search-input">
    <input type="text" id="busqueda" placeholder="Buscar paciente, DNI o fecha...">
    <i class="fa-solid fa-search" style="color:var(--primary)"></i>
  </div>
</div>

<div class="container">
  <!-- CONFIRMADOS (hoy + todos futuros) -->
  <div class="card" id="cardConfirmados" style="display:block">
    <div class="section-title"><h2 style="margin:0">Turnos Confirmados del día</h2><span class="pill">Fecha actual</span></div>
    <div id="listadoHoy"><div class="muted">Cargando...</div></div>

    <hr style="margin:18px 0;border:none;border-top:1px solid #eee">

    <div class="section-title"><h2 style="margin:0">Próximos turnos confirmados</h2><span class="pill">Todos</span></div>
    <div id="listadoConfirmados"><div class="muted">Cargando...</div></div>
  </div>

  <!-- OTRAS PESTAÑAS -->
  <div class="card" id="cardOtros" style="display:none">
    <div class="section-title"><h2 id="tituloSeccion" style="margin:0">Turnos</h2></div>
    <div id="listadoTurnos"><div class="muted">Cargando...</div></div>
  </div>

  <!-- VENCIDOS -->
  <div class="card" id="cardVencidos" style="display:none">
    <div class="section-title"><h2 style="margin:0">Turnos Vencidos</h2><span class="pill">No atendidos en 24 h</span></div>
    <div id="listadoVencidos"><div class="muted">Cargando...</div></div>
  </div>

  <!-- ATENDIDOS -->
  <div class="card" id="cardAtendidos" style="display:none">
    <div class="section-title"><h2 style="margin:0">Turnos Atendidos de HOY</h2><span class="pill">Fecha actual</span></div>
    <div id="listadoAtHoy"><div class="muted">Cargando...</div></div>
    <hr style="margin:18px 0;border:none;border-top:1px solid #eee">
    <div class="section-title"><h2 style="margin:0">Todos los Atendidos</h2><span class="pill">Histórico</span></div>
    <div id="listadoAtTodos"><div class="muted">Cargando...</div></div>
  </div>
</div>

<!-- Modal -->
<div class="modal" id="modalTurno">
  <div class="modal-content">
    <div class="modal-header"><h3 id="modalTitle">Detalle</h3><span class="close" id="cerrarModal">&times;</span></div>
    <div id="detalleTurno"></div>
  </div>
</div>

<script>
(() => {
  /* ---------- util front ---------- */
  // CORREGIDO: rutas relativas a /interfaces/Medico/
  const API='api/turnos_list.php';

  const busqueda=document.getElementById('busqueda');

  const cardConfirmados=document.getElementById('cardConfirmados');
  const listadoConfirmados=document.getElementById('listadoConfirmados');
  const listadoHoy=document.getElementById('listadoHoy');

  const cardOtros=document.getElementById('cardOtros');
  const listado=document.getElementById('listadoTurnos');
  const titulo=document.getElementById('tituloSeccion');

  const cardVencidos=document.getElementById('cardVencidos');
  const listadoVencidos=document.getElementById('listadoVencidos');

  const cardAtendidos=document.getElementById('cardAtendidos');
  const listadoAtHoy=document.getElementById('listadoAtHoy');
  const listadoAtTodos=document.getElementById('listadoAtTodos');

  const modal=document.getElementById('modalTurno');
  const modalTitle=document.getElementById('modalTitle');
  const detalle=document.getElementById('detalleTurno');
  const cerrarModal=document.getElementById('cerrarModal');

  let estadoActivo='confirmado';

  function todayISO(){return new Date().toISOString().slice(0,10);}
  function normalizarEstado(e){const v=(e??'').toString().toLowerCase();
    if(['1','pendiente'].includes(v))return'pendiente';
    if(['2','confirmado'].includes(v))return'confirmado';
    if(['3','atendido'].includes(v))return'atendido';
    if(['4','cancelado'].includes(v))return'cancelado';
    if(['5','reprogramado'].includes(v))return'reprogramado';
    return(v||'pendiente');
  }

  function isExpired(fecha, hora){
    try{ if(!fecha) return false; const ts=new Date(`${fecha}T${(hora||'').slice(0,8)}`); return ts.getTime()<Date.now(); }
    catch{ return false; }
  }

  /* Habilitado solo si faltan <=24h y >0 */
  function habilitadoHasta24h(fecha, hora){
    try{
      if(!fecha) return false;
      const ts = new Date(`${fecha}T${(hora||'').slice(0,8) || '00:00:00'}`);
      const diffH = (ts - Date.now()) / 36e5;
      return diffH > 0 && diffH <= 24;
    }catch{ return false; }
  }

  /* Vencido si pasaron >= 24h desde la hora del turno */
  function isVencido24h(fecha, hora){
    try {
      if(!fecha) return false;
      const ts = new Date(`${fecha}T${(hora||'').slice(0,8) || '00:00:00'}`);
      const diffH = (Date.now() - ts.getTime()) / 36e5;
      return diffH >= 24;
    } catch { return false; }
  }

  function extractLastBlock(observaciones, startsWith){
    if(!observaciones) return '';
    const lines = observaciones.split('\n');
    let cur = [], chunks = [];
    for(const ln of lines){
      if(ln.startsWith('----')){ if(cur.length) { chunks.push(cur.join('\n')); cur=[]; } continue; }
      cur.push(ln);
    }
    if(cur.length) chunks.push(cur.join('\n'));
    chunks = chunks.filter(c=>c.trim().toLowerCase().startsWith(`[${startsWith.toLowerCase()}`));
    return chunks.length ? chunks[chunks.length-1] : '';
  }

  async function fetchTurnos(params){
    const r=await fetch(`${API}?${params}`,{headers:{'Accept':'application/json'}});
    if(!r.ok)return[];
    const data=await r.json();
    const arr=Array.isArray(data.items)?data.items:[];
    return arr.map(t=>({...t,estado:normalizarEstado(t.estado)}));
  }

  // ---------- Cargar horas disponibles del día (adaptado a tu API) ----------
async function cargarHorasDisponibles(fecha) {
  if (!fecha) return [];
  try {
    const r = await fetch(`api/agenda_slots_medico.php?fecha=${encodeURIComponent(fecha)}`, { headers:{'Accept':'application/json'} });
    if (!r.ok) return [];
    const data = await r.json();
    const slots = Array.isArray(data.slots) ? data.slots : [];
    return slots
      .filter(s => s && s.estado === 'disponible')
      .map(s => ({hora: s.hora}))
      .sort((a,b) => (a.hora||'').localeCompare(b.hora||''));
  } catch {
    return [];
  }
}


  /* ---------- renderer ---------- */
  /* ctx: 'conf-hoy' | 'conf' | 'cancelados' | 'reprogramados' | 'atendidos' | 'pendiente' | 'vencidos' | 'otros' */
  function itemHTML(t, { ctx = 'conf', vencido = false } = {}) {
    const nombre = t.paciente || t.nombre_paciente || 'Paciente';
    const fecha = t.fecha || '';
    const hora  = (t.hora || '').slice(0, 8);
    const clase = normalizarEstado(t.estado);
    const dentro = habilitadoHasta24h(fecha, hora);

    let acciones = '';

    if (ctx === 'conf-hoy') {
        acciones = `
        <button class="btn-inline btn-primary" data-atender="${t.id_turno}">
            <i class="fa-solid fa-user-doctor"></i> Atender paciente
        </button>`;
    } else if (ctx === 'conf') {
        acciones = `
        <button class="btn-inline btn-outline ${dentro ? '' : 'btn-disabled'}" data-rep="${t.id_turno}" ${dentro ? '' : 'disabled title="Solo dentro de las 24 h previas"'}">
            <i class="fa-solid fa-calendar-day"></i> Reprogramar
        </button>
        <button class="btn-inline btn-danger ${dentro ? '' : 'btn-disabled'}" data-canc="${t.id_turno}" ${dentro ? '' : 'disabled title="Solo dentro de las 24 h previas"'}">
            <i class="fa-solid fa-ban"></i> Cancelar
        </button>`;
    } else if (ctx === 'cancelados') {
        acciones = `
        <button class="btn-inline btn-outline" data-motivo="${t.id_turno}">
            <i class="fa-solid fa-circle-info"></i> Motivo
        </button>`;
    } else if (ctx === 'reprogramados') {
        acciones = ''; // No acciones, solo mostrar info
    } else if (ctx === 'atendidos') {
        acciones = `
        <a class="btn-inline btn-outline" target="_blank" href="derivar_turno.php?id_turno=${t.id_turno}">
            <i class="fa-solid fa-share-nodes"></i> Derivar
        </a>`;
    } else if (ctx === 'pendiente') {
        acciones = `
        <button class="btn-inline btn-primary" data-aceptar="${t.id_turno}">
            <i class="fa-solid fa-check"></i> Aceptar turno
        </button>`;
    } else if (ctx === 'vencidos') {
        acciones = `
        <button class="btn-inline btn-primary" data-atender="${t.id_turno}">
            <i class="fa-solid fa-user-doctor"></i> Atender ahora
        </button>`;
    }

    const textoFecha = fecha ? fecha : 'Turno a confirmar';
    const textoHora  = hora  ? hora  : '--:--';

    const badgeVencido = vencido ? `<span class="badge vencido">Turno vencido</span>` : '';
    const notaReprog = t.reprogramado
        ? `<div class="note">Este turno fue reprogramado previamente.</div>` : '';

    return `
    <div class="turno-item" data-id="${t.id_turno}">
        <div class="turno-main">
            <div class="turno-nombre">${nombre}</div>
            <div class="turno-fecha-hora"><i class="fa-solid fa-clock"></i> ${textoFecha} · ${textoHora}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                <span class="badge ${clase}">${clase.charAt(0).toUpperCase() + clase.slice(1)}</span>
                ${badgeVencido}
            </div>
            ${notaReprog}
        </div>
        <div style="display:flex;gap:10px;align-items:center">
            <div class="muted">${t.dni ? 'DNI ' + t.dni : ''}</div>
            ${acciones}
        </div>
    </div>`;
}


  /* ---------- loaders ---------- */
async function cargarConfirmados() {
    const q = busqueda.value.trim();
    listadoHoy.innerHTML = '<div class="muted">Cargando...</div>';
    listadoConfirmados.innerHTML = '<div class="muted">Cargando...</div>';
    const h = todayISO();

    // ---------- HOY (excluyendo vencidos) ----------
    const hoyConf  = await fetchTurnos(new URLSearchParams({ estado: 'confirmado', desde: h, hasta: h, q }));
    const hoyRepr  = await fetchTurnos(new URLSearchParams({ estado: 'reprogramado', desde: h, hasta: h, q }));
    
    // Combinar y eliminar duplicados por id_turno
    const hoyTodos = Array.from(new Map(
        [...hoyConf, ...hoyRepr].map(t => [t.id_turno, t])
    ).values());

    const hoyNoVenc = hoyTodos
        .filter(t => !isVencido24h(t.fecha, t.hora))
        .sort((a, b) => (a.hora || '').localeCompare(b.hora || ''));

    // ---------- FUTUROS ----------
    const futConf = await fetchTurnos(new URLSearchParams({ estado: 'confirmado', desde: h, q }));
    const futRepr = await fetchTurnos(new URLSearchParams({ estado: 'reprogramado', desde: h, q }));
    
    // Combinar y eliminar duplicados por id_turno
    const dataFut = Array.from(new Map(
        [...futConf, ...futRepr].map(t => [t.id_turno, t])
    ).values())
    .sort((a, b) => (a.fecha || '').localeCompare(b.fecha || '') || (a.hora || '').localeCompare(b.hora || ''));

    // ---------- Renderizar ----------
    listadoHoy.innerHTML = hoyNoVenc.length 
        ? hoyNoVenc.map(t => itemHTML(t, { ctx: 'conf-hoy' })).join('')
        : '<div class="muted">Sin confirmados hoy.</div>';

    listadoConfirmados.innerHTML = dataFut.length
        ? dataFut.map(t => itemHTML(t, { ctx: 'conf' })).join('')
        : '<div class="muted">Sin turnos confirmados.</div>';

    bindConfirmadosHoy(listadoHoy, hoyNoVenc);
    bindConfirmados(listadoConfirmados, dataFut);
}

  async function cargarVencidos(){
    const q = busqueda.value.trim();
    const hoy = todayISO();
    listadoVencidos.innerHTML = '<div class="muted">Cargando...</div>';

    // Confirmados y reprogramados hasta hoy (incluye hoy)
    const hastaHoyConf = await fetchTurnos(new URLSearchParams({estado:'confirmado',hasta:hoy,q}));
    const hastaHoyRepr = await fetchTurnos(new URLSearchParams({estado:'reprogramado',hasta:hoy,q}));
    const candidatos = [...hastaHoyConf, ...hastaHoyRepr];

    const vencidos = candidatos.filter(t => isVencido24h(t.fecha, t.hora));
    if(!vencidos.length){
      listadoVencidos.innerHTML = '<div class="muted">No hay turnos vencidos.</div>';
      return;
    }

    vencidos.sort((a,b)=> (a.fecha||'').localeCompare(b.fecha||'') || (a.hora||'').localeCompare(b.hora||''));
    listadoVencidos.innerHTML = vencidos.map(t=>itemHTML(t,{ctx:'vencidos',vencido:true})).join('');

    // Permitir atenderlos aquí
    listadoVencidos.querySelectorAll('[data-atender]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        const id=Number(btn.getAttribute('data-atender'));
        const data = vencidos.find(d=>Number(d.id_turno)===id);
        abrirFichaAtencion(data);
      };
    });
  }

  async function cargarAtendidos(){
    const q = busqueda.value.trim();
    const h = todayISO();

    listadoAtHoy.innerHTML = '<div class="muted">Cargando...</div>';
    listadoAtTodos.innerHTML = '<div class="muted">Cargando...</div>';

    const atendidosHoy   = await fetchTurnos(new URLSearchParams({estado:'atendido',desde:h,hasta:h,q}));
    const atendidosTodos = await fetchTurnos(new URLSearchParams({estado:'atendido',q}));

    listadoAtHoy.innerHTML = atendidosHoy.length
      ? atendidosHoy.map(t=>itemHTML(t,{ctx:'atendidos'})).join('')
      : '<div class="muted">Sin atendidos hoy.</div>';

    atendidosTodos.sort((a,b)=> (a.fecha||'').localeCompare(b.fecha||'') || (a.hora||'').localeCompare(b.hora||''));
    listadoAtTodos.innerHTML = atendidosTodos.length
      ? atendidosTodos.map(t=>itemHTML(t,{ctx:'atendidos'})).join('')
      : '<div class="muted">Sin históricos atendidos.</div>';

    [listadoAtHoy, listadoAtTodos].forEach((box,idx)=>{
      const data = idx===0 ? atendidosHoy : atendidosTodos;
      box.querySelectorAll('.turno-item').forEach((row,i)=> row.onclick=()=>mostrarDetalleAtendido(data[i]));
    });
  }

  async function cargarOtros(){
    listado.innerHTML='<div class="muted">Cargando...</div>';
    const q = busqueda.value.trim();

    if(estadoActivo==='cancelado'){
      const data = await fetchTurnos(new URLSearchParams({estado:'cancelado',q}));
      listado.innerHTML = data.length ? data.map(t=>itemHTML(t,{ctx:'cancelados'})).join('')
                                      : '<div class="muted">Sin turnos cancelados.</div>';
      bindCancelados(listado, data);
      return;
    }

    if(estadoActivo==='reprogramado'){
      const data = await fetchTurnos(new URLSearchParams({estado:'reprogramado',q}));
      listado.innerHTML = data.length ? data.map(t=>itemHTML(t,{ctx:'reprogramados'})).join('')
                                      : '<div class="muted">Sin turnos reprogramados.</div>';
      listado.querySelectorAll('.turno-item').forEach((row,i)=> row.onclick=()=>mostrarDetalleReprogramado(data[i]));
      return;
    }

    if(estadoActivo==='pendiente'){
      const data = await fetchTurnos(new URLSearchParams({estado:'pendiente',q}));
      listado.innerHTML = data.length ? data.map(t=>itemHTML(t,{ctx:'pendiente'})).join('')
                                      : '<div class="muted">Sin turnos pendientes.</div>';
      bindPendientes(listado, data);
      return;
    }

    // genérico
    const data = await fetchTurnos(new URLSearchParams({estado:estadoActivo,q}));
    listado.innerHTML = data.length ? data.map(t=>itemHTML(t,{ctx:'otros'})).join('')
                                    : `<div class="muted">Sin turnos ${estadoActivo}.</div>`;
    listado.querySelectorAll('.turno-item').forEach((row,i)=> row.onclick=()=>mostrarDetalleConf(data[i]));
  }

  /* ---------- binders ---------- */
  function bindConfirmadosHoy(container, datos){
    container.querySelectorAll('[data-atender]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        const id=Number(btn.getAttribute('data-atender'));
        const data = datos.find(d=>Number(d.id_turno)===id);
        abrirFichaAtencion(data);
      };
    });
    container.querySelectorAll('.turno-item').forEach((row,i)=>{
      row.onclick=()=>mostrarDetalleConf(datos[i]);
    });
  }

  function bindConfirmados(container, datos){
    container.querySelectorAll('[data-rep]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        if(btn.disabled) return;
        const id=Number(btn.getAttribute('data-rep'));
        const data=datos.find(d=>Number(d.id_turno)===id);
        abrirReprogramar(data);
      };
    });
    container.querySelectorAll('[data-canc]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        if(btn.disabled) return;
        const id=Number(btn.getAttribute('data-canc'));
        const data=datos.find(d=>Number(d.id_turno)===id);
        abrirCancelar(data);
      };
    });
    container.querySelectorAll('.turno-item').forEach((row,i)=>{
      row.onclick=()=>mostrarDetalleConf(datos[i]);
    });
  }

  function bindPendientes(container, datos){
    container.querySelectorAll('[data-aceptar]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        const id=Number(btn.getAttribute('data-aceptar'));
        aceptarTurno(id);
      };
    });
    container.querySelectorAll('.turno-item').forEach((row,i)=>{
      row.onclick=()=>mostrarDetallePendiente(datos[i]);
    });
  }

  function bindCancelados(container, datos){
    container.querySelectorAll('[data-motivo]').forEach(btn=>{
      btn.onclick=(e)=>{e.stopPropagation();
        const id=Number(btn.getAttribute('data-motivo'));
        const data=datos.find(d=>Number(d.id_turno)===id);
        mostrarMotivoCancelacion(data);
      };
    });
    container.querySelectorAll('.turno-item').forEach((row,i)=>{
      row.onclick=()=>mostrarDetalleCancelado(datos[i]);
    });
  }

  /* ---------- modales de detalle ---------- */
  function mostrarDetalleConf(t){
    const n=t.paciente||t.nombre_paciente||'Paciente';
    modalTitle.textContent='Detalle del turno';
    detalle.innerHTML=`<div class="form-grid">
      <div class="full"><strong>Paciente:</strong> ${n}</div>
      <div><strong>Fecha:</strong> ${t.fecha||'-'}</div>
      <div><strong>Hora:</strong> ${(t.hora||'').slice(0,8)}</div>
      <div class="full"><strong>Estado:</strong> ${t.estado}</div>
      <div class="full"><strong>Observaciones:</strong><br>${(t.observaciones||'-').replace(/\n/g,'<br>')}</div>
    </div>
    <div class="actions"><button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button></div>`;
    modal.style.display='flex'; document.getElementById('btnCerrar').onclick=()=>modal.style.display='none';
  }

  function mostrarDetalleAtendido(t){
    const n=t.paciente||t.nombre_paciente||'Paciente';
    const ficha = extractLastBlock(t.observaciones||'', 'Ficha médica ');
    modalTitle.textContent='Ficha médica';
    detalle.innerHTML=`<div class="form-grid">
      <div class="full"><strong>Paciente:</strong> ${n}</div>
      <div><strong>Fecha:</strong> ${t.fecha||'-'}</div>
      <div><strong>Hora:</strong> ${(t.hora||'').slice(0,8)}</div>
      <div class="full"><strong>Detalle</strong>
        <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:8px;padding:10px;margin:6px 0">${ficha||'Sin ficha registrada.'}</pre>
      </div>
    </div>
    <div class="actions">
      <button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button>
      <a class="btn-inline btn-primary" target="_blank" href="derivar_turno.php?id_turno=${t.id_turno}">
        <i class="fa-solid fa-share-from-square"></i> Derivar paciente
      </a>
    </div>`;
    modal.style.display='flex';
    document.getElementById('btnCerrar').onclick=()=>modal.style.display='none';
  }

  // Modal pendiente/derivación con fecha+hora y carga de slots desde tu API
  function mostrarDetallePendiente(t){
    const n = t.paciente || t.nombre_paciente || 'Paciente';
    const obs = (t.observaciones || '');
    const esDeriv = /\[(\s*derivado|derivaci[oó]n)/i.test(obs);

    const sinFecha = !t.fecha || t.fecha === '-' || /^\s*$/.test(t.fecha);
    const horaTxt  = (t.hora||'').slice(0,8);
    const sinHora  = !horaTxt || horaTxt.startsWith('--') || /^\s*$/.test(horaTxt);
    const requiereAgenda = sinFecha || sinHora || esDeriv;

    modalTitle.textContent = esDeriv ? 'Derivación recibida' : 'Turno pendiente';

    const cuerpoExtra = requiereAgenda ? `
        <div><label>Fecha</label><input type="date" id="acep_fecha"></div>
        <div>
          <label>Hora</label>
          <select id="acep_hora">
            <option value="">Seleccioná un horario</option>
          </select>
          <div id="acep_msg" class="muted" style="margin-top:6px"></div>
        </div>
      ` : `
        <div><strong>Fecha:</strong> ${t.fecha || '-'}</div>
        <div><strong>Hora:</strong> ${horaTxt || '-'}</div>
      `;

    const bloqueInfo = obs
      ? `<div class="full"><strong>Observaciones</strong><br>${obs.replace(/\n/g,'<br>')}</div>`
      : '';

    detalle.innerHTML = `<div class="form-grid">
      <div class="full"><strong>Paciente:</strong> ${n}</div>
      ${cuerpoExtra}
      ${bloqueInfo}
    </div>
    <div class="actions">
      <button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button>
      ${esDeriv ? `<button class="btn-inline btn-danger" id="btnRechazarDeriv"><i class="fa-solid fa-xmark"></i> Rechazar derivación</button>` : ''}
      <button class="btn-inline btn-primary" id="btnAceptarDeriv"><i class="fa-solid fa-check"></i> ${requiereAgenda ? 'Confirmar' : 'Aceptar'}</button>
    </div>`;
    modal.style.display='flex';
    document.getElementById('btnCerrar').onclick=()=>modal.style.display='none';

    if (requiereAgenda) {
      const inpFecha = document.getElementById('acep_fecha');
      const selHora  = document.getElementById('acep_hora');
      const msgBox   = document.getElementById('acep_msg');

      const hoyISO = new Date().toISOString().slice(0,10);
      inpFecha.value = !sinFecha ? t.fecha : hoyISO;

      async function actualizarHoras() {
        selHora.innerHTML = `<option value="">Cargando...</option>`;
        msgBox.textContent = '';
        const slots = await cargarHorasDisponibles(inpFecha.value);
        if (!slots.length) {
          selHora.innerHTML = `<option value="">Sin horarios disponibles</option>`;
          msgBox.textContent = 'No hay horarios para el día elegido. Probá con otra fecha.';
          return;
        }
        selHora.innerHTML = `<option value="">Seleccioná un horario</option>` +
          slots.map(s => `<option value="${s.hora}">${s.hora}</option>`).join('');
      }

      inpFecha.onchange = actualizarHoras;
      actualizarHoras();
    }

    document.getElementById('btnAceptarDeriv').onclick = async () => {
      if (requiereAgenda) {
        const f=(document.getElementById('acep_fecha').value||'').trim();
        const h=(document.getElementById('acep_hora').value||'').trim();
        if(!f||!h){ alert('Elegí fecha y horario'); return; }
        const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({__action:'aceptar_derivacion',id_turno:t.id_turno,fecha:f,hora:h})});
        const d=await r.json(); alert(d.msg||'Listo');
        if(d.ok){ modal.style.display='none'; switchTab('confirmado'); }
      }else{
        const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
          body:new URLSearchParams({__action:'aceptar',id_turno:t.id_turno})});
        const d=await r.json(); alert(d.msg||'Listo');
        if(d.ok){ modal.style.display='none'; switchTab('confirmado'); }
      }
    };

    const rej=document.getElementById('btnRechazarDeriv');
    if(rej){ rej.onclick=async()=>{
      const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({__action:'rechazar_derivacion',id_turno:t.id_turno})});
      const d=await r.json(); alert(d.msg||'Listo'); if(d.ok){ modal.style.display='none'; switchTab('cancelado'); }
    }; }
  }

  function mostrarDetalleCancelado(t){
    const n=t.paciente||t.nombre_paciente||'Paciente';
    modalTitle.textContent='Turno cancelado';
    detalle.innerHTML=`<div class="form-grid">
      <div class="full"><strong>Paciente:</strong> ${n}</div>
      <div><strong>Fecha:</strong> ${t.fecha||'-'}</div>
      <div><strong>Hora:</strong> ${(t.hora||'').slice(0,8)}</div>
      <div class="full"><strong>Estado:</strong> ${t.estado}</div>
    </div>
    <div class="actions">
      <button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button>
    </div>`;
    modal.style.display='flex';
    document.getElementById('btnCerrar').onclick=()=>modal.style.display='none';
  }

  function mostrarDetalleReprogramado(t) {
    const n = t.paciente || t.nombre_paciente || 'Paciente';
    const bloque = extractLastBlock(t.observaciones || '', 'Reprogramación ');

    modalTitle.textContent = 'Detalle de reprogramación';
    detalle.innerHTML = `
    <div class="form-grid">
        <div class="full"><strong>Paciente:</strong> ${n}</div>
        <div><strong>Fecha:</strong> ${t.fecha || '-'}</div>
        <div><strong>Hora:</strong> ${(t.hora || '').slice(0, 8)}</div>
        <div class="full"><strong>Cambios</strong>
            <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:8px;padding:10px;margin:6px 0">
${bloque || 'Sin información de cambios.'}
            </pre>
        </div>
    </div>
    <div class="actions">
        <button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button>
    </div>`;

    modal.style.display = 'flex';
    document.getElementById('btnCerrar').onclick = () => modal.style.display = 'none';
}


  function mostrarMotivoCancelacion(t){
    const bloque = extractLastBlock(t.observaciones||'', 'Cancelación ');
    modalTitle.textContent='Motivo de cancelación';
    detalle.innerHTML = `
    <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:8px;padding:12px;margin:0">
${bloque || 'Sin motivo registrado.'}
    </pre>
    <div class="actions" style="margin-top:12px">
      <button class="btn-inline btn-outline" id="btnCerrar">Cerrar</button>
    </div>`;
    modal.style.display='flex';
    document.getElementById('btnCerrar').onclick = () => modal.style.display='none';
  }

  /* ---------- acciones ---------- */
  async function aceptarTurno(id){
    const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({__action:'aceptar',id_turno:id})});
    const d=await r.json(); alert(d.msg||'Listo'); if(d.ok){ switchTab('confirmado'); }
  }

  function abrirFichaAtencion(t){
    const fh = [t.fecha, t.hora].filter(Boolean).join(' ');
    const n = t.paciente || t.nombre_paciente || 'Paciente';

    modalTitle.textContent = 'Atender paciente';
    detalle.innerHTML = `
      <div class="form-grid">
        <div><label>Paciente</label><input type="text" value="${n}" disabled></div>
        <div><label>Fecha y hora</label><input type="text" value="${fh}" disabled></div>
        <div class="full"><label>Motivo / Diagnóstico</label><textarea id="f_diag"></textarea></div>
        <div class="full"><label>Indicaciones / Tratamiento</label><textarea id="f_ind"></textarea></div>
        <div class="full"><label>Notas</label><textarea id="f_not"></textarea></div>
      </div>
      <div class="actions">
        <button class="btn-inline btn-outline" id="btnCancelarAt">Cancelar</button>
        <button class="btn-inline btn-primary" id="btnGuardarAt">
          <i class="fa-solid fa-floppy-disk"></i> Guardar atención
        </button>
      </div>
    `;

    modal.style.display = 'flex';

    document.getElementById('btnCancelarAt').onclick = () => modal.style.display = 'none';
    document.getElementById('btnGuardarAt').onclick = () => guardarAtencion(t.id_turno);
}


 function abrirReprogramar(t){
  const n = t.paciente || t.nombre_paciente || 'Paciente';
  modalTitle.textContent = 'Reprogramar turno';

  const bloque = extractLastBlock(t.observaciones || '', 'Reprogramación ');
  const nota = bloque ? `<div class="note">Última reprogramación registrada:</div>
    <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:8px;padding:10px;margin:6px 0">${bloque}</pre>` : '';

  detalle.innerHTML = `
    <div style="margin-bottom:12px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div><strong>Paciente:</strong> ${n}</div>
        <div><strong>Original:</strong> ${t.fecha||'-'} ${(t.hora||'').slice(0,8)}</div>
      </div>
      ${nota}
    </div>
    
    <div style="border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;margin-bottom:12px">
      <iframe 
        src="agenda_iframe.php" 
        id="agendaFrameRep" 
        style="width:100%;height:540px;border:none;display:block"
        frameborder="0">
      </iframe>
    </div>

    <div class="actions">
      <button class="btn-inline btn-outline" id="btnCancelarRep">Cerrar</button>
      <button class="btn-inline btn-primary" id="btnGuardarRep">
        <i class="fa-solid fa-rotate"></i> Reprogramar
      </button>
    </div>`;

  modal.style.display = 'flex';
  document.getElementById('btnCancelarRep').onclick = () => modal.style.display = 'none';

  let fechaSeleccionada = '';
  let horaSeleccionada = '';

  function handlerReprogramar(e) {
    if(e.data && e.data.tipo === 'seleccion_agenda') {
      fechaSeleccionada = e.data.fecha;
      horaSeleccionada = e.data.hora;
    }
  }
  
  window.addEventListener('message', handlerReprogramar);

  document.getElementById('btnGuardarRep').onclick = async () => {
    if(!fechaSeleccionada || !horaSeleccionada){ 
      alert('Seleccioná una fecha y horario del calendario'); 
      return; 
    }
    
    window.removeEventListener('message', handlerReprogramar);
    
    const r = await fetch('turnos.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({
        __action:'reprogramar', 
        id_turno:t.id_turno, 
        nueva_fecha:fechaSeleccionada, 
        nueva_hora:horaSeleccionada
      })
    });
    const d = await r.json();
    alert(d.msg || 'Listo');
    if(d.ok){ 
      modal.style.display='none'; 
      cargarConfirmados(); 
    }
  };
}


  function abrirCancelar(t){
    const n=t.paciente||t.nombre_paciente||'Paciente';
    const bloque = extractLastBlock(t.observaciones||'', 'Cancelación ');
    const ultima = bloque ? `<div class="note">Última cancelación registrada:</div>
      <pre style="white-space:pre-wrap;background:#fafafa;border:1px solid #eee;border-radius:8px;padding:10px;margin:6px 0">${bloque}</pre>` : '';
    modalTitle.textContent='Cancelar turno';
    detalle.innerHTML=`<div class="form-grid">
      <div class="full">¿Desea cancelar el turno de <strong>${n}</strong> del <strong>${t.fecha||'-'} ${(t.hora||'').slice(0,8)}</strong>?</div>
      <div class="full"><label>Motivo (opcional)</label><textarea id="canc_mot"></textarea></div>
      <div class="full">${ultima}</div>
    </div>
    <div class="actions">
      <button class="btn-inline btn-outline" id="btnCerrarCanc">Cerrar</button>
      <button class="btn-inline btn-danger" id="btnGuardarCanc"><i class="fa-solid fa-ban"></i> Cancelar turno</button>
    </div>`;
    modal.style.display='flex';
    document.getElementById('btnCerrarCanc').onclick=()=>modal.style.display='none';
    document.getElementById('btnGuardarCanc').onclick=()=>guardarCancelar(t.id_turno);
  }

  cerrarModal.onclick=()=>modal.style.display='none';
  window.onclick=e=>{if(e.target===modal)modal.style.display='none';};

  /* ---------- llamadas POST ---------- */
  async function guardarAtencion(id){
    const body=new URLSearchParams({
      __action:'atender',
      id_turno:id,
      diagnostico:document.getElementById('f_diag').value.trim(),
      indicaciones:document.getElementById('f_ind').value.trim(),
      notas:document.getElementById('f_not').value.trim()
    });
    const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
    const d=await r.json(); alert(d.msg||'Listo');
    if(d.ok){ modal.style.display='none'; switchTab('atendido'); }
  }

async function guardarReprogramar(id){
  const f = document.getElementById('rep_fecha').value;
  const h = document.getElementById('rep_hora').value; // viene del <select> de slots
  if(!f || !h){ alert('Elegí fecha y un horario disponible'); return; }

  const r = await fetch('turnos.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams({__action:'reprogramar', id_turno:id, nueva_fecha:f, nueva_hora:h})
  });
  const d = await r.json();
  alert(d.msg || 'Listo');
  if(d.ok){ 
    modal.style.display='none'; 
    cargarConfirmados(); 
  }
}


  async function guardarCancelar(id){
    const mot=document.getElementById('canc_mot').value.trim();
    const r=await fetch('turnos.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({__action:'cancelar',id_turno:id,motivo:mot})});
    const d=await r.json(); alert(d.msg||'Listo'); if(d.ok){ modal.style.display='none'; cargarConfirmados(); }
  }

  /* ---------- tabs ---------- */
  const tabs=document.querySelectorAll('.tab[data-estado]');
  function switchTab(e){
    estadoActivo=e; tabs.forEach(b=>b.classList.remove('active'));
    const b=[...tabs].find(x=>x.dataset.estado===e); if(b) b.classList.add('active');
    cardConfirmados.style.display='none'; cardOtros.style.display='none'; cardAtendidos.style.display='none'; cardVencidos.style.display='none';
    if(e==='confirmado'){ cardConfirmados.style.display='block'; cargarConfirmados(); return; }
    if(e==='vencido'){    cardVencidos.style.display='block';    cargarVencidos();    return; }
    if(e==='atendido'){   cardAtendidos.style.display='block';   cargarAtendidos();   return; }
    cardOtros.style.display='block';
    const titleMap={pendiente:'Turnos Pendientes',reprogramado:'Turnos Reprogramados',cancelado:'Turnos Cancelados'};
    titulo.textContent = titleMap[e] || 'Turnos';
    cargarOtros();
  }
  tabs.forEach(t=>t.onclick=()=>switchTab(t.dataset.estado));
  busqueda.onkeyup=()=>{
    if(estadoActivo==='confirmado') cargarConfirmados();
    else if(estadoActivo==='vencido') cargarVencidos();
    else if(estadoActivo==='atendido') cargarAtendidos();
    else cargarOtros();
  };

  switchTab('confirmado');
})();
</script>
</body>
</html>


