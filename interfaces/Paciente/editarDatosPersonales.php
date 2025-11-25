<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');
require_once('../../interfaces/mostrarAlerta.php');

$id_usuario = $_SESSION['id_usuario'] ?? null;

$conn = ConexionBD::conectar();

// ===== FUNCIONALIDAD: Guardar cambios =====
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $genero = trim($_POST['genero'] ?? '');
    $tipo_documento = trim($_POST['tipo_documento'] ?? '');
    $nro_documento = trim($_POST['nro_documento'] ?? '');
    $fecha_nacimiento = trim($_POST['fecha_nacimiento'] ?? null);
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validaciones mínimas
    if (!$nombre || !$apellido || !$email) {
        $mensaje = 'Nombre, apellido y email son obligatorios.';
         mostrarAlerta('error', $mensaje);
    } else {
        // Verificar duplicados de email en otros usuarios
        $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE email = ? AND id_usuario != ?");
        $stmt->bind_param("si", $email, $id_usuario);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $mensaje = 'El email ya está en uso por otro usuario.';
            mostrarAlerta('error', $mensaje); 
        } else {
            // Verificar duplicados de nro_documento en otros pacientes
            $stmt = $conn->prepare("SELECT id_paciente FROM pacientes WHERE nro_documento = ? AND id_usuario != ?");
            $stmt->bind_param("si", $nro_documento, $id_usuario);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $mensaje = 'El número de documento ya está en uso por otro paciente.';
                mostrarAlerta('error', $mensaje);
            } else {
                // ===== UPDATE seguro =====
                $conn->begin_transaction();

                try {
                    $stmt = $conn->prepare("UPDATE usuarios SET nombre=?, apellido=?, email=?, genero=? WHERE id_usuario=?");
                    $stmt->bind_param("ssssi", $nombre, $apellido, $email, $genero, $id_usuario);
                    $stmt->execute();

                    $stmt = $conn->prepare("UPDATE pacientes SET tipo_documento=?, nro_documento=?, fecha_nacimiento=?, direccion=?, telefono=? WHERE id_usuario=?");
                    $stmt->bind_param("sssssi", $tipo_documento, $nro_documento, $fecha_nacimiento, $direccion, $telefono, $id_usuario);
                    $stmt->execute();

                    $conn->commit();
                    $mensaje = 'Datos actualizados correctamente.';
                     mostrarAlerta('success', $mensaje);
                } catch (Throwable $e) {
                    $conn->rollback();
                    $mensaje = 'Error al actualizar datos: ' . $e->getMessage();
                    mostrarAlerta('error', $mensaje);
                }
            }
        }
    }
}

// ===== Obtener datos actuales =====
$sql = "SELECT u.nombre, u.apellido, u.email, u.genero,
               p.tipo_documento, p.nro_documento, p.fecha_nacimiento, p.direccion, p.telefono
        FROM usuarios u
        LEFT JOIN pacientes p ON u.id_usuario = p.id_usuario
        WHERE u.id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$datosPaciente = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Editar Datos Personales</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="../../css/principalPac.css">
<link rel="stylesheet" href="../../css/turnoMedico.css">
<style>
    /* Ajustes específicos para datosPersonales */
    .card-form {
        max-width: 500px;
        margin: 40px auto;
    }

    .card-form h1 {
        background: linear-gradient(135deg, #1a4d6d 0%, #0d2738 100%);
        color: white;
        padding: 25px;
        margin: -30px -30px 30px -30px;
        border-radius: 15px 15px 0 0;
        text-align: center;
        font-size: 28px;
        font-weight: 700;
        text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    }

    .card-form label {
        color: white;
        font-weight: 600;
        font-size: 15px;
        margin-bottom: 8px;
        display: block;
        text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
    }

    .card-form input,
    .card-form select {
        width: 100%;
        padding: 12px 15px;
        margin-bottom: 20px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 15px;
        background: white;
        transition: all 0.3s ease;
    }

    .card-form input:focus,
    .card-form select:focus {
        outline: none;
        border-color: #00bcd4;
        box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.1);
    }

    .card-form button[type="submit"] {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 15px;
        box-shadow: 0 4px 15px rgba(0, 188, 212, 0.3);
    }

    .card-form button[type="submit"]:hover {
        background: linear-gradient(135deg, #0097a7 0%, #00838f 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 188, 212, 0.4);
    }

    .card-form button[type="submit"] i {
        margin-right: 8px;
    }

    .card-form .btn-volver {
        display: block;
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, #00bcd4 0%, #0097a7 100%);
        color: white;
        text-align: center;
        text-decoration: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(0, 188, 212, 0.3);
    }

    .card-form .btn-volver:hover {
        background: linear-gradient(135deg, #0097a7 0%, #00838f 100%);
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 188, 212, 0.4);
    }

    /* Mensaje de éxito/error */
    .mensaje-alert {
        padding: 15px 20px;
        border-radius: 8px;
        margin-bottom: 25px;
        font-weight: 600;
        text-align: center;
        animation: slideDown 0.3s ease;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .mensaje-success {
        background: #d4edda;
        color: #155724;
        border: 2px solid #c3e6cb;
    }

    .mensaje-error {
        background: #f8d7da;
        color: #721c24;
        border: 2px solid #f5c6cb;
    }

    /* Ajustes responsivos */
    @media (max-width: 768px) {
        .card-form {
            margin: 20px auto;
            padding: 20px;
        }

        .card-form h1 {
            font-size: 24px;
            padding: 20px;
        }
    }

    /* Agregar después de los estilos existentes */

/* Secciones del formulario */
.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid rgba(255, 255, 255, 0.2);
}

.form-section:last-of-type {
    border-bottom: none;
}

.form-section h3 {
    color: white;
    font-size: 18px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
}

.form-section h3 i {
    color: #00bcd4;
}

/* Campos requeridos */
.required {
    color: #ff5252;
    font-weight: bold;
    margin-left: 3px;
}

/* Grupo de botones */
.button-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-top: 30px;
}

.btn-guardar {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
}

.btn-guardar:hover {
    background: linear-gradient(135deg, #45a049 0%, #3d8b40 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
}

.btn-cancelar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(244, 67, 54, 0.3);
}

.btn-cancelar:hover {
    background: linear-gradient(135deg, #d32f2f 0%, #c62828 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(244, 67, 54, 0.4);
}

/* Feedback visual en inputs */
.card-form input:valid:not(:placeholder-shown) {
    border-color: #4caf50;
}

.card-form input:invalid:not(:placeholder-shown) {
    border-color: #f44336;
}

/* Tooltips para errores */
.card-form input:invalid:focus {
    box-shadow: 0 0 0 3px rgba(244, 67, 54, 0.1);
}

/* Estilos responsive mejorados */
@media (max-width: 768px) {
    .button-group {
        grid-template-columns: 1fr;
    }
    
    .form-section h3 {
        font-size: 16px;
    }
    
    .card-form label {
        font-size: 14px;
    }
}
</style>
</head>
<body>
<?php include('navPac.php'); ?>
        
<div class="container">
    <div class="card-form">
        <h1>Mis Datos Personales</h1>
        
        <?php if ($mensaje): ?>
            <div class="mensaje-alert <?= strpos($mensaje, 'correctamente') !== false ? 'mensaje-success' : 'mensaje-error' ?>">
                <?php if (strpos($mensaje, 'correctamente') !== false): ?>
                    <i class="fas fa-check-circle"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($datosPaciente): ?>
        <form method="POST" action="" id="form-datos">
            <div class="form-section">
                <h3><i class="fas fa-user"></i> Información Personal</h3>
                
                <label for="nombre">Nombre <span class="required">*</span></label>
                <input type="text" id="nombre" name="nombre" 
                       value="<?= htmlspecialchars($datosPaciente['nombre']) ?>" 
                       placeholder="Ingrese su nombre"
                       required>

                <label for="apellido">Apellido <span class="required">*</span></label>
                <input type="text" id="apellido" name="apellido" 
                       value="<?= htmlspecialchars($datosPaciente['apellido']) ?>" 
                       placeholder="Ingrese su apellido"
                       required>

                <label for="genero">Género</label>
                <select id="genero" name="genero">
                    <option value="">-- Seleccione --</option>
                    <option value="Masculino" <?= $datosPaciente['genero']=='Masculino'?'selected':'' ?>>Masculino</option>
                    <option value="Femenino" <?= $datosPaciente['genero']=='Femenino'?'selected':'' ?>>Femenino</option>
                    <option value="Otro" <?= $datosPaciente['genero']=='Otro'?'selected':'' ?>>Otro</option>
                </select>

                <label for="fecha_nacimiento">Fecha de Nacimiento</label>
                <input type="date" id="fecha_nacimiento" name="fecha_nacimiento" 
                       value="<?= htmlspecialchars($datosPaciente['fecha_nacimiento']) ?>"
                       max="<?= date('Y-m-d') ?>">
            </div>

            <div class="form-section">
    <h3><i class="fas fa-id-card"></i> Documentación</h3>
    
    <label for="tipo_documento">Tipo de Documento</label>
    <select id="tipo_documento" name="tipo_documento_display" disabled>
        <option value="">-- Seleccione --</option>
        <option value="DNI" <?= $datosPaciente['tipo_documento']=='DNI'?'selected':'' ?>>DNI</option>
        <option value="LC" <?= $datosPaciente['tipo_documento']=='LC'?'selected':'' ?>>LC</option>
        <option value="LE" <?= $datosPaciente['tipo_documento']=='LE'?'selected':'' ?>>LE</option>
        <option value="Pasaporte" <?= $datosPaciente['tipo_documento']=='Pasaporte'?'selected':'' ?>>Pasaporte</option>
    </select>
    <!-- Input hidden para enviar el valor en el POST -->
    <input type="hidden" name="tipo_documento" value="<?= htmlspecialchars($datosPaciente['tipo_documento']) ?>">

    <label for="nro_documento">Número de Documento</label>
    <input type="text" id="nro_documento" name="nro_documento" 
           value="<?= htmlspecialchars($datosPaciente['nro_documento']) ?>"
           placeholder="Ej. 30111222"
           readonly>
</div>


            <div class="form-section">
                <h3><i class="fas fa-envelope"></i> Contacto</h3>
                
                <label for="email">Email <span class="required">*</span></label>
                <input type="email" id="email" name="email" 
                       value="<?= htmlspecialchars($datosPaciente['email']) ?>" 
                       placeholder="ejemplo@email.com"
                       required>

                <label for="telefono">Teléfono</label>
                <input type="tel" id="telefono" name="telefono" 
                       value="<?= htmlspecialchars($datosPaciente['telefono']) ?>"
                       placeholder="Ej. 1130111222"
                       pattern="[0-9]{10,15}"
                       title="Ingrese un teléfono válido (10-15 dígitos)">

                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion" 
                       value="<?= htmlspecialchars($datosPaciente['direccion']) ?>"
                       placeholder="Ej. Avellaneda 100, Lanús">
            </div>

            <div class="button-group">
                <button type="submit" class="btn-guardar">
                    <i class="fas fa-save"></i> Guardar Cambios
                </button>
                <a href="principalPac.php" class="btn-cancelar" onclick="return confirmarSalir()">
                    <i class="fas fa-times"></i> Cancelar
                </a>
            </div>
        </form>
        <?php else: ?>
            <div class="mensaje-alert mensaje-error">
                <i class="fas fa-exclamation-triangle"></i>
                No se encontraron datos para editar.
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="principalPac.php" class="btn-volver">
                    <i class="fas fa-arrow-left"></i> Volver al Inicio
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Validación en tiempo real
    const form = document.getElementById('form-datos');
    const inputs = form?.querySelectorAll('input, select');
    
    // Marcar campos modificados
    let datosOriginales = {};
    inputs?.forEach(input => {
        datosOriginales[input.name] = input.value;
        
        input.addEventListener('input', function() {
            if (this.value !== datosOriginales[this.name]) {
                this.style.borderColor = '#ffc107';
            } else {
                this.style.borderColor = '#e0e0e0';
            }
        });
    });

    // Confirmar antes de salir si hay cambios
    function confirmarSalir() {
        let hayCambios = false;
        inputs?.forEach(input => {
            if (input.value !== datosOriginales[input.name]) {
                hayCambios = true;
            }
        });
        
        if (hayCambios) {
            return confirm('¿Está seguro que desea salir? Los cambios no guardados se perderán.');
        }
        return true;
    }

    // Validación del documento
    const nroDoc = document.getElementById('nro_documento');
    nroDoc?.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Validación del teléfono
    const telefono = document.getElementById('telefono');
    telefono?.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
    });

    // Validación del formulario
    form?.addEventListener('submit', function(e) {
        const email = document.getElementById('email').value;
        const nombre = document.getElementById('nombre').value;
        const apellido = document.getElementById('apellido').value;

        if (!nombre.trim() || !apellido.trim() || !email.trim()) {
            e.preventDefault();
           mostrarAlerta('error', 'Por favor complete todos los campos obligatorios (*)');
            return false;
        }

        // Confirmar guardado
        if (!confirm('¿Está seguro que desea guardar los cambios?')) {
            e.preventDefault();
            return false;
        }
    });

    // Ocultar mensaje después de 5 segundos
    const mensaje = document.querySelector('.mensaje-alert');
    if (mensaje) {
        setTimeout(() => {
            mensaje.style.animation = 'fadeOut 0.5s ease';
            setTimeout(() => mensaje.remove(), 500);
        }, 5000);
    }

    // Animación de fade out
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
    `;
    document.head.appendChild(style);
</script>

<?php include '../footer.php'; ?>
</body>
</html>
