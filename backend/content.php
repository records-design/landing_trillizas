<?php
/**
 * Endpoint público de solo lectura: devuelve el contenido editable
 * de la landing (textos/links + lista de episodios) para que el
 * HTML lo pinte con JS al cargar. No requiere login — es la misma
 * información que cualquiera puede ver mirando la página.
 */

require_once __DIR__ . '/config_loader.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$pdo = db();

$content = [];
foreach ($pdo->query('SELECT content_key, content_value FROM site_content') as $row) {
    $content[$row['content_key']] = $row['content_value'];
}

$episodes = $pdo->query(
    'SELECT season, episode_number, title, thumbnail, youtube_url
     FROM episodes
     WHERE is_visible = 1
     ORDER BY sort_order ASC, episode_number ASC'
)->fetchAll();

echo json_encode([
    'content'  => $content,
    'episodes' => array_map(function ($ep) {
        return [
            'season'      => (int) $ep['season'],
            'episode'     => (int) $ep['episode_number'],
            'title'       => $ep['title'],
            'thumbnail'   => $ep['thumbnail'] ?: '',
            'youtubeUrl'  => $ep['youtube_url'],
        ];
    }, $episodes),
]);
