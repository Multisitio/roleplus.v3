<?php
/**
 */
class _array
{
    # 
	static public function count($array, $key='')
	{
		if ( ! $key) {
			if (empty($array)) {
				return 0;
			}
			return count($array);
		}

		if (empty($array[$key])) {
			return 0;
		}
		return count($array[$key]);
	}

    # 
	static public function sumaUnoSiExiste($array, $key)
	{
		if ( ! is_array($array)) {
			return $array;
		}

        $array[$key] = empty($array[$key]) ? 1 : ++$array[$key];
		return $array;
	}
}
