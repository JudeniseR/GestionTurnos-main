<?php
    class ConexionBD {
        private static $host = "localhost";
        private static $usuario = "root"; // Usuario local de cada uno
        private static $clave = "FMiN6Rx=IewO"; // Contraseña local de cada uno
        private static $bd = "gestionturnos";

        public static function conectar() {
            $conn = new mysqli(self::$host, self::$usuario, self::$clave, self::$bd);

            if ($conn->connect_error) {
                die("Error de conexión: " . $conn->connect_error);
            }
            return $conn;
        }
}

?>
