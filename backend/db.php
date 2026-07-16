<?php
/**
 * Conexión PDO a MySQL. Usa la config de config.php.
 * Devuelve siempre la misma instancia (singleton).
 */

require_once __DIR__ . '/config_loader.php';

function db()
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg = load_config()['db'];

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['name'],
        $cfg['charset']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        // No exponemos detalles del error al cliente.
        http_response_code(500);
        error_log('DB connection failed: ' . $e->getMessage());
        exit('DB error');
    }

    return $pdo;
}
