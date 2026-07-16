<?php
/**
 * Autenticación del panel: sesión, login, límite de fuerza bruta.
 * Incluir al principio de cada página del panel.
 */

require_once __DIR__ . '/../backend/config_loader.php';
require_once __DIR__ . '/../backend/helpers.php';
require_once __DIR__ . '/../backend/db.php';

$PANEL_CFG = load_config()['panel'];

// Cookie de sesión endurecida.
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_name('trk_panel');
session_start();

/** ¿La sesión actual está autenticada? */
function is_logged_in()
{
    return !empty($_SESSION['panel_auth']) && $_SESSION['panel_auth'] === true;
}

/** Redirige al login si no hay sesión válida. */
function require_login()
{
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** ¿La IP está bloqueada por demasiados intentos fallidos recientes? */
function is_locked_out($cfg)
{
    $pdo = db();
    $ipHint = anonymize_ip(client_ip());
    $since = gmdate('Y-m-d H:i:s', time() - ((int) $cfg['lockout_minutes'] * 60));

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS n FROM login_attempts
         WHERE ip_hint = ? AND success = 0 AND attempted_at > ?'
    );
    $stmt->execute([$ipHint, $since]);
    $row = $stmt->fetch();

    return ((int) $row['n']) >= (int) $cfg['max_login_attempts'];
}

/** Registra un intento de login (exitoso o no). */
function record_attempt($success)
{
    $pdo = db();
    $ipHint = anonymize_ip(client_ip());
    $pdo->prepare('INSERT INTO login_attempts (ip_hint, attempted_at, success) VALUES (?, ?, ?)')
        ->execute([$ipHint, gmdate('Y-m-d H:i:s'), $success ? 1 : 0]);
}

/** Valida usuario + contraseña contra la config. */
function check_credentials($user, $pass, $cfg)
{
    if (!hash_equals((string) $cfg['user'], (string) $user)) {
        return false;
    }
    return password_verify((string) $pass, (string) $cfg['password_hash']);
}
