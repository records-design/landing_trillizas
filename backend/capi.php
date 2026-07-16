<?php
/**
 * Envío server-side de eventos a la Meta Conversions API.
 * - No hace hashing de PII porque solo mandamos IP, user agent y fbc
 *   (esos campos NO se hashean, según la doc de Meta).
 * - Usa el mismo event_id que el frontend para deduplicar con el Pixel
 *   (si en el futuro se agrega el Pixel del navegador).
 * - Nunca lanza excepción: si Meta falla, el evento ya quedó en la DB.
 */

/**
 * @param array $event  Datos ya normalizados del evento.
 * @param array $meta   Config['meta'].
 * @return bool         true si Meta respondió 200.
 */
function send_to_meta(array $event, array $meta)
{
    if (empty($meta['enabled']) || empty($meta['pixel_id']) || empty($meta['access_token'])) {
        return false;
    }

    // Nombre del evento en Meta.
    $eventName = $event['event_name'] === 'page_view'
        ? 'PageView'
        : 'ClicBoton'; // evento custom para clics en botones

    // fbc a partir del fbclid capturado en la URL: fb.1.{ts}.{fbclid}
    $fbc = null;
    if (!empty($event['fbclid'])) {
        $fbc = 'fb.1.' . (time() * 1000) . '.' . $event['fbclid'];
    }

    $userData = array_filter([
        'client_ip_address' => $event['ip_raw'] ?? null,   // IP real (Meta la usa para matching)
        'client_user_agent' => $event['user_agent'] ?? null,
        'fbc'               => $fbc,
    ]);

    $customData = array_filter([
        'button'      => $event['button'] ?? null,
        'destination' => $event['destination'] ?? null,
        'placement'   => $event['placement'] ?? null,
        'campaign'    => $event['utm_campaign'] ?? null,
    ]);

    $data = [
        'event_name'       => $eventName,
        'event_time'       => time(),
        'event_id'         => $event['event_id'],
        'action_source'    => 'website',
        'event_source_url' => $event['url'] ?? null,
        'user_data'        => $userData,
    ];
    if (!empty($customData)) {
        $data['custom_data'] = $customData;
    }

    $body = ['data' => [$data]];
    if (!empty($meta['test_event_code'])) {
        $body['test_event_code'] = $meta['test_event_code'];
    }

    $url = sprintf(
        'https://graph.facebook.com/%s/%s/events?access_token=%s',
        $meta['api_version'],
        $meta['pixel_id'],
        urlencode($meta['access_token'])
    );

    $status = http_post_json($url, $body, 3);
    return $status >= 200 && $status < 300;
}

/** POST JSON con timeout corto. Devuelve el código HTTP (0 si falló la conexión). */
function http_post_json($url, array $payload, $timeout)
{
    $json = json_encode($payload);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($res === false) {
            error_log('Meta CAPI error: ' . curl_error($ch));
        }
        curl_close($ch);
        return $code;
    }

    $ctx = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $json,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false) {
        return 0;
    }
    // Extrae el código de $http_response_header.
    if (isset($http_response_header[0]) &&
        preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
        return (int) $m[1];
    }
    return 0;
}
