<?php
/**
 * Geolocalización por IP.
 * Proveedor por defecto: ip-api.com (gratuito, sin API key, ~45 req/min).
 * Devuelve ['country','country_code','region','city'] o valores null si falla.
 * Nunca lanza excepción: si el servicio no responde, el evento igual se guarda.
 */

function geolocate($ip, $cfg)
{
    $empty = ['country' => null, 'country_code' => null, 'region' => null, 'city' => null];

    if (empty($cfg['enabled']) || ($cfg['provider'] ?? 'none') === 'none') {
        return $empty;
    }
    if (!filter_var($ip, FILTER_VALIDATE_IP) || $ip === '0.0.0.0') {
        return $empty;
    }

    if ($cfg['provider'] === 'ip-api') {
        return geolocate_ipapi($ip, (int) ($cfg['timeout'] ?? 2)) ?: $empty;
    }

    return $empty;
}

function geolocate_ipapi($ip, $timeout)
{
    $url = 'http://ip-api.com/json/' . urlencode($ip)
         . '?fields=status,country,countryCode,regionName,city&lang=es';

    $raw = http_get($url, $timeout);
    if ($raw === null) {
        return null;
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
        return null;
    }

    return [
        'country'      => $data['country']     ?? null,
        'country_code' => $data['countryCode'] ?? null,
        'region'       => $data['regionName']  ?? null,
        'city'         => $data['city']        ?? null,
    ];
}

/** GET HTTP simple con timeout corto. Usa cURL si está, si no file_get_contents. */
function http_get($url, $timeout)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
        ]);
        $res = curl_exec($ch);
        curl_close($ch);
        return $res === false ? null : $res;
    }

    $ctx = stream_context_create(['http' => ['timeout' => $timeout]]);
    $res = @file_get_contents($url, false, $ctx);
    return $res === false ? null : $res;
}
