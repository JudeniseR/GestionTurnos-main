<?php
// ===== Seguridad =====
$rol_requerido = 2;
require_once('../../Logica/General/verificarSesion.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ===== Conexión =====
require_once('../../Persistencia/conexionBD.php');

$id_medico_login = $_SESSION['id_medico'] ?? 0;
$id_turno = isset($_GET['id_turno']) ? (int)$_GET['id_turno'] : 0;
if ($id_turno <= 0) { http_response_code(400); echo "Falta id_turno"; exit; }

// --- Helpers de datos ---
function getTurno($id_turno) {
  $cn = ConexionBD::conectar();
  $sql = "SELECT t.*,
                 p.id_paciente, p.nro_documento AS dni,
                 u.nombre AS nombre_pac, u.apellido AS ape_pac
            FROM turnos t
            JOIN pacientes p ON p.id_paciente = t.id_paciente
            JOIN usuario   u ON u.id_usuario   = p.id_usuario
           WHERE t.id_turno = ?";
  $st = $cn->prepare($sql);
  $st->bind_param("i", $id_turno);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $st->close(); $cn->close();
  return $res ?: [];
}

function listarMedicos($excluir_id_medico = 0) {
  $cn = ConexionBD::conectar();
  $sql = "SELECT m.id_medico, u.apellido, u.nombre,
                 GROUP_CONCAT(DISTINCT e.nombre_especialidad ORDER BY e.nombre_especialidad SEPARATOR ', ') AS especialidades
            FROM medicos m
            JOIN usuario u ON u.id_usuario = m.id_usuario
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

function registrarDerivacion($turno_origen, $id_medico_origen, $id_medico_destino, $motivo) {
  $cn = ConexionBD::conectar();
  $cn->set_charset('utf8mb4');
  $cn->begin_transaction();
  try {
    $id_turno    = (int)$turno_origen['id_turno'];
    $id_paciente = (int)$turno_origen['id_paciente'];
    $obs_original = $turno_origen['observaciones'] ?? '';

    // Notificación
    $msg = "[Derivación] desde médico #$id_medico_origen hacia médico #$id_medico_destino. Motivo: $motivo";
    $st = $cn->prepare("INSERT INTO notificaciones (id_turno, id_paciente, mensaje, estado) VALUES (?, ?, ?, 'pendiente')");
    $st->bind_param("iis", $id_turno, $id_paciente, $msg);
    $st->execute();
    $st->close();

    // Nuevo turno PENDIENTE con tag [DERIVADO]
    $st2 = $cn->prepare(
      "INSERT INTO turnos (id_paciente, id_medico, id_estado, id_estudio, fecha, hora, copago, observaciones, id_recurso, reprogramado)
       VALUES (?, ?, 1, NULL, NULL, NULL, 0.00, CONCAT('[DERIVADO] ', ?, '\nMotivo: ', ?), NULL, 0)"
    );
    $st2->bind_param("iiss", $id_paciente, $id_medico_destino, $obs_original, $motivo);
    $st2->execute();
    $st2->close();

    $cn->commit();
    $cn->close();
    return true;
  } catch (Exception $e) {
    $cn->rollback();
    $cn->close();
    error_log("Error derivar turno: ".$e->getMessage());
    return false;
  }
}

// ===== POST: enviar derivación =====
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

// ===== Datos para render =====
$turno   = getTurno($id_turno);
$medicos = listarMedicos($id_medico_login);

$pacienteCompleto = trim(($turno['ape_pac'] ?? '').', '.($turno['nombre_pac'] ?? ''));
$fecha = $turno['fecha'] ?? '';
$hora  = $turno['hora']  ?? '';
$dni   = $turno['dni']   ?? '-';
$obs   = trim($turno['observaciones'] ?? '') ?: 'Sin ficha registrada.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Derivar paciente</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --bg:#F3F6FD; --card:#fff; --ink:#0f172a; --muted:#64748b;
  --primary:#1e88e5; --primary-ink:#0d47a1; --ring:#cfe8ff;
  --ok:#16a34a; --warn:#f59e0b; --danger:#dc2626;
  --radius:16px; --shadow:0 10px 24px rgba(16,24,40,.08);
}
*{box-sizing:border-box}
html,body{margin:0}
body{font-family:Inter,system-ui,-apple-system,Segoe UI,Arial,sans-serif;background:var(--bg);color:var(--ink)}

.wrap{max-width:1100px;margin:28px auto;padding:0 16px}
.header{display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;gap:10px}
.back{display:inline-flex;align-items:center;gap:8px;padding:8px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#fff;text-decoration:none;color:#111;font-weight:600}
.back:hover{background:#f8fafc}

.page-title{display:flex;align-items:center;gap:10px;margin:0}
.page-title i{color:var(--primary)}
.kicker{font-size:13px;color:var(--muted)}

.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px}
@media (max-width:900px){ .grid{grid-template-columns:1fr} }

.card{background:var(--card);border:1px solid #e5e7eb;border-radius:var(--radius);box-shadow:var(--shadow)}
.card-hd{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #eef2f7;padding:14px 16px;border-top-left-radius:var(--radius);border-top-right-radius:var(--radius)}
.card-bd{padding:16px}

.badge{display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:700;padding:4px 8px;border-radius:999px;border:1px solid #e5e7eb;background:#f8fafc;color:#334155}
.badge i{opacity:.8}
.meta{display:flex;flex-wrap:wrap;gap:12px;color:#0d47a1;font-weight:700}

.block + .block{margin-top:14px}
label{font-size:13px;color:#374151;font-weight:700;margin-bottom:6px;display:block}
select,input[type=text],textarea{width:100%;padding:12px;border:1px solid #dde3ea;border-radius:12px;background:#fbfdff;font:inherit}
select:focus,input:focus,textarea:focus{outline:2px solid var(--ring);border-color:var(--primary)}
textarea{min-height:140px;resize:vertical}

.row{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.input-icon{position:relative}
.input-icon input{padding-left:36px}
.input-icon i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#7c8da6}

.helper{font-size:12px;color:var(--muted);margin-top:6px}

.sticky-actions{position:sticky;bottom:0;background:linear-gradient(180deg, rgba(243,246,253,0), var(--bg) 30%, var(--bg));
  padding:14px 0 6px;margin-top:14px}
.btn{display:inline-flex;gap:10px;align-items:center;border:none;border-radius:12px;padding:12px 16px;font-weight:800;cursor:pointer}
.btn-primary{background:var(--primary);color:#fff}
.btn-primary:hover{filter:brightness(0.98)}
.btn-outline{background:#fff;border:1px solid #d1d5db}
.btn-danger{background:var(--danger);color:#fff}

.code{white-space:pre-wrap;background:#fbfbfb;border:1px dashed #e5e7eb;border-radius:12px;padding:12px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#334155}

.pill{display:inline-flex;gap:6px;align-items:center;font-size:12px;padding:4px 8px;border-radius:999px;background:#eaf4ff;color:var(--primary-ink);border:1px solid #d7e8ff}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1 class="page-title"><i class="fa-solid fa-share-nodes"></i> Derivar paciente</h1>
    <a class="back" href="turnos.php"><i class="fa-solid fa-arrow-left"></i> Volver a Turnos</a>
  </div>

  <div class="grid">
    <!-- Columna izquierda: Turno origen -->
    <section class="card">
      <div class="card-hd">
        <div class="badge"><i class="fa-solid fa-user"></i> Paciente</div>
        <span class="kicker">Detalle del turno de origen</span>
      </div>
      <div class="card-bd">
        <div class="block">
          <div class="meta">
            <div><i class="fa-solid fa-id-card-clip"></i> <?= htmlspecialchars($pacienteCompleto) ?></div>
            <div><i class="fa-solid fa-id-card"></i> DNI <?= htmlspecialchars($dni) ?></div>
          </div>
        </div>
        <div class="block">
          <span class="pill"><i class="fa-solid fa-calendar-day"></i> <?= htmlspecialchars($fecha ?: '-') ?> · <?= htmlspecialchars(substr((string)$hora,0,8) ?: '--:--') ?></span>
        </div>
        <div class="block">
          <label>Ficha médica (última)</label>
          <div class="code">[Ficha]
<?= htmlspecialchars($obs) ?></div>
          <div class="helper">Esta ficha no se envía automáticamente; podés adjuntarla al mensaje con el switch de la derecha.</div>
        </div>
      </div>
    </section>

    <!-- Columna derecha: Derivación -->
    <form method="post" class="card" id="formDerivar">
      <div class="card-hd">
        <div class="badge"><i class="fa-solid fa-user-doctor"></i> Derivación</div>
        <span class="kicker">Seleccioná destino y escribí un breve motivo</span>
      </div>
      <div class="card-bd">
        <?php if(!$medicos): ?>
          <div class="block">
            <div class="helper" style="color:#b91c1c"><i class="fa-solid fa-circle-exclamation"></i> No se encontraron médicos para derivar.</div>
          </div>
        <?php else: ?>
          <div class="block">
            <label>Médico destino</label>
            <div class="row">
              <div class="input-icon" style="flex:1 1 260px;min-width:220px">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="filtroMed" placeholder="Buscar por nombre o especialidad…">
              </div>
              <select name="id_medico_dest" id="selectMedico" required style="flex:1 1 320px;min-width:280px">
                <option value="">-- Elegí un médico --</option>
                <?php foreach($medicos as $m):
                  $esp = $m['especialidades'] ?: 'Sin especialidad';
                  $label = trim(mb_strtoupper($m['apellido']).", ".$m['nombre'])." — ".$esp;
                ?>
                  <option value="<?= (int)$m['id_medico'] ?>"
                          data-text="<?= htmlspecialchars(strtolower($label),ENT_QUOTES) ?>">
                    <?= htmlspecialchars($label) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="helper">Usá el buscador para filtrar por apellido, nombre o especialidad.</div>
          </div>

          <div class="block">
            <div class="row" style="justify-content:space-between">
              <label style="margin:0">Motivo de derivación</label>
              <label class="row" style="gap:8px;margin:0;font-weight:600;color:#334155">
                <input type="checkbox" id="adjFicha"> Adjuntar ficha al mensaje
              </label>
            </div>
            <textarea name="motivo" id="motivo" placeholder="Breve motivo para el colega..." required maxlength="2000"></textarea>
            <div class="helper"><span id="count">0</span>/2000</div>
          </div>

          <div class="sticky-actions">
            <div class="row" style="justify-content:flex-end">
              <button type="button" class="btn btn-outline" id="btnCancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
              <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar derivación</button>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<script>
// --- Filtro del select de médicos ---
const filtro = document.getElementById('filtroMed');
const sel    = document.getElementById('selectMedico');
if (filtro && sel){
  const all = [...sel.options].map(o => ({val:o.value, text:(o.dataset.text||'')||o.textContent.toLowerCase()}));
  filtro.addEventListener('input', () => {
    const q = filtro.value.trim().toLowerCase();
    // preservar primer option placeholder
    for(let i=1;i<sel.options.length;i++){ sel.options[i].style.display = ''; }
    if(!q) return;
    for(let i=1;i<sel.options.length;i++){
      const txt = all[i].text;
      sel.options[i].style.display = txt.includes(q) ? '' : 'none';
    }
  });
}

// --- Auto-resize & contador de motivo ---
const ta = document.getElementById('motivo');
const count = document.getElementById('count');
if (ta){
  const fit = () => { ta.style.height = 'auto'; ta.style.height = (ta.scrollHeight+2)+'px'; count.textContent = ta.value.length; };
  ['input','change'].forEach(ev => ta.addEventListener(ev, fit));
  setTimeout(fit, 0);
}

// --- Adjuntar ficha al mensaje (no altera BD, solo UI) ---
const chk = document.getElementById('adjFicha');
if (chk && ta){
  const ficha = `\n\n[Ficha]\n<?= addslashes($obs) ?>`;
  chk.addEventListener('change', () => {
    const has = ta.value.includes(ficha);
    if (chk.checked && !has) { ta.value += ficha; }
    if (!chk.checked && has) { ta.value = ta.value.replace(ficha,''); }
    ta.dispatchEvent(new Event('input'));
  });
}

// --- Cancelar vuelve a Turnos ---
const btnCancelar = document.getElementById('btnCancelar');
if (btnCancelar){ btnCancelar.onclick = () => { window.location.href='turnos.php'; }; }

// --- Confirmación al enviar ---
const form = document.getElementById('formDerivar');
if (form){
  form.addEventListener('submit', (e) => {
    const ok = confirm('¿Confirmás enviar la derivación al médico seleccionado?');
    if (!ok) e.preventDefault();
  });
}
</script>
</body>
</html>
