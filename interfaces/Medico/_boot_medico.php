<?php
// Cargar id_medico en sesión si falta, usando id_usuario.
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Si ya está, listo
if (!empty($_SESSION['id_medico'])) { return; }

// Verificamos que haya login
$id_usuario = $_SESSION['id_usuario'] ?? null;
if (!$id_usuario) { return; }

// Traemos id_medico desde la BD
require_once('../../Persistencia/conexionBD.php');
$conn = ConexionBD::conectar();
$conn->set_charset('utf8mb4');

$sql = "SELECT id_medico FROM medicos WHERE id_usuario = ? LIMIT 1";
if ($st = $conn->prepare($sql)) {
  $st->bind_param('i', $id_usuario);
  $st->execute();
  $st->bind_result($id_medico);
  if ($st->fetch() && $id_medico) {
    $_SESSION['id_medico'] = (int)$id_medico;
  }
  $st->close();
}
