<?php 
$rol_requerido = 2; require_once('../../Logica/General/verificarSesion.php');
if (session_status()===PHP_SESSION_NONE) { session_start(); }
$nom = $_SESSION['nombre'] ?? ''; $ape = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($ape).', '.mb_convert_case($nom, MB_CASE_TITLE, 'UTF-8'));
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Turnos · Atendidos</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{--brand:#1e88e5;--ok:#16a34a;--bg:#f5f7fb;--card:#fff;--muted:#6b7280;--border:#e5e7eb;--shadow:0 10px 25px rgba(0,0,0,.08)}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,Helvetica,sans-serif;background:var(--bg)}
.topbar{position:sticky;top:0;z-index:10;background:#fff;border-bottom:1px solid var(--border)}
.navbar{max-width:1200px;margin:0 auto;padding:12px 16px;display:flex;justify-content:space-between;gap:10px}
.btn-link{background:#fff;border:1px solid var(--border);border-radius:10px;padding:8px 10px;display:inline-flex;gap:8px;align-items:center;text-decoration:none;color:#111;font-weight:700}
.wrap{max-width:1200px;margin:18px auto;padding:0 12px 34px}
.subnav{display:flex;gap:8px;flex-wrap:wrap}
.subnav a{border:1px solid var(--border);border-radius:999px;padding:8px 12px;text-decoration:none;color:#111;background:#fff;font-weight:700}
.subnav a.active{background:var(--brand);color:#fff;border-color:var(--brand)}
.card{background:var(--card);border-radius:14px;box-shadow:var(--shadow);padding:14px;border:1px solid rgba(0,0,0,.03)}
.list-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border)}
.thead,.row{display:grid;grid-template-columns:120px 90px 1.1fr .8fr 1fr 170px;align-items:center;gap:10px}
.thead{font-size:12px;color:var(--muted);padding:0 6px}
.row{background:#fff;border:1px solid var(--border);border-radius:12px;padding:10px;margin-bottom:10px}
.badge{padding:4px 8px;border-radius:999px;font-size:12px;display:inline-block;background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
.btn{border:none;border-radius:10px;padding:8px 12px;display:inline-flex;gap:8px;align-items:center;font-weight:700;cursor:pointer}
.btn-outline{background:#fff;border:1px solid var(--border)}
.btn-xs{height:36px;min-width:110px;font-size:14px}
.empty{padding:16px;text-align:center;color:#muted}
.toast{position:fixed;right:16px;bottom:16px;background:#111827;color:#fff;padding:10px 12px;border-radius:10px;display:none}
.filters{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:14px}
.filters .fld{display:flex;flex-direction:column;gap:6px}
.filters input{border:1px solid var(--border);border-radius:10px;padding:9px 10px;background:#fff}
</style>
</head><body>
<header class="topbar">
  <nav class="navbar">
    <div>
      <a class="btn-link" href="principalMed.php"><i class="fa-solid fa-house-medical"></i> Inicio</a>
      <a class="btn-link" href="agenda.php"><i class="fa-solid fa-calendar-days"></i> Agenda</a>
      <a class="btn-link" href="turnos_pendientes.php"><i class="fa-solid fa-list-check"></i> Turnos</a>
    </div>
    <div>
      <a class="btn-link" href="../../Logica/General/cerrarSesion.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
      <span class="btn-link" style="pointer-events:none"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight) ?></span>
    </div>
  </nav>
</header>

<main class="wrap">
  <div class="card" style="margin-bottom:12px">
    <div class="subnav">
      <a href="turnos_pendientes.php"><i class="fa-solid fa-bell"></i> Por confirmar</a>
      <a href="turnos_confirmados.php"><i class="fa-solid fa-calendar-day"></i> Confirmados (hoy)</a>
      <a class="active" href="turnos_atendidos.php"><i class="fa-solid fa-user-check"></i> Atendidos</a>
      <a href="turnos_cancelados.php"><i class="fa-solid fa-ban"></i> Cancelados</a>
    </div>
  </div>

  <section class="card">
    <div class="filters">
      <div class="fld"><label>Desde</label><input id="f-desde" type="date"></div>
      <div class="fld"><label>Hasta</label><input id="f-hasta" type="date"></div>
      <div class="fld"><button id="btnFiltrar" class="btn btn-outline"><i class="fa-solid fa-filter"></i> Filtrar</button></div>
    </div>
  </section>

  <section class="card list-card">
    <div class="list-head">
      <div style="font-weight:800"><i class="fa-solid fa-table-list"></i> Turnos atendidos</div>
      <div id="pagerTop" class="muted"></div>
    </div>
    <div class="list-body">
      <div class="thead"><div>Fecha</div><div>Hora</div><div>Paciente</div><div>Estado</div><div>Obs.</div><div>Acciones</div></div>
      <div id="grid"></div>
      <div id="pagerBottom" class="muted" style="display:flex;gap:8px;justify-content:flex-end;margin-top:6px;"></div>
    </div>
  </section>
</main>

<div id="toast" class="toast"></div>

<script>
(() => {
  const API='api'; const grid=document.getElementById('grid'); const toast=document.getElementById('toast');
  const fDesde=document.getElementById('f-desde'); const fHasta=document.getElementById('f-hasta'); const btnFiltrar=document.getElementById('btnFiltrar');
  const pagerTop=document.getElementById('pagerTop'); const pagerBottom=document.getElementById('pagerBottom');
  function tip(m){toast.textContent=m;toast.style.display='block';setTimeout(()=>toast.style.display='none',2200);}
  function el(t,c,x){const e=document.createElement(t); if(c)e.className=c; if(x!==undefined)e.appendChild(document.createTextNode(x)); return e;}
  async function asJson(r){const t=await r.text(); if(!r.ok) throw new Error('HTTP '+r.status+' '+t); return JSON.parse(t);}
  function pager(where,total,per,page,onPage){ where.innerHTML=''; const pages=Math.max(1,Math.ceil(total/per));
    const pr=el('button','btn btn-outline btn-sm','Anterior'), nx=el('button','btn btn-outline btn-sm','Siguiente');
    pr.onclick=()=>{if(page>1)onPage(page-1)}; nx.onclick=()=>{if(page<pages)onPage(page+1)}; const lb=el('span',''); lb.style.margin='0 8px'; lb.textContent='Página '+page+' de '+pages;
    where.append(pr,lb,nx);
  }
  function fila(t){
    const r=el('div','row'); r.dataset.id=t.id_turno;
    const cF=el('div','',t.fecha_fmt||t.fecha||''); const cH=el('div','',(t.hora||'').slice(0,5));
    const cP=el('div'); cP.append(el('div','',t.paciente||('Paciente #'+t.id_paciente)), el('div','muted',t.dni?('DNI '+t.dni):''));
    const cE=el('div'); cE.appendChild(el('span','badge','atendido'));
    const cO=el('div'); cO.appendChild(el('div','',t.observaciones? (t.observaciones.length>80?t.observaciones.slice(0,80)+'…':t.observaciones) : '-'));
    const cA=el('div','');
    const ver=document.createElement('a'); ver.className='btn btn-outline btn-xs'; ver.href='turno_atendido.php?id='+t.id_turno; ver.target='_blank';
    ver.appendChild(el('i','fa-solid fa-notes-medical')); ver.appendChild(document.createTextNode(' Abrir ficha'));
    cA.appendChild(ver);
    r.append(cF,cH,cP,cE,cO,cA); return r;
  }
  async function cargar(page=1){
    grid.innerHTML='';
    const qs=new URLSearchParams({desde:fDesde.value||'',hasta:fHasta.value||'',estado:'atendido',page,per_page:10});
    try{
      const data=await fetch(API+'/turnos_list.php?'+qs.toString()).then(asJson);
      const items=Array.isArray(data)?data:(data.items||[]); const total=Array.isArray(data)?items.length:(data.total??items.length);
      const per=Array.isArray(data)?10:(data.per_page??10); const cur=Array.isArray(data)?page:(data.page??page);
      if(!items.length) grid.appendChild(el('div','empty','Sin turnos atendidos')); else items.forEach(i=>grid.appendChild(fila(i)));
      pager(pagerTop,total,per,cur,cargar); pager(pagerBottom,total,per,cur,cargar);
    }catch(e){ console.error(e); tip('No se pudo cargar'); grid.appendChild(el('div','empty','Error')); }
  }
  (function init(){ const dt=new Date(); const d1=new Date(dt.getTime()-30*24*60*60*1000); const d2=dt;
    fDesde.value=new Date(d1).toISOString().slice(0,10); fHasta.value=new Date(d2).toISOString().slice(0,10);
  })();
  btnFiltrar.onclick=()=>cargar(1); cargar(1);
})();
</script>
</body></html>
