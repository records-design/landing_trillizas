<?php
/**
 * Helpers compartidos: IP del cliente, anonimización, parseo de user agent.
 */

/** Obtiene la IP real del visitante (considerando proxies de Hostinger). */
function client_ip()
{
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
    foreach ($keys as $k) {
        if (!empty($_SERVER[$k])) {
            // X-Forwarded-For puede traer varias IPs separadas por coma.
            $parts = explode(',', $_SERVER[$k]);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/** Trunca el último octeto de una IPv4 (o los últimos bloques de IPv6). */
function anonymize_ip($ip)
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $p = explode('.', $ip);
        $p[3] = '0';
        return implode('.', $p);
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $p = explode(':', $ip);
        // Deja los primeros 3 bloques, cero el resto.
        for ($i = 3; $i < count($p); $i++) {
            $p[$i] = '0';
        }
        return implode(':', $p);
    }
    return '0.0.0.0';
}

/** Detección simple de dispositivo/SO/navegador a partir del user agent. */
function parse_user_agent($ua)
{
    $ua = (string) $ua;
    $device = 'desktop';
    $os = 'Desconocido';
    $browser = 'Desconocido';

    $isMobile = preg_match('/Mobile|Android|iPhone|iPod|Windows Phone/i', $ua);
    $isTablet = preg_match('/iPad|Tablet/i', $ua);
    if ($isTablet) {
        $device = 'tablet';
    } elseif ($isMobile) {
        $device = 'mobile';
    }

    if (preg_match('/iPhone|iPad|iPod/i', $ua)) {
        $os = 'iOS';
    } elseif (preg_match('/Android/i', $ua)) {
        $os = 'Android';
    } elseif (preg_match('/Windows/i', $ua)) {
        $os = 'Windows';
    } elseif (preg_match('/Mac OS X/i', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/i', $ua)) {
        $os = 'Linux';
    }

    // Orden importa: Edge/Chrome contienen "Safari" en el UA.
    if (preg_match('/Instagram/i', $ua)) {
        $browser = 'Instagram';
    } elseif (preg_match('/FBAN|FBAV/i', $ua)) {
        $browser = 'Facebook';
    } elseif (preg_match('/EdgA?|Edge/i', $ua)) {
        $browser = 'Edge';
    } elseif (preg_match('/OPR|Opera/i', $ua)) {
        $browser = 'Opera';
    } elseif (preg_match('/Chrome|CriOS/i', $ua)) {
        $browser = 'Chrome';
    } elseif (preg_match('/Firefox|FxiOS/i', $ua)) {
        $browser = 'Firefox';
    } elseif (preg_match('/Safari/i', $ua)) {
        $browser = 'Safari';
    }

    return ['device' => $device, 'os' => $os, 'browser' => $browser];
}

/** Recorta un string a un largo máximo (para columnas VARCHAR). */
function clip($value, $max)
{
    if ($value === null) {
        return null;
    }
    $value = (string) $value;
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}
