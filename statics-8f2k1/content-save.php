<?php
/**
 * Guarda los cambios del panel de contenido (content.php). Requiere
 * sesión iniciada. Recibe JSON, no formularios clásicos.
 *
 * Todo el guardado está envuelto en try/catch: si algo falla (un
 * texto demasiado largo, un problema de conexión a la base, lo que
 * sea), el panel tiene que mostrar un mensaje claro en español, nunca
 * una pantalla en blanco ni un error críptico de PHP.
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Tu sesión venció. Recargá la página y volvé a entrar.']));
}

// Única acción de lectura (GET): la lista completa de episodios CON
// id, para que el panel pueda editar/borrar una fila puntual. El
// endpoint público (backend/content.php) no manda el id porque la
// landing no lo necesita.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && ($_GET['action'] ?? '') === 'episodes_list') {
    try {
        $pdo = db();
        $episodes = $pdo->query(
            'SELECT id, season, episode_number, title, thumbnail, youtube_url, sort_order, is_visible
             FROM episodes ORDER BY sort_order ASC, episode_number ASC'
        )->fetchAll();
        exit(json_encode(['ok' => true, 'episodes' => $episodes]));
    } catch (Throwable $e) {
        error_log('content-save episodes_list: ' . $e->getMessage());
        http_response_code(500);
        exit(json_encode(['ok' => false, 'error' => 'No se pudo cargar la lista de episodios. Probá recargar la página.']));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Método no permitido']));
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in) || empty($in['action'])) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'Pedido inválido']));
}

// Todas las claves de texto/link que puede tocar el formulario de
// "Textos y links" — cualquier otra clave mandada se ignora (evita
// que alguien inyecte una fila arbitraria en site_content).
$ALLOWED_CONTENT_KEYS = [
    'hero_claim', 'hero_title_line1', 'hero_title_line2',
    'cta_serie_link', 'cta_album_link', 'youtube_channel_link',
    'spotify_link', 'instagram_link', 'babidibu_records_link',
    'album_name_line1', 'album_name_line2', 'album_volume',
];

// Límites de caracteres — coinciden con lo que soportan las columnas
// de la base de datos. Se valida ACÁ (con un mensaje entendible)
// para no depender de que MySQL rechace el guardado con un error
// críptico si alguien pega un texto gigante por error.
const MAX_CONTENT_VALUE = 500;
const MAX_TITLE = 255;
const MAX_THUMBNAIL = 500;
const MAX_YOUTUBE_URL = 500;

/** Corta espacios y devuelve null si, después de eso, el texto supera el máximo. */
function tooLong($value, $max)
{
    return mb_strlen((string) $value) > $max;
}

try {
    $pdo = db();
    $now = gmdate('Y-m-d H:i:s');

    switch ($in['action']) {

        case 'save_content':
            if (!is_array($in['fields'] ?? null)) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'Faltan los campos a guardar.']));
            }

            // Se valida todo ANTES de guardar nada, para que no quede
            // guardada la mitad de los campos si uno falla.
            foreach ($in['fields'] as $key => $value) {
                if (!in_array($key, $ALLOWED_CONTENT_KEYS, true)) {
                    continue;
                }
                if (tooLong($value, MAX_CONTENT_VALUE)) {
                    http_response_code(400);
                    exit(json_encode(['ok' => false, 'error' => "El campo \"$key\" es demasiado largo (máximo " . MAX_CONTENT_VALUE . ' caracteres).']));
                }
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
                $stmt->execute([$key, trim((string) $value), $now]);
            }
            echo json_encode(['ok' => true]);
            break;

        case 'episode_save':
            $title = trim((string) ($in['title'] ?? ''));
            $youtubeUrl = trim((string) ($in['youtube_url'] ?? ''));
            $thumbnail = trim((string) ($in['thumbnail'] ?? ''));

            if ($title === '') {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'Falta el título del episodio.']));
            }
            if ($youtubeUrl === '') {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'Falta el link de YouTube del episodio.']));
            }
            if (tooLong($title, MAX_TITLE)) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'El título es demasiado largo (máximo ' . MAX_TITLE . ' caracteres).']));
            }
            if (tooLong($youtubeUrl, MAX_YOUTUBE_URL)) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'El link de YouTube es demasiado largo.']));
            }
            if (tooLong($thumbnail, MAX_THUMBNAIL)) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'La ruta de la miniatura es demasiado larga.']));
            }
            if ($youtubeUrl !== '' && !preg_match('#^https?://#i', $youtubeUrl)) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'El link de YouTube tiene que empezar con http:// o https://']));
            }

            $season = max(1, (int) ($in['season'] ?? 1));
            $episodeNumber = max(1, (int) ($in['episode_number'] ?? 1));
            $sortOrder = (int) ($in['sort_order'] ?? $episodeNumber);
            $isVisible = !empty($in['is_visible']) ? 1 : 0;

            if (!empty($in['id'])) {
                $stmt = $pdo->prepare(
                    'UPDATE episodes SET season=?, episode_number=?, title=?, thumbnail=?, youtube_url=?, sort_order=?, is_visible=?, updated_at=?
                     WHERE id=?'
                );
                $stmt->execute([$season, $episodeNumber, $title, $thumbnail, $youtubeUrl, $sortOrder, $isVisible, $now, (int) $in['id']]);
                $id = (int) $in['id'];
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO episodes (season, episode_number, title, thumbnail, youtube_url, sort_order, is_visible, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$season, $episodeNumber, $title, $thumbnail, $youtubeUrl, $sortOrder, $isVisible, $now, $now]);
                $id = (int) $pdo->lastInsertId();
            }
            echo json_encode(['ok' => true, 'id' => $id]);
            break;

        case 'episode_delete':
            if (empty($in['id'])) {
                http_response_code(400);
                exit(json_encode(['ok' => false, 'error' => 'Falta indicar qué episodio borrar.']));
            }
            $pdo->prepare('DELETE FROM episodes WHERE id = ?')->execute([(int) $in['id']]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Acción desconocida.']);
    }
} catch (Throwable $e) {
    error_log('content-save error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'No se pudo guardar. Probá de nuevo en un momento, y si sigue fallando avisale a quien mantiene la web.']);
}
