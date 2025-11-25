<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL); ini_set('display_errors','0');

session_start();
if(!isset($_SESSION['id_medico'])){ http_response_code(401); echo json_encode([]); exit; }
require_once('../../../Persistencia/conexionBD.php');

// Reutilizamos el cálculo del archivo “medico”.
require_once(__DIR__ . '/agenda_slots_medico.php');
