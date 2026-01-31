<?php
/**
 */
class _cut
{
    # 1
    public static function cut($from, $str, $to)
    {
        if ( ! stristr($str, $from)){
            return false;
        }
        $array = explode($from, $str, 2);
        if (empty($array[1]) || ! stristr($array[1], $to)) {
            return false;
        }
        $array = explode($to, $array[1], 2);
        if (empty($array[0])) {
            return false;
        }
        return trim($array[0]);
    }

    # 2
    public static function cut2($from, $from2, $str, $to)
    {
        if ( ! stristr($str, $from)){
            return false;
        }
        $array = explode($from, $str, 2);
        if (empty($array[1]) || ! stristr($array[1], $from2)) {
            return false;
        }
        $array = explode($from2, $array[1], 2);
        if (empty($array[1]) || ! stristr($array[1], $to)) {
            return false;
        }
        $array = explode($to, $array[1], 2);
        if (empty($array[0])) {
            return false;
        }
        return trim($array[0]);
    }

    # 3
    public static function cuts($from, $str, $to)
    {
        if ( ! stristr($str, $from)){
            return false;
        }
        $cuts = explode($from, $str);
        array_shift($cuts);
        $array = [];
        foreach ($cuts as $cut) {
			if ( ! stristr($cut, $to)){
				continue;
			}
			$array = explode($to, $cut, 2);
			if (empty($array[0])) {
				continue;
			}
            $result[] = trim($array[0]);
        }
        return $result;
    }

    # 4
    public static function cuts2($from, $from2, $str, $to)
    {
        if ( ! stristr($str, $from)){
            return false;
        }
        $cuts = explode($from, $str);
        array_shift($cuts);
        $array = [];
        foreach ($cuts as $cut) {
			if ( ! stristr($cut, $from2)){
				continue;
			}
			$array = explode($from2, $cut, 2);
			if (empty($array[1]) || ! stristr($array[1], $to)) {
				continue;
			}
			$array = explode($to, $array[1], 2);
			if (empty($array[0])) {
				continue;
			}
            $result[] = trim($array[0]);
        }
        return $result;
    }
}
