<head>
    <!-- Agregar Font Awesome desde CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>

<header>
    <nav>
        <ul>
            <div class="nav-links">
                 <li><a href="/index.php"><i class="fa fa-home"></i> Inicio</a></li>
                <li><a href="/interfaces/Paciente/principalPac.php"><i class="fa fa-tachometer-alt"></i> Principal</a></li>
                <li><a href="/interfaces/Paciente/Gestion/misTurnos.php"><i class="fa fa-calendar-check"></i> Mis Turnos</a></li>
                <li>
                    <a data-fancybox
                        data-caption="Sistema Gestión Turnos - Credencial virtual afiliado"
                        data-type="iframe"
                        data-src="/interfaces/Paciente/verCredencial.php"
                        data-width="800"
                        data-height="400"
                        href="javascript:;">
                        <i class="fa fa-id-card"></i> Ver credencial
                    </a>
                </li>
                <li>
                    <input type="text" placeholder="Buscar..." />
                    <button><i class="fa fa-search"></i> Buscar</button>
                </li>
                <!-- Este Cerrar Sesión ya no es necesario aquí porque está en el menú -->
                <!-- <li><a href="../../Logica/General/cerrarSesion.php">Cerrar Sesión</a></li> -->
            </div>

            <!-- PERFIL CON MENÚ DESPLEGABLE E INDICADOR -->
<div class="perfil">
    <span>
        <?php
        echo mb_strtoupper($_SESSION['apellido'], 'UTF-8') . ", " . mb_convert_case($_SESSION['nombre'], MB_CASE_TITLE, 'UTF-8');
        ?>
    </span>

    <!-- Contenedor interactivo con imagen + ícono -->
    <div class="perfil-interactivo" id="perfil-img" title="Abrir menú">
        <img src="/assets/img/loginAdmin.png" alt="Foto perfil">
        <!-- <img src="../../assets/img/loginAdmin.png" alt="Foto perfil"> -->
        <i class="fa-solid fa-caret-down"></i> <!-- Ícono de flecha -->
    </div>

    <!-- Menú desplegable -->
    <div id="perfil-menu" class="perfil-menu">
        <ul>
            <li><a href="/interfaces/Paciente/datosPersonales.php"><i class="fa fa-user"></i> Datos Personales</a></li>
            <li><a href="/interfaces/cambiarContrasenia.php"><i class="fa fa-key"></i> Cambiar contraseña</a></li>
            <li><a href="/Logica/General/cerrarSesion.php"><i class="fa fa-sign-out-alt"></i> Cerrar Sesión</a></li>
        </ul>
    </div>
</div>

        </ul>
    </nav>

    <!-- Cargar el JS del menú de perfil -->
    <script src="/interfaces/Paciente/js/perfilMenu.js"></script> 
    <!--<script src="js/perfilMenu.js"></script> -->
</header>
