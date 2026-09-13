<?php
/**
 * KumbiaPHP - Session OO wrapper
 */

/* Arranque inicial de sesión (como tenías) */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

class Session
{
    private const COOKIE_LIFETIME = 2592000; // 30 días desde la última renovación.
    private const COOKIE_REFRESH_INTERVAL = 43200; // Como máximo, una cabecera cada 12 horas.

    /** Reabre la sesión si se necesita escribir */
    private static function ensureOpenForWrite(): bool
    {
        $openedHere = session_status() !== PHP_SESSION_ACTIVE;
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        return $openedHere && session_status() === PHP_SESSION_ACTIVE;
    }

    /**
     * Renueva la caducidad durante el uso normal sin generar tráfico extra.
     * El sello evita enviar Set-Cookie en cada refresco o petición AJAX.
     */
    private static function refreshCookieIfDue(): void
    {
        if (headers_sent() || session_status() !== PHP_SESSION_ACTIVE || session_id() === '') {
            return;
        }

        $session = $_SESSION['KUMBIA_SESSION'][APP_PATH]['default'] ?? [];
        if (empty($session['idu'])) {
            return;
        }

        $now = time();
        $lastRefresh = (int) ($_SESSION['KUMBIA_SESSION'][APP_PATH]['__meta']['cookie_refreshed_at'] ?? 0);
        if ($lastRefresh > $now - self::COOKIE_REFRESH_INTERVAL) {
            return;
        }

        $params = session_get_cookie_params();
        $options = [
            'expires' => $now + self::COOKIE_LIFETIME,
            'path' => $params['path'] ?: '/',
            'secure' => (bool) $params['secure'],
            'httponly' => true,
            'samesite' => $params['samesite'] ?: 'Lax',
        ];
        if (!empty($params['domain'])) {
            $options['domain'] = $params['domain'];
        }

        if (setcookie(session_name(), session_id(), $options)) {
            $_SESSION['KUMBIA_SESSION'][APP_PATH]['__meta']['cookie_refreshed_at'] = $now;
        }
    }

    /** Cierra la sesión liberando el lock (seguro llamar múltiples veces) */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::refreshCookieIfDue();
            @session_write_close();
        }
    }

    /** Abre explícitamente (normalmente no hace falta; set()/delete() ya reabren) */
    public static function open(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /*** API existente ***/

    // setArray: ESCRIBE -> requiere sesión abierta
    public static function setArray($index, $value, $namespace='default')
    {
        $openedHere = self::ensureOpenForWrite();
        $_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index][] = $value;
        if ($openedHere) {
            self::close();
        }
    }

    // set: ESCRIBE -> requiere sesión abierta
    public static function set($index, $value, $namespace='default')
    {
        $openedHere = self::ensureOpenForWrite();
        $_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index] = $value;
        if ($openedHere) {
            self::close();
        }
    }

    // get: LECTURA -> no bloquea; no reabre
    public static function get($index, $namespace='default')
    {
        if (isset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index])) {
            return $_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index];
        }
    }

    // delete: ESCRIBE -> requiere sesión abierta
    public static function delete($index, $namespace='default')
    {
        $openedHere = self::ensureOpenForWrite();
        unset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index]);
        if ($openedHere) {
            self::close();
        }
    }

    // deleteAll: ESCRIBE -> requiere sesión abierta
    public static function deleteAll($namespace='default')
    {
        $openedHere = self::ensureOpenForWrite();
        unset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace]);
        if ($openedHere) {
            self::close();
        }
    }

    // has: LECTURA
    public static function has($index, $namespace='default')
    {
        return isset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index]);
    }
}
