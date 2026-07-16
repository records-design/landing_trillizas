<?php
/**
 * Exporta a CSV los eventos del rango filtrado.
 * Requiere sesión. Uso: export.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 */

require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();

$from = $_GET['from'] ?? gmdate('Y-m-d', time() - 6 * 86400);
$to   = $_GET['to']   ?? gmdate('Y-m-d');
$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reDate, $from)) $from = gmdate('Y-m-d', time() - 6 * 86400);
if (!preg_match($reDate, $to))   $to   = gmdate('Y-m-d');

$fromDt = $from . ' 00:00:00';
$toDt   = $to   . ' 23:59:59';

$filename = "eventos_{$from}_a_{$to}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM para que Excel abra bien los acentos.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'fecha_utc', 'tipo', 'boton', 'destino', 'session_id',
    'utm_source', 'utm_campaign', 'ad_id', 'placement',
    'dispositivo', 'pais', 'ciudad', 'enviado_meta', 'url',
]);

$stmt = $pdo->prepare(
    "SELECT created_at, event_name, button, destination, session_id,
            utm_source, utm_campaign, ad_id, placement,
            device_type, country_code, city, sent_to_meta, url
     FROM events
     WHERE created_at BETWEEN ? AND ?
     ORDER BY created_at ASC"
);
$stmt->execute([$fromDt, $toDt]);

while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    fputcsv($out, $row);
}

fclose($out);
