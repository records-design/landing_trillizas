<?php
/**
 * Endpoint del formulario de suscripción del hero.
 * Recibe { email } por POST JSON, valida y lo guarda en la tabla
 * `subscribers`. Si el email ya existía, no hace nada (no es error).
 */

require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('{"ok":false}');
}

$cfg = load_config();

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);

$email = is_array($in) ? trim((string) ($in['email'] ?? '')) : '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('{"ok":false,"error":"invalid_email"}');
}

$ipRaw = client_ip();
if (in_array($ipRaw, $cfg['exclude_ips'] ?? [], true)) {
    http_response_code(204);
    exit;
}
$ipHint = !empty($cfg['privacy']['anonymize_ip']) ? anonymize_ip($ipRaw) : $ipRaw;

$campaign = is_array($in['campaign'] ?? null) ? $in['campaign'] : [];
$utmSource = isset($campaign['utm_source']) ? (string) $campaign['utm_source'] : null;
$utmCampaign = isset($campaign['utm_campaign']) ? (string) $campaign['utm_campaign'] : null;

$pdo = db();
$stmt = $pdo->prepare(
    'INSERT IGNORE INTO subscribers (email, created_at, utm_source, utm_campaign, ip_hint)
     VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([
    clip($email, 255),
    gmdate('Y-m-d H:i:s'),
    clip($utmSource, 120),
    clip($utmCampaign, 255),
    $ipHint,
]);

http_response_code(200);
echo '{"ok":true}';
