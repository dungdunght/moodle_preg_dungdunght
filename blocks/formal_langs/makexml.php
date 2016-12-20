<?php
global $CFG;

define('MOODLE_INTERNAL', 1);

$CFG = new stdClass();
$CFG->dirroot = dirname(dirname(dirname(__FILE__)));
$CFG->libdir = $CFG->dirroot . '/lib';

require_once($CFG->dirroot .'/lib/classes/text.php');
require_once($CFG->dirroot .'/blocks/formal_langs/language_cpp_parseable_language.php');

global $string;
include_once($CFG->dirroot . '/blocks/formal_langs/lang/ru/block_formal_langs.php');
global $stringbank;
$stringbank = $string;
global $errors;
$errors = array();
function get_string($string, $component, $o) {
	global $stringbank, $a, $errors;
	$a = $o;
	$evald = str_replace('"', '\\"', $stringbank[$string]);
	$evald = 'return "' . $evald . '";';
    $result = eval($evald);
	$errors[] = $result;
	return $result;
}

function print_node($node, $paddingcount) {
    $result = '';
	if ($node == null) {
        return $result;
	}
	$padding = str_repeat(' ', $paddingcount);
    if (is_array($node)) {
        foreach($node as $i => $nodechild) {
            $result .= print_node($nodechild, $paddingcount);
            if ($i != count($node) -1) {
                $result .= $padding . PHP_EOL;
            }
        }
        return $result;
    }
	
    $value = '';
    if (is_a($node, 'block_formal_langs_token_base')) {
        $value = $node->value();
    }
    $attrs = array();
    $attrs[] = "linestart=\"" . $node->position()->linestart() .  "\"";
    $attrs[] = "lineend=\"" . $node->position()->lineend() .  "\"";
    $attrs[] = "colstart=\"" . $node->position()->colstart() .  "\"";
    $attrs[] = "colend=\"" . $node->position()->colend() .  "\"";
    $attrs[] = "stringstart=\"" . $node->position()->stringstart() . "\"";
    $attrs[] = "stringend=\"" . $node->position()->stringend() . "\"";    
    if (core_text::strlen($value)) {
        $attrs[] = "value=\"" . str_replace('"', '\\"', $value);
        $result .= $padding . "<" . (string)($node->type()) . ' ' . implode(' ', $attrs)  . "\" />";
        return $result;
    }
	$result .= $padding . "<" . (string)($node->type()) . ' ' . implode(' ', $attrs)  . ">" . PHP_EOL;
	if (count($node->children()))  {
		foreach($node->children() as $child) {
			$result .= print_node($child, $paddingcount + 1) . PHP_EOL;
		}
	}
	$result .= $padding . "</" . (string)($node->type()) . ">";
    return $result;
}

$inputfile = "in.txt";
$outputfile = "out.xml";
if (count($argv) == 2) {
    $inputfile = $argv[1];
}

if (count($argv) >= 2) {
    $inputfile = $argv[1];
    $outputfile = $argv[2];    
}

function first_token($node) {
    if (count($node->children()))  {
        $children = $node->children();
        return first_token($children[0]);
    }
    return $node;
}

$inputtext = file_get_contents($inputfile);

if ($inputtext !== false) {
    $lang = new block_formal_langs_language_cpp_parseable_language();
    /*$lang->parser()->set_namespace_tree($namespacetree);
    if (isset($donotstripcomments)) {
        $lang->parser()->set_strip_comments(false);
    }*/
    $result = $lang->create_from_string($inputtext);
    $tree = $result->syntaxtree;
    $newstring = print_node($result->syntaxtree, 0);
    if (count($tree) <= 1) {
        $newstring = print_node($result->syntaxtree, 0);
    } else {
        $firsttoken = first_token($result->syntaxtree[1]);
        $newstring = "Синтаксическая ошибка около позиции " 
                   . ($firsttoken->position()->linestart() + 1) 
                   . ":"
                   . $firsttoken->position()->colstart();                   
    }
	if (count($errors)) {
		if (mb_strlen($newstring)) {
			$newstring .= "\r\n";
		}
		$newstring .= implode("\r\n", $errors);
	}
    @file_put_contents($outputfile, $newstring);
} else {
    echo "Cannot open file " . $inputfile;
}