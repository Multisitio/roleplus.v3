<?php
/**
 */
class _fastbond
{
    # 1
	static public function readDir($dir)
	{
		return glob($dir);
	}

    # 2
	static public function readFile($file)
	{
		return file_get_contents($file);
	}

    # 3
	static public function arrayByTokens($tokens, $array=[], $c=0)
	{
		$new_array = $new_value = $i = 0;
        foreach ($tokens as $token) {
			if ($i < $c) {
				continue;
			}

            echo "<h4>$token->id is {$token->getTokenName()}</h4>";
			echo "<h5>{$token->__toString()}</h5>";

            if ($token->getTokenName() == '[') {
                $new_array = 1;
            }
			elseif ($token->id == T_DOUBLE_ARROW) {
                $new_value = 1;
            }
			elseif ($token->id == T_CONSTANT_ENCAPSED_STRING) {
				if ($new_array) {
					$array[$token->__toString()] = [];
					$new_array = 0;

					$key = $token->__toString();
					$array[$key] = self::arrayByTokens($tokens, $array, ++$i);
				}
				elseif ($new_value) {
					$array[$key] = $token->__toString();
					$new_value = 0;
				}
				else {
					$key = $token->__toString();
					$array[$key] = '';
				}
			}
			++$i;
        }
		return $array;
	}
}
