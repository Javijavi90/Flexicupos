<?php
class Database {
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $envPath = __DIR__ . '/../.env';
            if (!file_exists($envPath)) {
                throw new RuntimeException("El archivo .env no existe.");
            }

            $env = parse_ini_file($envPath);
            if (!$env) {
                 throw new RuntimeException("Error al procesar el archivo .env.");
            }

            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                $env['DB_HOST'] ?? 'localhost',
                $env['DB_NAME'] ?? 'flexicupos'
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], $options);
            } catch (PDOException $e) {
                error_log(date('[Y-m-d H:i:s] ') . "DB Error: " . $e->getMessage() . "\n", 3, __DIR__ . '/../logs/errores.log');
                throw new RuntimeException("Error crítico al conectar con la base de datos.");
            }
        }
        return self::$instance;
    }
}
