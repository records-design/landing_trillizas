<?php
/**
 * Exporta a CSV los emails de la tabla `subscribers` del rango filtrado.
 * Requiere sesión. Uso: export-subscribers.php?from=YYYY-MM-DD&to=YYYY-MM-DD
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

$adFilter = (isset($_GET['ad_id']) && $_GET['ad_id'] !== '') ? substr((string) $_GET['ad_id'], 0, 64) : null;
$sourceFilter = (isset($_GET['utm_source']) && $_GET['utm_source'] !== '') ? substr((string) $_GET['utm_source'], 0, 120) : null;

$adCond = '';
$adParam = [];
if ($adFilter !== null) { $adCond .= ' AND ad_id = ?'; $adParam[] = $adFilter; }
if ($sourceFilter !== null) {
    if ($sourceFilter === 'directo') {
        $adCond .= " AND (utm_source IS NULL OR utm_source = '')";
    } else {
        $adCond .= ' AND utm_source = ?';
        $adParam[] = $sourceFilter;
    }
}

$filename = "suscriptores_{$from}_a_{$to}.csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM para que Excel abra bien los acentos.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['email', 'fecha_utc', 'utm_source', 'utm_campaign'], ',', '"', '');

$stmt = $pdo->prepare(
    "SELECT email, created_at, utm_source, utm_campaign
     FROM subscribers
     WHERE created_at BETWEEN ? AND ?{$adCond}
     ORDER BY created_at ASC"
);
$stmt->execute(array_merge([$fromDt, $toDt], $adParam));

while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    fputcsv($out, $row, ',', '"', '');
}

fclose($out);
