<?php
/**
 * Carga la configuración desde config.php.
 * Busca en varias ubicaciones para permitir mover config.php fuera
 * del directorio público (más seguro). La primera que exista, gana.
 */

function load_config()
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $candidates = [
        __DIR__ . '/config.php',                 // misma carpeta (default)
        __DIR__ . '/../config.php',              // un nivel arriba
        __DIR__ . '/../../config.php',           // dos niveles arriba (fuera de public_html)
        __DIR__ . '/../private/config.php',      // carpeta privada hermana
    ];

    foreach ($candidates as $path) {
        if (is_file($path)) {
            $config = require $path;
            return $config;
        }
    }

    http_response_code(500);
    exit('Config no encontrada. Copiar config.example.php a config.php y completar.');
}
