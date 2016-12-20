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
 * Defines class for basic AST node
 *
 * @package    blocks
 * @subpackage formal_langs
 * @copyright &copy; 2011 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev, Mamontov Dmitriy, Maria Birukova
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/**
 * @class block_formal_langs_ast_node_base
 * A basic AST node
 */
class block_formal_langs_ast_node_base {

    /**
     * Type of node.
     * @var string
     */
    protected $type;

    /**
     * Node position - c.g. block_formal_langs_node_position object
     */
    protected $position;

    /**
     * Node number in a tree.
     * @var integer
     */
    protected $number;

    /**
     * Child nodes.
     * @var array of ast_node_base
     */
    public $children;

    /**
     * True if this node needs user-defined description
     * @var bool
     */
    protected $needuserdescription;

    /**
     * Node description.
     * @var string
     */
    protected $description;

    /**
     * A rule for generating node description
     * @var block_formal_langs_description_rule
     */
    public $rule;

    public function __construct($type, $position, $number, $needuserdescription) {
        $this->number = $number;
        $this->type = $type;
        $this->position = $position;
        $this->needuserdescription = $needuserdescription;

        $this->children = array();
        $this->description = '';
        $this->rule = null;
    }

    /**
     * Returns actual type of the token.
     *
     * Usually will be overloaded in child classes to return constant string.
     */
    public function type() {
        return $this->type;
    }

    public function number() {
        return $this->number;
    }

    /**
     * Returns position for tokem or a position for most left position
     * @return block_formal_langs_node_position
     */
    public function position() {
        if ($this->position == null) {
            if (count($this->children())) {
                $children = $this->children();
                /** @var block_formal_langs_ast_node_base $firstchild */
                $firstchild = $children[0];
                /** @var block_formal_langs_ast_node_base $lastchild */
                $lastchild  = $children[count($children) - 1];
                $firstchildpos = $firstchild->position();
                $lastchildpos = $lastchild->position();
                $this->position = new block_formal_langs_node_position(
                    $firstchildpos->linestart(),
                    $lastchildpos->lineend(),
                    $firstchildpos->colstart(),
                    $lastchildpos->colend(),
                    $firstchildpos->stringstart(),
                    $lastchildpos->stringend()
                );
            } else {
                $this->position = new block_formal_langs_node_position( 0, 0, 0, 0, 0, 0);
            }
        }
        return $this->position;
    }

    /**
     * Sets a position for base node
     * @param block_formal_langs_node_position $position a position
     */
    public function set_position($position) {
        $this->position = $position;
    }

    public function need_user_description() {
        return $this->needuserdescription;
    }

    public function description() {
        if (!$this->needuserdescription) {
            // TODO: calc description
            return $this->description;
        } else {
            return $this->description;
        }
    }

    public function set_description($str) {
        $this->description = $str;
    }

    /**
     * Returns list of children nodes
     * @return array
     */
    public function children() {
        return $this->children;
    }

    /**
     * Returns list of children of specified type
     * @param string $type type of child
     * @return array of block_formal_langs_ast_node_base
     */
    public function children_of_type($type) {
        if (count($this->children) == 0) {
            return array();
        }
        $result = array();
        foreach($this->children as $child) {
            /** @var block_formal_langs_ast_node_base $child */
            if ($child->type == $type || $type == '*') {
                $result[] = $child;
            }
        }
        return $result;
    }

    /**
     * Finds children of specified type amongst list of nodes
     * @param array $nodes  list of nodes
     * @param string $type a type of node
     * @return array of block_formal_langs_ast_node_base
     */
    public static function list_children_of_type($nodes, $type) {
        $result = array();
        if (count($nodes) != 0) {
            foreach($nodes as $node) {
                /** @var block_formal_langs_ast_node_base $node */
                $part = $node->children_of_type($type);
                if (count($result) == 0) {
                    $result = $part;
                } else {
                    if (count($part) != 0) {
                        $result = array_merge($result, $part);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Finds children of specified type, including indirect children
     * @param string $type type of child
     * @return array of block_formal_langs_ast_node_base
     */
    public function find_children_of_type($type) {
        $result = array();
        if (count($this->children)) {
            $scanrecursive = function($node) use(&$scanrecursive, $type) {
                /** @var block_formal_langs_ast_node_base $node */
                $result = array();
                if ($node->type == $type || $type == '*') {
                    $result[] = $node;
                }
                $children = $node->children();
                if (count($children)) {
                    foreach($children as $child) {
                        $part = $scanrecursive($child);
                        if (count($result) == 0) {
                            $result = $part;
                        } else {
                            if (count($part) != 0) {
                                $result = array_merge($result, $part);
                            }
                        }
                    }
                }
                return $result;
            };
            foreach($this->children as $child) {
                /** @var block_formal_langs_ast_node_base $child */
                $part = $scanrecursive($child);
                if (count($result) == 0) {
                    $result = $part;
                } else {
                    if (count($part) != 0) {
                        $result = array_merge($result, $part);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Finds children of specified type amongst list of nodes
     * @param array $nodes  list of nodes
     * @param string $type a type of node
     * @return array of block_formal_langs_ast_node_base
     */
    public static function list_find_children_of_type($nodes, $type) {
        $result = array();
        if (count($nodes) != 0) {
            foreach($nodes as $node) {
                /** @var block_formal_langs_ast_node_base $node */
                $part = $node->find_children_of_type($type);
                if (count($result) == 0) {
                    $result = $part;
                } else {
                    if (count($part) != 0) {
                        $result = array_merge($result, $part);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Returns list of most left children, which does not have a child
     * @param array $nodes  list of nodes
     * @return array of block_formal_langs_ast_node_base
     */
    public static function list_left_leaf($nodes) {
        $result = array();
        if (count($nodes) != 0) {
            foreach($nodes as $node) {
                /** @var block_formal_langs_ast_node_base $node */
                $result[] = $node->left_leaf();
            }
        }
        return $result;
    }

    /**
     * Returns most left child, which does not have a child
     * @return block_formal_langs_ast_node_base
     */
    public function left_leaf() {
        if (count($this->children) == 0) {
            return $this;
        }
        /** @var  block_formal_langs_ast_node_base $node */
        $node = $this->children[0];
        return $node->left_leaf();
    }

    /**
     * Returns list of most right children, which does not have a child
     * @param array $nodes  list of nodes
     * @return array of block_formal_langs_ast_node_base
     */
    public static function list_right_leaf($nodes) {
        $result = array();
        if (count($nodes) != 0) {
            foreach($nodes as $node) {
                /** @var block_formal_langs_ast_node_base $node */
                $result[] = $node->right_leaf();
            }
        }
        return $result;
    }

    /**
     * Returns most right child, which does not have a child
     * @return block_formal_langs_ast_node_base
     */
    public function right_leaf() {
        if (count($this->children) == 0) {
            return $this;
        }
        /** @var  block_formal_langs_ast_node_base $node */
        $node = $this->children[count($this->children) - 1];
        return $node->right_leaf();
    }

    /**
     * Sets list of children nodes
     * @param array $children list of children
     */
    public function set_children($children) {
        $this->children = $children;
    }

    public function add_child($node, $recomputeposition = true) {
        array_push($this->children, $node);
        if ($recomputeposition) {
            $this->position = null;
            $this->position();
        }
    }

    public function is_leaf() {
        if (0 == count($this->children)) {
            return true;
        }
        return false;
    }

    /**
     * Returns value for node
     * @return string value for text of node
     */
    public function value() {
        $values = array();
        foreach($this->children() as $child) {
            /** @var block_formal_langs_ast_node_base $child */
            $data = $child->value();
            if ($data != null) {
                if (is_object($data)) {
                    /** @var qtype_poasquestion\utf8_string $data */
                    $data = $data->string();
                }
                $values[] = $data;
            }
        }

        return implode(' ', $values);
    }

    /**
     * Returns list of tokens, covered by AST node. Tokens determined as not having any children
     * @return array list of tokens
     */
    public function tokens_list() {
        $childcount = count($this->children());
        $children = $this->children();
        $result = array();
        if (count($childcount) == 0 || $children === null || !is_array($children)) {
            $result[] = $this;
        } else {
            /** @var block_formal_langs_ast_node_base $child */
            foreach($children as $child) {
                $tmp = $child->tokens_list();
                if (count($result) == 0) {
                    $result = $tmp;
                } else {
                    $result = array_merge($result, $tmp);
                }
            }
        }
        return $result;
    }
}
