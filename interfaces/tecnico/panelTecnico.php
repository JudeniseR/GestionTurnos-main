<?php
error_reporting(E_ALL); ini_set('display_errors', 1);
session_start();

// Guardia de sesión: técnico (rol_id = 4)
$uid = $_SESSION['id_usuario'] ?? null;
$rol = (int)($_SESSION['rol_id'] ?? 0);
if (!$uid || $rol !== 4) { header('Location: ../login.php'); exit; }

$nombreTecnico   = $_SESSION['nombre']   ?? 'Técnico';
$apellidoTecnico = $_SESSION['apellido'] ?? '—';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Panel del Técnico</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  /* ===== Reset / base ===== */
  *{ margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
  body{
    background:url("https://i.pinimg.com/1200x/9b/e2/12/9be212df4fc8537ddc31c3f7fa147b42.jpg") no-repeat center center fixed;
    background-size:cover; color:#333;
  }
  /* ===== Navbar ===== */
  nav{ background:#fff; padding:15px 40px; box-shadow:0 4px 6px rgba(0,0,0,.1); position:sticky; top:0; z-index:10; }
  nav ul{ list-style:none; display:flex; justify-content:space-between; align-items:center; font-size:1.05em; }
  .nav-links{ display:flex; align-items:center; gap:24px; }
  nav a{ color:#1e88e5; text-decoration:none; font-weight:bold; }
  nav a:hover{ text-decoration:underline; }
  nav input[type="text"]{ padding:7px 10px; border-radius:6px; border:1px solid #cfd8dc; }
  nav button{ padding:7px 15px; border:none; border-radius:6px; background:#1e88e5; color:#fff; cursor:pointer; font-weight:bold; }
  nav button:hover{ background:#1565c0; }
  .perfil{ display:flex; align-items:center; gap:12px; }
  .perfil span{ font-weight:bold; color:#1e88e5; }
  .perfil img{ width:40px; height:40px; border-radius:50%; object-fit:cover; border:2px solid #1e88e5; }

  /* ===== Hero ===== */
  .container{ padding:56px 20px; text-align:center; }
  .container h1{ margin-bottom:34px; color:#f5f8fa; font-size:2.6em; text-shadow:1px 1px 3px rgba(0,0,0,.5); }

  /* ===== Cards ===== */
  .cards{ display:flex; justify-content:center; gap:30px; flex-wrap:wrap; }
  .card{
    background:rgba(255,255,255,.92); border-radius:15px; padding:36px 20px; width:260px; height:230px;
    box-shadow:0 6px 12px rgba(0,0,0,.15); text-align:center; transition:transform .2s; display:flex; flex-direction:column; justify-content:space-between;
  }
  .card:hover{ transform:translateY(-6px); }
  .card i{ font-size:48px; color:#1e88e5; }
  .card h3{ color:#333; }
  .card a,.card button{ padding:10px; text-decoration:none; color:#fff; background:#1e88e5; border-radius:8px; border:none; cursor:pointer; font-weight:bold; width:80%; margin:0 auto; }
  .card a:hover,.card button:hover{ background:#1565c0; }

  /* ===== Secciones ===== */
  .section{ max-width:1050px; margin:22px auto; background:rgba(255,255,255,.95); border-radius:16px; padding:22px; }
  .section h2{ color:#1e293b; margin-bottom:14px; }
  .row{ display:grid; grid-template-columns: repeat(12, 1fr); gap:12px; }
  .col-4{ grid-column: span 4; } .col-6{ grid-column: span 6; } .col-12{ grid-column: span 12; }
  label{ font-weight:600; color:#374151; margin-bottom:6px; display:block; text-align:left; }
  input[type="text"], input[type="email"], select{ width:100%; padding:10px; border:1px solid #cfd8dc; border-radius:8px; background:#fff; }
  .btn{ padding:10px 14px; border:none; border-radius:8px; cursor:pointer; font-weight:bold; }
  .btn-primary{ background:#1e88e5; color:#fff; } .btn-primary:hover{ background:#1565c0; }
  .btn-success{ background:#0ea371; color:#fff; } .btn-success:hover{ background:#0a7d56; }
  .btn-neutral{ background:#eceff1; } .btn-neutral:hover{ background:#e0e6ea; }
  .btn-danger{ background:#d93636; color:#fff; } .btn-danger:hover{ background:#b62a2a; }
  .muted{ color:#607d8b; } .pill{ display:inline-block; padding:2px 8px; border-radius:999px; background:#0ea371; color:#fff; font-size:.85em; }
  .list{ margin-top:10px; }
  .list button{
    width:100%; text-align:left; background:#f7fafc; border:1px solid #e2e8f0; padding:10px 12px; border-radius:8px; margin-bottom:8px;
    display:flex; align-items:center; justify-content:space-between; cursor:pointer;
  }
  .list button:hover{ background:#eef5ff; border-color:#cfe3ff; }
  .table-wrap{ overflow-x:auto; }
  table{ width:100%; border-collapse:collapse; }
  thead th{ text-align:left; color:#334155; border-bottom:2px solid #e2e8f0; padding:10px 8px; }
  tbody td{ padding:10px 8px; border-bottom:1px solid #eceff1; }
  .anchor{ scroll-margin-top: 90px; }
  .foot{ text-align:center; color:#90a4ae; font-size:.9em; padding:10px 0 30px; }
</style>
</head>
<body>

<header>
  <nav>
    <ul>
      <div class="nav-links">
        <li><a href="#inicio">Inicio</a></li>
        <li><a href="#paciente">Gestionar paciente</a></li>
        <li><a href="#agenda">Asignar turno</a></li>
        <li><a href="#turnos">Turnos del paciente</a></li>
        <li><input type="text" placeholder="Buscar..." id="buscadorGlobal"></li>
        <li><button>Buscar</button></li>
        <li><a href="../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li>
      </div>
      <div class="perfil">
        <span><?= strtoupper($apellidoTecnico) . ", " . ucwords($nombreTecnico) ?></span>
        <img src="../../assets/img/loginAdmin.png" alt="Foto perfil">
      </div>
    </ul>
  </nav>
</header>

<!-- Hero + cards -->
<div id="inicio" class="container">
  <h1>Panel del Técnico</h1>
  <div class="cards">
    <div class="card"><i class="fa-solid fa-id-card"></i><h3>Paciente</h3><a href="#paciente">Ir</a></div>
    <div class="card"><i class="fa-solid fa-calendar-check"></i><h3>Agenda</h3><a href="#agenda">Ir</a></div>
    <div class="card"><i class="fa-solid fa-clipboard-list"></i><h3>Turnos</h3><a href="#turnos">Ir</a></div>
  </div>
</div>

<!-- 1) Paciente -->
<section id="paciente" class="section anchor">
  <h2><i class="fa-regular fa-id-badge"></i> Gestionar paciente</h2>
  <div class="row">
    <div class="col-4"><label>DNI</label><input type="text" id="dni" placeholder="Ej. 44000555"></div>
    <div class="col-4"><label>Email</label><input type="email" id="email" placeholder="ejemplo@correo.com"></div>
    <div class="col-4" style="display:flex;gap:10px;align-items:flex-end;">
      <button class="btn btn-primary" style="width:50%" onclick="buscarPaciente()">Buscar</button>
      <button class="btn btn-neutral" style="width:50%" onclick="showAlta()">Registrar</button>
    </div>
    <div class="col-12"><div id="boxPaciente" class="muted" style="margin-top:10px">Sin selección.</div></div>
  </div>

  <!-- Alta completa (TODOS los campos que pide registrarPaciente.php) -->
<div id="altaWrap" style="display:none; margin-top:14px;">
  <div class="row">
    <div class="col-6">
      <label>Nombre</label>
      <input id="a_nombre" type="text" required>
    </div>
    <div class="col-6">
      <label>Apellido</label>
      <input id="a_apellido" type="text" required>
    </div>

    <div class="col-6">
      <label>Email</label>
      <input id="a_email" type="email" required>
    </div>
    <div class="col-6">
      <label>Tipo de documento</label>
      <select id="a_tdoc" required>
        <option value="">-- Seleccionar --</option>
        <option value="DNI">DNI</option>
        <option value="LC">LC</option>
        <option value="LE">LE</option>
        <option value="CI">CI</option>
        <option value="PAS">PAS</option>
      </select>
    </div>

    <div class="col-6">
      <label>Número de documento</label>
      <input id="a_dni" type="text" inputmode="numeric" pattern="\d+" required>
    </div>
    <div class="col-6">
      <label>Fecha de nacimiento</label>
      <!-- type="date" SIEMPRE envía YYYY-MM-DD -->
      <input id="a_fnac" type="date" required>
    </div>

    <div class="col-12">
      <label>Dirección</label>
      <input id="a_dir" type="text" required>
    </div>

    <div class="col-6">
      <label>Teléfono</label>
      <input id="a_tel" type="text" required>
    </div>
    <div class="col-6">
      <label>Estado civil</label>
      <select id="a_ecivil" required>
        <option value="">-- Seleccionar --</option>
        <option value="Soltero/a">Soltero/a</option>
        <option value="Casado/a">Casado/a</option>
        <option value="Divorciado/a">Divorciado/a</option>
        <option value="Viudo/a">Viudo/a</option>
        <option value="Unión convivencial">Unión convivencial</option>
      </select>
    </div>

    <div class="col-12" style="display:flex; gap:10px; margin-top:8px;">
      <button class="btn btn-success" onclick="registrarPaciente()">Crear paciente</button>
      <button class="btn btn-neutral" onclick="hideAlta()">Cancelar</button>
    </div>
    <div class="col-12">
      <div id="altaMsg" class="muted" style="margin-top:8px;"></div>
    </div>
  </div>
</div>



<!-- 2) Agenda -->
<section id="agenda" class="section anchor">
  <h2><i class="fa-regular fa-calendar"></i> Agendar paciente </h2>

  <!-- Filtros mínimos para agenda -->
  <div class="row">
    <div class="col-6">
      <label>Especialidad</label>
      <select id="especialidad"><option value="">-- Todas --</option></select>
    </div>
    <div class="col-6">
      <label>Médico</label>
      <select id="medico"><option value="">-- Seleccioná especialidad --</option></select>
    </div>
    <div class="col-12" style="display:flex; gap:10px; margin-top:6px;">
      <button class="btn btn-primary" onclick="cargarAgenda()">Ver agenda</button>
      <button class="btn btn-neutral" onclick="limpiarAgenda()">Limpiar</button>
    </div>
    <div class="col-12"><div id="boxAgenda" class="muted" style="margin-top:10px;">Sin datos.</div></div>
  </div>
</section>

<!-- 3) Turnos del paciente -->
<section id="turnos" class="section anchor">
  <h2><i class="fa-solid fa-list-check"></i> Turnos del paciente</h2>
  <div id="boxTurnos" class="muted">Buscá o registrá un paciente para ver/cancelar o reprogramar.</div>
</section>

<p class="foot">© <?=date('Y')?> Sistema de Gestión de Turnos</p>

<script>
let pacienteSel = null;   // {id_paciente, nombre, apellido, nro_documento, email}
let turnoEnReprog = null; // {id_turno}
function toJSONorNull(txt){ try{ return JSON.parse(txt); } catch{ return null; } }

// Anclas con scroll suave
document.querySelectorAll('a[href^="#"]').forEach(a=>{
  a.addEventListener('click', e=>{
    const id = a.getAttribute('href');
    if(id && id.length>1 && document.querySelector(id)){
      e.preventDefault();
      document.querySelector(id).scrollIntoView({behavior:'smooth'});
    }
  });
});

function showAlta(){ document.getElementById('altaWrap').style.display='block'; }
function hideAlta(){ document.getElementById('altaWrap').style.display='none'; document.getElementById('altaMsg').innerText=''; }

// Cargar combos de especialidad/médico al abrir
document.addEventListener('DOMContentLoaded', ()=>{
  fetch('./combosTecnico.php')
    .then(r=>r.json())
    .then(d=>{
      const espSel = document.getElementById('especialidad');
      (d?.especialidades||[]).forEach(x=> espSel.add(new Option(x.nombre_especialidad, x.id_especialidad)));
    });
});

// Al cambiar especialidad, cargo médicos de esa esp
document.getElementById('especialidad').addEventListener('change', ()=>{
  const id_esp = document.getElementById('especialidad').value;
  const medSel = document.getElementById('medico');
  medSel.innerHTML = '<option value="">-- Seleccioná --</option>';
  if(!id_esp) return;
  const body = new URLSearchParams({ especialidad: id_esp });
  fetch('./listarMedicos.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.text())
    .then(txt=>{
      const lst = toJSONorNull(txt) || [];
      lst.forEach(m => medSel.add(new Option(m.apellido+', '+m.nombre, m.id_medico)));
    });
});

// Buscar/seleccionar paciente
function buscarPaciente(){
  const body = new URLSearchParams({
    dni:   document.getElementById('dni').value.trim(),
    email: document.getElementById('email').value.trim()
  });
  fetch('./buscarPaciente.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.text())
    .then(txt=>{
      const d = toJSONorNull(txt);
      if(!d || d.error){ alert(d?.error||'Error'); return; }
      if(!d.found){
        document.getElementById('boxPaciente').innerHTML='No existe. Podés registrarlo con el botón "Registrar".';
        pacienteSel=null; return;
      }
      pacienteSel=d;
      document.getElementById('boxPaciente').innerHTML =
        `<b>${d.apellido}, ${d.nombre}</b> <span class="pill">ID ${d.id_paciente}</span> · DNI ${d.nro_documento||'-'} · ${d.email||''}`;
      cargarTurnosPaciente();
    })
    .catch(e=>alert('Error buscar paciente: '+e));
}

// Alta de paciente
function registrarPaciente(){
  const body = new URLSearchParams({
    nombre:   document.getElementById('a_nombre').value.trim(),
    apellido: document.getElementById('a_apellido').value.trim(),
    email:    document.getElementById('a_email').value.trim(),
    dni:      document.getElementById('a_dni').value.trim(),
    telefono: document.getElementById('a_tel').value.trim(),
    fecha_nac:document.getElementById('a_fnac').value
  });
  fetch('./registrarPaciente.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.json())
    .then(d=>{
      const msg = document.getElementById('altaMsg');
      if(!d.success){ msg.innerHTML = '<span style="color:#d93636">'+d.error+'</span>'; return; }
      msg.innerHTML = 'Paciente creado. ID '+d.id_paciente+(d.password_temp?(' · Password temp: <b>'+d.password_temp+'</b>'):'');
      // Seteo como seleccionado
      pacienteSel = { id_paciente:d.id_paciente, nombre:document.getElementById('a_nombre').value, apellido:document.getElementById('a_apellido').value, nro_documento:document.getElementById('a_dni').value, email:document.getElementById('a_email').value };
      document.getElementById('boxPaciente').innerHTML = `<b>${pacienteSel.apellido}, ${pacienteSel.nombre}</b> <span class="pill">ID ${pacienteSel.id_paciente}</span>`;
      cargarTurnosPaciente();
    })
    .catch(e=>alert('Error registrando: '+e));
}

// Agenda
function limpiarAgenda(){ document.getElementById('boxAgenda').innerHTML='Sin datos.'; }
function cargarAgenda(){
  const id_medico = document.getElementById('medico').value;
  if(!id_medico){ alert('Seleccioná un médico'); return; }
  const body = new URLSearchParams({ id_medico });
  fetch('./verDisponibilidad.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.text())
    .then(txt=>{
      const map = toJSONorNull(txt) || {};
      const box = document.getElementById('boxAgenda');
      if(!Object.keys(map).length){ box.innerHTML='Sin horarios libres futuros.'; return; }
      let html = '';
      for(const [fecha, slots] of Object.entries(map)){
        html += `<div style="margin:10px 0;"><b>${fecha}</b><ul style="margin:8px 0 0 16px">`;
        slots.forEach(s=>{
          html += `<li>${s.inicio}—${s.fin}
                     <button class="btn btn-primary" style="padding:6px 10px; margin-left:8px" onclick="accionAgenda('${fecha}','${s.inicio}')">
                       ${turnoEnReprog?'Mover aquí':'Asignar'}
                     </button>
                   </li>`;
        });
        html += `</ul></div>`;
      }
      box.innerHTML = html;
    })
    .catch(e=>alert('Error agenda: '+e));
}

// Asignar / Reprogramar
function accionAgenda(fecha, hora_inicio){
  const id_medico = document.getElementById('medico').value;
  if(!id_medico){ alert('Seleccioná médico'); return; }
  if(turnoEnReprog){
    const body = new URLSearchParams({ turno_id:turnoEnReprog.id_turno, id_medico, fecha, hora_inicio });
    fetch('./reprogramarTurnoTecnico.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
      .then(r=>r.json()).then(d=>{
        if(!d.success){ alert('Error: '+d.error); return; }
        turnoEnReprog=null; alert('Turno reprogramado.');
        cargarTurnosPaciente(); cargarAgenda();
      }).catch(e=>alert('Error reprogramando: '+e));
    return;
  }
  if(!pacienteSel){ alert('Seleccioná/registrá un paciente'); return; }
  const body = new URLSearchParams({ id_paciente:pacienteSel.id_paciente, id_medico, fecha, hora_inicio });
  fetch('./confirmarTurno.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.json()).then(d=>{
      if(!d.success){ alert('Error: '+d.error); return; }
      alert('Turno asignado.'); cargarTurnosPaciente(); cargarAgenda();
    }).catch(e=>alert('Error asignando: '+e));
}

// Turnos del paciente
function cargarTurnosPaciente(){
  if(!pacienteSel) return;
  const body = new URLSearchParams({ id_paciente: pacienteSel.id_paciente });
  fetch('./listarTurnosPacientes.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.json()).then(lst=>{
      const box = document.getElementById('boxTurnos');
      if(!lst.length){ box.innerHTML='Sin turnos.'; return; }
      let html = '<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Hora</th><th>Médico</th><th>Estado</th><th style="text-align:right"></th></tr></thead><tbody>';
      lst.forEach(t=>{
        html += `<tr>
          <td>${t.fecha}</td><td>${t.hora}</td><td>${t.medico}</td><td>${t.estado}</td>
          <td style="text-align:right; display:flex; gap:6px; justify-content:flex-end;">
            <button class="btn btn-danger" onclick="cancelarTurno(${t.id_turno})">Cancelar</button>
            <button class="btn btn-primary" onclick="prepararReprog(${t.id_turno})">Reprogramar</button>
          </td></tr>`;
      });
      html += '</tbody></table></div>';
      box.innerHTML = html;
    });
}

function cancelarTurno(id_turno){
  if(!confirm('¿Cancelar este turno? (mínimo 48 h de anticipación)')) return;
  const body = new URLSearchParams({ turno_id:id_turno });
  fetch('./cancelarTurnoTecnico.php',{ method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r=>r.json()).then(d=>{
      if(!d.success){ alert('Error: '+d.error); return; }
      alert('Turno cancelado.'); cargarTurnosPaciente(); cargarAgenda();
    }).catch(e=>alert('Error cancelando: '+e));
}

function prepararReprog(id_turno){
  turnoEnReprog = { id_turno };
  alert('Elegí un nuevo horario en la Agenda para reprogramar.');
  document.querySelector('#agenda').scrollIntoView({behavior:'smooth'});
}
</script>
</body>
</html>
