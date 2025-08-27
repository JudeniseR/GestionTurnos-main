<?php
    class ConexionBD {
        private static $host = "localhost";
        private static $usuario = "root"; // Usuario local de cada uno
        private static $clave = ""; // Contraseña local de cada uno
        private static $bd = "GestionTurnos";

        public static function conectar() {
            $conn = new mysqli(self::$host, self::$usuario, self::$clave, self::$bd);
            if ($conn->connect_error) {
                die("Error de conexión: " . $conn->connect_error);
            }
            return $conn;
        }
}

?>
