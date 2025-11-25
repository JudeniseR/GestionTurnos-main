<?php  
function mostrarAlerta($tipo, $mensaje, $volverAtras = true, $duracion = 2800) {

    // Colores según tipo de alerta
    $colores = [
        'success' => '#4CAF50',   // Verde
        'error'   => '#F44336',   // Rojo
        'warning' => '#FF9800',   // Naranja
        'info'    => '#2196F3'    // Azul
    ];

    // Si el tipo no es válido, se asigna el color de error por defecto
    $color = $colores[$tipo] ?? '#F44336';

    // Código para mostrar el alerta con SweetAlert2
    echo "
    <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: '$tipo',        // success | error | warning | info
                title: '$mensaje',    // El mensaje que se quiere mostrar
                confirmButtonColor: '$color', // Color del botón de confirmación
                background: '#f4f7fb',  // Fondo suave y limpio
                color: '#333',        // Color del texto
                padding: '1.5rem',    // Espaciado interno
                showConfirmButton: true, // Mostrar el botón de confirmar
                allowOutsideClick: false, // Impide cerrar al hacer clic fuera
                
            }).then(() => {
                " . ($volverAtras ? "window.history.back();" : "") . " // Regresa a la página anterior si se pasa 'true'
            });
        });
    </script>
    ";
}
?>
