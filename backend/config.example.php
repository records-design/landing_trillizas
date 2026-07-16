<?php
/**
 * PLANTILLA DE CONFIGURACIÓN — copiar a config.php y completar.
 * ------------------------------------------------------------
 * config.php NO se sube al repositorio (está en .gitignore) y, si es
 * posible, debe vivir FUERA del directorio público del hosting.
 * Ver BACKEND-README.md, sección "Dónde poner config.php".
 *
 * NADA de este archivo debe llegar nunca al navegador.
 */

return [

    // --------------------------------------------------------
    // Base de datos MySQL (datos que da Hostinger al crear la DB)
    // --------------------------------------------------------
    'db' => [
        'host'    => 'localhost',
        'name'    => 'NOMBRE_DE_LA_BASE',
        'user'    => 'USUARIO_MYSQL',
        'pass'    => 'CONTRASEÑA_MYSQL',
        'charset' => 'utf8mb4',
    ],

    // --------------------------------------------------------
    // Meta Conversions API (server-side)
    // --------------------------------------------------------
    'meta' => [
        'enabled'         => true,
        'pixel_id'        => 'PEGAR_PIXEL_O_DATASET_ID',
        'access_token'    => 'PEGAR_ACCESS_TOKEN_CAPI',
        'api_version'     => 'v21.0',
        // Código temporal para probar en Events Manager → Test Events.
        // Dejar vacío ('') en producción.
        'test_event_code' => '',
    ],

    // --------------------------------------------------------
    // Panel /statics — acceso restringido
    // --------------------------------------------------------
    'panel' => [
        'user' => 'admin',
        // Generar con: php -r "echo password_hash('TU_CLAVE', PASSWORD_DEFAULT);"
        // Pegar acá el hash resultante (NO la contraseña en texto plano).
        'password_hash' => 'PEGAR_HASH_DE_LA_CONTRASEÑA',
        // Clave para firmar la sesión (cualquier string largo y random).
        'session_secret' => 'CAMBIAR_POR_UN_STRING_LARGO_Y_RANDOM',
        // Intentos de login fallidos antes de bloquear temporalmente una IP.
        'max_login_attempts' => 5,
        'lockout_minutes'    => 15,
    ],

    // --------------------------------------------------------
    // Privacidad / operación
    // --------------------------------------------------------
    'privacy' => [
        // Trunca el último octeto de IPv4 antes de guardarla (recomendado).
        'anonymize_ip' => true,
    ],

    // IPs del equipo a excluir del tracking (no se guardan ni se envían a Meta).
    'exclude_ips' => [
        // '190.x.x.x',
    ],

    // --------------------------------------------------------
    // Geolocalización por IP
    // --------------------------------------------------------
    // Proveedor gratuito por defecto: ip-api.com (sin API key, con límite de
    // ~45 req/min). Para más volumen, cambiar a ipinfo.io / ipapi.co con token.
    'geo' => [
        'enabled'  => true,
        'provider' => 'ip-api', // 'ip-api' | 'none'
        'timeout'  => 2,        // segundos; corto para no frenar la respuesta
    ],
];
