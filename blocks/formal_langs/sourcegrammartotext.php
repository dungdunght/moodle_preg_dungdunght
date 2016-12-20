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
			$resulttext .= mb_substr($text, $i, 2);
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
				$resulttext .= mb_substr($text, $i, 2);				
				$i++;
			} else {
				$resulttext .= $text[$i];
			}
		}
	}
}

$lines =  explode("\n", $resulttext);
$lines = $filteremptylines($lines);
$text = implode("\n", $lines);
echo $text;