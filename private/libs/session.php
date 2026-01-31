<?php
/**
 * KumbiaPHP - Session OO wrapper
 */

/* Arranque inicial de sesión (como tenías) */
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

class Session
{
    /** Reabre la sesión si se necesita escribir */
    private static function ensureOpenForWrite(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    /** Cierra la sesión liberando el lock (seguro llamar múltiples veces) */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
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
        self::ensureOpenForWrite();
        $_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index][] = $value;
    }

    // set: ESCRIBE -> requiere sesión abierta
    public static function set($index, $value, $namespace='default')
    {
        self::ensureOpenForWrite();
        $_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index] = $value;
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
        self::ensureOpenForWrite();
        unset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index]);
    }

    // deleteAll: ESCRIBE -> requiere sesión abierta
    public static function deleteAll($namespace='default')
    {
        self::ensureOpenForWrite();
        unset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace]);
    }

    // has: LECTURA
    public static function has($index, $namespace='default')
    {
        return isset($_SESSION['KUMBIA_SESSION'][APP_PATH][$namespace][$index]);
    }
}
