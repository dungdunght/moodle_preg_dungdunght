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
 * Defines an attributed grammar language for partsing
 *
 * @package    blocks
 * @subpackage formal_langs
 * @copyright &copy; 2011 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev, Mamontov Dmitriy, Maria Birukova
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */
global $CFG;
require_once($CFG->dirroot .'/blocks/formal_langs/classes/attributed_grammar/language_attributed_grammar.php');
require_once($CFG->dirroot .'/blocks/formal_langs/classes/attributed_grammar/parser_attributed_grammar_language.php');
require_once($CFG->dirroot .'/blocks/formal_langs/lexer_to_parser_mapper.php');

/**
 * Class block_formal_langs_lexer_cpp_mapper
 * A mapper for mapping C++ lexer to parser constants
 */
class block_formal_langs_lexer_attibuted_grammar_mapper extends block_formal_langs_lexer_to_parser_mapper {

    /**
     * Construcs mapper
     */
    public function __construct() {
        parent::__construct();
    }
    /**
     * Returns name of parser class
     * @return name of parser class
     */
    public function parsername() {
        return 'block_formal_langs_parser_attributed_grammar_language';
    }
    /**
     * Returns mappings of lexer tokens to parser tokens
     * @param string $any name for any value matching
     * @return array mapping
     */
    public function maptable($any) {
        $table = array(
            'semicolon' => array( $any => 'SEMICOLON' ),
            'dot' => array( $any => 'DOT' ),
            'rule_part' => array( $any => 'RULE_PART' ),
            'lexemename' => array( $any => 'LEXEME_NAME' ),
            'start_of_description' => array( $any => 'START_OF_DESCRIPTION' ),
            'end_of_description' => array( $any => 'END_OF_DESCRIPTION' ),
            'text' => array( $any => 'TEXT' ),
            'number' => array( $any => 'NUMBER' ),
            'comma' => array( $any => 'COMMA' ),
            'closingbrace' => array( $any => 'CLOSING_BRACE' ),
            'openingbrace' => array( $any => 'OPENING_BRACE' ),
            'opening_figure_brace' => array( $any => 'OPENING_FIGURE_BRACE' ),
            'closing_figure_brace' => array( $any => 'CLOSING_FIGURE_BRACE' ),
            'specifier' => array( $any => 'SPECIFIER' ),
        );
        return $table;
    }


}


class block_formal_langs_language_attribute_grammar_language extends block_formal_langs_predefined_language
{
    /**
     * Constructs a language
     */
    public function __construct() {
        parent::__construct(null,null);
    }

    /**
     * Returns name for language
     * @return string
     */
    public function name() {
        return 'attribute_grammar';
    }

    /**
     * Returns name for language
     * @return string
     */
    public function lexem_name() {
        return get_string('lexeme', 'block_formal_langs');
    }
    /**
     * Returns name for lexer class
     * @return string
     */
    protected function lexername() {
        return 'block_formal_langs_predefined_attributed_grammar_lexer_raw';
    }

    /**
     * Returns name for parser class
     * @return string
     */
    protected function parsername() {
        return 'block_formal_langs_lexer_attibuted_grammar_mapper';
    }

    /**
     * Returns true if this language has parser enabled
     * @return boolean
     */
    public function could_parse() {
        return true;
    }
}
