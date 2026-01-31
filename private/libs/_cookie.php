<?php
/**
 */
class _cookie
{
    #
	static public function add($name, $value, $options=[])
	{
        $secure = strstr($_SERVER['HTTP_HOST'], 'roleplus.vh')
            ? false : true;

        $options['expires'] = $options['expires'] ?? strtotime('+100 days');
        $options['path'] = $options['path'] ?? '/';
        $options['domain'] = $options['domain'] ?? $_SERVER['HTTP_HOST'];
        $options['secure'] = $options['secure'] ?? $secure;
        $options['httponly'] = $options['httponly'] ?? true;
        $options['samesite'] = $options['samesite'] ?? 'Strict'; # None || Lax || Strict

        $r = setcookie($name, $value, $options);
        #_var::die([$name, $value, $options, $r, $_COOKIE['user']['lang']]);
    }

    #
	static public function quit($name)
	{
        setcookie($name, null, -1);
    }
}
