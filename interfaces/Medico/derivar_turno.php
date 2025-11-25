<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== Seguridad =====
$rol_requerido = 2;
require_once('../../Logica/General/verificarSesion.php');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// ===== Conexión =====
require_once('../../Persistencia/conexionBD.php');

$id_medico_login = $_SESSION['id_medico'] ?? 0;
$id_turno = isset($_GET['id_turno']) ? (int)$_GET['id_turno'] : 0;
if ($id_turno <= 0) { http_response_code(400); echo "Falta id_turno"; exit; }

// --- Mensaje de resultado ---
$mensaje_resultado = null;
$tipo_mensaje = null; // 'success' o 'error'

// --- Helpers de datos ---
function getTurno($id_turno) {
  $cn = ConexionBD::conectar();
  $sql = "SELECT t.*, p.id_paciente, p.nro_documento AS dni,
                 u.nombre AS nombre_pac, u.apellido AS ape_pac
            FROM turnos t
            JOIN pacientes p ON p.id_paciente = t.id_paciente
            JOIN usuarios   u ON u.id_usuario   = p.id_usuario
           WHERE t.id_turno = ?";
  $st = $cn->prepare($sql);
  $st->bind_param("i", $id_turno);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $st->close(); $cn->close();
  return $res ?: [];
}

function listarEspecialidades() {
  $cn = ConexionBD::conectar();
  $res = $cn->query("SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad");
  $data = $res->fetch_all(MYSQLI_ASSOC);
  $cn->close();
  return $data;
}

function verificarDisponibilidadSlot($id_medico, $fecha, $hora) {
  $cn = ConexionBD::conectar();
  
  // Verificar si el slot está disponible
  $sql = "SELECT COUNT(*) as ocupado FROM turnos 
          WHERE id_medico = ? AND fecha = ? AND hora = ? 
          AND id_estado IN (1, 2)"; // 1=Pendiente, 2=Confirmado
  
  $st = $cn->prepare($sql);
  $st->bind_param("iss", $id_medico, $fecha, $hora);
  $st->execute();
  $res = $st->get_result()->fetch_assoc();
  $st->close();
  $cn->close();
  
  return ($res['ocupado'] == 0);
}

function registrarDerivacion($turno_origen, $id_medico_origen, $id_medico_destino, $motivo, $fecha, $hora) {
    if (!verificarDisponibilidadSlot($id_medico_destino, $fecha, $hora)) {
        error_log("Slot no disponible: medico=$id_medico_destino, fecha=$fecha, hora=$hora");
        return ['success' => false, 'error' => 'El horario seleccionado ya no está disponible.'];
    }

    $cn = ConexionBD::conectar();
    $cn->set_charset('utf8mb4');
    $cn->begin_transaction();

    try {
        $id_turno_origen = (int)$turno_origen['id_turno'];
        $id_paciente = (int)$turno_origen['id_paciente'];
        $obs_original = $turno_origen['observaciones'] ?? '';

        // ===== Obtener id_estado dinámicos =====
        $getIdEstado = function($nombre) use ($cn) {
            $sql = "SELECT id_estado FROM estados WHERE LOWER(nombre_estado) = LOWER(?) LIMIT 1";
            $st = $cn->prepare($sql);
            $st->bind_param("s", $nombre);
            $st->execute();
            $res = $st->get_result()->fetch_assoc();
            $st->close();
            return $res['id_estado'] ?? null;
        };

        $id_estado_derivado = $getIdEstado('derivado') ?? 6;
        $id_estado_atendido = $getIdEstado('atendido') ?? 3;

        // ===== Obtener id_recurso del médico destino =====
        $sql_recurso = "SELECT id_recurso FROM medico_recursos WHERE id_medico = ? LIMIT 1";
        $stRecurso = $cn->prepare($sql_recurso);
        $stRecurso->bind_param("i", $id_medico_destino);
        $stRecurso->execute();
        $res = $stRecurso->get_result()->fetch_assoc();
        $stRecurso->close();

        $id_recurso = $res['id_recurso'] ?? null;
        if (!$id_recurso) throw new Exception("El médico destino no tiene un recurso asignado.");

        // ===== Preparar observaciones del turno derivado =====
        $obs_derivado = "[DERIVADO desde turno #$id_turno_origen]\n";
        if ($obs_original) $obs_derivado .= "Observaciones previas: $obs_original\n";
        $obs_derivado .= "Motivo de derivación: $motivo";

        // ===== Insertar nuevo turno para el médico destino =====
        $sql_turno = "INSERT INTO turnos (id_paciente, id_medico, id_estado, fecha, hora, copago, observaciones, fecha_creacion, id_recurso)
                      VALUES (?, ?, ?, ?, ?, 0.00, ?, NOW(), ?)";
        $st = $cn->prepare($sql_turno);
        $st->bind_param("iiisssi", $id_paciente, $id_medico_destino, $id_estado_derivado, $fecha, $hora, $obs_derivado, $id_recurso);
        if (!$st->execute()) throw new Exception("Error al insertar nuevo turno: " . $st->error);
        $id_nuevo_turno = $cn->insert_id;
        $st->close();

        // ===== Actualizar turno original (marcar como atendido) =====
        $sql_update_origen = "UPDATE turnos 
                              SET observaciones = CONCAT(COALESCE(observaciones,''), '\n[DERIVADO el ', NOW(), ' al médico #', ?, ']'),
                                  id_estado = ?
                              WHERE id_turno = ?";
        $st4 = $cn->prepare($sql_update_origen);
        $st4->bind_param("iii", $id_medico_destino, $id_estado_atendido, $id_turno_origen);
        $st4->execute();
        $st4->close();

        // ===== Opcional: guardar nota en observaciones adicionales =====
        $nota_derivacion = "Derivación recibida del médico #$id_medico_origen";
        $sql_obs = "INSERT INTO observaciones (id_turno, id_paciente, fecha, nota) VALUES (?, ?, CURDATE(), ?)";
        $stObs = $cn->prepare($sql_obs);
        $stObs->bind_param("iis", $id_nuevo_turno, $id_paciente, $nota_derivacion);
        $stObs->execute();
        $stObs->close();

        $cn->commit();
        $cn->close();

        error_log("Derivación exitosa: turno origen=$id_turno_origen, nuevo turno=$id_nuevo_turno");
        return ['success' => true, 'id_turno' => $id_nuevo_turno];

    } catch (Exception $e) {
        $cn->rollback();
        $cn->close();
        error_log("Error derivar turno: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}





// ===== POST: enviar derivación =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $destino = (int)($_POST['id_medico_dest'] ?? 0);
  $motivo = trim($_POST['motivo'] ?? '');
  $fecha = $_POST['fecha'] ?? null;
  $hora = $_POST['hora'] ?? null;

  if (!$destino || !$fecha || !$hora || !$motivo) {
    $mensaje_resultado = "Faltan datos obligatorios. Verifique médico destino, fecha, hora y motivo.";
    $tipo_mensaje = 'error';
  } else {
    $tk = getTurno($id_turno);
    
    if (!$tk) {
      $mensaje_resultado = "El turno de origen no existe.";
      $tipo_mensaje = 'error';
    } else {
      $resultado = registrarDerivacion($tk, $id_medico_login, $destino, $motivo, $fecha, $hora);
      
      if ($resultado['success']) {
        $mensaje_resultado = "✓ Derivación enviada exitosamente. Turno #" . $resultado['id_turno'] . " creado.";
        $tipo_mensaje = 'success';
        
        // Redirigir después de 2 segundos
        header("Refresh: 2; url=turnos.php");
      } else {
        $mensaje_resultado = "Error al registrar la derivación: " . ($resultado['error'] ?? 'Error desconocido');
        $tipo_mensaje = 'error';
      }
    }
  }
}

// ===== Datos para render =====
$turno = getTurno($id_turno);
$especialidades = listarEspecialidades();

$pacienteCompleto = trim(($turno['ape_pac'] ?? '').', '.($turno['nombre_pac'] ?? ''));
$fecha = $turno['fecha'] ?? '';
$hora  = $turno['hora']  ?? '';
$dni   = $turno['dni']   ?? '-';
$obs   = trim($turno['observaciones'] ?? '') ?: 'Sin ficha registrada.';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Derivar Paciente</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; }
    .wrap { max-width: 1200px; margin: 0 auto; padding: 20px; }
    .header { margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
    .page-title { font-size: 28px; font-weight: 600; }
    .back { text-decoration: none; color: #666; font-size: 14px; }
    .back:hover { color: #000; }
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .badge { display: inline-block; background: #e3f2fd; color: #1976d2; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }
    .kicker { color: #666; font-size: 14px; }
    .meta { margin: 16px 0; display: flex; flex-direction: column; gap: 8px; color: #555; font-size: 14px; }
    .block { margin-bottom: 20px; }
    .block label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 14px; }
    .pill { display: inline-block; background: #f5f5f5; padding: 6px 12px; border-radius: 16px; font-size: 13px; }
    .code { background: #f8f9fa; border: 1px solid #e0e0e0; padding: 12px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 13px; white-space: pre-wrap; }
    select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
    textarea { resize: vertical; min-height: 80px; font-family: inherit; }
    .helper { text-align: right; font-size: 12px; color: #999; margin-top: 4px; }
    .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .month { font-weight: 600; text-transform: capitalize; }
    .cal-nav { display: flex; gap: 4px; }
    .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500; }
    .btn-primary { background: #1976d2; color: white; }
    .btn-outline { background: white; border: 1px solid #ddd; color: #333; }
    .btn-ghost { background: transparent; color: #666; }
    .btn-icon { padding: 6px 10px; }
    .btn:hover { opacity: 0.9; }
    .btn:disabled { opacity: 0.5; cursor: not-allowed; }
    .dow{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;margin:6px 0 6px}
    .dow span{font-size:12px;color:var(--muted);text-align:center}
    .cal-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;min-height:238px}
    .day{
  background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;height:44px;
  display:flex;align-items:center;justify-content:center;cursor:pointer;user-select:none;transition:.15s;
}
.day:hover{transform:translateY(-1px); border-color:#d1d5db}
.day.pad{background:transparent;border:none;cursor:default}
.day.disabled{opacity:.35; cursor:not-allowed}
.day.free{background:#ecfdf5;border-color:#a7f3d0;color:#166534;font-weight:700}
.day.busy{background:#fef2f2;border-color:#fecaca;color:#7f1d1d;font-weight:700}
.day.none{background:#f3f4f6;border-color:#e5e7eb;color:#6b7280;font-weight:700}
.day.selected{outline:2px solid var(--primary)}
    .legend { display: flex; gap: 16px; font-size: 12px; color: #666; }
    .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; }
    .dot-green { background: #4caf50; }
    .dot-red { background: #f44336; }
    .dot-gray { background: #bbb; }
    .slots-list { max-height: 200px; overflow-y: auto; }
    .slot { padding: 12px; border: 1px solid #e0e0e0; border-radius: 4px; margin-bottom: 8px; display: flex; justify-content: space-between; cursor: pointer; }
    .slot:hover { background: #f5f5f5; }
    .slot.selected { background: #e3f2fd; border-color: #1976d2; }
    .empty { padding: 20px; text-align: center; color: #999; font-size: 14px; }
    .actions-row { display: flex; gap: 12px; justify-content: flex-end; margin-top: 24px; }
    #calendarSection { display: none; }
    
    /* Mensajes de resultado */
    .mensaje-resultado { padding: 16px; border-radius: 4px; margin-bottom: 20px; font-weight: 500; }
    .mensaje-resultado.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .mensaje-resultado.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
  </style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <h1 class="page-title"><i class="fa-solid fa-share-nodes"></i> Derivar paciente</h1>
    <a class="back" href="turnos.php"><i class="fa-solid fa-arrow-left"></i> Volver a Turnos</a>
  </div>

  <?php if ($mensaje_resultado): ?>
  <div class="mensaje-resultado <?= $tipo_mensaje ?>">
    <?= htmlspecialchars($mensaje_resultado) ?>
  </div>
  <?php endif; ?>

  <div class="grid">
    <!-- Columna izquierda: Info del paciente -->
    <section class="card">
      <div style="margin-bottom:12px">
        <div class="badge"><i class="fa-solid fa-user"></i> Paciente</div>
        <span class="kicker" style="margin-left:8px">Detalle del turno de origen</span>
      </div>
      
      <div class="meta">
        <div><i class="fa-solid fa-id-card-clip"></i> <?= htmlspecialchars($pacienteCompleto) ?></div>
        <div><i class="fa-solid fa-id-card"></i> DNI <?= htmlspecialchars($dni) ?></div>
      </div>
      
      <div class="block">
        <span class="pill"><i class="fa-solid fa-calendar-day"></i> <?= htmlspecialchars($fecha ?: '-') ?> · <?= htmlspecialchars(substr((string)$hora,0,8) ?: '--:--') ?></span>
      </div>
      
      <div class="block">
        <label>Observaciones del turno</label>
        <div class="code"><?= htmlspecialchars($obs) ?></div>
      </div>
    </section>

    <!-- Columna derecha: Formulario de derivación -->
    <section class="card">
      <form method="post" id="formDerivar">
        <div style="margin-bottom:12px">
          <div class="badge"><i class="fa-solid fa-user-doctor"></i> Derivación</div>
          <span class="kicker" style="margin-left:8px">Seleccioná destino, fecha y hora</span>
        </div>

        <div class="block">
          <label>Especialidad destino</label>
          <select id="especialidad" required>
            <option value="">-- Seleccione especialidad --</option>
            <?php foreach($especialidades as $e): ?>
              <option value="<?= (int)$e['id_especialidad'] ?>"><?= htmlspecialchars($e['nombre_especialidad']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="block">
          <label>Médico destino</label>
          <select name="id_medico_dest" id="selectMedico" required>
            <option value="">-- Seleccione médico --</option>
          </select>
        </div>

        <!-- CALENDARIO -->
        <div id="calendarSection">
          <div class="block">
            <label>Seleccione fecha</label>
            <div class="cal-header">
              <div class="month" id="monthLabel">—</div>
              <div class="cal-nav">
                <button type="button" class="btn btn-ghost btn-icon" id="prevBtn" title="Mes anterior"><i class="fa-solid fa-chevron-left"></i></button>
                <button type="button" class="btn btn-ghost btn-icon" id="nextBtn" title="Mes siguiente"><i class="fa-solid fa-chevron-right"></i></button>
              </div>
            </div>
            <div class="dow"><span>LUN</span><span>MAR</span><span>MIÉ</span><span>JUE</span><span>VIE</span><span>SÁB</span><span>DOM</span></div>
            <div class="cal-grid" id="days"></div>
            <div class="legend">
              <span><span class="dot dot-green"></span> Disponible</span>
              <span><span class="dot dot-red"></span> Ocupado/Bloqueado</span>
              <span><span class="dot dot-gray"></span> Sin agenda</span>
            </div>
          </div>

          <div class="block">
            <label>Horario disponible</label>
            <input type="hidden" name="fecha" id="fechaHidden">
            <input type="hidden" name="hora" id="horaHidden">
            <div class="slots-list" id="slotsList">
              <div class="empty">Seleccioná un día en el calendario para ver horarios.</div>
            </div>
          </div>
        </div>

        <div class="block">
          <label>Motivo de derivación *</label>
          <textarea name="motivo" id="motivo" placeholder="Describa brevemente el motivo de la derivación..." required maxlength="2000"></textarea>
          <div class="helper"><span id="count">0</span>/2000</div>
        </div>

        <div class="actions-row">
          <button type="button" class="btn btn-outline" id="btnCancelar"><i class="fa-solid fa-xmark"></i> Cancelar</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar derivación</button>
        </div>
      </form>
    </section>
  </div>
</div>

<script>
(() => {
  const API_ESTADO_MES = 'api/agenda_estado.php';
  const API_SLOTS = 'api/agenda_slots_medico.php';
  const API_MEDICOS_ESP = 'api/medicos_por_especialidad.php';

  const selectEsp = document.getElementById('especialidad');
  const selectMed = document.getElementById('selectMedico');
  const calSection = document.getElementById('calendarSection');
  const monthLabel = document.getElementById('monthLabel');
  const daysGrid = document.getElementById('days');
  const prevBtn = document.getElementById('prevBtn');
  const nextBtn = document.getElementById('nextBtn');
  const slotsList = document.getElementById('slotsList');
  const fechaHidden = document.getElementById('fechaHidden');
  const horaHidden = document.getElementById('horaHidden');
  const ta = document.getElementById('motivo');
  const count = document.getElementById('count');

  const asJson = (resp) => {
    const ct = resp.headers.get('content-type') || '';
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    if (!ct.includes('application/json')) {
      return resp.text().then(t => { throw new Error('No JSON: ' + t.slice(0,200)); });
    }
    return resp.json();
  };
  const fromISODateLocal = (iso) => { const [Y,M,D]=iso.split('-').map(Number); return new Date(Y,M-1,D); };
  const pad = (n) => String(n).padStart(2,'0');

  const today = new Date(); today.setHours(0,0,0,0);
  let current = new Date(today.getFullYear(), today.getMonth(), 1);
  let selectedDate = null;
  let selectedMedicoId = null;

  selectEsp.addEventListener('change', async (e) => {
    const espId = e.target.value;
    selectMed.innerHTML = '<option value="">Cargando...</option>';
    calSection.style.display = 'none';
    selectedMedicoId = null;
    
    if (!espId) {
      selectMed.innerHTML = '<option value="">-- Seleccione médico --</option>';
      return;
    }
    
    try {
      const res = await fetch(`${API_MEDICOS_ESP}?id_especialidad=${espId}`);
      const data = await res.json();
      selectMed.innerHTML = '<option value="">-- Seleccione médico --</option>';
      data.forEach(m => {
        selectMed.innerHTML += `<option value="${m.id_medico}">${m.nombre_completo}</option>`;
      });
    } catch (err) {
      selectMed.innerHTML = '<option value="">Error al cargar médicos</option>';
      console.error(err);
    }
  });

  selectMed.addEventListener('change', (e) => {
    selectedMedicoId = e.target.value;
    if (selectedMedicoId) {
      calSection.style.display = 'block';
      current = new Date(today.getFullYear(), today.getMonth(), 1);
      renderMonth();
    } else {
      calSection.style.display = 'none';
    }
  });

  prevBtn.onclick = () => { shiftMonth(-1); };
  nextBtn.onclick = () => { shiftMonth(1); };

  function canGoPrevMonth() {
    return (current.getFullYear() > today.getFullYear()) ||
           (current.getFullYear() === today.getFullYear() && current.getMonth() > today.getMonth());
  }

  function shiftMonth(delta) {
    const nd = new Date(current); 
    nd.setMonth(current.getMonth() + delta);
    if (delta < 0) {
      const before = (nd.getFullYear() < today.getFullYear()) ||
                     (nd.getFullYear() === today.getFullYear() && nd.getMonth() < today.getMonth());
      if (before) return;
    }
    current = nd;
    renderMonth();
    clearSelection();
  }

  function clearSelection() {
    selectedDate = null;
    fechaHidden.value = '';
    horaHidden.value = '';
    slotsList.innerHTML = '<div class="empty">Seleccioná un día en el calendario para ver horarios.</div>';
  }

  async function renderMonth() {
    if (!selectedMedicoId) return;

    const y = current.getFullYear();
    const m = current.getMonth() + 1;
    monthLabel.textContent = current.toLocaleDateString('es-AR', {month:'long', year:'numeric'});
    daysGrid.innerHTML = '';
    prevBtn.disabled = !canGoPrevMonth();

    const lead = (new Date(y, m-1, 1).getDay() + 6) % 7;
    for (let i = 0; i < lead; i++) {
      const p = document.createElement('div');
      p.className = 'day pad';
      daysGrid.appendChild(p);
    }

    const lastDate = new Date(y, m, 0).getDate();
    const cells = [];
    for (let d = 1; d <= lastDate; d++) {
      const el = document.createElement('div');
      el.className = 'day';
      el.textContent = d;
      const iso = `${y}-${pad(m)}-${pad(d)}`;
      el.dataset.date = iso;
      if (fromISODateLocal(iso) < today) el.classList.add('disabled');
      daysGrid.appendChild(el);
      cells.push(el);
    }

    let map = {};
    try {
      const data = await fetch(`${API_ESTADO_MES}?id_medico=${selectedMedicoId}&anio=${y}&mes=${m}`).then(asJson);
      (Array.isArray(data) ? data : []).forEach(d => { map[d.dia] = d; });
    } catch (e) {
      console.error('Error estado mes:', e);
    }

    cells.forEach((box, idx) => {
      const info = map[idx + 1];
      box.classList.remove('free', 'busy', 'none');

      if (!info) {
        box.classList.add('none');
        box.title = 'Sin agenda';
      } else {
        switch(info.estado) {
          case 'verde':
            box.classList.add('free');
            box.title = 'Disponible';
            break;
          case 'rojo':
            box.classList.add('busy');
            box.title = 'Ocupado/Bloqueado';
            break;
          case 'gris':
            box.classList.add('none');
            box.title = 'Día pasado';
            break;
          default:
            box.classList.add('none');
            box.title = 'Sin agenda';
        }
      }

      if (!box.classList.contains('disabled')) {
        box.onclick = () => {
          document.querySelectorAll('.day.selected').forEach(n => n.classList.remove('selected'));
          box.classList.add('selected');
          selectedDate = box.dataset.date;
          fechaHidden.value = selectedDate;
          loadSlots(selectedDate);
        };
      } else {
        box.onclick = null;
      }
    });
  }

  async function loadSlots(fecha) {
    slotsList.innerHTML = '<div class="empty">Cargando horarios...</div>';
    horaHidden.value = '';

    try {
      const res = await fetch(`${API_SLOTS}?id_medico=${selectedMedicoId}&fecha=${encodeURIComponent(fecha)}`);
      const data = await res.json();

      if (!data.ok || !data.slots) {
        slotsList.innerHTML = '<div class="empty">No hay horarios disponibles para este día.</div>';
        return;
      }

      const disponibles = data.slots.filter(s => s.estado === 'disponible' && s.en_franja);

      if (disponibles.length === 0) {
        slotsList.innerHTML = '<div class="empty">No hay horarios disponibles para este día.</div>';
        return;
      }

      slotsList.innerHTML = '';
      disponibles.forEach(s => {
        const row = document.createElement('div');
        row.className = 'slot free';
        row.innerHTML = `<span>${s.hora}</span><i class="fa-solid fa-check"></i>`;
        row.onclick = () => {
          document.querySelectorAll('.slot.selected').forEach(n => n.classList.remove('selected'));
          row.classList.add('selected');
          horaHidden.value = s.hora;
        };
        slotsList.appendChild(row);
      });
    } catch (err) {
      console.error('Error al cargar slots:', err);
      slotsList.innerHTML = '<div class="empty">Error al cargar horarios.</div>';
    }
  }

  if (ta) {
    const fit = () => {
      ta.style.height = 'auto';
      ta.style.height = (ta.scrollHeight + 2) + 'px';
      count.textContent = ta.value.length;
    };
    ['input', 'change'].forEach(ev => ta.addEventListener(ev, fit));
    setTimeout(fit, 0);
  }

  document.getElementById('btnCancelar').onclick = () => window.location.href = 'turnos.php';

  document.getElementById('formDerivar').addEventListener('submit', e => {
    if (!fechaHidden.value || !horaHidden.value) {
      e.preventDefault();
      alert('Debe seleccionar una fecha y hora del calendario.');
      return;
    }
    if (!confirm('¿Confirmás enviar la derivación al médico seleccionado?')) {
      e.preventDefault();
    }
  });
})();
</script>
</body>
</html