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
// Los 4 objetivos reales de la landing (usados por el embudo y por
// "Fuentes por conversión" / "Nuevos vs. recurrentes" más abajo).
// ------------------------------------------------------------
const YOUTUBE_SUB_BUTTONS = ['social_youtube', 'proximos_episodios_canal', 'footer_babidibu_tv'];
const SERIE_BUTTONS = ['ver_serie'];
const ALBUM_BUTTONS = ['escuchar_album', 'social_spotify'];
const GOAL_BUTTONS_SQL = "button IN ('ver_serie','escuchar_album','social_spotify','social_youtube','proximos_episodios_canal','footer_babidibu_tv') OR button REGEXP '^episodio_[0-9]+$'";

// ------------------------------------------------------------
// Filtros opcionales por anuncio específico (?ad_id=...) y/o por
// fuente de tráfico (?utm_source=..., ej. "qr_cartel" para un QR de
// vía pública). Cuando están activos, todas las métricas de abajo
// se recalculan solo con ese tráfico (las tablas "Resultados por
// anuncio" y "Fuentes de tráfico" siempre muestran el listado
// completo sin filtrar, para poder elegir y comparar).
// ------------------------------------------------------------
$adFilter = (isset($_GET['ad_id']) && $_GET['ad_id'] !== '') ? substr((string) $_GET['ad_id'], 0, 64) : null;
$sourceFilter = (isset($_GET['utm_source']) && $_GET['utm_source'] !== '') ? substr((string) $_GET['utm_source'], 0, 120) : null;

// "landing" en el selector representa utm_source vacío/NULL (así lo
// muestra el gráfico de "Fuentes de tráfico"), no el texto literal
// "landing" — hay que traducirlo a "IS NULL OR = ''" sin parámetro.
$sourceIsDirecto = $sourceFilter === 'landing';

/** Devuelve [sql, params] para el filtro de utm_source, con el prefijo de tabla dado (o ''). */
function sourceCond($sourceFilter, $sourceIsDirecto, $prefix = '')
{
    if ($sourceFilter === null) return ['', []];
    if ($sourceIsDirecto) return [" AND ({$prefix}utm_source IS NULL OR {$prefix}utm_source = '')", []];
    return [" AND {$prefix}utm_source = ?", [$sourceFilter]];
}

$adEventsCond = '';
$adEventsCondE = ''; // igual, pero con prefijo "e." para queries con JOIN (evita "column is ambiguous")
$adEventsParam = [];
if ($adFilter !== null) {
    $adEventsCond .= ' AND ad_id = ?';
    $adEventsCondE .= ' AND e.ad_id = ?';
    $adEventsParam[] = $adFilter;
}
[$srcSql, $srcParam] = sourceCond($sourceFilter, $sourceIsDirecto);
[$srcSqlE, $srcParamE] = sourceCond($sourceFilter, $sourceIsDirecto, 'e.');
$adEventsCond .= $srcSql;
$adEventsCondE .= $srcSqlE;
$adEventsParam = array_merge($adEventsParam, $srcParam);

// Mismo filtro de anuncio/fuente que $adEventsCond, pero con prefijo
// "sess." — para queries que hacen JOIN sessions+events (ahí sí hace
// falta el prefijo, "ad_id"/"utm_source" existen en las 2 tablas y
// quedarían ambiguos sin aclarar de cuál).
$srcCondSess = '';
$srcCondSessParam = [];
if ($adFilter !== null) { $srcCondSess .= ' AND sess.ad_id = ?'; $srcCondSessParam[] = $adFilter; }
if ($sourceFilter !== null) {
    if ($sourceIsDirecto) {
        $srcCondSess .= " AND (sess.utm_source IS NULL OR sess.utm_source = '')";
    } else {
        $srcCondSess .= ' AND sess.utm_source = ?';
        $srcCondSessParam[] = $sourceFilter;
    }
}

$adSessionsCond = '';
$adSessionsParam = [];
if ($adFilter !== null || $sourceFilter !== null) {
    $sub = 'SELECT DISTINCT session_id FROM events WHERE created_at BETWEEN ? AND ?';
    $subParam = [$fromDt, $toDt];
    if ($adFilter !== null) { $sub .= ' AND ad_id = ?'; $subParam[] = $adFilter; }
    $sub .= $srcSql;
    $subParam = array_merge($subParam, $srcParam);
    $adSessionsCond = " AND session_id IN ($sub)";
    $adSessionsParam = $subParam;
}

// Filtro directo sobre la tabla `subscribers` (tiene sus propias
// columnas ad_id / utm_source, no hace falta ir a events).
$subsCond = '';
$subsParam = [];
if ($adFilter !== null) { $subsCond .= ' AND ad_id = ?'; $subsParam[] = $adFilter; }
$subsCond .= $srcSql;
$subsParam = array_merge($subsParam, $srcParam);

// ------------------------------------------------------------
// Período anterior, de la misma duración, para comparar ("esta
// semana vs. la semana pasada"). Ej.: si el rango es 7 días,
// el período anterior son los 7 días inmediatamente antes.
// ------------------------------------------------------------
$days = (int) round((strtotime($to) - strtotime($from)) / 86400) + 1;
$prevTo = gmdate('Y-m-d', strtotime($from) - 86400);
$prevFrom = gmdate('Y-m-d', strtotime($prevTo) - ($days - 1) * 86400);
$prevRange = [$prevFrom . ' 00:00:00', $prevTo . ' 23:59:59'];

// ------------------------------------------------------------
// Totales
// ------------------------------------------------------------
$pageViews = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='page_view' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

$uniqueVisitors = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

$totalClicks = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='click' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

// Sesiones con al menos un clic (para el embudo).
$sessionsWithClick = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE event_name='click' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

// Sesiones que hicieron click en alguno de los 4 objetivos reales
// (ver la serie, escuchar el álbum/canción, o suscribirse al canal).
$sessionsWithGoalClick = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE event_name='click' AND (" . GOAL_BUTTONS_SQL . ") AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

// Sesiones que llegaron al final del todo: se suscribieron (al
// newsletter o al canal de YouTube — cualquiera de las dos cuenta
// como "convirtió del todo").
$ytPlaceholdersFunnel = implode(',', array_fill(0, count(YOUTUBE_SUB_BUTTONS), '?'));
$sessionsSubscribed = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM (
        SELECT session_id FROM subscribers WHERE created_at BETWEEN ? AND ?{$subsCond}
        UNION
        SELECT session_id FROM events WHERE event_name='click' AND button IN ($ytPlaceholdersFunnel) AND created_at BETWEEN ? AND ?{$adEventsCond}
     ) t",
    array_merge($range, $subsParam, YOUTUBE_SUB_BUTTONS, $range, $adEventsParam))[0]['n'];

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
     FROM events WHERE event_name='page_view' AND created_at BETWEEN ? AND ?{$adEventsCond}
     GROUP BY DATE(created_at) ORDER BY d ASC",
    array_merge($range, $adEventsParam));

// ------------------------------------------------------------
// Clics por botón
// ------------------------------------------------------------
$clicksByButton = q($pdo,
    "SELECT button, COUNT(*) n
     FROM events WHERE event_name='click' AND button IS NOT NULL AND created_at BETWEEN ? AND ?{$adEventsCond}
     GROUP BY button ORDER BY n DESC",
    array_merge($range, $adEventsParam));

// ------------------------------------------------------------
// Fuentes de tráfico (utm_source; 'landing' si es NULL)
// ------------------------------------------------------------
$sources = q($pdo,
    "SELECT COALESCE(NULLIF(utm_source,''),'landing') src, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY src ORDER BY n DESC",
    array_merge($range, $adSessionsParam));

// Igual, pero siempre sin filtrar (para poblar el selector de fuentes
// con todas las opciones posibles, aunque haya un filtro activo).
$sourcesAll = q($pdo,
    "SELECT COALESCE(NULLIF(utm_source,''),'landing') src, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?
     GROUP BY src ORDER BY n DESC",
    $range);

// ------------------------------------------------------------
// Dispositivos
// ------------------------------------------------------------
$devices = q($pdo,
    "SELECT COALESCE(device_type,'?') device, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY device ORDER BY n DESC",
    array_merge($range, $adSessionsParam));

// ------------------------------------------------------------
// Placement
// ------------------------------------------------------------
$placements = q($pdo,
    "SELECT COALESCE(NULLIF(placement,''),'(sin dato)') placement, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY placement ORDER BY n DESC",
    array_merge($range, $adSessionsParam));

// ------------------------------------------------------------
// Países y ciudades
// ------------------------------------------------------------
// Se agrupa por country_code (código ISO, siempre estable) y no por el
// nombre de texto: el servicio de geolocalización a veces devuelve
// nombres distintos para el mismo país (ej. "Argentina" y "República
// Argentina"), lo que partía un mismo país en dos filas.
$countries = q($pdo,
    "SELECT COALESCE(NULLIF(country_code,''),'') cc,
            COALESCE(NULLIF(MIN(country),''),'(sin dato)') country,
            COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY cc ORDER BY n DESC LIMIT 15",
    array_merge($range, $adSessionsParam));

// "Santa María del Buen Ayre" es el nombre fundacional/histórico de la
// Ciudad de Buenos Aires. Algunos proveedores de geolocalización lo usan
// como alias para cierto rango de IPs de esa ciudad — se unifica acá
// para no partir Buenos Aires en dos filas.
$cities = q($pdo,
    "SELECT
        CASE
          WHEN city = 'Santa María del Buen Ayre' AND country_code = 'AR' THEN 'Buenos Aires'
          ELSE COALESCE(NULLIF(city,''),'(sin dato)')
        END AS city_norm,
        COALESCE(NULLIF(country_code,''),'') cc, COUNT(*) n
     FROM sessions WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY city_norm, cc ORDER BY n DESC LIMIT 15",
    array_merge($range, $adSessionsParam));
foreach ($cities as &$__c) { $__c['city'] = $__c['city_norm']; unset($__c['city_norm']); }
unset($__c);

// ------------------------------------------------------------
// Por anuncio (ad_id) con nombre legible + clics.
// Se lee de `events`, no de `sessions`: la sesión solo guarda el
// ad_id de la primera visita de ese navegador (atribución "primer
// toque"), así que un anuncio de remarketing que le llega a alguien
// que ya había visitado antes quedaría invisible si se leyera de ahí.
// Cada evento, en cambio, siempre lleva el ad_id real de ESE clic.
// ------------------------------------------------------------
$ads = q($pdo,
    "SELECT e.ad_id,
            COALESCE(r.ad_name, e.utm_campaign, e.ad_id) ad_name,
            COALESCE(r.campaign_name, e.utm_campaign) campaign_name,
            COUNT(DISTINCT e.session_id) visitas,
            SUM(CASE WHEN e.event_name = 'click' THEN 1 ELSE 0 END) clics,
            (SELECT COUNT(*) FROM subscribers s
               WHERE s.ad_id = e.ad_id AND s.created_at BETWEEN ? AND ?) suscripciones
     FROM events e
     LEFT JOIN ad_reference r ON r.ad_id = e.ad_id
     WHERE e.ad_id IS NOT NULL AND e.created_at BETWEEN ? AND ?
     GROUP BY e.ad_id, ad_name, campaign_name
     ORDER BY visitas DESC LIMIT 50",
    [$fromDt, $toDt, $fromDt, $toDt]);

// ------------------------------------------------------------
// Pagado vs. orgánico: "pagado" es cualquier sesión que llegó con
// utm_medium=paid (como arman los links de Meta/Google Ads) o con
// un click id real de un anuncio (fbclid/gclid) — eso solo lo genera
// un anuncio de verdad, nunca puede salir de una visita orgánica por
// accidente. Todo lo demás (bio, historias, QR, directo, etc.) es
// orgánico, sin importar cuántas fuentes distintas tenga.
// ------------------------------------------------------------
$paidVsOrganic = q($pdo,
    "SELECT
        CASE
          WHEN utm_medium = 'paid' OR fbclid IS NOT NULL OR gclid IS NOT NULL THEN 'Pagado'
          ELSE 'Orgánico'
        END AS tipo,
        COUNT(*) n
     FROM sessions
     WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY tipo",
    array_merge($range, $adSessionsParam));

// Mismo cruce que "Fuentes por conversión", pero por pagado/orgánico
// en vez de por fuente puntual — para comparar los 2 baldes grandes
// de un vistazo, sin tener que sumar "an" + "fb" + "ig" a mano.
$ytPlaceholdersPvo = implode(',', array_fill(0, count(YOUTUBE_SUB_BUTTONS), '?'));
$paidVsOrganicConversion = q($pdo,
    "SELECT
        CASE
          WHEN sess.utm_medium = 'paid' OR sess.fbclid IS NOT NULL OR sess.gclid IS NOT NULL THEN 'Pagado'
          ELSE 'Orgánico'
        END AS tipo,
        COUNT(DISTINCT sess.session_id) visitas,
        COUNT(DISTINCT CASE WHEN " . str_replace('button', 'ev.button', GOAL_BUTTONS_SQL) . " THEN sess.session_id END) con_objetivo,
        COUNT(DISTINCT sub.email) suscripciones,
        COUNT(DISTINCT CASE WHEN ev.button IN ($ytPlaceholdersPvo) THEN sess.session_id END) con_youtube
     FROM sessions sess
     LEFT JOIN events ev ON ev.session_id = sess.session_id AND ev.event_name = 'click'
     LEFT JOIN subscribers sub ON sub.session_id = sess.session_id
     WHERE sess.first_seen BETWEEN ? AND ?{$srcCondSess}
     GROUP BY tipo",
    array_merge(YOUTUBE_SUB_BUTTONS, $range, $srcCondSessParam));

foreach ($paidVsOrganicConversion as &$pvc) {
    $pvc['visitas'] = (int) $pvc['visitas'];
    $pvc['con_objetivo'] = (int) $pvc['con_objetivo'];
    $pvc['suscripciones'] = (int) $pvc['suscripciones'];
    $pvc['con_youtube'] = (int) $pvc['con_youtube'];
    $pvc['pct_objetivo'] = $pvc['visitas'] ? round($pvc['con_objetivo'] / $pvc['visitas'] * 100, 1) : 0;
    $pvc['pct_sub'] = $pvc['visitas'] ? round($pvc['suscripciones'] / $pvc['visitas'] * 100, 1) : 0;
    $pvc['pct_youtube'] = $pvc['visitas'] ? round($pvc['con_youtube'] / $pvc['visitas'] * 100, 1) : 0;
}
unset($pvc);

// ------------------------------------------------------------
// Canal vs. newsletter: cuánta gente se suscribió a SOLO uno de los
// dos, y cuánta a los DOS — para saber si son públicos superpuestos
// o si conviene pedir las 2 cosas porque la gente solo hace una.
// ------------------------------------------------------------
$ytPlaceholdersOverlap = implode(',', array_fill(0, count(YOUTUBE_SUB_BUTTONS), '?'));
$subsOverlap = q($pdo,
    "SELECT
        SUM(CASE WHEN has_canal = 1 AND has_news = 0 THEN 1 ELSE 0 END) solo_canal,
        SUM(CASE WHEN has_canal = 0 AND has_news = 1 THEN 1 ELSE 0 END) solo_newsletter,
        SUM(CASE WHEN has_canal = 1 AND has_news = 1 THEN 1 ELSE 0 END) ambos
     FROM (
        SELECT sess.session_id,
            MAX(CASE WHEN ev.button IN ($ytPlaceholdersOverlap) THEN 1 ELSE 0 END) has_canal,
            MAX(CASE WHEN sub.email IS NOT NULL THEN 1 ELSE 0 END) has_news
        FROM sessions sess
        LEFT JOIN events ev ON ev.session_id = sess.session_id AND ev.event_name = 'click'
        LEFT JOIN subscribers sub ON sub.session_id = sess.session_id
        WHERE sess.first_seen BETWEEN ? AND ?{$srcCondSess}
        GROUP BY sess.session_id
     ) t",
    array_merge(YOUTUBE_SUB_BUTTONS, $range, $srcCondSessParam))[0];
$subsOverlap = [
    'solo_canal' => (int) ($subsOverlap['solo_canal'] ?? 0),
    'solo_newsletter' => (int) ($subsOverlap['solo_newsletter'] ?? 0),
    'ambos' => (int) ($subsOverlap['ambos'] ?? 0),
];

// ------------------------------------------------------------
// Fuentes por conversión: de las visitas de cada fuente, qué % logró
// cada uno de los 4 objetivos reales de la landing — ver la serie,
// escuchar el álbum, suscribirse al canal de YouTube, y suscribirse
// al newsletter — cada uno por separado, no mezclados. No solo
// cuántas visitas trajo cada fuente (eso ya lo muestra "Fuentes de
// tráfico"), sino qué tan bien convierte para lo que realmente
// importa. Con pocas visitas el % es poco confiable (un solo caso de
// suerte cambia todo el porcentaje) — se marca "confiable" a partir
// de MIN_SAMPLE_SOURCE visitas, y el panel atenúa visualmente las que
// todavía no llegan a ese mínimo.
// ------------------------------------------------------------
const MIN_SAMPLE_SOURCE = 30;

$ytPlaceholders = implode(',', array_fill(0, count(YOUTUBE_SUB_BUTTONS), '?'));
$seriePlaceholders = implode(',', array_fill(0, count(SERIE_BUTTONS), '?'));
$albumPlaceholders = implode(',', array_fill(0, count(ALBUM_BUTTONS), '?'));
$sourceConversion = q($pdo,
    "SELECT
        COALESCE(NULLIF(sess.utm_source,''),'landing') src,
        COUNT(DISTINCT sess.session_id) visitas,
        COUNT(DISTINCT CASE WHEN ev.button IN ($seriePlaceholders) OR ev.button REGEXP '^episodio_[0-9]+$' THEN sess.session_id END) con_serie,
        COUNT(DISTINCT CASE WHEN ev.button IN ($albumPlaceholders) THEN sess.session_id END) con_album,
        COUNT(DISTINCT CASE WHEN ev.button IN ($ytPlaceholders) THEN sess.session_id END) con_youtube,
        COUNT(DISTINCT sub.email) suscripciones
     FROM sessions sess
     LEFT JOIN events ev ON ev.session_id = sess.session_id AND ev.event_name = 'click'
     LEFT JOIN subscribers sub ON sub.session_id = sess.session_id
     WHERE sess.first_seen BETWEEN ? AND ?{$srcCondSess}
     GROUP BY src
     ORDER BY visitas DESC",
    array_merge(SERIE_BUTTONS, ALBUM_BUTTONS, YOUTUBE_SUB_BUTTONS, $range, $srcCondSessParam));

foreach ($sourceConversion as &$sc) {
    $sc['visitas'] = (int) $sc['visitas'];
    $sc['con_serie'] = (int) $sc['con_serie'];
    $sc['con_album'] = (int) $sc['con_album'];
    $sc['con_youtube'] = (int) $sc['con_youtube'];
    $sc['suscripciones'] = (int) $sc['suscripciones'];
    $sc['pct_serie'] = $sc['visitas'] ? round($sc['con_serie'] / $sc['visitas'] * 100, 1) : 0;
    $sc['pct_album'] = $sc['visitas'] ? round($sc['con_album'] / $sc['visitas'] * 100, 1) : 0;
    $sc['pct_youtube'] = $sc['visitas'] ? round($sc['con_youtube'] / $sc['visitas'] * 100, 1) : 0;
    $sc['pct_sub'] = $sc['visitas'] ? round($sc['suscripciones'] / $sc['visitas'] * 100, 1) : 0;
    $sc['confiable'] = $sc['visitas'] >= MIN_SAMPLE_SOURCE;
}
unset($sc);

// ------------------------------------------------------------
// Nuevos vs. recurrentes: un visitante es "recurrente" si su
// visitor_id (persiste entre visitas, ver tracking.js) ya tenía
// una sesión ANTERIOR a esta, sin importar cuándo. "(sin dato)"
// son sesiones de antes de este cambio, o con localStorage
// bloqueado (modo privado), donde no hay visitor_id para comparar.
// ------------------------------------------------------------
$newVsReturning = q($pdo,
    "SELECT
        CASE
          WHEN visitor_id IS NULL THEN '(sin dato)'
          WHEN EXISTS (
            SELECT 1 FROM sessions s2
             WHERE s2.visitor_id = sessions.visitor_id
               AND s2.first_seen < sessions.first_seen
          ) THEN 'recurrente'
          ELSE 'nuevo'
        END AS tipo,
        COUNT(*) n
     FROM sessions
     WHERE first_seen BETWEEN ? AND ?{$adSessionsCond}
     GROUP BY tipo",
    array_merge($range, $adSessionsParam));

// Mismo cruce que "Fuentes por conversión", pero por nuevo/recurrente
// en vez de por fuente: ¿los que ya habían entrado antes convierten
// mejor que los que llegan por primera vez?
$ytPlaceholdersNvr = implode(',', array_fill(0, count(YOUTUBE_SUB_BUTTONS), '?'));
$newVsReturningConversion = q($pdo,
    "SELECT
        CASE
          WHEN sess.visitor_id IS NULL THEN '(sin dato)'
          WHEN EXISTS (
            SELECT 1 FROM sessions s2
             WHERE s2.visitor_id = sess.visitor_id
               AND s2.first_seen < sess.first_seen
          ) THEN 'recurrente'
          ELSE 'nuevo'
        END AS tipo,
        COUNT(DISTINCT sess.session_id) visitas,
        COUNT(DISTINCT CASE WHEN " . str_replace('button', 'ev.button', GOAL_BUTTONS_SQL) . " THEN sess.session_id END) con_objetivo,
        COUNT(DISTINCT sub.email) suscripciones,
        COUNT(DISTINCT CASE WHEN ev.button IN ($ytPlaceholdersNvr) THEN sess.session_id END) con_youtube
     FROM sessions sess
     LEFT JOIN events ev ON ev.session_id = sess.session_id AND ev.event_name = 'click'
     LEFT JOIN subscribers sub ON sub.session_id = sess.session_id
     WHERE sess.first_seen BETWEEN ? AND ?{$srcCondSess}
     GROUP BY tipo",
    array_merge(YOUTUBE_SUB_BUTTONS, $range, $srcCondSessParam));

foreach ($newVsReturningConversion as &$nvc) {
    $nvc['visitas'] = (int) $nvc['visitas'];
    $nvc['con_objetivo'] = (int) $nvc['con_objetivo'];
    $nvc['suscripciones'] = (int) $nvc['suscripciones'];
    $nvc['con_youtube'] = (int) $nvc['con_youtube'];
    $nvc['pct_objetivo'] = $nvc['visitas'] ? round($nvc['con_objetivo'] / $nvc['visitas'] * 100, 1) : 0;
    $nvc['pct_sub'] = $nvc['visitas'] ? round($nvc['suscripciones'] / $nvc['visitas'] * 100, 1) : 0;
    $nvc['pct_youtube'] = $nvc['visitas'] ? round($nvc['con_youtube'] / $nvc['visitas'] * 100, 1) : 0;
}
unset($nvc);

// ------------------------------------------------------------
// Interés real en el video y la canción: tasa de clic sobre el
// total de visitantes (no solo el número absoluto) y cuánto tiempo
// pasó, en promedio, antes de que alguien clickeara cada uno —
// un clic a los 2 segundos de entrar vale distinto que uno a los 30.
// ------------------------------------------------------------
$buttonInterest = q($pdo,
    "SELECT button,
            COUNT(DISTINCT session_id) sesiones,
            AVG(dwell_ms) avg_dwell_ms
     FROM events
     WHERE event_name = 'click' AND button IN ('videoclip', 'cancion_spotify')
       AND created_at BETWEEN ? AND ?{$adEventsCond}
     GROUP BY button",
    array_merge($range, $adEventsParam));
foreach ($buttonInterest as &$b) {
    $b['pct_visitantes'] = $uniqueVisitors ? round($b['sesiones'] / $uniqueVisitors * 100, 1) : 0;
    $b['avg_dwell_ms'] = $b['avg_dwell_ms'] !== null ? round($b['avg_dwell_ms']) : null;
}
unset($b);

// ------------------------------------------------------------
// Cruce interés musical <-> suscripción: de la gente que clickeó
// el video o la canción, ¿cuántos además dejaron el mail? Y cuántos
// clickearon LOS DOS (el segmento más interesado de todos).
// ------------------------------------------------------------
$videoClicks = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events
     WHERE event_name='click' AND button='videoclip' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

$videoSubs = (int) q($pdo,
    "SELECT COUNT(DISTINCT e.session_id) n FROM events e
     JOIN subscribers s ON s.session_id = e.session_id
     WHERE e.event_name='click' AND e.button='videoclip' AND e.created_at BETWEEN ? AND ?{$adEventsCondE}",
    array_merge($range, $adEventsParam))[0]['n'];

$songClicks = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events
     WHERE event_name='click' AND button='cancion_spotify' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0]['n'];

$songSubs = (int) q($pdo,
    "SELECT COUNT(DISTINCT e.session_id) n FROM events e
     JOIN subscribers s ON s.session_id = e.session_id
     WHERE e.event_name='click' AND e.button='cancion_spotify' AND e.created_at BETWEEN ? AND ?{$adEventsCondE}",
    array_merge($range, $adEventsParam))[0]['n'];

$bothClicks = (int) q($pdo,
    "SELECT COUNT(DISTINCT e1.session_id) n FROM events e1
     WHERE e1.event_name='click' AND e1.button='videoclip' AND e1.created_at BETWEEN ? AND ?{$adEventsCond}
       AND EXISTS (
         SELECT 1 FROM events e2
          WHERE e2.session_id = e1.session_id AND e2.event_name='click'
            AND e2.button='cancion_spotify' AND e2.created_at BETWEEN ? AND ?{$adEventsCond}
       )",
    array_merge([$fromDt, $toDt], $adEventsParam, [$fromDt, $toDt], $adEventsParam))[0]['n'];

$musicEngagement = [
    'video' => ['clics' => $videoClicks, 'suscripciones' => $videoSubs],
    'cancion' => ['clics' => $songClicks, 'suscripciones' => $songSubs],
    'ambos_clics' => $bothClicks,
];

// ------------------------------------------------------------
// Tiempo en la página, en general (no solo antes de un clic): se
// manda un evento "engagement" cuando la persona se va, con el
// tiempo total que pasó en la página.
// ------------------------------------------------------------
$pageEngagement = q($pdo,
    "SELECT AVG(dwell_ms) avg_time_ms, COUNT(*) sesiones
     FROM events
     WHERE event_name = 'engagement' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($range, $adEventsParam))[0];
$pageEngagement = [
    'avg_time_ms' => $pageEngagement['avg_time_ms'] !== null ? round($pageEngagement['avg_time_ms']) : null,
    'sesiones'    => (int) $pageEngagement['sesiones'],
];

// ------------------------------------------------------------
// Totales del período anterior, para la comparación en el panel.
// Se recalculan las mismas 3 métricas clave, respetando el mismo
// filtro de anuncio si está activo.
// ------------------------------------------------------------
$prevPageViews = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='page_view' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($prevRange, $adEventsParam))[0]['n'];

$prevUniqueVisitors = (int) q($pdo,
    "SELECT COUNT(DISTINCT session_id) n FROM events WHERE created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($prevRange, $adEventsParam))[0]['n'];

$prevClicks = (int) q($pdo,
    "SELECT COUNT(*) n FROM events WHERE event_name='click' AND created_at BETWEEN ? AND ?{$adEventsCond}",
    array_merge($prevRange, $adEventsParam))[0]['n'];

$prevSubs = (int) q($pdo,
    "SELECT COUNT(*) n FROM subscribers WHERE created_at BETWEEN ? AND ?{$subsCond}",
    array_merge($prevRange, $subsParam))[0]['n'];

$subs = (int) q($pdo,
    "SELECT COUNT(*) n FROM subscribers WHERE created_at BETWEEN ? AND ?{$subsCond}",
    array_merge($range, $subsParam))[0]['n'];

// ------------------------------------------------------------
// Listas para los selectores de filtro (siempre completas, sin
// aplicar ningún filtro, para que se puedan ver/elegir todas las
// opciones aunque ya haya un filtro activo).
// ------------------------------------------------------------
$adsList = array_map(function ($r) {
    return ['ad_id' => $r['ad_id'], 'ad_name' => $r['ad_name'] ?? $r['ad_id']];
}, $ads);
$sourcesList = array_map(function ($r) {
    return ['src' => $r['src']];
}, $sourcesAll);

// ------------------------------------------------------------
// Respuesta
// ------------------------------------------------------------
echo json_encode([
    'range' => ['from' => $from, 'to' => $to],
    'ad_filter' => $adFilter,
    'source_filter' => $sourceFilter,
    'ads_list' => $adsList,
    'sources_list' => $sourcesList,
    'totals' => [
        'page_views'      => $pageViews,
        'unique_visitors' => $uniqueVisitors,
        'clicks'          => $totalClicks,
        'active_now'      => $activeNow,
        'subscriptions'   => $subs,
    ],
    'comparison' => [
        'prev_range' => ['from' => $prevFrom, 'to' => $prevTo],
        'prev_totals' => [
            'page_views'      => $prevPageViews,
            'unique_visitors' => $prevUniqueVisitors,
            'clicks'          => $prevClicks,
            'subscriptions'   => $prevSubs,
        ],
    ],
    'funnel' => [
        'visits'                  => $uniqueVisitors,
        'sessions_with_click'     => $sessionsWithClick,
        'sessions_with_goal_click' => $sessionsWithGoalClick,
        'sessions_subscribed'     => $sessionsSubscribed,
    ],
    'timeline'         => $timeline,
    'clicks_by_button' => $clicksByButton,
    'sources'          => $sources,
    'source_conversion' => $sourceConversion,
    'paid_vs_organic'  => $paidVsOrganic,
    'paid_vs_organic_conversion' => $paidVsOrganicConversion,
    'subs_overlap' => $subsOverlap,
    'devices'          => $devices,
    'placements'       => $placements,
    'countries'        => $countries,
    'cities'           => $cities,
    'ads'              => $ads,
    'new_vs_returning' => $newVsReturning,
    'new_vs_returning_conversion' => $newVsReturningConversion,
    'button_interest'  => $buttonInterest,
    'music_engagement' => $musicEngagement,
    'page_engagement'  => $pageEngagement,
], JSON_UNESCAPED_UNICODE);
