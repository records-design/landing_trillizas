<?php
/**
 * Guarda los cambios del panel de contenido (content.php). Requiere
 * sesión iniciada. Recibe JSON, no formularios clásicos.
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'no auth']));
}

// Única acción de lectura (GET): la lista completa de episodios CON
// id, para que el panel pueda editar/borrar una fila puntual. El
// endpoint público (backend/content.php) no manda el id porque la
// landing no lo necesita.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'episodes_list') {
    $pdo = db();
    $episodes = $pdo->query(
        'SELECT id, season, episode_number, title, thumbnail, youtube_url, sort_order, is_visible
         FROM episodes ORDER BY sort_order ASC, episode_number ASC'
    )->fetchAll();
    exit(json_encode(['ok' => true, 'episodes' => $episodes]));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'method not allowed']));
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['action'])) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'bad request']));
}

$pdo = db();
$now = gmdate('Y-m-d H:i:s');

// Todas las claves de texto/link que puede tocar el formulario de
// "Textos y links" — cualquier otra clave mandada se ignora (evita
// que alguien inyecte una fila arbitraria en site_content).
$ALLOWED_CONTENT_KEYS = [
    'hero_claim', 'hero_title_line1', 'hero_title_line2',
    'cta_serie_link', 'cta_album_link', 'youtube_channel_link',
    'spotify_link', 'instagram_link', 'babidibu_records_link',
    'album_name_line1', 'album_name_line2', 'album_volume',
];

switch ($in['action']) {

    case 'save_content':
        if (!is_array($in['fields'] ?? null)) {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'fields requerido']));
        }
        $stmt = $pdo->prepare(
            'INSERT INTO site_content (content_key, content_value, updated_at)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE content_value = VALUES(content_value), updated_at = VALUES(updated_at)'
        );
        foreach ($in['fields'] as $key => $value) {
            if (!in_array($key, $ALLOWED_CONTENT_KEYS, true)) {
                continue;
            }
            $stmt->execute([$key, (string) $value, $now]);
        }
        echo json_encode(['ok' => true]);
        break;

    case 'episode_save':
        $title = trim((string) ($in['title'] ?? ''));
        $youtubeUrl = trim((string) ($in['youtube_url'] ?? ''));
        if ($title === '' || $youtubeUrl === '') {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'Título y link de YouTube son obligatorios']));
        }

        $season = max(1, (int) ($in['season'] ?? 1));
        $episodeNumber = max(1, (int) ($in['episode_number'] ?? 1));
        $thumbnail = trim((string) ($in['thumbnail'] ?? ''));
        $sortOrder = (int) ($in['sort_order'] ?? $episodeNumber);
        $isVisible = !empty($in['is_visible']) ? 1 : 0;

        if (!empty($in['id'])) {
            $stmt = $pdo->prepare(
                'UPDATE episodes SET season=?, episode_number=?, title=?, thumbnail=?, youtube_url=?, sort_order=?, is_visible=?, updated_at=?
                 WHERE id=?'
            );
            $stmt->execute([$season, $episodeNumber, $title, $thumbnail, $youtubeUrl, $sortOrder, $isVisible, $now, (int) $in['id']]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO episodes (season, episode_number, title, thumbnail, youtube_url, sort_order, is_visible, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$season, $episodeNumber, $title, $thumbnail, $youtubeUrl, $sortOrder, $isVisible, $now, $now]);
        }
        echo json_encode(['ok' => true, 'id' => $in['id'] ?? $pdo->lastInsertId()]);
        break;

    case 'episode_delete':
        if (empty($in['id'])) {
            http_response_code(400);
            exit(json_encode(['ok' => false, 'error' => 'id requerido']));
        }
        $pdo->prepare('DELETE FROM episodes WHERE id = ?')->execute([(int) $in['id']]);
        echo json_encode(['ok' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'acción desconocida']);
}
