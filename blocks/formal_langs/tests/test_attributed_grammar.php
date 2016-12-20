<?php
// This file is part of Formal Languages block - https://bitbucket.org/oasychev/moodle-plugins/
//
// Formal Languages block is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Formal Languages block is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Formal Languages block.  If not, see <http://www.gnu.org/licenses/>.
/**
 * Defines unit-tests for attributed grammar language
 *
 * For a complete info, see qtype_correctwriting_token_base
 *
 * @copyright &copy; 2011 Oleg Sychev
 * @author Oleg Sychev, Dmitriy Mamontov, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */
global $CFG;
require_once($CFG->dirroot.'/blocks/formal_langs/language_simple_english.php');
require_once($CFG->dirroot.'/blocks/formal_langs/classes/attributed_grammar/language_attributed_grammar_lexer.php');
require_once($CFG->dirroot.'/blocks/formal_langs/classes/attributed_grammar/language_attributed_grammar.php');
require_once($CFG->dirroot.'/blocks/formal_langs/tests/test_utils.php');



/**
 * Tests a simple english language
 */
class block_formal_langs_attributed_grammar_lexer_test extends PHPUnit_Framework_TestCase
{

    /**
     * Utilities for testing
     * @var block_formal_langs_language_test_utils
     */
    protected $utils;


    public function __construct()
    {
        $this->utils = new block_formal_langs_language_test_utils('block_formal_langs_language_simple_english', $this);
    }

    public function test_simple_rule()
    {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('expr_prec_11 "объявление переменной %2(имя переменной)" ::= type "%ur(именительный)"  primitive_or_complex_type "%s" ASSIGN "оператор присваивания"  expr_prec_9 "%ur(именительный)" .');
        $result = $processedstring->stream->tokens;
        $list = array();
        foreach($result as $token) {
            /** @var block_formal_langs_token_base $token */
            $list[] = $token->type() . ' - '.  (string)($token->value());
        }
        $original = 'lexemename - expr_prec_11
start_of_description - "
text - объявление переменной
specifier - %2
openingbrace - (
text - имя переменной
closingbrace - )
end_of_description - "
rule_part - ::=
lexemename - type
start_of_description - "
specifier - %ur
openingbrace - (
text - именительный
closingbrace - )
end_of_description - "
lexemename - primitive_or_complex_type
start_of_description - "
specifier - %s
end_of_description - "
lexemename - ASSIGN
start_of_description - "
text - оператор присваивания
end_of_description - "
lexemename - expr_prec_9
start_of_description - "
specifier - %ur
openingbrace - (
text - именительный
closingbrace - )
end_of_description - "
dot - .';
        $original = explode("\n", $original);
        $this->assertTrue(count($original) == count($list));
        for($i = 0; $i < count($original); $i++) {
            $oi = trim($list[$i]);
            $oj = trim($original[$i]);
            $this->assertTrue($oi == $oj, $oi);
        }
    }

    public function test_rule_other_data()
    {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('expr_prec_11 "о\"2\\\\ %2(имя переменной,21,32)" ::= type "%ur(именительный)"  primitive_or_complex_type "%s" ASSIGN "оператор присваивания"  expr_prec_9 "%ur(именительный)" .');
        $result = $processedstring->stream->tokens;
        $list = array();
        foreach($result as $token) {
            /** @var block_formal_langs_token_base $token */
            $list[] = $token->type() . ' - '.  (string)($token->value());
        }

        $original = 'lexemename - expr_prec_11
start_of_description - "
text - о
text - "
number - 2
text - \
text - \
text -
specifier - %2
openingbrace - (
text - имя переменной
comma - ,
number - 21
comma - ,
number - 32
closingbrace - )
end_of_description - "
rule_part - ::=
lexemename - type
start_of_description - "
specifier - %ur
openingbrace - (
text - именительный
closingbrace - )
end_of_description - "
lexemename - primitive_or_complex_type
start_of_description - "
specifier - %s
end_of_description - "
lexemename - ASSIGN
start_of_description - "
text - оператор присваивания
end_of_description - "
lexemename - expr_prec_9
start_of_description - "
specifier - %ur
openingbrace - (
text - именительный
closingbrace - )
end_of_description - "
dot - .';
        $original = explode("\n", $original);
        $this->assertTrue(count($original) == count($list));
        for($i = 0; $i < count($original); $i++) {
            $oi = trim($list[$i]);
            $oj = trim($original[$i]);
            $this->assertTrue($oi == $oj, $oi);
        }
    }


    public function test_nested_rules()
    {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('expr_prec_11 "a" ::= { kv "2" ::= "cb"  } "td"  { kv "2" ::= "cb"  } "td" .');
        $result = $processedstring->stream->tokens;
        $list = array();
        foreach($result as $token) {
            /** @var block_formal_langs_token_base $token */
            $list[] = $token->type() . ' - '.  (string)($token->value());
        }
        $original = 'lexemename - expr_prec_11
start_of_description - "
text - a
end_of_description - "
rule_part - ::=
opening_figure_brace - {
lexemename - kv
start_of_description - "
number - 2
end_of_description - "
rule_part - ::=
start_of_description - "
text - cb
end_of_description - "
closing_figure_brace - }
start_of_description - "
text - td
end_of_description - "
opening_figure_brace - {
lexemename - kv
start_of_description - "
number - 2
end_of_description - "
rule_part - ::=
start_of_description - "
text - cb
end_of_description - "
closing_figure_brace - }
start_of_description - "
text - td
end_of_description - "
dot - .';
        $original = explode("\n", $original);
        $this->assertTrue(count($original) == count($list));
        for($i = 0; $i < count($original); $i++) {
            $oi = trim($list[$i]);
            $oj = trim($original[$i]);
            $this->assertTrue($oi == $oj, $oi);
        }
    }


    public function test_parsing_rule_1() {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('expr_prec_11 "a" ::= { kv "2" ::= TLDR "cb"  }  { kv "2" ::= TLDR "cb"  } .');
        $result = $processedstring->syntaxtree;
        $val = block_formal_langs_language_test_utils::print_node_for_external($result, 0);
        // file_put_contents(dirname(__FILE__) . '/attr_grammar/attr1.txt', $val);
        $original = file_get_contents(dirname(__FILE__) . '/attr_grammar/attr1.txt');
        $this->assertTrue($val == $original, $val);
    }

    public function test_parsing_rule_2() {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('
           expr_prec_11 "a" ::= a "тест %ur(2, именительный) 22" b "%1" .
           expr_prec_10 "\"\"" ::= a "тест %ur(2, именительный) 22" b "%1" .
        ');
        $result = $processedstring->syntaxtree;
        $val = block_formal_langs_language_test_utils::print_node_for_external($result, 0);
        // file_put_contents(dirname(__FILE__) . '/attr_grammar/attr2.txt', $val);
        $original = file_get_contents(dirname(__FILE__) . '/attr_grammar/attr2.txt');
        $this->assertTrue($val == $original, $val);
    }

    public function test_parsing_rule_3() {
        $lang = new block_formal_langs_language_attribute_grammar_language();
        $processedstring = $lang->create_from_string('
           expr_prec_11 "a" ::= { head "%ur" ::=  { tail "%ur" ::= { tail2 "%ur" ::= tail3 "%ur" } } }  test1 "%ur(именительный)".
           expr_prec_11 "a" ::= { head "%ur" ::=  { tail "%ur" ::= { tail2 "%ur" ::= tail3 "%ur" } } }  test1 "%ur(именительный)".
           expr_prec_11 "a" ::= { head "%ur" ::=  { tail "%ur" ::= { tail2 "%ur" ::= tail3 "%ur" } } }  test1 "%ur(именительный)".
           expr_prec_10 "\"\"" ::= a "тест %ur(2, именительный) 22" b "%1" .
        ');
        $result = $processedstring->syntaxtree;
        $val = block_formal_langs_language_test_utils::print_node_for_external($result, 0);
        //file_put_contents(dirname(__FILE__) . '/attr_grammar/attr3.txt', $val);
        $original = file_get_contents(dirname(__FILE__) . '/attr_grammar/attr3.txt');
        $this->assertTrue($val == $original, $val);
    }
}