<?php
// Mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../../../Persistencia/conexionBD.php';
require_once '../../../../Logica/General/verificarSesion.php';

$conn = ConexionBD::conectar();

session_start();
$paciente_id = $_SESSION['paciente_id'] ?? null;
if (!$paciente_id) {
    die("Debe estar logueado para reservar un turno.");
}

// Asegurarse de que vengan como enteros
$estudioId = isset($_POST['estudioId']) ? (int) $_POST['estudioId'] : null;
$sedeId = isset($_POST['sedeId']) ? (int) $_POST['sedeId'] : null;

if (!$estudioId || !$sedeId) {
    die("Faltan datos necesarios.");
}

// Obtener nombre del estudio
$stmt = $conn->prepare("SELECT nombre FROM estudios WHERE id = ?");
$stmt->bind_param("i", $estudioId);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows == 0) {
    die("Estudio no encontrado.");
}
$estudio = $res->fetch_assoc();

// Función para obtener días disponibles
function getDisponibilidadDias($conn, $estudioId, $sedeId) {
    $dias = [];
    $today = new DateTime();
    for ($i = 0; $i < 15; $i++) {
        $date = $today->format('Y-m-d');

        $sql = "
            SELECT COUNT(*) as cnt 
            FROM agenda_estudios a 
            JOIN recursos r ON a.recurso_id = r.id
            WHERE a.estudio_id = ? AND r.sede_id = ? AND a.fecha = ? AND a.disponible = TRUE
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $estudioId, $sedeId, $date);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $dias[$date] = ($row['cnt'] > 0);
        $today->modify('+1 day');
    }
    return $dias;
}

$diasDisponibles = getDisponibilidadDias($conn, $estudioId, $sedeId);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>Disponibilidad para <?= htmlspecialchars($estudio['nombre']) ?></title>
    <style>
        .disponible {
            background-color: #cfc;
            color: #060;
            cursor: pointer;
        }
        .no-disponible {
            background-color: #fdd;
            color: #900;
        }
        .dia {
            margin: 5px;
            padding: 5px 10px;
            display: inline-block;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .selected {
            border: 2px solid #333;
        }
    </style>
</head>
<body>
    <h2>Disponibilidad para <?= htmlspecialchars($estudio['nombre']) ?></h2>

    <h3>Seleccione un día:</h3>
    <div id="dias">
        <?php foreach ($diasDisponibles as $fecha => $disp): 
            $clase = $disp ? "disponible" : "no-disponible";
        ?>
            <div class="dia <?= $clase ?>" data-fecha="<?= $fecha ?>">
                <?= (new DateTime($fecha))->format('d/m/Y') ?>
            </div>
        <?php endforeach; ?>
    </div>

    <h3>Horarios disponibles para <span id="fechaSeleccionada">[Seleccione un día]</span>:</h3>

    <form action="../../../../../Logica/Paciente/Gestion-Turnos/confirmarTurno.php" method="POST" id="formTurno" style="display:none;" enctype="multipart/form-data">
        <input type="hidden" name="estudioId" value="<?= $estudioId ?>">
        <input type="hidden" name="sedeId" value="<?= $sedeId ?>">
        <input type="hidden" name="fecha" id="inputFecha">

        <label for="hora">Horario:</label>
        <select name="hora" id="hora" required></select>
        <br><br>

        <label for="orden">Subir imagen de la orden médica (requerido):</label>
        <input type="file" name="orden" id="orden" accept="image/*" required>
        <br><br>

        <button type="submit">Confirmar Turno</button>
    </form>

<script>
document.querySelectorAll('.disponible').forEach(diaDiv => {
    diaDiv.addEventListener('click', () => {
        document.querySelectorAll('.dia').forEach(d => d.classList.remove('selected'));
        diaDiv.classList.add('selected');

        const fecha = diaDiv.getAttribute('data-fecha');
        document.getElementById('fechaSeleccionada').innerText = new Date(fecha).toLocaleDateString();
        document.getElementById('inputFecha').value = fecha;

        fetch('/Logica/Paciente/Gestion-Turnos/obtenerHorarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `estudioId=<?= (int) $estudioId ?>&sedeId=<?= (int) $sedeId ?>&fecha=${fecha}`
        })
        .then(response => response.text())
        .then(data => {
            console.log("Respuesta del servidor:", data);
            try {
                const json = JSON.parse(data);
                const select = document.getElementById('hora');
                select.innerHTML = '';
                if (json.length > 0) {
                    json.forEach(horario => {
                        const option = document.createElement('option');
                        option.value = horario.hora_inicio;
                        option.textContent = horario.hora_inicio + ' - ' + horario.hora_fin;
                        select.appendChild(option);
                    });
                    document.getElementById('formTurno').style.display = 'block';
                } else {
                    select.innerHTML = '<option value="">No hay horarios disponibles</option>';
                    document.getElementById('formTurno').style.display = 'none';
                }
            } catch (err) {
                console.error("Error al parsear JSON:", err);
                alert("Error al procesar la respuesta del servidor.");
            }
        })
        .catch(err => {
            alert('Error de red al cargar horarios.');
            console.error(err);
        });
    });
});
</script>

</body>
</html>