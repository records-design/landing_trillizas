<?php
/**
 * Endpoint de captura de eventos.
 * Recibe JSON desde tracking.js (page_view / click), lo enriquece
 * (IP, geo, user agent, campaña), lo guarda en MySQL y lo reenvía
 * a Meta CAPI. Responde rápido y no bloquea la carga de la landing.
 */

require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/geo.php';
require_once __DIR__ . '/capi.php';

header('Content-Type: application/json; charset=utf-8');

// Solo POST.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('{"ok":false}');
}

$cfg = load_config();

// ------------------------------------------------------------
// Leer y validar el cuerpo JSON
// ------------------------------------------------------------
$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

if (!is_array($in) || empty($in['event_name']) || empty($in['session_id']) || empty($in['event_id'])) {
    http_response_code(400);
    exit('{"ok":false}');
}

$eventName = $in['event_name'];
if (!in_array($eventName, ['page_view', 'click'], true)) {
    http_response_code(400);
    exit('{"ok":false}');
}

// ------------------------------------------------------------
// IP: cruda (para geo + CAPI) y anonimizada (para guardar)
// ------------------------------------------------------------
$ipRaw = client_ip();

// Excluir visitas del propio equipo.
if (in_array($ipRaw, $cfg['exclude_ips'] ?? [], true)) {
    http_response_code(204);
    exit;
}

$ipHint = !empty($cfg['privacy']['anonymize_ip']) ? anonymize_ip($ipRaw) : $ipRaw;

// ------------------------------------------------------------
// Datos derivados
// ------------------------------------------------------------
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$uaParsed = parse_user_agent($ua);

$campaign = is_array($in['campaign'] ?? null) ? $in['campaign'] : [];
$get = function ($key) use ($campaign) {
    return isset($campaign[$key]) && $campaign[$key] !== '' ? (string) $campaign[$key] : null;
};

$now = gmdate('Y-m-d H:i:s'); // UTC
$pdo = db();

// ------------------------------------------------------------
// Sesión: crear si no existe (geolocalizando una vez), o refrescar.
// ------------------------------------------------------------
$stmt = $pdo->prepare('SELECT country, country_code, region, city, device_type, os, browser FROM sessions WHERE session_id = ? LIMIT 1');
$stmt->execute([$in['session_id']]);
$session = $stmt->fetch();

if (!$session) {
    // Geolocalizar solo al crear la sesión (ahorra llamadas a la API de geo).
    $geo = geolocate($ipRaw, $cfg['geo']);

    // ON DUPLICATE evita un error si dos page_view de la misma sesión nueva
    // llegan casi al mismo tiempo (solo refresca last_seen en ese caso).
    $ins = $pdo->prepare(
        'INSERT INTO sessions
          (session_id, first_seen, last_seen,
           utm_source, utm_medium, utm_campaign, utm_content,
           campaign_id, adset_id, ad_id, placement, fbclid, gclid,
           device_type, os, browser,
           country, country_code, region, city,
           referrer, ip_hint, user_agent)
         VALUES
          (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE last_seen = VALUES(last_seen)'
    );
    $ins->execute([
        $in['session_id'], $now, $now,
        clip($get('utm_source'), 120), clip($get('utm_medium'), 120),
        clip($get('utm_campaign'), 255), clip($get('utm_content'), 255),
        clip($get('campaign_id'), 64), clip($get('adset_id'), 64),
        clip($get('ad_id'), 64), clip($get('placement'), 80),
        clip($get('fbclid'), 512), clip($get('gclid'), 512),
        $uaParsed['device'], $uaParsed['os'], $uaParsed['browser'],
        clip($geo['country'], 80), clip($geo['country_code'], 4),
        clip($geo['region'], 120), clip($geo['city'], 120),
        clip($in['referrer'] ?? null, 512), $ipHint, clip($ua, 512),
    ]);

    $country_code = $geo['country_code'];
    $city = $geo['city'];
    $device = $uaParsed['device'];
} else {
    $pdo->prepare('UPDATE sessions SET last_seen = ? WHERE session_id = ?')
        ->execute([$now, $in['session_id']]);

    $country_code = $session['country_code'];
    $city = $session['city'];
    $device = $session['device_type'] ?: $uaParsed['device'];
}

// ------------------------------------------------------------
// Guardar el evento (INSERT IGNORE evita duplicar por event_id)
// ------------------------------------------------------------
$evt = $pdo->prepare(
    'INSERT IGNORE INTO events
       (event_id, session_id, event_name, button, destination, created_at,
        url, referrer, utm_source, utm_campaign, ad_id, placement,
        device_type, country_code, city, sent_to_meta)
     VALUES
       (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
);
$evt->execute([
    $in['event_id'], $in['session_id'], $eventName,
    clip($in['button'] ?? null, 60), clip($in['destination'] ?? null, 60), $now,
    clip($in['url'] ?? null, 1000), clip($in['referrer'] ?? null, 512),
    clip($get('utm_source'), 120), clip($get('utm_campaign'), 255),
    clip($get('ad_id'), 64), clip($get('placement'), 80),
    $device, clip($country_code, 4), clip($city, 120),
]);
$eventRowId = $pdo->lastInsertId();

// ------------------------------------------------------------
// Tabla de referencia ad_id -> ad.name (para etiquetas legibles)
// ------------------------------------------------------------
if ($get('ad_id')) {
    $ref = $pdo->prepare(
        'INSERT INTO ad_reference (ad_id, ad_name, campaign_id, campaign_name, adset_id, updated_at)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            ad_name = VALUES(ad_name),
            campaign_id = VALUES(campaign_id),
            campaign_name = VALUES(campaign_name),
            adset_id = VALUES(adset_id),
            updated_at = VALUES(updated_at)'
    );
    $ref->execute([
        clip($get('ad_id'), 64), clip($get('utm_content'), 255),
        clip($get('campaign_id'), 64), clip($get('utm_campaign'), 255),
        clip($get('adset_id'), 64), $now,
    ]);
}

// ------------------------------------------------------------
// Responder YA al navegador; el envío a Meta sigue por detrás.
// ------------------------------------------------------------
http_response_code(204);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

// ------------------------------------------------------------
// Meta CAPI (después de responder, para no frenar la landing)
// ------------------------------------------------------------
$ok = send_to_meta([
    'event_name'   => $eventName,
    'event_id'     => $in['event_id'],
    'url'          => $in['url'] ?? null,
    'ip_raw'       => $ipRaw,
    'user_agent'   => $ua,
    'fbclid'       => $get('fbclid'),
    'button'       => $in['button'] ?? null,
    'destination'  => $in['destination'] ?? null,
    'placement'    => $get('placement'),
    'utm_campaign' => $get('utm_campaign'),
], $cfg['meta']);

if ($ok && $eventRowId) {
    try {
        $pdo->prepare('UPDATE events SET sent_to_meta = 1 WHERE id = ?')->execute([$eventRowId]);
    } catch (Exception $e) {
        error_log('No se pudo marcar sent_to_meta: ' . $e->getMessage());
    }
}
