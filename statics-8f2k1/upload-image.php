<?php
/**
 * Sube una imagen (miniatura de episodio) a la carpeta imagenes/ de
 * la landing, para que quien usa el panel no tenga que saber usar
 * FileZilla ni escribir rutas de archivo a mano.
 */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Sesión vencida, volvé a entrar al panel.']));
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'Método no permitido']));
}

if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'La imagen es demasiado pesada.',
        UPLOAD_ERR_FORM_SIZE  => 'La imagen es demasiado pesada.',
        UPLOAD_ERR_PARTIAL    => 'La subida se cortó a la mitad, probá de nuevo.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ninguna imagen.',
    ];
    $code = $_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE;
    exit(json_encode(['ok' => false, 'error' => $uploadErrors[$code] ?? 'No se pudo subir la imagen.']));
}

$file = $_FILES['image'];

// Límite de tamaño: 8MB (de sobra para una miniatura, evita subidas
// gigantes por error que después hacen lenta la web).
$MAX_BYTES = 8 * 1024 * 1024;
if ($file['size'] > $MAX_BYTES) {
    exit(json_encode(['ok' => false, 'error' => 'La imagen pesa demasiado (máximo 8MB). Probá con una versión más liviana.']));
}

// Se valida el tipo real del archivo (no el nombre ni lo que diga el
// navegador), para que no se pueda subir cualquier cosa disfrazada
// de imagen.
$ALLOWED_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!isset($ALLOWED_TYPES[$mime])) {
    exit(json_encode(['ok' => false, 'error' => 'Ese archivo no es una imagen válida (solo JPG, PNG o WEBP).']));
}

$ext = $ALLOWED_TYPES[$mime];

// Nombre de archivo: se genera uno nuevo (no se usa el original) para
// evitar espacios, acentos o caracteres raros que rompan la URL, y
// para que dos personas subiendo "foto.jpg" al mismo tiempo no se
// pisen entre sí.
$safeName = 'episodio-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;

$destDir = __DIR__ . '/../imagenes';
if (!is_dir($destDir) || !is_writable($destDir)) {
    exit(json_encode(['ok' => false, 'error' => 'El servidor no tiene permiso para guardar la imagen ahí. Avisale a quien mantiene la web.']));
}

$destPath = $destDir . '/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    exit(json_encode(['ok' => false, 'error' => 'No se pudo guardar la imagen en el servidor.']));
}

echo json_encode(['ok' => true, 'path' => 'imagenes/' . $safeName]);
