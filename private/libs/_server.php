<?php
/**
 */
class _server
{
    static public function isLocal()
    {
        return preg_match('/localhost|\.v/i', $_SERVER['HTTP_HOST'])
            ? true : false;
    }
}
