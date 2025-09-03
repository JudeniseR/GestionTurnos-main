<?php
require_once("../../../Logica/Admin/verificarSesionAdmin.php");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panel Administrador - Clínica Salud+</title>
  <link rel="stylesheet" href="../../style.css"/> <!-- Reutilizamos tu CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <style>
    /* Ajustes específicos para ADMIN */
    .admin-hero {
      text-align: center;
      padding: 80px 20px;
      background: rgba(0,0,0,0.5);
      color: white;
    }
    .admin-cards {
      display: flex;
      justify-content: center;
      flex-wrap: wrap;
      gap: 30px;
      margin-top: 30px;
    }
    .admin-card {
      background: rgba(255,255,255,0.9);
      border-radius: 12px;
      padding: 25px;
      width: 260px;
      box-shadow: 0 6px 12px rgba(0,0,0,0.15);
      transition: transform 0.3s;
      text-align: center;
    }
    .admin-card:hover { transform: translateY(-8px); }
    .admin-card i {
      font-size: 40px;
      colo
