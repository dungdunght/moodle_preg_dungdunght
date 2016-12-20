<?php
/**
 * Converts source grammar into readable format, stripping all unneeded actions
 */
$text = file_get_contents("langs_src/parser_cpp_language.y");
$filteremptylines = function($lines) {
	$lines = array_map('trim' , $lines);
	$lines = array_filter($lines, function($a) { return mb_strlen($a) != 0; });
	$lines = array_values($lines);
	return $lines;
};
$lines =  explode("\n", $text);
$lines = $filteremptylines($lines);
// Find first line after last, that start with %
$line = 0;
for($i = 0; $i < count($lines); $i++) {
	if (mb_strlen($lines[$i])) {
		if ($lines[$i][0] == '%') {
			$line = $i + 1;
		}
	}
}
$lines = array_slice($lines, $line);
$text = implode("\n", $lines);
$resulttext = "";
$state = 0;
$bracescounter = 0;
for($i = 0; $i < mb_strlen($text); $i++)
{
	if ($state == 0) {
		if (mb_substr($text, $i, 2) == '/*') {
			$state = 2;
			$i++;
		} else {
			if ($text[$i] == '.') {
				$resulttext .= ".\n";
				$state = 1;
				$bracescounter = 0;
			} else {
				$resulttext .= $text[$i];
			}
		}
	} else {
		if ($state == 1) {
			if ($text[$i] == '{') {
				$bracescounter += 1;
			} else {
				if ($text[$i] == '}') {
					$bracescounter -= 1;
					if ($bracescounter == 0) {
						$state = 0;
					}
				}
			}
		} else {
			if (mb_substr($text, $i, 2) == '*/') {
				$state = 0;
				$i++;
			}
		}
	}
}

$lines =  explode("\n", $resulttext);
$lines = $filteremptylines($lines);

for($i = 0; $i < count($lines); $i++) {
	if ($lines[$i][mb_strlen($lines[$i]) - 1] != ".") {
		$founddot = false;
		for($j = $i + 1; ($j < count($lines)) && !$founddot; $j++) {
			$lines[$i] .= ' ' . $lines[$j];
			$founddot = ($lines[$j][mb_strlen($lines[$j]) - 1] == '.');
			unset($lines[$j]);
			$lines = array_values($lines);
			$j--;
		}
	}
}

$linestogroups = array_map(function($o) {
	$parts = explode("::=", $o);
	$part = trim(str_replace('.', ' ', $parts[1]));
	$parts = array_map('trim', explode(' ', $part));
	$parts = array_map(function($o) { return preg_replace("/\\([^)]+\\)/", "", $o); }, $parts);
	$parts = array_filter($parts, function($a) { return mb_strlen($a) != 0; });
	return array($parts, $o);
}, $lines);

$groups = array();
while(count($linestogroups)) {
	$group = array( array_shift($linestogroups) );
	for($j = 0; $j < count($linestogroups); $j++) {
		if ($group[0][0] == $linestogroups[$j][0]) {
			$group[] = $linestogroups[$j];
			unset($linestogroups[$j]);
			$linestogroups = array_values($linestogroups);
		}
	}
	if (count($group) > 1)
	{
		$groups[] = array_map(function($o) { return $o[1]; }, $group);
	}
}
 
for($i = 0; $i < count($groups); $i++) {
	echo implode("\n", $groups[$i]);
	echo "\n\n";
}