<?php
/**
 * Exporta a CSV todos los emails de la tabla `subscribers`.
 * Requiere sesión.
 */

require_once __DIR__ . '/auth.php';
require_login();

$pdo = db();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="suscriptores.csv"');

$out = fopen('php://output', 'w');
// BOM para que Excel abra bien los acentos.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['email', 'fecha_utc', 'utm_source', 'utm_campaign'], ',', '"', '');

$stmt = $pdo->query(
    'SELECT email, created_at, utm_source, utm_campaign
     FROM subscribers
     ORDER BY created_at ASC'
);

while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    fputcsv($out, $row, ',', '"', '');
}

fclose($out);
