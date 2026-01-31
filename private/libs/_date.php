<?php
/**
 */
class _date
{
    # TIEMPO PASADO
	static public function ago($date)
	{
        $now = new DateTime();
        $pub = new DateTime($date);
        $dif = $now->diff($pub);
        $s = '';
        if ($dif->y > 0) $s .= "$dif->y a";
        elseif ($dif->m > 0) $s .= "$dif->m m";
        elseif ($dif->d > 0) $s .= "$dif->d d";
        elseif ($dif->h > 0) $s .= "$dif->h h";
        elseif ($dif->i > 0) $s .= "$dif->i'";
        elseif ($dif->s > 0) $s .= "$dif->s\"";
        return $s;
    }

    #
	static public function format($s='', $format='Y-m-d H:i:s')
	{
        if ( ! $s) {
            return date($format);
        }
        return date($format, strtotime($s));
    }

    #
	static public function months()
	{
        return [
            1=>t('Enero'),
            2=>t('Febrero'),
            3=>t('Marzo'),
            4=>t('Abril'),
            5=>t('Mayo'),
            6=>t('Junio'),
            7=>t('Julio'),
            8=>t('Agosto'),
            9=>t('Septiembre'),
            10=>t('Octubre'),
            11=>t('Noviembre'),
            12=>t('Diciembre')
        ];
    }
}