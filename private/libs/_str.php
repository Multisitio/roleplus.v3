<?php
/**
 */
class _str
{
    static public function count_files($s, $cut="\n")
    {
        $a = explode($cut, $s);
        return count($a);
    }

    #
    static public function cut($from, $str, $to)
    {
        if ( ! stristr($str, $from) or ! stristr($str, $to)){
            return;
        }
        $arr = explode($from, $str, 2);
        if (empty($arr[1])) {
            return;
        }
        $arr = explode($to, $arr[1], 2);
        if (empty($arr[0])) {
            return;
        }
        return trim($arr[0]);
    }

    #
    static public function cut2($from, $from2, $str, $to)
    {
        $arr = explode($from, $str, 2);
        if (empty($arr[1])) {
            return;
        }
        $arr = explode($from2, $arr[1], 2);
        if (empty($arr[1])) {
            return;
        }
        $arr = explode($to, $arr[1], 2);
        if (empty($arr[0])) {
            return;
        }
        return trim($arr[0]);
    }

    #
    static public function cuts($from, $s, $to)
    {
        $cuts = explode($from, $s);
        array_shift($cuts);
        $a = [];
        foreach ($cuts as $cut) {
            $a[] = trim(explode($to, $cut, 2)[0]);
        }
        return $a;
    }

    # Monta una matriz con cada trozo cortado
	static public function getAll($from, $s, $to)
	{
        $a = explode($from, $s);
        array_shift($a);
        if ( ! $a) {
            return;
        }
        foreach($a as $s) {
            $r[] = explode($to, $s, 2)[0];
        }
        return $r;
    }

	# CONVIERTE UNA CADENA EN UN HASHTAG
    static public function hashtag($str)
    {    
		# 
		$str = self::normalize($str);
		#
		$str = preg_replace('/[^-\w&]+/', ' ', $str);
		# 
		$str = preg_replace('/-|_+/', ' ', $str);
		# 
		$str = ucwords($str);
		# 
		$str = preg_replace('/\s+/', '', $str);

        return trim($str);
    }

    # ID no único
	static public function id($s='')
	{
        return substr(md5($s), 0, 11);
    }

	# NORMALIZA UNA CADENA
    static public function normalize($str)
    {  
        $str = str_replace(
            ['ä', 'Ä', 'ª', 'á', 'Á', '@', 'ç', 'Ç', 'ë', 'Ë', 'é', 'É', 'ï', 'Ï', 'í', 'Í', 'ñ', 'Ñ', 'ö', 'Ö', 'º', 'ó', 'Ó', 'ü', 'Ü', 'ú', 'Ú'],
            ['a', 'A', 'a', 'a', 'A', 'a', 'c', 'C', 'e', 'E', 'e', 'E', 'i', 'I', 'i', 'I', 'ny', 'NY', 'o', 'O', 'o', 'o', 'O', 'u', 'U', 'u', 'U'],
            $str
        );
        return $str;
    }

    static public function truncate($s, $l=33)
    {
        $a = preg_split('/\s/', $s, $l);
        if (count($a) < $l) return $s;
        array_pop($a);
        return implode(' ', $a) . '...';
    }

    static public function truncate_files($s, $l=33, $cut="\n")
    {
        $a = explode($cut, $s, $l);
        if (count($a) < $l) {
            return $s;
        }
        array_pop($a);
        return implode($cut, $a) . '…';
    }

	static public function uid($s='')
	{
        return substr(md5(microtime().$s), 0, 12);
    }
}