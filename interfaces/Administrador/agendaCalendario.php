<?php
// ===== Seguridad =====
$rol_requerido = 3;
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status() == PHP_SESSION_NONE) { session_start(); }
$admin = $_SESSION['nombre'] ?? 'Admin';

$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// Médicos para el selector
$medicos = [];
$r = $conn->query("
  SELECT m.id_medico, CONCAT(u.apellido, ', ', u.nombre) AS nombre
  FROM medicos m
  JOIN usuario u ON u.id_usuario = m.id_usuario
  ORDER BY u.apellido, u.nombre
");
while($r && $row=$r->fetch_assoc()){ $medicos[]=$row; }
$r && $r->close();

$hoy = new DateTime();
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)$hoy->format('n'); // 1..12
$anio = isset($_GET['anio']) ? (int)$_GET['anio'] : (int)$hoy->format('Y');
$id_medico = isset($_GET['id_medico']) ? (int)$_GET['id_medico'] : (int)($medicos[0]['id_medico'] ?? 0);

$first = (new DateTime(sprintf('%04d-%02d-01', $anio, $mes)));
$start = (clone $first)->modify('monday this week');
$last  = (clone $first)->modify('last day of this month');
$end   = (clone $last)->modify('sunday this week');

function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1"/>
<title>Agenda (Almanaque + ABM)</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--brand:#1e88e5;--brand-dark:#1565c0;--ok:#22c55e;--bad:#ef4444;--muted:#9ca3af;--border:#e5e7eb;--bg:rgba(255,255,255,.94)}
body{font-family:Arial,Helvetica,sans-serif;background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center/cover fixed}
nav{position:sticky;top:0;background:#fff;border-bottom:1px solid var(--border);z-index:50}
.nav-inner{max-width:1200px;margin:0 auto;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
nav a{color:var(--brand);text-decoration:none;font-weight:700}
nav a:hover{text-decoration:underline}
.btn{border:none;border-radius:8px;padding:8px 14px;background:var(--brand);color:#fff;font-weight:700;cursor:pointer}
.btn:hover{background:var(--brand-dark)}
.btn-outline{background:#fff;color:#111;border:1px solid var(--border)}
.container{max-width:1200px;margin:0 auto;padding:24px 20px}
h1{color:#f7fafc;text-shadow:0 1px 3px rgba(0,0,0,.5);font-size:1.9rem;margin-bottom:12px}
.card{background:var(--bg);border-radius:16px;padding:16px;box-shadow:0 8px 18px rgba(0,0,0,.12);border:1px solid rgba(0,0,0,.03);margin-bottom:16px}
.controls{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
select,input[type="month"]{padding:10px;border:1px solid var(--border);border-radius:10px}
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:8px}
.weekday{font-weight:700;text-align:center;color:#111;background:#f8fafc;border:1px solid var(--border);padding:8px;border-radius:10px}
.day{background:#fff;border:1px solid var(--border);border-radius:12px;min-height:92px;padding:8px;position:relative;cursor:pointer;display:flex;flex-direction:column;gap:6px}
.day.out{opacity:.45}
.day .date{font-weight:800;color:#111}
.badge{display:inline-block;border-radius:999px;font-size:.75rem;padding:3px 8px;color:#fff;width:max-content}
.badge.free{background:var(--ok)}
.badge.full{background:var(--bad)}
.badge.closed{background:var(--muted)}
.legend{display:flex;gap:12px;align-items:center;margin-top:8px;color:#111}
.legend .dot{width:12px;height:12px;border-radius:999px;display:inline-block}
.dot.free{background:var(--ok)} .dot.full{background:var(--bad)} .dot.closed{background:var(--muted)}
/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.45);display:none;align-items:center;justify-content:center;padding:20px;z-index:60}
.modal .window{background:#fff;max-width:680px;width:100%;border-radius:16px;box-shadow:0 12px 24px rgba(0,0,0,.25);overflow:hidden}
.modal header{background:#f8fafc;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border)}
.modal .content{padding:14px}
.slot{display:grid;grid-template-columns:90px 1fr auto;align-items:center;border:1px solid var(--border);border-radius:10px;padding:8px 10px;margin-bottom:8px;gap:10px}
.slot.free{background:#ecfdf5} .slot.busy{background:#fef2f2}
.status{font-weight:800}
.status.free{color:#065f46} .status.busy{color:#991b1b}
.actions{display:flex;gap:6px;flex-wrap:wrap}
.small{font-size:.86rem;color:#374151}
.hr{height:1px;background:#e5e7eb;margin:10px 0}
</style>
</head>
<body>
<nav>
  <div class="nav-inner">
    <div style="display:flex;gap:8px;align-items:center">
      <button class="btn btn-outline" type="button" onclick="history.back()"><i class="fa fa-arrow-left"></i> Atrás</button>
      <a class="btn" href="principalAdmi.php"><i class="fa fa-house"></i> Dashboard</a>
    </div>
    <div style="display:flex;gap:10px;align-items:center">
      <span>Bienvenido, <?= esc($admin) ?></span>
      <a class="btn" href="../../Logica/General/cerrarSesion.php"><i class="fa fa-right-from-bracket"></i> Salir</a>
    </div>
  </div>
</nav>

<main class="container">
  <h1><i class="fa fa-calendar"></i> Agenda</h1>

  <div class="card">
    <div class="controls">
      <select id="id_medico" required>
        <?php foreach($medicos as $m): ?>
        <option value="<?= (int)$m['id_medico'] ?>" <?= $id_medico===(int)$m['id_medico']?'selected':'' ?>>
          <?= esc($m['nombre']) ?>
        </option>
        <?php endforeach; ?>
      </select>

      <div style="display:flex;gap:6px;align-items:center">
        <button class="btn btn-outline" id="prevMonth"><i class="fa fa-chevron-left"></i></button>
        <input type="month" id="month" value="<?= esc(sprintf('%04d-%02d',$anio,$mes)) ?>"/>
        <button class="btn btn-outline" id="nextMonth"><i class="fa fa-chevron-right"></i></button>
        <button class="btn" id="btnHoy"><i class="fa fa-calendar-day"></i> Hoy</button>
      </div>
    </div>
    <div class="legend">
      <span><span class="dot free"></span> Día con horarios libres</span>
      <span><span class="dot full"></span> Día completo</span>
      <span><span class="dot closed"></span> Cerrado/Feriado</span>
    </div>
  </div>

  <div class="card">
    <div id="calendar" class="calendar">
      <div class="weekday">Lun</div><div class="weekday">Mar</div><div class="weekday">Mié</div>
      <div class="weekday">Jue</div><div class="weekday">Vie</div><div class="weekday">Sáb</div><div class="weekday">Dom</div>
    </div>
  </div>
</main>

<!-- Modal -->
<div class="modal" id="modal">
  <div class="window">
    <header>
      <strong id="modalTitle">Horarios</strong>
      <button class="btn" id="closeModal" type="button"><i class="fa fa-xmark"></i></button>
    </header>
    <div class="content" id="modalBody">Cargando...</div>
  </div>
</div>

<script>
// ==== Helpers fecha ====
function fmtDate(d){ return d.toISOString().slice(0,10); }
function clone(d){ return new Date(d.getTime()); }
function addDays(d,n){ const x = clone(d); x.setDate(x.getDate()+n); return x; }

// ==== DOM ====
const calendarEl = document.getElementById('calendar');
const idMedicoEl = document.getElementById('id_medico');
const monthEl    = document.getElementById('month');
const prevBtn    = document.getElementById('prevMonth');
const nextBtn    = document.getElementById('nextMonth');
const btnHoy     = document.getElementById('btnHoy');

const modal      = document.getElementById('modal');
const modalTitle = document.getElementById('modalTitle');
const modalBody  = document.getElementById('modalBody');
document.getElementById('closeModal').onclick = ()=> modal.style.display='none';

// ======= Estado mes actual =======
let view = new Date(`${monthEl.value}-01T00:00:00`);

idMedicoEl.onchange = ()=> renderMonth(view.getFullYear(), view.getMonth()+1);
monthEl.onchange = ()=>{
  const [y,m]= monthEl.value.split('-').map(Number);
  view = new Date(`${y}-${String(m).padStart(2,'0')}-01T00:00:00`);
  renderMonth(y,m);
};
prevBtn.onclick = ()=>{
  const y = view.getFullYear(), m = view.getMonth()+1;
  const d = new Date(y, m-2, 1); // mes anterior
  view = d; monthEl.value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
  renderMonth(d.getFullYear(), d.getMonth()+1);
};
nextBtn.onclick = ()=>{
  const y = view.getFullYear(), m = view.getMonth()+1;
  const d = new Date(y, m, 1); // mes siguiente
  view = d; monthEl.value = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
  renderMonth(d.getFullYear(), d.getMonth()+1);
};
btnHoy.onclick = ()=>{
  const now = new Date();
  view = new Date(now.getFullYear(), now.getMonth(), 1);
  monthEl.value = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
  renderMonth(now.getFullYear(), now.getMonth()+1);
};

// ======= API =======
async function apiDays(id_medico, start, end){
  const r = await fetch(`api_agenda.php?mode=days&id_medico=${id_medico}&start=${start}&end=${end}`, {credentials:'same-origin'});
  return r.json();
}
async function apiSlots(id_medico, date){
  const r = await fetch(`api_agenda.php?mode=slots&id_medico=${id_medico}&date=${date}`, {credentials:'same-origin'});
  return r.json();
}
async function apiPost(data){
  const r = await fetch('api_agenda.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    credentials:'same-origin',
    body: JSON.stringify(data)
  });
  return r.json();
}

// ======= Render mes =======
async function renderMonth(y,m){
  // calcular cuadrícula
  const first = new Date(`${y}-${String(m).padStart(2,'0')}-01T00:00:00`);
  const day = (first.getDay()+6)%7; // 0=lun
  const start = addDays(first, -day);
  const last = new Date(y, m, 0);
  const end = addDays(last, 7-((last.getDay()+6)%7)-1);

  const medico = idMedicoEl.value;
  const data = await apiDays(medico, fmtDate(start), fmtDate(end));
  const map = {}; (data.days||[]).forEach(d=> map[d.date]=d);

  calendarEl.innerHTML = `
    <div class="weekday">Lun</div><div class="weekday">Mar</div><div class="weekday">Mié</div>
    <div class="weekday">Jue</div><div class="weekday">Vie</div><div class="weekday">Sáb</div><div class="weekday">Dom</div>
  `;
  let iter = new Date(start);
  while (iter <= end){
    const dstr = fmtDate(iter);
    const info = map[dstr] || {status:'closed', free:0, busy:0};
    const out = iter.getMonth() !== (m-1);
    const badge = info.status==='available' ? 'free' : (info.status==='full' ? 'full' : 'closed');
    const html = `
      <div class="day ${out?'out':''}" data-date="${dstr}">
        <div class="date">${iter.getDate()}</div>
        <span class="badge ${badge}">
          ${badge==='free' ? (info.free+' libres') : (badge==='full'?'Sin lugar':'Cerrado')}
        </span>
      </div>`;
    calendarEl.insertAdjacentHTML('beforeend', html);
    iter = addDays(iter,1);
  }

  document.querySelectorAll('.day').forEach(el=>{
    el.addEventListener('click', ()=> openDay(el.getAttribute('data-date')));
  });
}

async function openDay(date){
  modal.style.display='flex';
  modalTitle.textContent = `Horarios – ${date}`;
  modalBody.innerHTML = 'Cargando...';

  const medico = idMedicoEl.value;
  const slots = await apiSlots(medico, date);

  if (!slots || !slots.length){
    modalBody.innerHTML = '<p>No hay horarios definidos para este día.</p>';
    return;
  }

  // Cargar combos para acciones (estados disponibles)
  const meta = await (await fetch('api_agenda.php?mode=meta', {credentials:'same-origin'})).json();
  const estados = meta.estados || [];

  modalBody.innerHTML = `
    <div class="small" style="margin-bottom:8px">
      <strong>Médico:</strong> ${idMedicoEl.options[idMedicoEl.selectedIndex].text} · <strong>Fecha:</strong> ${date}
    </div>
    ${slots.map(s => renderSlotRow(s, estados, medico, date)).join('')}
  `;

  // Enlazar acciones
  attachSlotActions(estados, medico, date);
}

function renderSlotRow(s, estados, id_medico, date){
  const busy = !!s.busy;
  const turnoId = s.id_turno || null;
  const estadoActual = s.estado || null;
  const paciente = s.paciente || '';
  const medicoNombre = s.medico || '';
  return `
    <div class="slot ${busy?'busy':'free'}" data-time="${s.time}" data-turno="${turnoId||''}">
      <div><strong>${s.time}</strong></div>
      <div>
        ${busy
          ? `<div><span class="status busy">Ocupado</span> — ${paciente ? ('<strong>'+paciente+'</strong>') : ''} ${estadoActual ? ('· <em>'+estadoActual+'</em>') : ''}</div>`
          : `<div><span class="status free">Disponible</span></div>`
        }
      </div>
      <div class="actions">
        ${busy
          ? `
            <select class="estadoSelect">
              ${estados.map(e=>`<option value="${e.id_estado}" ${(e.nombre_estado===estadoActual)?'selected':''}>${e.nombre_estado}</option>`).join('')}
            </select>
            <button class="btn btn-outline actUpdate" title="Actualizar estado"><i class="fa fa-rotate"></i></button>
            <button class="btn btn-outline actMove" title="Mover dentro del día"><i class="fa fa-arrows-up-down-left-right"></i></button>
            <button class="btn btn-outline actCancel" title="Cancelar"><i class="fa fa-ban"></i></button>
            <button class="btn btn-outline actDelete" title="Eliminar"><i class="fa fa-trash"></i></button>
          `
          : `
            <a class="btn actAssign" href="asignarTurno.php?id_medico=${id_medico}&fecha=${date}&hora=${s.time}">
              <i class="fa fa-plus"></i> Asignar
            </a>
          `
        }
      </div>
    </div>
  `;
}

function attachSlotActions(estados, id_medico, date){
  // Update estado
  document.querySelectorAll('.actUpdate').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const slot = e.target.closest('.slot');
      const id_turno = slot.getAttribute('data-turno');
      const estadoSel = slot.querySelector('.estadoSelect');
      const id_estado = parseInt(estadoSel.value,10);
      const res = await apiPost({mode:'update_estado', id_turno, id_estado});
      await openDay(date);
    });
  });

  // Cancelar (estado cancelado)
  document.querySelectorAll('.actCancel').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const slot = e.target.closest('.slot');
      const id_turno = slot.getAttribute('data-turno');
      if (!confirm('¿Cancelar este turno?')) return;
      const res = await apiPost({mode:'cancel', id_turno});
      await openDay(date);
    });
  });

  // Eliminar
  document.querySelectorAll('.actDelete').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const slot = e.target.closest('.slot');
      const id_turno = slot.getAttribute('data-turno');
      if (!confirm('¿Eliminar definitivamente este turno?')) return;
      const res = await apiPost({mode:'delete', id_turno});
      await openDay(date);
    });
  });

  // Mover (dentro del mismo día): pide nueva hora (HH:MM)
  document.querySelectorAll('.actMove').forEach(btn=>{
    btn.addEventListener('click', async (e)=>{
      e.preventDefault();
      const slot = e.target.closest('.slot');
      const id_turno = slot.getAttribute('data-turno');
      const nuevaHora = prompt('Nueva hora (HH:MM):', slot.getAttribute('data-time'));
      if (!nuevaHora) return;
      const res = await apiPost({mode:'move', id_turno, fecha: date, hora: nuevaHora});
      if (res && res.error){ alert(res.error); }
      await openDay(date);
    });
  });
}

// Render inicial
renderMonth(view.getFullYear(), view.getMonth()+1);
</script>
</body>
</html>
