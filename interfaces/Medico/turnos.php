<?php
// ===== Seguridad / Sesión =====
$rol_requerido = 2; // Médico
require_once('../../Logica/General/verificarSesion.php');

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$id_medico = $_SESSION['id_medico'] ?? null;
$nombre    = $_SESSION['nombre']   ?? '';
$apellido  = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($apellido) . ', ' . mb_convert_case($nombre, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gestionar turnos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

<style>
  :root{
    --primary:#1e88e5; --primary-600:#1565c0; --text:#0f172a; --muted:#6b7280; --bg:#f5f7fb;
    --card:#fff; --shadow:0 10px 25px rgba(0,0,0,.08); --radius:14px;
    --danger:#ef4444; --danger-600:#dc2626; --green:#16a34a; --amber:#f59e0b;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text)}
  /* TOPBAR */
  .topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid #e5e7eb}
  .navbar{max-width:1200px;margin:0 auto;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px}
  .nav-left{display:flex;align-items:center;gap:16px}
  .brand{color:var(--primary);font-weight:800;text-decoration:none;display:flex;gap:8px;align-items:center}
  .nav-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
  .chip{border:1px dashed #d1d5db;padding:8px 10px;border-radius:10px;color:#374151;background:#f9fafb}
  .btn{border:none;background:var(--primary);color:#fff;border-radius:10px;padding:9px 12px;cursor:pointer;font-weight:700}
  .btn:hover{background:var(--primary-600)}
  .btn-outline{background:#fff;color:var(--primary);border:1px solid var(--primary);border-radius:10px;padding:8px 12px;cursor:pointer;font-weight:700}
  .btn-outline:hover{background:#f0f7ff}
  .btn-danger{background:var(--danger)} .btn-danger:hover{background:var(--danger-600)}
  .btn-muted{background:#e5e7eb;color:#111827}
  .btn-icon{display:inline-flex;align-items:center;gap:8px}

  .wrap{max-width:1200px;margin:22px auto;padding:0 16px 40px}
  .page-head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px}
  .title{font-size:28px;font-weight:800;margin:0 auto;text-align:center;flex:1}

  .card{background:var(--card);border-radius:var(--radius);box-shadow:var(--shadow);padding:14px}
  .filters{display:grid;grid-template-columns:repeat(6,1fr);gap:10px;align-items:end}
  .filters label{font-size:12px;color:var(--muted)}
  .filters input,.filters select{width:100%;border:1px solid #e5e7eb;border-radius:10px;padding:9px 10px;outline:none;background:#fff}
  .filters .col-span-2{grid-column:span 2}
  @media(max-width:1024px){ .filters{grid-template-columns:1fr 1fr} .filters .col-span-2{grid-column:span 2} }

  .table{display:flex;flex-direction:column;gap:8px}
  .thead,.row{display:grid;grid-template-columns:130px 110px 1.3fr 0.9fr 1fr 250px;align-items:center;gap:10px}
  .thead{font-size:12px;color:var(--muted);padding:0 6px}
  .row{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:10px}
  .muted{color:#6b7280;font-size:12px}

  .badge{display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;font-size:12px}
  .b-pend{background:#eef2ff;color:#3730a3} .b-conf{background:#ecfeff;color:#155e75}
  .b-aten{background:#ecfdf5;color:#166534} .b-canc{background:#fee2e2;color:#7f1d1d}

  .empty{padding:22px;text-align:center;color:var(--muted)}
  .skeleton{height:54px;border-radius:12px;background:linear-gradient(90deg,#f3f4f6 25%,#e5e7eb 37%,#f3f4f6 63%);background-size:400% 100%;animation:shimmer 1.2s infinite}
  @keyframes shimmer{0%{background-position:100% 0}100%{background-position:0 0}}

  /* MODALES */
  .modal-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.35);display:none;align-items:center;justify-content:center;z-index:50}
  .modal{background:#fff;width:min(520px,95vw);border-radius:14px;box-shadow:0 20px 60px rgba(0,0,0,.2);padding:18px}
  .modal h3{margin:0 0 10px}
  .field{display:flex;flex-direction:column;gap:6px;margin:10px 0 12px}
  .field label{font-size:12px;color:var(--muted)}
  .field input,.field select,.field textarea{border:1px solid #e5e7eb;border-radius:10px;padding:10px}
  .modal .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:4px}

  /* Toast */
  .toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;box-shadow:var(--shadow);display:none;z-index:60}
</style>
</head>
<body>
<header class="topbar">
  <nav class="navbar">
    <div class="nav-left">
      <a class="brand" href="principalMed.php"><i class="fa-solid fa-stethoscope"></i> Inicio</a>
      <a class="btn-outline btn-icon" href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a>
      <a class="btn-outline btn-icon" href="turnos.php"><i class="fa-solid fa-list-check"></i> Turnos</a>
    </div>
    <div class="nav-right">
      <a class="btn-outline btn-icon" href="principalMed.php"><i class="fa-solid fa-house"></i> Inicio</a>
      <a class="btn-outline btn-icon" href="javascript:history.back()"><i class="fa-solid fa-arrow-left"></i> Volver</a>
      <a class="btn-outline btn-icon" href="../../Logica/General/cerrarSesion.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      <span class="chip"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight ?: 'MÉDICO') ?></span>
    </div>
  </nav>
</header>

<main class="wrap">
  <div class="page-head">
    <h1 class="title">Gestionar turnos</h1>
    <button id="btnNuevo" class="btn btn-icon"><i class="fa-solid fa-plus"></i> Nuevo turno</button>
  </div>

  <!-- FILTROS -->
  <section class="card" style="margin-bottom:12px">
    <div class="filters">
      <div>
        <label>Desde</label>
        <input id="f-desde" type="date">
      </div>
      <div>
        <label>Hasta</label>
        <input id="f-hasta" type="date">
      </div>
      <div>
        <label>Estado</label>
        <select id="f-estado"><option value="">Todos</option></select>
      </div>
      <div class="col-span-2">
        <label>Buscar (paciente, DNI, observación)</label>
        <input id="f-q" type="text" placeholder="Ej.: Pérez 30111222">
      </div>
      <div>
        <button id="btnFiltrar" class="btn btn-icon" style="width:100%"><i class="fa-solid fa-filter"></i> Filtrar</button>
      </div>
    </div>
  </section>

  <!-- LISTA -->
  <section class="card">
    <div class="muted" style="margin-bottom:6px">Próximos turnos</div>
    <div id="grid" class="table">
      <div class="skeleton"></div>
      <div class="skeleton"></div>
      <div class="skeleton"></div>
    </div>
  </section>
</main>

<!-- MODAL: NUEVO TURNO -->
<div id="modalNuevo" class="modal-backdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Nuevo turno</h3>
    <div class="field">
      <label>ID paciente</label>
      <input id="nt-idpac" type="number" min="1" placeholder="ID del paciente">
      <div class="muted">(Luego integramos buscador por DNI y nombre)</div>
    </div>
    <div class="field">
      <label>Fecha</label>
      <input id="nt-fecha" type="date">
    </div>
    <div class="field">
      <label>Hora</label>
      <input id="nt-hora" type="time" step="1800">
    </div>
    <div class="field">
      <label>Observaciones (opcional)</label>
      <textarea id="nt-obs" placeholder="Ej.: control, primera consulta, etc."></textarea>
    </div>
    <div class="actions">
      <button class="btn btn-muted" data-close-nuevo>Cancelar</button>
      <button id="nt-confirm" class="btn">Crear</button>
    </div>
  </div>
</div>

<!-- MODAL: REPROGRAMAR -->
<div id="modalRep" class="modal-backdrop" aria-hidden="true">
  <div class="modal" role="dialog" aria-modal="true">
    <h3>Reprogramar turno</h3>
    <div class="chip" id="rp-info"></div>
    <div class="field"><label>Nueva fecha</label><input type="date" id="rp-fecha"></div>
    <div class="field"><label>Nueva hora</label><input type="time" id="rp-hora" step="1800"></div>
    <div class="actions">
      <button class="btn btn-muted" data-close-rep>Cancelar</button>
      <button id="rp-confirm" class="btn">Reprogramar</button>
    </div>
  </div>
</div>

<div id="toast" class="toast"></div>

<script>
(() => {
  const API = 'api';
  const grid = document.getElementById('grid');
  const toast = document.getElementById('toast');

  // ===== util toast + modales
  function showToast(msg){ toast.textContent = msg; toast.style.display='block'; setTimeout(()=>toast.style.display='none', 2600); }
  let lastFocus = null;
  const showModal = (el) => { lastFocus=document.activeElement; el.style.display='flex'; el.removeAttribute('aria-hidden'); el.querySelector('input,textarea,button,select')?.focus(); };
  const hideModal = (el) => { el.setAttribute('aria-hidden','true'); el.style.display='none'; lastFocus?.focus(); };

  const modalNuevo = document.getElementById('modalNuevo');
  const modalRep   = document.getElementById('modalRep');
  document.querySelector('[data-close-nuevo]').onclick = ()=> hideModal(modalNuevo);
  document.querySelector('[data-close-rep]').onclick   = ()=> hideModal(modalRep);

  // filtros
  const estadosSel = document.getElementById('f-estado');
  const fDesde = document.getElementById('f-desde');
  const fHasta = document.getElementById('f-hasta');
  const fQ     = document.getElementById('f-q');
  const hoyISO = new Date().toISOString().slice(0,10);
  fDesde.value = hoyISO;
  fHasta.value = new Date(Date.now()+1000*60*60*24*30).toISOString().slice(0,10); // +30 días

  // parse seguro
  async function asJson(resp){
    const ct = resp.headers.get('content-type') || '';
    if(!resp.ok){
      const t = await resp.text().catch(()=> '');
      throw new Error(`HTTP ${resp.status} ${t.slice(0,180)}`);
    }
    if(!ct.includes('application/json')){
      const t = await resp.text().catch(()=> '');
      throw new Error(`Respuesta no JSON: ${t.slice(0,180)}`);
    }
    return resp.json();
  }

  // ===== Cargar estados (usa api/estados_list.php que devuelve [{id, nombre}])
  fetch(`${API}/estados_list.php`)
    .then(asJson)
    .then(items => {
      (items||[]).forEach(e=>{
        const opt=document.createElement('option');
        opt.value=e.id; opt.textContent=e.nombre;
        estadosSel.appendChild(opt);
      });
    })
    .catch(err => showToast('Error al cargar estados: '+err.message));

  function badge(nombre){
    const map = {pendiente:'b-pend', confirmado:'b-conf', atendido:'b-aten', cancelado:'b-canc'};
    const key = (nombre||'').toLowerCase();
    return `<span class="badge ${map[key]||'b-pend'}">${nombre}</span>`;
  }

  async function cargar(){
    grid.innerHTML = `
      <div class="thead">
        <div>Fecha</div><div>Hora</div><div>Paciente</div><div>Estado</div><div>Obs.</div><div>Acciones</div>
      </div>
      <div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div>`;

    const qs = new URLSearchParams({
      desde:fDesde.value, hasta:fHasta.value, estado:estadosSel.value, q:fQ.value
    });

    try{
      const items = await fetch(`${API}/turnos_list.php?${qs.toString()}`).then(asJson);
      if(!items.length){ grid.innerHTML = '<div class="empty">Sin resultados</div>'; return; }
      grid.innerHTML = '<div class="thead"><div>Fecha</div><div>Hora</div><div>Paciente</div><div>Estado</div><div>Obs.</div><div>Acciones</div></div>';
      items.forEach(t=>{
        const obs = t.observaciones ? t.observaciones : '';
        const paciente = t.paciente || `Paciente #${t.id_paciente}`;
        grid.insertAdjacentHTML('beforeend', `
          <div class="row" data-id="${t.id_turno}">
            <div>${t.fecha_fmt}</div>
            <div>${t.hora.slice(0,5)}</div>
            <div><div>${paciente}</div><div class="muted">ID ${t.id_paciente}${t.dni?(' · DNI '+t.dni):''}</div></div>
            <div>${badge(t.estado)}</div>
            <div title="${obs}">${obs ? obs.slice(0,40)+(obs.length>40?'…':'') : '-'}</div>
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <button class="btn-outline btn-icon" data-act="confirmar"><i class="fa-solid fa-check"></i> Confirmar</button>
              <button class="btn-outline btn-icon" data-act="atender"><i class="fa-solid fa-user-check"></i> Atendido</button>
              <button class="btn-danger  btn-icon" data-act="cancelar"><i class="fa-solid fa-ban"></i> Cancelar</button>
              <button class="btn btn-icon" data-act="reprogramar"><i class="fa-solid fa-clock-rotate-left"></i> Reprogramar</button>
            </div>
          </div>`);
      });

      // actions
      grid.querySelectorAll('[data-act="confirmar"]').forEach(btn=>{
        btn.onclick = ()=> cambiarEstado(btn.closest('.row').dataset.id, 'confirmado');
      });
      grid.querySelectorAll('[data-act="atender"]').forEach(btn=>{
        btn.onclick = ()=> cambiarEstado(btn.closest('.row').dataset.id, 'atendido');
      });
      grid.querySelectorAll('[data-act="cancelar"]').forEach(btn=>{
        btn.onclick = ()=> cambiarEstado(btn.closest('.row').dataset.id, 'cancelado');
      });
      grid.querySelectorAll('[data-act="reprogramar"]').forEach(btn=>{
        btn.onclick = ()=> abrirReprogramar(btn.closest('.row').dataset.id);
      });

    }catch(e){
      console.error(e);
      grid.innerHTML = '<div class="empty">Error al cargar</div>';
      showToast(e.message);
    }
  }

  async function cambiarEstado(id_turno, estado){
    if(estado==='cancelado' && !confirm('¿Cancelar el turno?')) return;
    try{
      const body = new URLSearchParams({ id_turno, estado });
      const r = await fetch(`${API}/turnos_estado.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      await cargar(); showToast('Estado actualizado');
    }catch(e){ showToast('No se pudo actualizar el estado'); console.error(e); }
  }

  // nuevo
  document.getElementById('btnNuevo').onclick = ()=>{
    document.getElementById('nt-fecha').value = new Date().toISOString().slice(0,10);
    document.getElementById('nt-hora').value  = '';
    document.getElementById('nt-idpac').value = '';
    document.getElementById('nt-obs').value   = '';
    showModal(modalNuevo);
  };
  document.getElementById('nt-confirm').onclick = async ()=>{
    const id_paciente = document.getElementById('nt-idpac').value.trim();
    const fecha = document.getElementById('nt-fecha').value;
    const hora  = document.getElementById('nt-hora').value;
    const obs   = document.getElementById('nt-obs').value.trim();
    if(!id_paciente || !fecha || !hora){ showToast('Completá ID paciente, fecha y hora'); return; }
    try{
      const body = new URLSearchParams({ id_paciente, fecha, hora, observaciones:obs });
      const r = await fetch(`${API}/turnos_crear.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      const t = await r.text().catch(()=> '');
      if(!r.ok) throw new Error('HTTP '+r.status+' '+t.slice(0,160));
      hideModal(modalNuevo); await cargar(); showToast('Turno creado');
    }catch(e){ showToast('No se pudo crear: '+e.message); console.error(e); }
  };

  // reprogramar
  let rpTurno = null;
  function abrirReprogramar(id){
    rpTurno = id;
    const row = grid.querySelector(`.row[data-id="${id}"]`);
    document.getElementById('rp-info').textContent = `Turno #${id} · ${row.children[0].textContent} ${row.children[1].textContent} · ${row.children[2].textContent}`;
    document.getElementById('rp-fecha').value = new Date().toISOString().slice(0,10);
    document.getElementById('rp-hora').value  = '';
    showModal(modalRep);
  }
  document.getElementById('rp-confirm').onclick = async ()=>{
    const fecha = document.getElementById('rp-fecha').value, hora = document.getElementById('rp-hora').value;
    if(!fecha || !hora){ showToast('Completá nueva fecha y hora'); return; }
    try{
      const body = new URLSearchParams({ id_turno: rpTurno, fecha, hora });
      const r = await fetch(`${API}/turnos_reprogramar.php`, { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body });
      if(!r.ok) throw new Error('HTTP '+r.status);
      hideModal(modalRep); await cargar(); showToast('Turno reprogramado');
    }catch(e){ showToast('No se pudo reprogramar'); console.error(e); }
  };

  document.getElementById('btnFiltrar').onclick = cargar;
  cargar();
})();
</script>
</body>
</html>
