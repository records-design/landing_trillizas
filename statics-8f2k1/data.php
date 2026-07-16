<?php
/**
 * API JSON del panel: devuelve las métricas del rango de fechas pedido.
 * Requiere sesión iniciada. Uso: data.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 */

require_once __DIR__ . '/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    exit('{"error":"no auth"}');
}

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

// ------------------------------------------------------------
// Rango de fechas (default: últimos 7 días)
// ------------------------------------------------------------
$from = $_GET['from'] ?? gmdate('Y-m-d', time() - 6 * 86400);
$to   = $_GET['to']   ?? gmdate('Y-m-d');

// Validación básica de formato.
$reDate = '/^\d{4}-\d{2}-\d{2}$/';
if (!preg_match($reDate, $from)) $from = gmdate('Y-m-d', time() - 6 * 86400);
if (!preg_match($reDate, $to))   $to   = gmdate('Y-m-d');

$fromDt = $from . ' 00:00:00';
$toDt   = $to   . ' 23:59:59';

/** Helper: ejecuta una query con el rango y devuelve todas las filas. */
function q($pdo, $sql, $params)
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$range = [$fromDt, $toDt];

// ------------------------------------------------------------
// Totales
// ------------------------------------------------------------
$pageViews = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='page_view' AND created_at BETWEEN ? AND ?",
    $range)[0]['n'];

$uniqueVisitors = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE created_at BETWEEN ? AND ?",
    $range)[0]['n'];

$totalClicks = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='click' AND created_at BETWEEN ? AND ?",
    $range)[0]['n'];

// Sesiones con al menos un clic (para el embudo).
$sessionsWithClick = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE event_name='click' AND created_at BETWEEN ? AND ?",
    $range)[0]['n'];

// ------------------------------------------------------------
// Activos ahora (últimos 5 minutos)
// ------------------------------------------------------------
$activeNow = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE created_at > ?",
    [gmdate('Y-m-d H:i:s', time() - 300)])[0]['n'];

// ------------------------------------------------------------
// Timeline de visitas por día
// ------------------------------------------------------------
$timeline = q($pdo,
    "SELECT DATE(created_at) d, COUNT(*) views
     FROM events WHERE event_name='page_view' AND created_at BETWEEN ? AND ?
     GROUP BY DATE(created_at) ORDER BY d ASC",
    $range);

// ------------------------------------------------------------
// Clics por botón
// ------------------------------------------------------------
$clicksByButton = q($pdo,
    "SELECT button, COUNT(*) n
     FROM events WHERE event_name='click' AND button IS NOT NULL AND created_at BETWEEN ? AND ?
     GROUP BY button ORDER BY n DESC",
    $range);

// ------------------------------------------------------------
// Fuentes de tráfico (utm_source; 'directo' si es NULL)
// ------------------------------------------------------------
$sources = q($pdo,
    "SELECT COALESCE(NULLIF(utm_source,''),'directo') src, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY src ORDER BY n DESC",
    $range);

// ------------------------------------------------------------
// Dispositivos
// ------------------------------------------------------------
$devices = q($pdo,
    "SELECT COALESCE(device_type,'?') device, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY device ORDER BY n DESC",
    $range);

// ------------------------------------------------------------
// Placement
// ------------------------------------------------------------
$placements = q($pdo,
    "SELECT COALESCE(NULLIF(placement,''),'(sin dato)') placement, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY placement ORDER BY n DESC",
    $range);

// ------------------------------------------------------------
// Países y ciudades
// ------------------------------------------------------------
$countries = q($pdo,
    "SELECT COALESCE(NULLIF(country,''),'(sin dato)') country, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY country ORDER BY n DESC LIMIT 15",
    $range);

$cities = q($pdo,
    "SELECT COALESCE(NULLIF(city,''),'(sin dato)') city,
            COALESCE(NULLIF(country_code,''),'') cc, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY city, cc ORDER BY n DESC LIMIT 15",
    $range);

// ------------------------------------------------------------
// Por anuncio (ad_id) con nombre legible + clics de esas sesiones
// ------------------------------------------------------------
$ads = q($pdo,
    "SELECT s.ad_id,
            COALESCE(r.ad_name, s.utm_content, s.ad_id) ad_name,
            COALESCE(r.campaign_name, s.utm_campaign) campaign_name,
            COUNT(DISTINCT s.session_id) visitas,
            (SELECT COUNT(*) FROM events e
               WHERE e.event_name='click' AND e.ad_id = s.ad_id
               AND e.created_at BETWEEN ? AND ?) clics
     FROM sessions s
     LEFT JOIN ad_reference r ON r.ad_id = s.ad_id
     WHERE s.ad_id IS NOT NULL AND s.first_seen BETWEEN ? AND ?
     GROUP BY s.ad_id, ad_name, campaign_name
     ORDER BY visitas DESC LIMIT 50",
    [$fromDt, $toDt, $fromDt, $toDt]);

// ------------------------------------------------------------
// Respuesta
// ------------------------------------------------------------
echo json_encode([
    'range' => ['from' => $from, 'to' => $to],
    'totals' => [
        'page_views'      => $pageViews,
        'unique_visitors' => $uniqueVisitors,
        'clicks'          => $totalClicks,
        'active_now'      => $activeNow,
    ],
    'funnel' => [
        'visits'              => $uniqueVisitors,
        'sessions_with_click' => $sessionsWithClick,
    ],
    'timeline'         => $timeline,
    'clicks_by_button' => $clicksByButton,
    'sources'          => $sources,
    'devices'          => $devices,
    'placements'       => $placements,
    'countries'        => $countries,
    'cities'           => $cities,
    'ads'              => $ads,
], JSON_UNESCAPED_UNICODE);
