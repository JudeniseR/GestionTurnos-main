<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Nuevo turno</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --brand:#1e88e5; --brand-600:#1565c0;
  --ok:#16a34a; --ok-600:#15803d;
  --bad:#ef4444; --bad-600:#dc2626;
  --bg:#f5f7fb; --card:#fff; --border:#e5e7eb; --text:#0f172a; --muted:#6b7280;
  --shadow:0 10px 25px rgba(0,0,0,.08); --radius:14px;
}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--text);font-family:Inter,Arial,Helvetica,sans-serif}

/* Topbar */
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--border)}
.navbar{max-width:1100px;margin:0 auto;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;gap:10px}
.nav-left,.nav-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.btn-link{background:#fff;border:1px solid var(--border);border-radius:10px;padding:8px 10px;display:inline-flex;gap:8px;align-items:center;text-decoration:none;color:#111;font-weight:700}
.btn-link:hover{background:#f8fafc}
.chip{border:1px dashed #d1d5db;padding:8px 10px;border-radius:10px;color:#374151;background:#f9fafb}

/* Layout */
.wrap{max-width:960px;margin:18px auto;padding:0 12px 40px}
.card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:16px;border:1px solid rgba(0,0,0,.03)}
h1{font-size:26px;margin:0 0 12px;text-align:center}

/* Form */
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.grid .full{grid-column:1 / -1}
label{font-size:12px;color:var(--muted);display:block;margin-bottom:6px}
input,select,textarea{width:100%;border:1px solid var(--border);border-radius:10px;padding:10px;background:#fff}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px}
.btn{border:none;border-radius:10px;padding:9px 12px;font-weight:700;cursor:pointer;display:inline-flex;gap:8px;align-items:center}
.btn-primary{background:var(--brand);color:#fff}.btn-primary:hover{background:var(--brand-600)}
.btn-outline{background:#fff;border:1px solid var(--border)}
.btn-danger{background:var(--bad);color:#fff}.btn-danger:hover{background:var(--bad-600)}

/* Pacientes search dropdown */
.searchbox{position:relative}
.results{position:absolute;left:0;right:0;top:100%;background:#fff;border:1px solid var(--border);border-radius:10px;margin-top:6px;max-height:240px;overflow:auto;z-index:20;box-shadow:0 10px 20px rgba(0,0,0,.08);display:none}
.item{padding:8px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer}
.item:hover{background:#f8fafc}
.muted{color:var(--muted);font-size:12px}

/* Toast */
.toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;display:none;box-shadow:var(--shadow);z-index:50}
</style>
</head>
<body>
<header class="topbar">
  <nav class="navbar">
    <div class="nav-left">
      <a class="btn-link" href="principalMed.php"><i class="fa-solid fa-house-medical"></i> Inicio</a>
      <a class="btn-link" href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a>
      <a class="btn-link" href="turnos.php"><i class="fa-solid fa-list-check"></i> Turnos</a>
    </div>
    <div class="nav-right">
      <a class="btn-link" href="../../Logica/General/cerrarSesion.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      <span class="chip"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight ?: 'MÉDICO') ?></span>
    </div>
  </nav>
</header>

<main class="wrap">
  <h1><i class="fa-solid fa-plus"></i> Nuevo turno</h1>

  <div class="card">
    <form id="formNuevo" class="grid" autocomplete="off">
      <!-- Paciente -->
      <div class="full">
        <label>Buscar paciente</label>
        <div class="searchbox">
          <input id="pac-buscar" type="text" placeholder="Apellido, nombre o DNI">
          <input id="id_paciente" name="id_paciente" type="hidden">
          <div id="pac-result" class="results"></div>
        </div>
        <div id="pac-seleccion" class="muted" style="margin-top:6px">Aún no seleccionaste paciente.</div>
      </div>

      <!-- Fecha / Hora -->
      <div>
        <label>Fecha</label>
        <input id="fecha" name="fecha" type="date" required>
      </div>
      <div>
        <label>Hora</label>
        <select id="hora" name="hora" required>
          <option value="">Seleccioná fecha para ver horarios…</option>
        </select>
      </div>

      <!-- Observaciones -->
      <div class="full">
        <label>Observaciones</label>
        <textarea id="observaciones" name="observaciones" rows="2" placeholder="Motivo breve, notas, etc."></textarea>
      </div>

      <div class="actions full">
        <a class="btn btn-outline" href="turnos.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Crear</button>
      </div>
    </form>
  </div>
</main>

<div id="toast" class="toast"></div>

<script>
(() => {
  const API = 'api';

  const form = document.getElementById('formNuevo');
  const toast = document.getElementById('toast');

  function showToast(msg){
    toast.textContent = msg;
    toast.style.display = 'block';
    setTimeout(()=> toast.style.display='none', 2200);
  }

  // ==== PACIENTES: búsqueda rápida ====
  const inpBuscar = document.getElementById('pac-buscar');
  const results   = document.getElementById('pac-result');
  const idPaciente= document.getElementById('id_paciente');
  const pacSel    = document.getElementById('pac-seleccion');

  let lastQ = '', timer=null;

  function renderPacientes(items){
    results.innerHTML = '';
    if(!items || !items.length){ results.style.display='none'; return; }
    items.forEach(p => {
      const div = document.createElement('div');
      div.className = 'item';
      const nombre = (p.apellido||'')+', '+(p.nombre||'');
      div.innerHTML = `<strong>${nombre}</strong> <span class="muted">DNI ${p.nro_documento||''}</span>`;
      div.onclick = () => {
        idPaciente.value = p.id_paciente;
        inpBuscar.value  = nombre + (p.nro_documento ? ' · DNI '+p.nro_documento : '');
        pacSel.textContent = `Seleccionado: ${inpBuscar.value}`;
        results.style.display='none';
      };
      results.appendChild(div);
    });
    results.style.display='block';
  }

  async function buscarPacientes(q){
    const url = `${API}/pacientes_buscar.php?q=${encodeURIComponent(q)}`;
    const r = await fetch(url);
    const ct = r.headers.get('content-type')||'';
    const t = await r.text();
    if(!r.ok) throw new Error('HTTP '+r.status);
    if(!/application\/json/i.test(ct)) throw new Error('Respuesta no JSON: '+t.slice(0,120));
    const data = JSON.parse(t);
    renderPacientes(data || []);
  }

  inpBuscar.addEventListener('input', e=>{
    const q = e.target.value.trim();
    if(q===lastQ) return;
    lastQ=q;
    clearTimeout(timer);
    if(q.length<2){ results.style.display='none'; return; }
    timer=setTimeout(()=> buscarPacientes(q).catch(err=>{ console.error(err); results.style.display='none'; }), 250);
  });

  document.addEventListener('click', (ev)=>{
    if(!results.contains(ev.target) && ev.target!==inpBuscar){
      results.style.display='none';
    }
  });

  // ==== HORARIOS: se cargan por fecha ====
  const inpFecha = document.getElementById('fecha');
  const selHora  = document.getElementById('hora');

  function hoyISO(){
    const d=new Date(); return d.toISOString().slice(0,10);
  }
  inpFecha.value = hoyISO();

  async function cargarHoras(){
    selHora.innerHTML = `<option value="">Cargando horarios…</option>`;
    const fecha = inpFecha.value;
    if(!fecha){ selHora.innerHTML = `<option value="">Seleccioná fecha…</option>`; return; }

    try{
      // Usa tu API existente
      const url = `${API}/agenda_slots_medico.php?fecha=${encodeURIComponent(fecha)}`;
      const r = await fetch(url);
      const ct = r.headers.get('content-type')||'';
      const t = await r.text();
      if(!r.ok) throw new Error('HTTP '+r.status+' '+t.slice(0,160));
      if(!/application\/json/i.test(ct)) throw new Error('Respuesta no JSON: '+t.slice(0,160));
      const data = JSON.parse(t);

      // esperamos formato: [{hora:"HH:MM", estado:"disponible"|"ocupado"|...}]
      const libres = (data||[]).filter(s => (s.estado||'').toLowerCase()==='disponible');

      if(!libres.length){
        selHora.innerHTML = `<option value="">No hay horarios libres en ese día.</option>`;
        return;
      }
      selHora.innerHTML = `<option value="">Seleccioná…</option>`;
      libres.forEach(s => {
        const h = (s.hora||'').slice(0,5);
        const op = document.createElement('option');
        op.value = h; op.textContent = h;
        selHora.appendChild(op);
      });
    }catch(e){
      console.error(e);
      selHora.innerHTML = `<option value="">No se pudo cargar horarios</option>`;
    }
  }
  inpFecha.addEventListener('change', cargarHoras);
  cargarHoras();

  // ==== Envío ====
  form.addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    const id_paciente = idPaciente.value.trim();
    const fecha = inpFecha.value;
    const hora  = selHora.value;
    const observaciones = document.getElementById('observaciones').value.trim();

    if(!id_paciente || !fecha || !hora){
      showToast('Completá paciente, fecha y hora');
      return;
    }

    try{
      const body = new URLSearchParams({ id_paciente, fecha, hora, observaciones });
      const r = await fetch(`${API}/turnos_crear.php`, {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body
      });
      const t = await r.text(); // para diagnosticar si no es JSON
      if(!r.ok) throw new Error('HTTP '+r.status+' '+t.slice(0,160));
      const data = JSON.parse(t);
      if(!data.ok) throw new Error(data.msg || 'No se pudo crear');

      showToast('Turno creado');
      setTimeout(()=> window.location.href='turnos.php', 600);
    }catch(e){
      console.error(e);
      showToast(e.message || 'Error al crear');
    }
  });
})();
</script>
</body>
</html>
