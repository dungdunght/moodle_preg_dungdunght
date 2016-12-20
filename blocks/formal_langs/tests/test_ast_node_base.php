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
 * Defines unit-tests for ast_node_base class
 *
 * For a complete info, see block_formal_langs_token_base
 *
 * @copyright &copy; 2011  Oleg Sychev
 * @author Oleg Sychev, Dmitriy Mamontov, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

global $CFG;
require_once($CFG->dirroot.'/blocks/formal_langs/tokens_base.php');
require_once($CFG->dirroot.'/blocks/formal_langs/language_cpp_parseable_language.php');

 /**
  * This class contains the test cases for ast_node_base class
  */
class block_formal_langs_ast_node_base_test extends PHPUnit_Framework_TestCase {
    // Case, when a tokens are totally equal
    public function test() {
        $lang = new block_formal_langs_language_cpp_parseable_language();
        $string = $lang->create_from_string('int a = 2;');
        /** @var array $tree */
        $tree  = $string->syntax_tree(false);
        $this->assertTrue(count($tree) == 1);
        /** @var block_formal_langs_ast_node_base $node */
        $node = $tree[0];
        $vardecl = $node->children_of_type('variable_declaration');
        $this->assertTrue( count($vardecl) == 1);
        $identifier = $node->find_children_of_type('identifier');
        $identifier = $identifier[0];
        $this->assertTrue( $identifier->value() == 'a');
        $int = $node->left_leaf();
        $this->assertTrue( $int->value() == 'int');
        $semicolon = $node->right_leaf();
        $this->assertTrue( $semicolon->value() == ';');
    }

}
