<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('../../Logica/General/verificarSesion.php');
require_once('../../Persistencia/conexionBD.php');

$id_usuario = $_SESSION['id_usuario'] ?? null;
$conn = ConexionBD::conectar();

// Obtener documento del paciente actual
$stmt = $conn->prepare("
    SELECT nro_documento, tipo_documento 
    FROM pacientes 
    WHERE id_usuario = ?
");
$stmt->bind_param("i", $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$paciente_actual = $result->fetch_assoc();

if (!$paciente_actual) {
    die("❌ No se encontró información del paciente");
}

$nro_doc = $paciente_actual['nro_documento'];

// Buscar si es titular
$stmt = $conn->prepare("
    SELECT id, numero_afiliado, tipo_beneficiario 
    FROM afiliados 
    WHERE numero_documento = ? 
      AND tipo_beneficiario = 'titular'
    LIMIT 1
");
$stmt->bind_param("s", $nro_doc);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("⚠️ Solo los titulares pueden ver el grupo familiar.");
}

$titular = $result->fetch_assoc();
$id_titular = $titular['id'];
$numero_afiliado_titular = $titular['numero_afiliado'];

// Obtener afiliados asociados al titular
$stmt = $conn->prepare("
    SELECT 
        a.id,
        a.numero_documento,
        a.numero_afiliado,
        a.nombre,
        a.apellido,
        a.tipo_beneficiario,
        a.estado,
        a.fecha_nacimiento,
        TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE()) AS edad,
        p.id_paciente
    FROM afiliados a
    LEFT JOIN pacientes p ON p.nro_documento = a.numero_documento
    WHERE a.id_titular = ?
      AND a.tipo_beneficiario != 'titular'
    ORDER BY a.fecha_nacimiento DESC
");

$stmt->bind_param("i", $id_titular);
$stmt->execute();
$result = $stmt->get_result();
$afiliados = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Grupo Familiar</title>
    <link rel="stylesheet" href="../../css/principalPac.css">
    <link rel="stylesheet" href="../../css/misTurnos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .afiliado-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin: 15px 0;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .afiliado-card h3 {
            margin-top: 0;
            color: #333;
        }
        .afiliado-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 10px;
            margin: 15px 0;
        }
        .afiliado-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn-turno {
            background: #007bff;
            color: white;
        }
        .btn-turno:hover {
            background: #0056b3;
        }
        .btn-credencial {
            background: #28a745;
            color: white;
        }
        .btn-credencial:hover {
            background: #218838;
        }
        .sin-registrar {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php include('navPac.php'); ?>

    <main class="container">
        <h1>👨‍👩‍👧‍👦 Mi Grupo Familiar</h1>
        <p>Afiliados asociados a su cobertura</p>

        <?php if (empty($afiliados)): ?>
            <div class="sin-registrar">
                <p><i class="fas fa-info-circle"></i> No hay afiliados registrados en su grupo familiar.</p>
            </div>
        <?php else: ?>
            <?php foreach ($afiliados as $afiliado): ?>
                <div class="afiliado-card">
                    <h3>
                        <i class="fas fa-user"></i>
                        <?= htmlspecialchars($afiliado['nombre'] . ' ' . $afiliado['apellido']) ?>
                        <?php if (empty($afiliado['id_paciente'])): ?>
                            <span style="color: #cc0000; font-size: 0.9em;">(Afiliado no registrado)</span>
                        <?php endif; ?>
                    </h3>

                    <div class="afiliado-info">
                        <p><strong>DNI:</strong> <?= htmlspecialchars($afiliado['numero_documento']) ?></p>
                        <p><strong>N° Afiliado:</strong> <?= htmlspecialchars($afiliado['numero_afiliado']) ?></p>
                        <p><strong>Tipo:</strong> <?= htmlspecialchars(ucfirst($afiliado['tipo_beneficiario'])) ?></p>
                        <?php if (!empty($afiliado['fecha_nacimiento'])): ?>
                            <p><strong>Edad:</strong> <?= htmlspecialchars($afiliado['edad']) ?> años</p>
                        <?php endif; ?>
                        <p><strong>Estado:</strong> 
                            <span style="color: <?= $afiliado['estado'] === 'activo' ? 'green' : 'red' ?>">
                                <?= htmlspecialchars(ucfirst($afiliado['estado'])) ?>
                            </span>
                        </p>
                    </div>

                    <?php if (!empty($afiliado['id_paciente'])): ?>
                        <div class="afiliado-actions">
                            <a href="Gestion/Turnos-Medico/turnoMedicoAfiliado.php?id_afiliado=<?= $afiliado['id_paciente'] ?>" 
                               class="btn btn-turno">
                                <i class="fas fa-calendar-plus"></i> Solicitar Turno Médico
                            </a>
                            <a href="Gestion/Turnos-Estudio/turnoEstudioAfiliado.php?id_afiliado=<?= $afiliado['id_paciente'] ?>" 
                               class="btn btn-turno">
                                <i class="fas fa-vials"></i> Solicitar Estudio
                            </a>
                            <a href="verCredencialAfiliado.php?id_afiliado=<?= $afiliado['id_paciente'] ?>" 
                               class="btn btn-credencial" target="_blank">
                                <i class="fas fa-id-card"></i> Ver Credencial
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="sin-registrar">
                            <p><i class="fas fa-exclamation-triangle"></i> Este afiliado aún no tiene cuenta en el sistema.</p>
                            <p>Para solicitar turnos debe registrarse usando el DNI: 
                               <strong><?= htmlspecialchars($afiliado['numero_documento']) ?></strong></p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>

    <?php include '../footer.php'; ?>
</body>
</html>
