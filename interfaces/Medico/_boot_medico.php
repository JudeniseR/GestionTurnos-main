<?php
// Carga id_medico en la sesión a partir de id_usuario. Soporta tablas "medicos" o "medico".
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Ya lo tenemos
if (!empty($_SESSION['id_medico'])) { return; }

// Debe haber login
$id_usuario = $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) { return; }

require_once __DIR__ . '/../../Persistencia/conexionBD.php'; // desde /interfaces/Medico/api/
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

// Averiguar qué tabla existe
$table = null;
foreach (['medicos','medico'] as $t) {
  $check = $conn->query("SHOW TABLES LIKE '".$conn->real_escape_string($t)."'");
  if ($check && $check->num_rows > 0) { $table = $t; break; }
}
if (!$table) { return; } // no hay tabla de médicos -> no podemos cargar

// Buscar id_medico
$sql = "SELECT id_medico FROM {$table} WHERE id_usuario = ? LIMIT 1";
if ($st = $conn->prepare($sql)) {
  $st->bind_param('i', $id_usuario);
  $st->execute();
  $st->bind_result($id_medico);
  if ($st->fetch() && $id_medico) {
    $_SESSION['id_medico'] = (int)$id_medico;
  }
  $st->close();
}
