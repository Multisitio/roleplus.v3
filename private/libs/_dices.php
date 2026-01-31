<?php
class _dices
{
	static public function str2html($str)
	{
		return preg_replace_callback(
			'/\{D([a-z]+)(\d+f?):((?:&[A-Za-z0-9#]+;)|.)\}/u',
			function ($m) {
				$glyph = htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8');
				return '<i class="dice size-'.$m[2].' '.$m[1].'-text">'.$glyph.'</i>';
			},
			$str
		);
	}

	static public function render_md($text)
	{
		$placeholders = [];
		$idx = 0;

		$tmp = preg_replace_callback(
			'/\{D([a-z]+)(\d+f?):((?:&[A-Za-z0-9#]+;)|.)\}/u',
			function ($m) use (&$placeholders, &$idx) {
				$key = '~DICEPH~'.($idx++).'~';
				$glyph = htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8');
				$placeholders[$key] = '<i class="dice size-'.$m[2].' '.$m[1].'-text">'.$glyph.'</i>';
				return $key;
			},
			$text
		);

		$md = new Parsedown;
		$html = $md->text($tmp);

		if (!empty($placeholders)) {
			$html = str_replace(array_keys($placeholders), array_values($placeholders), $html);
		}
		return $html;
	}

	static public function throw($s)
	{
		$dices = [
			'3f' => [1 => '^', 2 => '&', 3 => '*'],
			4 => [1 => 'a', 2 => 'b', 3 => 'c', 4 => 'd'],
			6 => [1 => 'A', 2 => 'B', 3 => 'C', 4 => 'D', 5 => 'E', 6 => 'F'],
			8 => [1 => 'e', 2 => 'f', 3 => 'g', 4 => 'h', 5 => 'i', 6 => 'j', 7 => 'k', 8 => 'l'],
			10 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 0],
			12 => [1 => 'm', 2 => 'n', 3 => 'o', 4 => 'p', 5 => 'q', 6 => 'r', 7 => 's', 8 => 't', 9 => 'u', 10 => 'v', 11 => 'w', 12 => 'x'],
			20 => [1 => 'G', 2 => 'H', 3 => 'I', 4 => 'J', 5 => 'K', 6 => 'L', 7 => 'M', 8 => 'N', 9 => 'O', 10 => 'P', 11 => 'Q', 12 => 'R', 13 => 'S', 14 => 'T', 15 => 'U', 16 => 'V', 17 => 'W', 18 => 'X', 19 => 'Y', 20 => 'Z'],
		];

		$parts = explode('/r ', $s, 2);
		if (count($parts) < 2) {
			return $s;
		}

		$rest = explode(' ', $parts[1], 2);
		if (!preg_match('/^(\d*)d([a-z]*)(\d+f?)/i', $rest[0], $m)) {
			return $s;
		}

		$num = $m[1] === '' ? 1 : (int)$m[1];
		$color = $m[2] === '' ? 'grey' : $m[2];
		$key = strtolower($m[3]);
		$faces = ($key === '3f') ? 3 : (int)$key;

		if ($num === 1 && $faces === 100) {
			$num = 2;
			$key = 10;
			$faces = 10;
		}

		$out = [];
		for ($i = 0; $i < $num; ++$i) {
			$r = random_int(1, $faces);
			if (empty($dices[$key])) {
				$out[] = '['.$r.']';
			} else {
				$out[] = '{D'.$color.$key.':'.$dices[$key][$r].'}';
			}
		}

		$tail = empty($rest[1]) ? '' : $rest[1];
		$s = $parts[0].implode(' ', $out).' '.$tail;

		return self::throw($s);
	}
}
