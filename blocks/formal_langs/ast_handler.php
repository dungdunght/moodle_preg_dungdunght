<?php
// This file is part of Correct Writing question type - https://bitbucket.org/oasychev/moodle-plugins/
//
// Correct Writing question type is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Correct Writing is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains definition of ast handler, which can go through items of tree
 *
 * @package    blocks
 * @copyright  2016 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev <oasychev@gmail.com>, Dmitry Mamontov <mamontov.dp@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

abstract class block_formal_langs_ast_handler  {
    /**
     * Visits array of parent nodes
     * @param array $nodes
     */
    public function visit_array($nodes) {
        if (count($nodes)) {
            foreach($nodes as $node) {
                $this->visit($node);
            }
        }
    }

    /**
     * Visits node, performing needed operations
     * @param block_formal_langs_ast_node_base $node
     * @return mixed
     */
    public function visit($node) {
        $children = $this->children($node);
        if (count($children)) {
            foreach($children as $child) {
                $this->visit($child);
            }
        }
    }

    /**
     * Returns children data
     * @param block_formal_langs_ast_node_base $node node data
     * @return mixed
     */
    public function children($node) {
        return $node->children();
    }
}