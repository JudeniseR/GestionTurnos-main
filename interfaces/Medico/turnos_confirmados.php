<?php
$rol_requerido = 2; 
require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

if (session_status()===PHP_SESSION_NONE) { session_start(); }
$id_medico = $_SESSION['id_medico'] ?? null;
$nom = $_SESSION['nombre'] ?? ''; 
$ape = $_SESSION['apellido'] ?? '';
$displayRight = trim(mb_strtoupper($ape).', '.mb_convert_case($nom, MB_CASE_TITLE, 'UTF-8'));

// ============= API interno: confirmar turno vía POST =============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['__action'] ?? '') === 'confirmar') {
    header('Content-Type: application/json; charset=utf-8');
    if (!$id_medico) { echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit; }

    $id_turno = (int)($_POST['id_turno'] ?? 0);
    if ($id_turno <= 0) { echo json_encode(['ok'=>false,'msg'=>'ID inválido']); exit; }

    try {
        $conn = ConexionBD::conectar();
        $conn->set_charset('utf8mb4');

        $stmt = $conn->prepare("UPDATE turnos SET id_estado=2 WHERE id_turno=? AND id_medico=? AND id_estado=1");
        $stmt->bind_param('ii', $id_turno, $id_medico);
        $stmt->execute();

        if ($stmt->affected_rows > 0)
            echo json_encode(['ok'=>true,'msg'=>'Turno confirmado']);
        else
            echo json_encode(['ok'=>false,'msg'=>'No se pudo confirmar el turno']);
    } catch (Throwable $e) {
        echo json_encode(['ok'=>false,'msg'=>$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Turnos · Confirmados</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
<style>
:root{
  --brand:#1976d2;--success:#2e7d32;--danger:#c62828;--bg:#f5f7fb;--card:#fff;
  --border:#e5e7eb;--muted:#6b7280;--shadow:0 2px 8px rgba(0,0,0,.08);
}
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:var(--bg);}
.navbar{display:flex;justify-content:space-between;align-items:center;padding:10px 20px;background:#fff;box-shadow:var(--shadow);}
.btn-link{background:#fff;border:1px solid var(--border);border-radius:8px;padding:6px 10px;text-decoration:none;color:#111;font-weight:600;margin-right:8px}
.btn-link:hover{background:#e3f2fd;color:var(--brand)}
.subnav{display:flex;gap:10px;padding:10px 20px;background:#fff;border-bottom:1px solid var(--border);flex-wrap:wrap;}
.tab{border:none;border-radius:8px;padding:8px 12px;font-weight:600;cursor:pointer;}
.tab.active{background:var(--brand);color:#fff;}
.container{max-width:1200px;margin:20px auto;padding:0 16px;}
.card{background:var(--card);padding:16px;border-radius:10px;box-shadow:var(--shadow);}
.turno-item{border:1px solid #ddd;border-left:5px solid var(--brand);padding:10px;border-radius:10px;margin-bottom:10px;cursor:pointer;}
.turno-item:hover{transform:scale(1.01);box-shadow:var(--shadow);}
.badge{padding:4px 8px;border-radius:8px;font-size:12px;font-weight:600;display:inline-block;margin-top:5px}
.pendiente{background:#fff8e1;color:#9a6a00;}
.confirmado{background:#e3f2fd;color:#0d47a1;}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);justify-content:center;align-items:center;}
.modal-content{background:#fff;padding:20px;border-radius:10px;max-width:500px;width:90%;box-shadow:var(--shadow);}
.close{cursor:pointer;float:right;font-size:20px;color:var(--muted);}
.btn-action{border:none;padding:8px 12px;border-radius:8px;font-weight:600;cursor:pointer;}
.btn-success{background:var(--success);color:#fff;}
.btn-success:hover{background:#1b5e20;}
</style>
</head><body>
<div class="navbar">
  <div>
    <a href="principalMed.php" class="btn-link"><i class="fa-solid fa-house-medical"></i> Inicio</a>
    <a href="agenda.php" class="btn-link"><i class="fa-solid fa-calendar-days"></i> Agenda</a>
    <a href="turnos_confirmados.php" class="btn-link" style="background:#e3f2fd;color:var(--brand)"><i class="fa-solid fa-list-check"></i> Turnos</a>
  </div>
  <div>
    <span class="btn-link" style="pointer-events:none"><i class="fa-solid fa-user-doctor"></i> <?= htmlspecialchars($displayRight) ?></span>
    <a href="../../Logica/General/cerrarSesion.php" class="btn-link"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
  </div>
</div>

<div class="subnav">
  <button class="tab active" data-estado="confirmado">Confirmados</button>
  <button class="tab" data-estado="confirmadohoy">Confirmados (hoy)</button>
  <button class="tab" data-estado="pendiente">Pendientes</button>
</div>

<div class="container">
  <div class="card">
    <h2 id="tituloSeccion">Turnos Confirmados</h2>
    <div id="listadoTurnos"><div class="muted">Cargando...</div></div>
  </div>
</div>

<div class="modal" id="modalTurno">
  <div class="modal-content">
    <span class="close" id="cerrarModal">&times;</span>
    <div id="detalleTurno"></div>
  </div>
</div>

<script>
(() => {
  const API = 'api/turnos_list.php';
  const listado = document.getElementById('listadoTurnos');
  const tabs = document.querySelectorAll('.tab');
  const titulo = document.getElementById('tituloSeccion');
  const modal = document.getElementById('modalTurno');
  const detalle = document.getElementById('detalleTurno');
  const cerrarModal = document.getElementById('cerrarModal');
  let estadoActivo = 'confirmado';

  function todayISO(){ return new Date().toISOString().slice(0,10); }

  async function fetchTurnos(estado){
    let estadoAPI = estado==='confirmadohoy' ? 'confirmado' : estado;
    const params = new URLSearchParams({ estado: estadoAPI });
    if (estado==='confirmadohoy'){ const h=todayISO(); params.set('desde',h); params.set('hasta',h); }

    const r = await fetch(`${API}?${params}`);
    if (!r.ok) return [];
    const data = await r.json();
    const arr = Array.isArray(data)?data:(data.items||data.turnos||data.data||[]);
    return arr;
  }

  function renderItem(t){
    const div = document.createElement('div');
    div.className='turno-item';
    div.innerHTML=`<strong>${t.paciente||'Paciente'}</strong><br><span class="muted">${t.fecha} - ${t.hora}</span><br><span class="badge ${t.estado}">${t.estado}</span>`;
    div.addEventListener('click',()=>mostrarDetalle(t));
    return div;
  }

  async function cargarTurnos(){
    listado.innerHTML='<div class="muted">Cargando...</div>';
    const data = await fetchTurnos(estadoActivo);
    listado.innerHTML='';
    if(!data.length){
      listado.innerHTML='<div class="muted">Sin turnos.</div>';return;
    }
    data.forEach(t=>listado.appendChild(renderItem(t)));
  }

  function mostrarDetalle(t){
    let btn = '';
    if (t.estado==='pendiente'){
      btn = `<button class="btn-action btn-success" id="btnConfirmar">Confirmar turno</button>`;
    }
    detalle.innerHTML=`
      <h3>Detalle del Turno</h3>
      <p><strong>Paciente:</strong> ${t.paciente}</p>
      <p><strong>Fecha:</strong> ${t.fecha} ${t.hora}</p>
      <p><strong>Estado:</strong> ${t.estado}</p>
      <p><strong>Observaciones:</strong> ${t.observaciones||'-'}</p>
      ${btn}
    `;
    modal.style.display='flex';
    const b=document.getElementById('btnConfirmar');
    if(b){ b.onclick=()=>confirmarTurno(t.id_turno); }
  }

  cerrarModal.onclick=()=>modal.style.display='none';
  window.onclick=e=>{if(e.target===modal)modal.style.display='none';};

  async function confirmarTurno(id_turno){
    if(!confirm('¿Confirmar este turno?'))return;
    const r=await fetch('turnos_confirmados.php',{
      method:'POST',
      headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({__action:'confirmar',id_turno})
    });
    const d=await r.json();
    alert(d.msg);
    modal.style.display='none';
    cargarTurnos();
  }

  tabs.forEach(btn=>{
    btn.onclick=()=>{
      tabs.forEach(b=>b.classList.remove('active'));
      btn.classList.add('active');
      estadoActivo=btn.dataset.estado;
      titulo.textContent='Turnos '+btn.textContent;
      cargarTurnos();
    };
  });

  cargarTurnos();
})();
</script>
</body></html>
