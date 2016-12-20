<?php
/**
 * Defines simplification tool.
 *
 * @copyright &copy; 2012 Oleg Sychev, Volgograd State Technical University
 * @author Terechov Grigory <grvlter@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_preg
 */

require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_authoring_tool.php');

/**
 * Options for simplification tool
 */
class qtype_preg_simplification_tool_options extends qtype_preg_handling_options {
    /**
     * @var bool Is need check quivalences hints
     */
    public $is_check_equivalences = true;

    /**
     * @var bool Is need check errors hints
     */
    public $is_check_errors = true;

    /**
     * @var bool Is need check tips hints
     */
    public $is_check_tips = true;

    /**
     * @var array List of problem subrees roots ids
     */
    public $problem_ids = array();

    /**
     * @var int Type of problem (hint class name)
     */
    public $problem_type = -2;

    /**
     * @var int Left index of problem subexpression in regex string
     */
    public $indfirst = -2;

    /**
     * @var int Right index of problem subexpression in regex string
     */
    public $indlast = -2;
}



class quantsui {
    public $first = '';
    public $second = '';
}


/**
 * Result after search problem situation in regex
 */
class qtype_preg_regex_hint_result {
    public $problem = '';
    public $solve = '';
    public $problem_ids = array();
    public $problem_type = -2;
    public $problem_indfirst = -2;
    public $problem_indlast = -2;
}


/**
 * Class qtype_preg_regex_hint Abstract class of regex hint
 */
abstract class qtype_preg_regex_hint {
    const TYPE_ERROR = 'error';
    const TYPE_TIP = 'tip';
    const TYPE_EQUIVALENT = 'equivalent';

    /**
     * @var Type of hint
     */
    public $type;

    /**
     * @var Tree for analization
     */
    public $tree;

    /**
     * @var Result after search problem situation in regex
     */
    public $regex_hint_result;

    public function __construct($tree) {
        $this->tree = $tree;
        $this->regex_hint_result = new qtype_preg_regex_hint_result();
    }

    /**
     * Search problem situation in regex
     */
    abstract public function check_hint();

    /**
     * Resolve problem situation in regex
     * @param $regex_hint_result Result after search problem situation in regex
     */
    abstract public function use_hint($regex_hint_result);

    /**
     * Whether the node is operator
     */
    protected function is_operator($node) {
        return !($node->type == qtype_preg_node::TYPE_LEAF_CHARSET
            || $node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            || $node->type == qtype_preg_node::TYPE_LEAF_META
            || $node->type == qtype_preg_node::TYPE_LEAF_BACKREF
            || $node->type == qtype_preg_node::TYPE_LEAF_SUBEXPR_CALL
            || $node->type == qtype_preg_node::TYPE_LEAF_TEMPLATE
            || $node->type == qtype_preg_node::TYPE_LEAF_CONTROL
            || $node->type == qtype_preg_node::TYPE_LEAF_OPTIONS
            || $node->type == qtype_preg_node::TYPE_LEAF_COMPLEX_ASSERT);
    }

    /**
     * Remove subtree in regex tree
     * @param $tree_root Root of regex tree
     * @param $node Current node of tree regex
     * @param $remove_node_id Id of root subtree for remove
     * @return bool
     */
    protected function remove_subtree($tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            if ($node->id == $tree_root->id) {
                $tree_root = null;
            }
            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->remove_subtree($tree_root, $operand, $remove_node_id)) {
                    if (count($node->operands) === 1) {
                        return $this->remove_subtree($tree_root, $tree_root, $node->id);
                    }

                    array_splice($node->operands, $i, 1);
                    if ($this->is_associative_commutative_operator($node) && count($node->operands) < 2) {
                        $node->operands[] = new qtype_preg_leaf_meta(qtype_preg_leaf_meta::SUBTYPE_EMPTY);
                    }

                    return false;
                }
            }
        }

        return false;
    }

    /**
     * Check node is associative commutative operator
     * @param $node Node for check
     * @return bool Is associative commutative operator
     */
    protected function is_associative_commutative_operator($node) {
        return $node->type == qtype_preg_node::TYPE_NODE_ALT;
    }

    /**
     * Get parent node from node id
     * @param $tree_root Root of tree for search parent node
     * @param $node_id Id of node for search him parent
     * @return null
     */
    protected function get_parent_node($tree_root, $node_id) {
        $local_root = null;
        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($operand->id == $node_id) {
                    return $tree_root;
                }
                $local_root = $this->get_parent_node($operand, $node_id);
                if ($local_root !== null) {
                    return $local_root;
                }
            }
        }
        return $local_root;
    }

    /**
     * Check included empty grouping node in empty grouping node.
     * @param $node Grouping node
     * @return bool Other grouping node was found
     */
    protected function check_other_grouping_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING) {
            if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                return true;
            } else {
                return $this->check_other_grouping_node($node->operands[0]);
            }
        }
        return false;
    }

    /**
     * Check backreference to subexpression.
     * @param $node Subtree root for search
     * @param $number Number of subexpr
     * @return bool Backreference was found
     */
    protected function check_backref_to_subexpr($node, $number) {
        if (($node->type == qtype_preg_node::TYPE_LEAF_BACKREF
                && $node->subtype == qtype_preg_node::TYPE_LEAF_BACKREF && $node->number == $number)
            || ($node->type == qtype_preg_node::TYPE_NODE_COND_SUBEXPR
                && $node->subtype == qtype_preg_node_cond_subexpr::SUBTYPE_SUBEXPR && $node->number == $number)) {
            return true;
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->check_backref_to_subexpr($operand, $number)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check included empty subpattern node in empty subpattern node.
     * @param $node Subtree root for search
     * @return bool Other subpattern was found
     */
    protected function check_other_subpattern_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                if (!$this->check_backref_to_subexpr($this->tree, $node->number)) {
                    return true;
                }
            } else {
                return $this->check_other_subpattern_node($node->operands[0]);
            }
        }
        return false;
    }

    /**
     * Rename backreferences for subpattern
     * @param $tree_root Root of tree
     * @param $node Node
     * @param int $subpattern_last_number Last number of searched subpattern
     */
    protected function rename_backreferences($tree_root, $node, &$subpattern_last_number = 0) {
        if ($node !== null) {
            if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
                ++$subpattern_last_number;
                $this->rename_backreferences_for_subpattern($tree_root, $node->number, $subpattern_last_number);
            }
            if ($this->is_operator($node)) {
                foreach ($node->operands as $operand) {
                    $this->rename_backreferences($tree_root, $operand, $subpattern_last_number);
                }
            }
        }
    }

    /**
     * Rename backreference
     * @param $node Subtree root for search
     * @param $old_number Old backreference number
     * @param $new_number New backreference number
     */
    protected function rename_backreferences_for_subpattern($node, $old_number, $new_number) {
        if ($node !== null) {
            if (($node->type == qtype_preg_node::TYPE_LEAF_BACKREF
                    && $node->subtype == qtype_preg_node::TYPE_LEAF_BACKREF && $node->number == $old_number)
                || ($node->type == qtype_preg_node::TYPE_NODE_COND_SUBEXPR
                    && $node->subtype == qtype_preg_node_cond_subexpr::SUBTYPE_SUBEXPR && $node->number == $old_number)
                || ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                    && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR && $node->number == $old_number)
            ) {
                $node->number = $new_number;
            }
            if ($this->is_operator($node)) {
                foreach ($node->operands as $operand) {
                    $this->rename_backreferences_for_subpattern($operand, $old_number, $new_number);
                }
            }
        }
    }

    /**
     * Check charset node with two and more same characters
     */
    protected function check_many_charset_node($node) {
        if (count($node->userinscription) > 2) {
            $symbol = $node->userinscription[1]->data;
            if (strlen($symbol) > 2) {
                return false;
            }
            if ($this->check_escaped_characters($symbol)) {
                return false;
            }
            for ($i = 2; $i < count($node->userinscription) - 1; ++$i) {
                if ($node->userinscription[$i]->data !== $symbol) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Check escaped characters
     */
    protected function check_escaped_characters($symbol) {
        return $symbol === '\\a'
        ||$symbol === '\\b' || $symbol === '\\e'
        || $symbol === '\\f' || $symbol === '\\n'
        || $symbol === '\\r' || $symbol === '\\t';
    }

    /**
     * Check non escaped characters
     */
    protected function non_escaped_characters($symbol) {
        if ($symbol === '\\z' || $symbol === '\\Z'
            /*|| $symbol === '\\a'*/ || $symbol === '\\A'
            /*|| $symbol === '\\b'*/ || $symbol === '\\B') {
            return $symbol[1];
        }
        return $symbol;
    }

    /**
     * Check empty node in alt
     */
    protected function check_empty_node_in_alt($alt) {
        foreach ($alt->operands as $operand) {
            if ($operand->type == qtype_preg_node::TYPE_LEAF_META
                && $operand->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                return true;
            }
        }
        return false;
    }

    /**
     * Remove quant node from tree
     */
    protected function remove_quant(&$tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $parent = $this->get_parent_node($tree_root, $node->id);

            if ($parent !== null) {
                foreach ($parent->operands as $i => $operand) {
                    if ($operand->id == $node->id) {
                        $node->operands[0]->position->indfirst = $node->position->indfirst;
                        $node->operands[0]->position->indlast = $node->position->indlast;
                        $parent->operands[$i] = $node->operands[0];
                        return true;
                    }
                }
            } else {
                $tree_root = $node->operands[0];
                return true;
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if($this->remove_quant($tree_root, $operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check space charsets
     */
    protected function check_space_charsets($charset_data) {
        return $charset_data === ' ' || $charset_data === '\s' || $charset_data === '[:space:]';
    }

    /**
     * Check space charset node
     */
    protected function check_space_charset_node($node) {
        if (count($node->userinscription) > 2) {
            foreach($node->userinscription as $ui) {
                if ($ui->data === '[' || $ui->data === ']') {
                    continue;
                }
                if (!$this->check_space_charsets($ui->data)) {
                    return false;
                }
            }

            return true;
        }
        return false;
    }

    /**
     * Get quant node for space charset
     */
    protected function get_quant_for_space_charset($node) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent != NULL) {
            if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                return $parent;
            } else if ($parent->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                && ($parent->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR
                    || $parent->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING)) {
                return $this->get_quant_for_space_charset($parent);
            }
        }
        return NULL;
    }

    /**
     * Return true if $node is first operand in tree
     */
    protected function check_first_operand($node, &$is_alternative = false) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent != null) {
            if ($parent->type == qtype_preg_node::TYPE_NODE_ALT) {
                $is_alternative = true;
            }
            $index = array_search($node, $parent->operands);
            if (($index === false || $index !== 0) && !$is_alternative) {
                return false;
            }
            return $this->check_first_operand($parent, $is_alternative);
        }

        return true;
    }

    /**
     * Return true if $node is last operand in tree
     */
    protected function check_last_operand($node, &$is_alternative = false) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent != null) {
            if ($parent->type == qtype_preg_node::TYPE_NODE_ALT) {
                $is_alternative = true;
            }
            $index = array_search($node, $parent->operands);
            if (($index === false || $index !== count($parent->operands) - 1) && !$is_alternative) {
                return false;
            }
            return $this->check_last_operand($parent, $is_alternative);
        } else {
            return true;
        }
    }

    /**
     * Get list max length with $size contains right nodes
     */
    protected function get_next_right_leafs($tree_root, $current_leaf, $size, &$is_found, &$leafs = null) {
        if ($current_leaf == NULL) {
            return array();
        }

        if ($leafs == NULL) {
            $leafs = array();
        }

        if ($current_leaf->id == $tree_root->id) {
            $is_found = false;
            $leafs = $this->get_right_leafs($this->get_parent_node($this->tree, $tree_root->id), $current_leaf, $size, $is_found, $leafs);
            return $leafs;
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                $this->get_next_right_leafs($operand, $current_leaf, $size, $is_found, $leafs);
            }
        }

        return $leafs;
    }

    /**
     * Get list max length with $size contains right nodes begin $current_leaf
     */
    protected function get_right_leafs($tree_root, $current_leaf, $size, &$is_found, &$leafs = null) {
        if ($current_leaf == NULL) {
            return array();
        }
        if ($leafs == NULL) {
            $leafs = array();
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($current_leaf->id == $operand->id) {
                    $is_found = true;
                }

                if ($is_found && count($leafs) < $size) {
                    array_push($leafs, $operand);
                    if (count($leafs) >= $size) {
                        return $leafs;
                    }
                }

                $this->get_right_leafs($operand, $current_leaf, $size, $is_found, $leafs);
            }
        }

        return $leafs;
    }
}


/**
 * Repeated assertion hint
 */
class qtype_preg_regex_hint_repeated_assertions extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    /**
     * Check repeated assertions.
     */
    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_1', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_1', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search repeated assertions in tree.
     */
    protected function search($node, &$is_find_assert = false) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            && ($node->subtype == qtype_preg_leaf_assert::SUBTYPE_CIRCUMFLEX
                || $node->subtype == qtype_preg_leaf_assert::SUBTYPE_DOLLAR
                || $node->subtype == qtype_preg_leaf_assert::SUBTYPE_CAPITAL_ESC_Z
                || $node->subtype == qtype_preg_leaf_assert::SUBTYPE_ESC_A
                || $node->subtype == qtype_preg_leaf_assert::SUBTYPE_SMALL_ESC_Z)) {
            if ($is_find_assert == true) {
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_repeated_assertions';
                $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                $this->regex_hint_result->problem_indlast = $node->position->indlast;
                return true;
            } else {
                $is_find_assert = true;
            }
        } else {
            $is_find_assert = false;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand, $is_find_assert)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_subtree($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }
}


/**
 * Empty and useless grouping node hint
 */
class qtype_preg_regex_hint_grouping_node extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    /**
     * Check empty grouping node.
     */
    public function check_hint() {
        $this->search($this->tree);
        return $this->regex_hint_result;
    }

    /**
     * Search repeated assertions assertions in tree.
     */
    private function search($node) {
        if ($node !== null) {
            if ($this->search_not_empty_grouping_node($node)) {
                return true;
            }
            return $this->search_empty_grouping_node($node);
        }
        return false;
    }

    /**
     * Search empty grouping node
     */
    private function search_empty_grouping_node($node) {
        if ($node !== null) {
            if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING
            ) {
                if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY
                ) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_grouping_node';
                    $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_2', 'qtype_preg'));
                    $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_2', 'qtype_preg'));
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
            if ($this->is_operator($node)) {
                foreach ($node->operands as $operand) {
                    if ($this->search_empty_grouping_node($operand)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Search useless (not empty) grouping node
     */
    private function search_not_empty_grouping_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING) {
            $parent = $this->get_parent_node($this->tree, $node->id);
            if ($parent !== null) {
                $group_operand = $node->operands[0];
                if ($parent->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    && $parent->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    && $group_operand->type != qtype_preg_node::TYPE_LEAF_META
                    && $group_operand->subtype != qtype_preg_leaf_meta::SUBTYPE_EMPTY
                    && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT) {

                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_grouping_node';
                    $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_2_1', 'qtype_preg'));
                    $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_2_1', 'qtype_preg'));
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                } else if (($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                    && $group_operand->type != qtype_preg_node::TYPE_NODE_CONCAT
                    && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                    && $group_operand->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    && $group_operand->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_grouping_node';
                    $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_2_1', 'qtype_preg'));
                    $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_2_1', 'qtype_preg'));
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            } else {
                if ($node->position != NULL) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_grouping_node';
                    $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_2_1', 'qtype_preg'));
                    $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_2_1', 'qtype_preg'));
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search_not_empty_grouping_node($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_grouping_node($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    /**
     * Remove grouping node from tree
     */
    protected function remove_grouping_node(&$tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $parent = $this->get_parent_node($tree_root, $node->id);
            if ($parent !== null) {
                $group_operand = $node->operands[0];
                if ($this->check_included_empty_grouping($node)) {
                    if (count($parent->operands) === 1) {
                        return $this->remove_subtree($tree_root, $tree_root, $parent->id);
                    }
                    foreach ($parent->operands as $i => $operand) {
                        if ($operand->id == $remove_node_id) {
                            array_splice($parent->operands, $i, 1);
                            if ($this->is_associative_commutative_operator($parent) && count($parent->operands) < 2) {
                                $parent->operands[] = new qtype_preg_leaf_meta(qtype_preg_leaf_meta::SUBTYPE_EMPTY);
                            }

                            return true;
                        }
                    }
                } else {
//                    if ($parent->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
//                        && $parent->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
//                    ) {
                    foreach ($parent->operands as $i => $operand) {
                        if ($operand->id == $node->id) {
                            if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                            ) {
                                $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                    $group_operand->operands,
                                    array_slice($parent->operands, $i + 1));
                            } else {
                                $parent->operands[$i] = $group_operand;
                            }
                            return true;
                        }
                    }
                    //}
                }
            } else {
                // TODO: fix this
                if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    $tree_root = null;
                } else if ($this->check_other_grouping_node($node->operands[0])) {
                    $tree_root = null;
                } else {
                    $tree_root = $node->operands[0];
                }
                return true;
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if($this->remove_grouping_node($tree_root, $operand, $remove_node_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return true if grouping node $node contain other grouping node
     */
    protected function check_included_empty_grouping($node) {
        $group_operand = $node->operands[0];
        if ($group_operand->type == qtype_preg_node::TYPE_LEAF_META
            && $group_operand->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
            return true;
        } else if ($group_operand->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $group_operand->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING) {
            return $this->check_included_empty_grouping($group_operand);
        }
        return false;
    }
}


/**
 * Empty and useless subpattern node hint
 */
class qtype_preg_regex_hint_subpattern_node extends qtype_preg_regex_hint {
    // TODO
    public $deleted_subpattern_positions = array();

    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        $this->search($this->tree);
        return $this->regex_hint_result;
    }

    /**
     * Search empty subpattern node.
     */
    private function search($node) {
        if ($node !== null) {
            if ($this->search_not_empty_subpattern_node($node)) {
                return true;
            }
            return $this->search_empty_subpattern_node($node);
        }
        return false;
    }

    /**
     * Search empty subpattern_node
     */
    private function search_empty_subpattern_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            if (!$this->check_backref_to_subexpr($this->tree, $node->number)) {
                if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY
                ) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_subpattern_node';
                    $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_3', 'qtype_preg'));
                    $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_3', 'qtype_preg'));
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search_empty_subpattern_node($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Search useless (not empty) subpattern node
     */
    private function search_not_empty_subpattern_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            if (!$this->check_backref_to_subexpr($this->tree, $node->number)) {
                $parent = $this->get_parent_node($this->tree, $node->id);
                if ($parent !== null) {
                    $group_operand = $node->operands[0];
                    if ($parent->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $parent->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_LEAF_META
                        && $group_operand->subtype != qtype_preg_leaf_meta::SUBTYPE_EMPTY
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                    ) {
                        $this->regex_hint_result->problem_ids[] = $node->id;
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_subpattern_node';
                        $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_3_1', 'qtype_preg'));
                        $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_3_1', 'qtype_preg'));
                        $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                        $this->regex_hint_result->problem_indlast = $node->position->indlast;
                        return true;
                    } else if (($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                            || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_CONCAT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    ) {
                        $this->regex_hint_result->problem_ids[] = $node->id;
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_subpattern_node';
                        $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_3_1', 'qtype_preg'));
                        $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_3_1', 'qtype_preg'));
                        $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                        $this->regex_hint_result->problem_indlast = $node->position->indlast;
                        return true;
                    }
                } else {
                    if ($node->position != NULL) {
                        $this->regex_hint_result->problem_ids[] = $node->id;
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_subpattern_node';
                        $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_3_1', 'qtype_preg'));
                        $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_3_1', 'qtype_preg'));
                        $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                        $this->regex_hint_result->problem_indlast = $node->position->indlast;
                        return true;
                    }
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search_not_empty_subpattern_node($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_subpattern_node($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        $this->rename_backreferences($this->tree, $this->tree);
        return $this->tree;
    }

    /**
     * Remove subpattern node from tree
     */
    protected function remove_subpattern_node(&$tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $parent = $this->get_parent_node($tree_root, $node->id);
            if ($parent !== null) {
                $group_operand = $node->operands[0];
                if ($group_operand->type === qtype_preg_node::TYPE_NODE_CONCAT) {
                    /*if ($group_operand->operands[0]->type === qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        || $group_operand->operands[0]->type === qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                        $group_operand->operands[0]->position->indfirst = $node->position->indfirst;
                        $group_operand->operands[0]->position->indlast = $node->position->indlast;
                        $group_operand->operands[0]->operands[0]->position->indfirst = $node->position->indfirst;
                        $group_operand->operands[0]->operands[0]->position->indlast = $node->position->indlast;
                    } else {
                        $group_operand->operands[0]->position->indfirst = $node->position->indfirst;
                        $group_operand->operands[0]->position->indlast = $node->position->indlast;
                    }*/
                    $this->deleted_subpattern_positions[] = array($node->position->indfirst, $node->position->indlast);
                } else if ($group_operand->type === qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $group_operand->type === qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                    $this->deleted_subpattern_positions[] = array($node->position->indfirst, $node->position->indlast);

                    /*$group_operand->position->indfirst = $node->position->indfirst;
                    $group_operand->position->indlast = $node->position->indlast;
                    $group_operand->operands[0]->position->indfirst = $node->position->indfirst;
                    $group_operand->operands[0]->position->indlast = $node->position->indlast;*/
                } else {
                    $this->deleted_subpattern_positions[] = array($node->position->indfirst, $node->position->indlast);
                    $group_operand->position->indfirst = $node->position->indfirst;
                    $group_operand->position->indlast = $node->position->indlast;
                }

                if ($this->check_included_empty_subpattern($node)) {
                    if (count($parent->operands) === 1) {
                        return $this->remove_subtree($tree_root, $tree_root, $parent->id);
                    }
                    foreach ($parent->operands as $i => $operand) {
                        if ($operand->id == $remove_node_id) {
                            array_splice($parent->operands, $i, 1);
                            if ($this->is_associative_commutative_operator($parent) && count($parent->operands) < 2) {
                                $parent->operands[] = new qtype_preg_leaf_meta(qtype_preg_leaf_meta::SUBTYPE_EMPTY);
                            }

                            return true;
                        }
                    }
                } else {
                    foreach ($parent->operands as $i => $operand) {
                        if ($operand->id == $node->id) {
                            if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                            ) {
                                $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                    $group_operand->operands,
                                    array_slice($parent->operands, $i + 1));
                            } else {
                                $parent->operands[$i] = $group_operand;
                            }
                            return true;
                            break;
                        }
                    }
                }
            } else {
                $this->deleted_subpattern_positions[] = array($node->position->indfirst, $node->position->indlast);
                // TODO: fix this
                if ($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    $tree_root = null;
                } else if ($this->check_other_subpattern_node($node->operands[0])) {
                    $tree_root = null;
                } else {
                    $tree_root = $node->operands[0];
                }
                return true;
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if($this->remove_subpattern_node($tree_root, $operand, $remove_node_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Return true if subpattern node $node contain other subpattern node
     */
    protected function check_included_empty_subpattern($node) {
        $group_operand = $node->operands[0];
        if ($group_operand->type == qtype_preg_node::TYPE_LEAF_META
            && $group_operand->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
            return true;
        } else if ($group_operand->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $group_operand->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            return $this->check_included_empty_subpattern($group_operand);
        }
        return false;
    }
}


/**
 * Single charset node with square brackets hint
 */
class qtype_preg_regex_hint_single_charset_node extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_5', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_5', 'qtype_preg'));
        }
        return $this->regex_hint_result;
    }

    /**
     * Search charset node with one character.
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_CHARSET && $node->subtype == NULL) {
            if (!$node->negative && count($node->userinscription) > 1) {
                if ($node->is_single_character()) {
                    $symbol = $node->userinscription[1]->data;
                    if (!$this->check_escaped_characters($symbol)) {
                        $this->regex_hint_result->problem_ids[] = $node->id;
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_single_charset_node';
                        $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                        $this->regex_hint_result->problem_indlast = $node->position->indlast;
                        return true;
                    }
                } else if ($this->check_many_charset_node($node)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_single_charset_node';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_square_brackets_from_charset($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    /**
     * Remove square brackets from charset
     */
    private function remove_square_brackets_from_charset($tree_root, $remove_node_id) {
        if ($tree_root->id == $remove_node_id) {
            if (count($tree_root->userinscription) > 1) {
                $tmp = $tree_root->userinscription[1];
                $tmp->data = $this->escape_character_for_single_charset($tmp->data);
                $tmp->data = $this->non_escaped_characters($tmp->data);
                $tmp->data = $this->characters_interval_for_single_charset($tmp->data);
                $tree_root->userinscription = array($tmp);
                $tree_root->flags[0][0]->data = new qtype_poasquestion\string($tmp->data);
                $tree_root->subtype = "enumerable_characters";
                return true;
            }
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($this->remove_square_brackets_from_charset($operand, $remove_node_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check escape character for single charset
     */
    private function escape_character_for_single_charset($character) {
        if ($character === '\\' || $character === '^' || $character === '$'
            || $character === '.' || $character === '[' || $character === ']'
            || $character === '|' || $character === '(' || $character === ')'
            || $character === '?' || $character === '*' || $character === '+'
            || $character === '{' || $character === '}') {
            return '\\' . $character;
        }
        return $character;
    }

    /**
     * Check characters interval for single charset
     */
    private function characters_interval_for_single_charset($character) {
        if (strrpos($character, '-') > -1) {
            return preg_split('/-/', $character)[0];
        }
        return $character;
    }
}


/**
 * Alternative to charset hint
 */
class qtype_preg_regex_hint_single_alternative_node extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_6', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_6', 'qtype_preg'));
        }
        return $this->regex_hint_result;
    }

    /**
     * Search alternative node with only charsets operands with one character
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if ($this->is_single_alternative($node)) {
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_single_alternative_node';
                return true;
            } else {
                $this->regex_hint_result->problem_indfirst = -2;
                $this->regex_hint_result->problem_indlast = -2;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check found alternative node with only charsets operands with one character
     */
    private function is_single_alternative($node) {
        $repeats_count = 0;
        foreach ($node->operands as $i => $operand) {
            if ($operand->type == qtype_preg_node::TYPE_LEAF_CHARSET && !$operand->negative
                && $operand->userinscription[0]->data != '.') {

                $repeats_count++;
                if ($this->regex_hint_result->problem_indfirst == -2) {
                    $this->regex_hint_result->problem_indfirst = $operand->position->indfirst;
                }
                $this->regex_hint_result->problem_indlast = $operand->position->indlast;
            }
        }
        return $repeats_count > 1;
    }

    public function use_hint($regex_hint_result) {
        $this->change_alternative_to_charset($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function change_alternative_to_charset(&$tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $uicharacters = array();
            $uicharacters[] = new qtype_preg_userinscription('[', null);
            $characters = '';
            $count = 0;

            $alt = new qtype_preg_node_alt();

            foreach ($node->operands as $operand) {
                if ($operand->type == qtype_preg_node::TYPE_LEAF_CHARSET && !$operand->negative
                    && $operand->userinscription[0]->data != '.') {
                    $count++;
                    if (count($operand->userinscription) === 1) {
                        $uicharacters[] = new qtype_preg_userinscription($this->escape_character_for_charset($operand->userinscription[0]->data), null);
                        $characters .= $this->escape_character_for_charset($operand->userinscription[0]->data);
                    } else {
                        for ($i = 1; $i < count($operand->userinscription) - 1; ++$i) {
                            $uicharacters[] = new qtype_preg_userinscription($this->escape_character_for_charset($operand->userinscription[$i]->data), null);
                            $characters .= $this->escape_character_for_charset($operand->userinscription[$i]->data);
                        }
                    }
                } else {
                    $alt->operands[] = $operand;
                }
            }

            $uicharacters[] = new qtype_preg_userinscription(']', null);

            $new_node = new qtype_preg_leaf_charset();
            $new_node->set_user_info(null, $uicharacters);
            $new_node->id = $remove_node_id; //$this->parser->get_max_id() + 1;
            //$this->parser->set_max_id($new_node->id + 1);

            if ($characters !== null) {
                $flag = new qtype_preg_charset_flag;
                $flag->negative = false;
//                $characters = new qtype_poasquestion\string($characters);
                $flag->set_data(qtype_preg_charset_flag::TYPE_SET, new qtype_poasquestion\string($characters));
                $new_node->flags = array(array($flag));
            }

            $new_node->position = new qtype_preg_position($node->position->indfirst, $node->position->indlast, null, null, null, null);
            $alt->position = new qtype_preg_position($node->position->indfirst, $node->position->indlast, null, null, null, null);


            if ($count == count($node->operands)) {
                if ($node->id == $tree_root->id) {
                    $tree_root = $new_node;
                } else {
                    $local_root = $this->get_parent_node($tree_root, $node->id);

                    if ($local_root !== NULL) {
                        foreach ($local_root->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                $local_root->operands[$i] = $new_node;
                                break;
                            }
                        }
                    }
                }
            } else {
                array_unshift($alt->operands, $new_node);

                $local_root = $this->get_parent_node($tree_root, $node->id);

                if ($local_root === NULL) {
                    $tree_root = $alt;
                } else {
                    foreach ($local_root->operands as $i => $operand) {
                        if ($operand->id == $node->id) {
                            $local_root->operands[$i] = $alt;
                            break;
                        }
                    }
                }
            }

            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->change_alternative_to_charset($tree_root, $operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function escape_character_for_charset($character) {
        if ($character === '-' || $character === ']' || $character === '{' || $character === '}') {
            return '\\' . $character;
        }
        return $character;
    }
}


/**
 * Replace quant to equivalence short quant hint
 */
class qtype_preg_regex_hint_quant_node extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        $quantsui = new quantsui();
        if ($this->search($this->tree, $quantsui)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_11', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_11', 'qtype_preg', $quantsui));
        }
        return $this->regex_hint_result;
    }

    /**
     * Search quantifier node who can convert to short quantifier
     */
    private function search($node, $quantsui) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            if ($this->is_simple_quant_node($node)) {
                $quantsui->first = $node->userinscription[0]->data;
                $quantsui->second = $this->get_eq_quant_ui($node);
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_quant_node';
                $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                $this->regex_hint_result->problem_indlast = $node->position->indlast;
                return true;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand, $quantsui)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check found quantifier node who can convert to short quantifier
     */
    private function is_simple_quant_node($node) {
        if ($node->greedy) {
            if ($node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                return (($node->leftborder === 0 && $node->userinscription[0]->data !== '*')
                    || ($node->leftborder === 1 && $node->userinscription[0]->data !== '+'));
            } else if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
                return ($node->leftborder === 0 && $node->rightborder === 1 && $node->userinscription[0]->data !== '?');
            }
        }
        return false;
    }

    /**
     *
     */
    private function get_eq_quant_ui($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            if ($node->leftborder === 0 && $node->userinscription[0]->data !== '*') {
                return '*';
            } else if ($node->leftborder === 1 && $node->userinscription[0]->data !== '+') {
                return '+';
            }
        } else if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
            if ($node->leftborder === 0 && $node->rightborder === 1 && $node->userinscription[0]->data !== '?') {
                return '?';
            }
        }
    }

    public function use_hint($regex_hint_result) {
        $this->change_quant_to_equivalent($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function change_quant_to_equivalent(&$tree_root, $remove_node_id) {
        if ($tree_root->id == $remove_node_id) {
            if ($tree_root->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                if ($tree_root->leftborder === 0 && $tree_root->userinscription[0]->data !== '*') {
                    $tmp = $tree_root->userinscription[0];
                    $tree_root->userinscription = array($tmp);
                    $tree_root->userinscription[0]->data = new qtype_poasquestion\string('*');
                    return true;
                } else if ($tree_root->leftborder === 1 && $tree_root->userinscription[0]->data !== '+') {
                    $tmp = $tree_root->userinscription[0];
                    $tree_root->userinscription = array($tmp);
                    $tree_root->userinscription[0]->data = new qtype_poasquestion\string('+');
                    if ($tree_root->operands[0]->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                        || $tree_root->operands[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
                        $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                        $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));
                        $se->operands[] = $tree_root->operands[0];
                        $tree_root->operands[0] = $se;
                    }
                    return true;
                }
            } else if ($tree_root->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
                if ($tree_root->leftborder === 0 && $tree_root->rightborder === 1 && $tree_root->userinscription[0]->data !== '?') {
                    $tmp = $tree_root->userinscription[0];
                    $tree_root->userinscription = array($tmp);
                    $tree_root->userinscription[0]->data = new qtype_poasquestion\string('?');
                    if ($tree_root->operands[0]->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                        || $tree_root->operands[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
                        $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                        $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));
                        $se->operands[] = $tree_root->operands[0];
                        $tree_root->operands[0] = $se;
                    }
                    return true;
                }
            }
            return false;
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($this->change_quant_to_equivalent($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}


/**
 * Alternative with emptiness without quesiont quant hint
 */
class qtype_preg_regex_hint_alt_without_question_quant extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    /**
     * Check repeated assertions.
     */
    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_8', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_8', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Check alternative node with empty operand without question quant
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if ($this->check_empty_node_in_alt($node)) {
                if (!$this->check_quant_with_zero_left_border_for_node($node)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;

                    $alt_empty = false;
                    foreach ($node->operands as $tmp_operand) {
                        if ($tmp_operand->nullable
                            && $tmp_operand->type != qtype_preg_leaf::TYPE_LEAF_META
                            && $tmp_operand->type != qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                            $alt_empty = true;
                            break;
                        }
                    }
                    if (!$alt_empty) {
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_alt_without_question_quant';
                    }
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_quant_with_zero_left_border_for_node($node) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent == null) {
            return false;
        } else if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            if ($parent->leftborder == 0) {
                return true;
            }
        }

        return $this->check_quant_with_zero_left_border_for_node($parent);
    }

    public function use_hint($regex_hint_result) {
        if ($regex_hint_result->problem_type === 'qtype_preg_regex_hint_alt_without_question_quant') {
            $this->add_question_quant_to_alt($this->tree, $regex_hint_result->problem_ids[0]);
        } else if ($regex_hint_result->problem_type === 13) {
            $this->remove_empty_node_from_alternative($this->tree, $regex_hint_result->problem_ids[0]);
        }
        return $this->tree;
    }

    protected function add_question_quant_to_alt(&$tree_root, $remove_node_id) {
        if ($tree_root->id == $remove_node_id) {
            $this->delete_empty_node_from_alternative($tree_root);

            $qu = new qtype_preg_node_finite_quant(0, 1);
            $qu->set_user_info(null, array(new qtype_preg_userinscription('?')));

            $parent = $this->get_parent_node($this->tree, $tree_root->id);
            if ($parent == null) {
                $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));
                $se->operands[] = $tree_root;
                $qu->operands[] = $se;

                $this->tree = $qu;
            } else if ($parent->type == qtype_preg_node::TYPE_NODE_SUBEXPR) {
                $new_parent = $this->get_parent_node($this->tree, $parent->id);

                if ($new_parent == null) {
                    $qu->operands[] = $parent;
                    $this->tree = $qu;
                } else if ($new_parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    || $new_parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                ) {
                    $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                    $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));
                    $se->operands[] = $tree_root;
                    $qu->operands[] = $se;

                    $parent->operands[0] = $qu;
                } else {
                    $qu->operands[] = $parent;

                    foreach ($new_parent->operands as $i => $operand) {
                        if ($parent->id == $operand->id) {
                            $new_parent->operands[$i] = $qu;
                            break;
                        }
                    }
                }
            } else {
                $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));
                $se->operands[] = $tree_root;
                $qu->operands[] = $se;

                foreach ($parent->operands as $i => $operand) {
                    if ($tree_root->id == $operand->id) {
                        $parent->operands[$i] = $qu;
                        break;
                    }
                }
            }
            return true;
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($this->add_question_quant_to_alt($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function delete_empty_node_from_alternative($node) {
        if ($this->check_empty_node_in_alt($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($operand->type == qtype_preg_node::TYPE_LEAF_META
                    && $operand->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    $node->operands = array_merge(array_slice($node->operands, 0, $i), array_slice($node->operands, $i + 1));
                    $this->delete_empty_node_from_alternative($node);
                }
            }
        }
        return true;
    }
}


/**
 * Alternative coinciding with emptiness without quesiont quant hint
 */
class qtype_preg_regex_hint_nullable_alt_without_question_quant extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    /**
     * Check repeated assertions.
     */
    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_13', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_13', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Check alternative node with empty operand without question quant
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if ($this->check_empty_node_in_alt($node)) {
                if (!$this->check_quant_with_zero_left_border_for_node($node)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;

                    $alt_empty = false;
                    foreach ($node->operands as $tmp_operand) {
                        if ($tmp_operand->nullable
                            && $tmp_operand->type != qtype_preg_leaf::TYPE_LEAF_META
                            && $tmp_operand->type != qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                            $alt_empty = true;
                            break;
                        }
                    }
                    if ($alt_empty) {
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_nullable_alt_without_question_quant';
                    }
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_quant_with_zero_left_border_for_node($node) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent == null) {
            return false;
        } else if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            if ($parent->leftborder == 0) {
                return true;
            }
        }

        return $this->check_quant_with_zero_left_border_for_node($parent);
    }

    public function use_hint($regex_hint_result) {
        $this->remove_empty_node_from_alternative($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    protected function remove_empty_node_from_alternative($node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            return $this->delete_empty_node_from_alternative($node);
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if($this->remove_empty_node_from_alternative($operand, $remove_node_id)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function delete_empty_node_from_alternative($node) {
        if ($this->check_empty_node_in_alt($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($operand->type == qtype_preg_node::TYPE_LEAF_META
                    && $operand->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    $node->operands = array_merge(array_slice($node->operands, 0, $i), array_slice($node->operands, $i + 1));
                    $this->delete_empty_node_from_alternative($node);
                }
            }
        }
        return true;
    }
}


/**
 * Alternative coinciding with emptiness with quesiont quant hint
 */
class qtype_preg_regex_hint_alt_with_question_quant extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    /**
     * Check repeated assertions.
     */
    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_9', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_9', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Check alternative node without empty operand with question quant
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if (!$this->check_empty_node_in_alt($node)) {
                if ($this->get_question_quant_for_node($node) != null) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_alt_with_question_quant';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_question_quant_for_alt($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function remove_question_quant_for_alt(&$tree_root, $node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            // Search quant
            $qu = $this->get_question_quant_for_node($node);
            // Remove quant
            $this->remove_quant($tree_root, $tree_root, $qu->id);
            if ($node->nullable === false) {
                // Add empty node to alt
                $mn = new qtype_preg_leaf_meta(qtype_preg_leaf_meta::SUBTYPE_EMPTY);
//                $mn->position = new qtype_preg_position($node->position->indfirst, $node->position->indlast, null, null, null, null);
                $node->operands[] = $mn;
            }
            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->remove_question_quant_for_alt($tree_root, $operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function get_question_quant_for_node($node) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent == null) {
            return null;
        } else if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            && $parent->leftborder == 0 && $parent->rightborder == 1) {
            return $parent;
        } else if ($parent->type != qtype_preg_node::TYPE_NODE_SUBEXPR) {
            return null;
        }

        return $this->get_question_quant_for_node($parent);
    }
}


/**
 * Useless quant {1} or {1,1} hint
 */
class qtype_preg_regex_hint_quant_node_1_to_1 extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_14', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_14', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search quantifier node from 1 to 1
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            && $node->leftborder === 1 && $node->rightborder === 1) {
            $this->regex_hint_result->problem_ids[] = $node->id;
            $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_quant_node_1_to_1';
            $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
            $this->regex_hint_result->problem_indlast = $node->position->indlast;
            return true;
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_quant($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }
}


/**
 * Alternative coinciding with emptiness without quesiont quant hint
 */
class qtype_preg_regex_hint_question_quant_for_alternative_node extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_12', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_12', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search question quantifier for alternative node
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            && $node->leftborder === 0 && $node->rightborder === 1) {
            if ($this->check_alternative_node_for_question_quant($node->operands[0])) {
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_question_quant_for_alternative_node';
                $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                $this->regex_hint_result->problem_indlast = $node->position->indlast;
                return true;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_alternative_node_for_question_quant($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if ($this->check_empty_node_in_alt($node)) {
                return true;
            }
        } else if (!($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR)) {
            return false;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->check_alternative_node_for_question_quant($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_quant($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }
}


/**
 * Replace two quants to equivalence one quant hint
 */
class qtype_preg_regex_hint_consecutive_quant_nodes extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_10', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_10', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search consecutive quantifier nodes
     */
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {

            if ($this->check_other_quant_for_quant($node->operands[0])) {
                $oq = $this->get_other_quant_for_quant($node->operands[0]);

                if ($oq != null && $this->check_quants_borders($oq, $node)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_consecutive_quant_nodes';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_other_quant_for_quant($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            return true;
        } else if (!($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR)) {
            return false;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->check_other_quant_for_quant($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_quants_borders($left_quant, $right_quant) {
        if ($left_quant->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
            || $right_quant->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            return true;
        }
        return !($left_quant->leftborder === $left_quant->rightborder && $right_quant->leftborder !== $right_quant->rightborder
            && $left_quant->leftborder !== 0 && $left_quant->leftborder !== 1);
    }

    public function use_hint($regex_hint_result) {
        $this->change_consecutive_quants($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function change_consecutive_quants($tree_root, $remove_node_id) {
        if ($tree_root->id == $remove_node_id) {
            $oq = $this->get_other_quant_for_quant($tree_root->operands[0]);

            $text = '';
            if ($tree_root->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                && $tree_root->leftborder === 0 && $tree_root->rightborder === 0) {
                $text = '{0}';
            } else if ($oq->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                && $oq->leftborder === 0 && $oq->rightborder === 0) {
                $text = '{0}';
            } else {
                //$leftborder = ($tree_root->leftborder < $oq->leftborder) ? $tree_root->leftborder : $oq->leftborder;
                $leftborder = $tree_root->leftborder * $oq->leftborder;
                $rightborder = 0;

                $infinite = false;
                if ($tree_root->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    || $oq->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                ) {
                    $infinite = true;
                } else {
                    //$rightborder = ($tree_root->rightborder > $oq->rightborder) ? $tree_root->rightborder : $oq->rightborder;
                    $rightborder = $tree_root->rightborder * $oq->rightborder;
                }

                $oq->leftborder = $leftborder;
                if ($infinite) {
                    $oq->rightborder = -999;
                    if ($leftborder === 0) {
                        $oq->type = qtype_preg_node::TYPE_NODE_INFINITE_QUANT;
                        $text = '*';
                    } else if ($leftborder === 1) {
                        $oq->type = qtype_preg_node::TYPE_NODE_INFINITE_QUANT;
                        $text = '+';
                    } else {
                        $oq->type = qtype_preg_node::TYPE_NODE_FINITE_QUANT;
                        $text = '{' . $leftborder . ',}';
                    }
                } else {
                    $oq->rightborder = $rightborder;
                    if ($leftborder === 0 && $rightborder === 1) {
                        $oq->type = qtype_preg_node::TYPE_NODE_FINITE_QUANT;
                        $text = '?';
                    } else if ($leftborder === $rightborder) {
                        $oq->type = qtype_preg_node::TYPE_NODE_FINITE_QUANT;
                        $text = '{' . $leftborder . '}';
                    } else {
                        $oq->type = qtype_preg_node::TYPE_NODE_FINITE_QUANT;
                        $text = '{' . $leftborder . ',' . $rightborder . '}';
                    }
                }
            }

            $oq->set_user_info(null, array(new qtype_preg_userinscription($text)));
            $oq->position = new qtype_preg_position($tree_root->position->indfirst, $tree_root->position->indlast, null, null, null, null);
            $tree_root->operands[0]->position = new qtype_preg_position($tree_root->position->indfirst, $tree_root->position->indlast, null, null, null, null);

            $parenttr = $this->get_parent_node($this->tree, $tree_root->id);

            if ($parenttr != null) {
                foreach ($parenttr->operands as $i => $operand) {
                    if ($operand->id == $tree_root->id) {
                        $parenttr->operands[$i] = $tree_root->operands[0];
                    }
                }
            } else {
                $this->tree = $tree_root->operands[0];
            }

            return true;
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($this->change_consecutive_quants($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function get_other_quant_for_quant($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
            || $node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
            return $node;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                return $this->get_other_quant_for_quant($operand);
            }
        }

        return null;
    }
}


/**
 * Replace space charset to \s hint
 */
class qtype_preg_regex_hint_space_charset extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_1', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_1', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_CHARSET) {
            if (($node->is_single_character() && $node->userinscription[0]->data === ' ')
                || ($this->check_many_charset_node($node) && $node->userinscription[1]->data === ' ')
                /*&& !$node->negative*/) {
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_space_charset';
                $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                $this->regex_hint_result->problem_indlast = $node->position->indlast;
                return true;
            } else {
                $is_contain_space_char = false;
                $is_contain_slash_s = false;
                foreach($node->userinscription as $ui) {
                    if ($ui->data === ' ') {
                        $is_contain_space_char = true;
                    } else if ($ui->data === '\\s' || $ui->data === '[:space:]') {
                        $is_contain_slash_s = true;
                    }
                }

                if ($is_contain_space_char && !$is_contain_slash_s) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_space_charset';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->change_space_to_charset_s($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function change_space_to_charset_s($node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            if ($node->is_single_character()) {
                if (count($node->userinscription) == 1) {
                    $node->userinscription[0]->data = '\s';
                } else {
                    $node->userinscription[1]->data = '\s';
                }
            } else {
                foreach ($node->userinscription as $i => $ui) {
                    if ($ui->data === ' ') {
                        $ui->data = '\s';
                    }
                }
            }
            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->change_space_to_charset_s($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}


/**
 * Add quant + to space charset hint
 */
class qtype_preg_regex_hint_space_charset_without_quant extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_2', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_2', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_CHARSET) {
            if ($this->check_space_charsets($node->userinscription[0]->data)
                || (count($node->userinscription) === 3 && $this->check_space_charsets($node->userinscription[1]->data))
                || ($this->check_many_charset_node($node) && $this->check_space_charsets($node->userinscription[1]->data))
                || $this->check_space_charset_node($node)
                && !$node->negative) {

                if (!$this->check_other_quant_for_space_charset($node)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_space_charset_without_quant';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function check_other_quant_for_space_charset($node) {
        $parent = $this->get_parent_node($this->tree, $node->id);
        if ($parent != NULL) {
            if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                return true;
            } else if ($parent->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                && ($parent->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR
                    || $parent->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING)) {
                return $this->check_other_quant_for_space_charset($parent);
            }
        }
        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->add_quant_to_space_charset($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function add_quant_to_space_charset($node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $qu = new qtype_preg_node_infinite_quant(1, false, true, true);
            $qu->set_user_info(null, array(new qtype_preg_userinscription('+')));
            $qu->operands[] = $node;

            $parent = $this->get_parent_node($this->tree, $node->id);
            if ($parent != NULL) {
                //if (count($parent->operands) > 1) {
                foreach ($parent->operands as $i => $operand) {
                    if ($operand->id === $node->id) {
                        $parent->operands[$i] = $qu;

//                            $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
//                                array($qu),
//                                array_slice($parent->operands, $i + 1));
                    }
                }
                /*} else {
                    $parent->operands = array($qu);
                }*/
            } else {
                $this->tree = $qu;
            }

            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->add_quant_to_space_charset($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}


/**
 * Replace subpattern without backreference to grouping hint
 */
class qtype_preg_regex_hint_subpattern_without_backref extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {

        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_3', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_3', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            if (!($node->operands[0]->type == qtype_preg_node::TYPE_LEAF_META
                && $node->operands[0]->subtype == qtype_preg_leaf_meta::SUBTYPE_EMPTY)) {
                if (!$this->check_backref_to_subexpr($this->tree, $node->number)) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_subpattern_without_backref';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->change_subpattern_to_group($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function change_subpattern_to_group($node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $node->subtype = qtype_preg_node_subexpr::SUBTYPE_GROUPING;
            //$subpattern_last_number = 0;
            $this->rename_backreferences($this->tree, $this->tree/*, $subpattern_last_number*/);
            return true;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->change_subpattern_to_group($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}


/**
 * Replace ? quant to * quant for space charset hint
 */
class qtype_preg_regex_hint_space_charset_with_finit_quant extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_4', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_4', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_CHARSET) {
            if ($this->check_space_charsets($node->userinscription[0]->data)
                || (count($node->userinscription) === 3 && $this->check_space_charsets($node->userinscription[1]->data))
                || ($this->check_many_charset_node($node) && $this->check_space_charsets($node->userinscription[1]->data))
                || $this->check_space_charset_node($node)
                && !$node->negative) {

                $qu = $this->get_quant_for_space_charset($node);
                if ($qu !== NULL) {
                    if ($qu->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $qu->leftborder === 0 && $qu->rightborder === 1) {
                        $this->regex_hint_result->problem_ids[] = $node->id;
                        $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_space_charset_with_finit_quant';
                        $this->regex_hint_result->problem_indfirst = $qu->position->indfirst;
                        $this->regex_hint_result->problem_indlast = $qu->position->indlast;
                        return true;
                    }
                }
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->add_finit_quant_to_space_charset($this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }

    private function add_finit_quant_to_space_charset($node, $remove_node_id) {
        if ($node->id == $remove_node_id) {
            $old_qu = $this->get_quant_for_space_charset($node);
            if ($old_qu != NULL) {
                $parent = $this->get_parent_node($this->tree, $old_qu->id);

                $qu = new qtype_preg_node_infinite_quant(0, false, true, true);
                $qu->set_user_info(null, array(new qtype_preg_userinscription('*')));
                $qu->operands[] = $old_qu->operands[0];

                if ($parent != NULL) {
                    foreach ($parent->operands as $i => $operand) {
                        if ($operand->id === $old_qu->id) {
                            $parent->operands[$i] = $qu;

//                            $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
//                                array($qu),
//                                array_slice($parent->operands, $i + 1));
                        }
                    }
                } else {
                    $this->tree = $qu;
                }
                return true;
            }
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $i => $operand) {
                if ($this->add_finit_quant_to_space_charset($operand, $remove_node_id)) {
                    return true;
                }
            }
        }

        return false;
    }
}


/**
 * Remove simple assertion and enable exact match option
 */
class qtype_preg_regex_hint_exact_match extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {
        if ($this->search($this->tree->get_regex_string())) {
            if (true/*$this->options->exactmatch*/) {
                $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_8', 'qtype_preg'));
                $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_8_alt', 'qtype_preg'));
            } else {
                $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_8', 'qtype_preg'));
                $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_8', 'qtype_preg'));
            }
        }

        return $this->regex_hint_result;
    }

    private function search($regex_string) {
        $regex_length = strlen($regex_string);
        if ($regex_length > 2) {
            $circumflex_assertions = array();
            $dollar_assertions = array();
            if ($this->search_left_circumflex_assertion($this->tree, $circumflex_assertions)
                && $this->search_right_dollar_assertion($this->tree, $dollar_assertions)) {
                $this->regex_hint_result->problem_ids = array_merge($circumflex_assertions, $dollar_assertions);
                $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_exact_match';
                $this->regex_hint_result->problem_indfirst = $this->tree->position->indfirst;
                $this->regex_hint_result->problem_indlast = $this->tree->position->indlast;
                return true;
            }
        }

        return false;
    }

    private function search_left_circumflex_assertion($node, &$circumflex_assertions = array()) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            && $node->subtype == qtype_preg_leaf_assert::SUBTYPE_CIRCUMFLEX) {
            if ($this->check_first_operand($node)) {
                $circumflex_assertions[] = $node->id;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->search_left_circumflex_assertion($operand, $circumflex_assertions);
            }
        }

        return count($circumflex_assertions);
    }

    private function search_right_dollar_assertion($node, &$dollar_assertions = array()) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            && $node->subtype == qtype_preg_leaf_assert::SUBTYPE_DOLLAR) {
            if ($this->check_last_operand($node)) {
                $dollar_assertions[] = $node->id;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->search_right_dollar_assertion($operand, $dollar_assertions);
            }
        }

        return count($dollar_assertions);
    }

    public function use_hint($regex_hint_result) {
        foreach ($regex_hint_result->problem_ids as $p_id) {
            $this->remove_subtree($this->tree, $this->tree, $p_id);
        }
        return $this->tree;
    }
}


/**
 * Check nullable regex hint
 */
class qtype_preg_regex_hint_nullable_regex extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_ERROR;
    }

    public function check_hint() {
        if ($this->tree->nullable) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_5', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_5', 'qtype_preg'));
            $this->regex_hint_result->problem_ids = array($this->tree->id);
            $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_nullable_regex';
            $this->regex_hint_result->problem_indfirst = $this->tree->position->indfirst;
            $this->regex_hint_result->problem_indlast = $this->tree->position->indlast;
        }

        return $this->regex_hint_result;
    }

    public function use_hint($regex_hint_result) {
        return null;
    }
}


/**
 * Remove useless circumflex assertion hint
 */
class qtype_preg_regex_hint_useless_circumflex_assertion extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_ERROR;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_errors_short_1', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_errors_full_1', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node, &$is_assert = false) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            && $node->subtype == qtype_preg_leaf_assert::SUBTYPE_CIRCUMFLEX) {
            if ($node->position->indfirst != 0 && !$this->check_first_operand($node)) {
                $is_found = false;
                $next_leaf = $this->get_next_right_leafs($this->tree, $node, 2, $is_found);

                if (count($next_leaf) === 1
                    || (count($next_leaf) > 1
                        && $next_leaf[1] !== null
                        && $next_leaf[1]->type != qtype_preg_node::TYPE_LEAF_ASSERT
                        && $next_leaf[1]->subtype != qtype_preg_leaf_assert::SUBTYPE_CIRCUMFLEX
                        && $is_assert == false)
                    || (count($next_leaf) > 1 && $next_leaf[1] === null)
                ) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_useless_circumflex_assertion';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
            $is_assert = true;
        } else {
            $is_assert = false;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand, $is_assert)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_subtree($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }
}


/**
 * Remove useless dollar assertion hint
 */
class qtype_preg_regex_hint_useless_dollar_assertion extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_ERROR;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_errors_short_2', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_errors_full_2', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node, &$is_assert = false) {
        if ($node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            && $node->subtype == qtype_preg_leaf_assert::SUBTYPE_DOLLAR) {
            if ($node->position->indfirst != strlen($this->tree->get_regex_string()) - 1
                && !$this->check_last_operand($node)) {
                $is_found = false;
                $next_leaf = $this->get_next_right_leafs($this->tree, $node, 2, $is_found);
                if (($next_leaf[1] !== null
                        && $next_leaf[1]->type != qtype_preg_node::TYPE_LEAF_ASSERT
                        && $next_leaf[1]->subtype != qtype_preg_leaf_assert::SUBTYPE_DOLLAR
                        && $is_assert == false)
                    || $next_leaf[1] === null
                ) {
                    $this->regex_hint_result->problem_ids[] = $node->id;
                    $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_useless_dollar_assertion';
                    $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                    $this->regex_hint_result->problem_indlast = $node->position->indlast;
                    return true;
                }
            }
            $is_assert = true;
        } else {
            $is_assert = false;
        }

        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        $this->remove_subtree($this->tree, $this->tree, $regex_hint_result->problem_ids[0]);
        return $this->tree;
    }
}

/*
// TODO
class qtype_preg_regex_hint_nested_subpatterns extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_TIP;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_tips_short_9', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_tips_full_9_alt', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR
            && $node->operand[0]->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->operand[0]->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {

            $this->regex_hint_result->problem_ids[] = $node->id;
            $this->regex_hint_result->problem_type = 109;
            $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
            $this->regex_hint_result->problem_indlast = $node->position->indlast;
            return true;

        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function use_hint($regex_hint_result) {
        return $this->tree;
    }
}


// TODO
class qtype_preg_regex_hint_partial_match_alt_operands extends qtype_preg_regex_hint {
    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_7', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_7', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search partial match alternative operands
     *
    private function search($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            $is_left_part_match = -1; // 1 if left part is match, 0 if right part is match
            $count = $this->partial_match_operands_count($node, $is_left_part_match);
            if ($count > 0) {
                $this->regex_hint_result->problem_ids[] = $count;
                $this->regex_hint_result->problem_ids[] = $is_left_part_match;
                $this->regex_hint_result->problem_ids[] = $node->id;
                $this->regex_hint_result->problem_type = 7;
                $this->regex_hint_result->problem_indfirst = $node->position->indfirst;
                $this->regex_hint_result->problem_indlast = $node->position->indlast;
                return true;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check found alternative node with partial match alternative operands
     *
    private function partial_match_operands_count($node, &$is_left_part_match) {
        $leafs = array();
        foreach ($node->operands as $i => $operand) {
            $leafs[$i] = array();
            $this->leafs_list($operand, $leafs[$i]);
        }

        $repeats_count = 0;
        for ($i = 1; $i < count ($leafs[0]); $i++) {
            if ($this->is_left_partial_match_leafs($leafs, $i)) {
                $repeats_count++;
                $is_left_part_match = 1;
            }
        }

        if ($repeats_count == 0) {
            for ($i = 1; $i < count ($leafs[0]); $i++) {
                if ($this->is_right_partial_match_leafs($leafs, $i)) {
                    $repeats_count++;
                    $is_left_part_match = 0;
                }
            }
        }

        return $repeats_count;
    }

    private function leafs_list($node, &$leafs) {
        $leafs[] = $node;
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->leafs_list($operand, $leafs);
            }
        }
    }

    private function is_left_partial_match_leafs($leafs, $index) {
        $j = 1;
        for (; $j < count($leafs); $j++) {
            if (!$leafs[0][$index]->is_equal($leafs[$j][$index], null)) {
                break;
            }
        }
        return $j == count($leafs);
    }

    private function is_right_partial_match_leafs($leafs, $index) {
        $j = 1;
//        for (; $j < count($leafs); $j++) {
//            if (!$leafs[0][$index]->is_equal($leafs[$j][$index], null)) {
//                break;
//            }
//        }
        return $j == count($leafs);
    }

    public function use_hint($regex_hint_result) {
        $this->bracketing_common_subexpr_from_alt($this->tree);
        return $this->tree;
    }

    private function bracketing_common_subexpr_from_alt($tree_root) {
//        if ($tree_root->id == $this->problem_ids[2]) {
//            if ($this->problem_ids[1] == 1) {
//                $leafs = array();
//
//                $this->get_left_part_of_operands_from_alt($tree_root->operands, $this->problem_ids[0], $leafs);
//
//
//            } else if ($this->problem_ids[1] == 0) {
//                $this->get_right_part_of_operands_from_alt($tree_root, $this->problem_ids[0]);
//            } else {
//                return false;
//            }
//        }
//
//        if ($this->is_operator($tree_root)) {
//            foreach ($tree_root->operands as $operand) {
//                if ($this->change_consecutive_quants($operand, $remove_node_id)) {
//                    return true;
//                }
//            }
//        }

        return false;
    }

    private function get_left_part_of_operands_from_alt($node, &$count, &$leafs) {
        if ($count > 0) {
            $leafs[] = $node;
            $count--;
            if ($this->is_operator($node)) {
                foreach ($node->operands as $operand) {
                    $this->leafs_list($operand, $leafs);
                }
            }
        }
    }

    private function get_right_part_of_operands_from_alt($node, $count) {
        return true;
    }
}
*/

// TODO
/**
 * Check common subexpressions in tree.
 */
class qtype_preg_regex_hint_common_subexpressions extends qtype_preg_regex_hint {
    private $regex_from_tree = '';
    public $regex_hint_result;

    private $deleted_grouping_positions = array();
    private $deleted_subpattern_positions = array();

    /** First index of something in regex string (absolute positioning). */
    private $indfirst = -2;
    /** Last index of something in regex string (absolute positioning). */
    private $indlast = -2;

    public function __construct($tree) {
        parent::__construct($tree);

        $this->type = qtype_preg_regex_hint::TYPE_EQUIVALENT;
    }

    public function check_hint() {
        if ($this->search($this->tree)) {
            $this->regex_hint_result->problem = htmlspecialchars(get_string('simplification_equivalences_short_4', 'qtype_preg'));
            $this->regex_hint_result->solve = htmlspecialchars(get_string('simplification_equivalences_full_4', 'qtype_preg'));
        }

        return $this->regex_hint_result;
    }

    /**
     * Search common subexpressions in tree.
     */
    private function search($tree_root, &$leafs = null) {
        if ($leafs == NULL) {
            $leafs = array();
            $leafs[0] = array();
            array_push($leafs[0], $tree_root);

            $norm = new qtype_preg_tree_normalization();
            $norm->normalization($tree_root);
//            $this->normalization($tree_root);
        }

        if ($tree_root !== null) {
            if ($this->is_operator($tree_root)) {
                foreach ($tree_root->operands as $operand) {
                    if ($this->search_subexpr($leafs, $operand, $tree_root)) {
                        return true;
                    }
                    $leafs[count($leafs)] = array();
                    for ($i = 0; $i < count($leafs); $i++) {
                        array_push($leafs[$i], $operand);
                    }

                    if ($this->search($operand, $leafs)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Search suitable common subexpressions in tree.
     */
    private function search_subexpr($leafs, $current_leaf, $tree_root) {
        foreach ($leafs as $leaf) {
            if ($leaf[0]->is_equal($current_leaf, null) /*|| $this->compare_quants($leaf[0], $current_leaf)*/) {
                $count_nodes = 0;
                $tmp_leafs = $this->delete_useless_nodes($current_leaf, $leaf, $count_nodes);

                $tmp_root = $this->get_parent_node($this->tree, $leaf[0]->id);

                if ($this->compare_parent_nodes($tmp_root, $tree_root, $count_nodes)) {
                    if ($this->compare_right_set_of_leafs(/*$leaf*/$tmp_leafs, $current_leaf, $tree_root, $count_nodes)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    private function delete_useless_nodes($current_leaf, $leaf, &$count_nodes, $operand = null) {
        $parent = $this->get_parent_node($this->tree, $current_leaf->id);

        $count_nodes = count($leaf);

        $tmp_leafs = null;
        if ($parent != null && $leaf[$count_nodes - 1]->id == $parent->id
            && (($parent->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                    && $parent->operands[0]->type == qtype_preg_node::TYPE_NODE_CONCAT)
                || (($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                    /*&& $parent->operands[0]->type != qtype_preg_node::TYPE_NODE_SUBEXPR*/)
                || $parent->type == qtype_preg_node::TYPE_NODE_CONCAT)) {
            $count_nodes--;

            $tmp_leafs = array_slice($leaf, 0, $count_nodes);

            if ($operand === null && isset($leaf[$count_nodes - 1]->operands)) {
                $operand = $current_leaf;
            }

            if ($operand !== null) {
                if ($operand->position->indfirst > $leaf[$count_nodes - 1]->position->indfirst) {
                    $operand->position->indfirst = $leaf[$count_nodes - 1]->position->indfirst;
                }

                if ($operand->position->indlast < $leaf[$count_nodes - 1]->position->indlast) {
                    $operand->position->indlast = $leaf[$count_nodes - 1]->position->indlast;
                }
            }

            $tmp_leafs = $this->delete_useless_nodes($parent, $tmp_leafs, $count_nodes, $operand);
        } else {
            $tmp_leafs = $leaf;
        }

        return $tmp_leafs;
    }

    private function get_next1($tree_root, $next_leaf, $count1, $is_fount2) {
        $right_leafs = null;
        if (($next_leaf->type == qtype_preg_node::TYPE_NODE_SUBEXPR
                && $next_leaf->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING)
            || $next_leaf->type == qtype_preg_node::TYPE_NODE_CONCAT) {
            $next_leaf->operands[0]->position->indfirst = $next_leaf->position->indfirst;
            $next_leaf->operands[0]->position->indlast = $next_leaf->position->indlast;
            $right_leafs = $this->get_next1($tree_root, $next_leaf->operands[0], $count1, $is_fount2);
        } else {
            $right_leafs = $this->get_next_right_leafs($tree_root, $next_leaf, $count1, $is_fount2);
        }
        return $right_leafs;
    }

    /**
     * Trying to get a set of equivalent $leafs nodes from $current_leaf.
     */
    private function compare_right_set_of_leafs($leafs, $current_leaf, $tree_root, $count_nodes) {
        $is_found = false;
        $right_leafs = $this->get_right_leafs($this->tree, $current_leaf, count($leafs), $is_found);
        $right_leafs_tmp = $right_leafs;

        if ($this->leafs_compare($leafs, $right_leafs)) {
            if ($leafs[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $leafs[0]->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {

                $node_counts = 0;
                $this->get_subtree_nodes_count($leafs[0], $node_counts);

                if ($node_counts < count($leafs)) {
                    $this->regex_hint_result->problem_ids[] = count($leafs);//count($leafs);//length
                } else {
                    $this->regex_hint_result->problem_ids[] = count($leafs) - 1;
                }
            } else {
                $this->regex_hint_result->problem_ids[] = $count_nodes;//count($leafs);//length
            }

            $this->regex_hint_result->problem_ids[] = $leafs[0]->id;
            $is_found = true;
            while ($is_found) {
                $this->regex_hint_result->problem_ids[] = $right_leafs_tmp[0]->id;

                $right_leafs_tmp = $right_leafs;
                $is_fount1 = false;

                $next_leafs = $this->get_right_leafs($this->tree,
                    $right_leafs_tmp[count($right_leafs_tmp) - 1], 2, $is_fount1);

                $next_leaf = null;
                if (count($next_leafs) > 1) {
                    $next_leaf = $next_leafs[1];
                }

                if ($next_leaf != null
                    && ($next_leaf->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        || $next_leaf->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)) {

                    $parent = $this->get_parent_node($this->tree, $leafs[0]->id);
                    $parent_curcur = $this->get_parent_node($this->tree, $next_leaf->id);
                    if ($parent != null && $parent_curcur != null && $parent_curcur->id == $parent->id) {
                        $is_fount2 = false;
//                        $right_leafs = $this->get_next_right_leafs($this->get_dst_root()/*$tree_root*/, $next_leaf->operands[0], count($leafs), $is_fount2);
                        $next_leaf->operands[0]->position->indfirst = $next_leaf->position->indfirst;
                        $next_leaf->operands[0]->position->indlast = $next_leaf->position->indlast;
                        $right_leafs = $this->get_next1($this->tree,
                            $next_leaf->operands[0], count($leafs), $is_fount2);
                    } else {
                        if ($parent != null
                            && ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                                || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)) {

                            $parent_cur = $this->get_parent_node($this->tree, $parent->id);

                            if ($parent_cur != null && $parent_curcur != null && $parent_curcur->id == $parent_cur->id) {
                                $is_fount2 = false;
                                $right_leafs = $this->get_next_right_leafs($this->tree,
                                    $next_leaf->operands[0], count($leafs), $is_fount2);
                            } else {
                                $right_leafs = array();
                            }
                        } else {
                            $right_leafs = array();
//                          $is_fount2 = false;
//                          $right_leafs = $this->get_next_right_leafs($this->get_dst_root()/*$tree_root*/, $next_leaf->operands[0], count($leafs), $is_fount2);
                        }
                    }
                } else {
                    $is_fount2 = false;
                    $right_leafs = $this->get_right_leafs($this->tree,
                        $next_leaf, /*count($leafs)*/$this->regex_hint_result->problem_ids[0], $is_fount2);
                }

                $this->get_subexpression_regex_position_for_nodes($leafs, $right_leafs_tmp);

                $right_leafs_tmp = $right_leafs;

                $is_found = $this->leafs_compare($leafs, $right_leafs);
                //$is_found = false;
            }
            $this->regex_hint_result->problem_type = 'qtype_preg_regex_hint_common_subexpressions';
            return true;
        }

        return false;
    }

    /**
     * Get subexpression position in regex string
     */
    private function get_subexpression_regex_position_for_nodes($leafs1, $leafs2) {
        $this->regex_hint_result->problem_indfirst = $leafs1[0]->position->indfirst;

        $this->regex_hint_result->problem_indlast = $leafs2[count($leafs2)-1]->position->indlast;
        foreach($leafs1 as $leaf) {
            if ($leaf->position->indfirst < $this->regex_hint_result->problem_indfirst) {
                $this->regex_hint_result->problem_indfirst = $leaf->position->indfirst;
            }
            if ($leaf->position->indlast > $this->regex_hint_result->problem_indlast){
                $this->regex_hint_result->problem_indlast = $leaf->position->indlast;
            }
        }

        foreach($leafs2 as $leaf) {
            if ($leaf->position->indfirst < $this->regex_hint_result->problem_indfirst) {
                $this->regex_hint_result->problem_indfirst = $leaf->position->indfirst;
            }
            if ($leaf->position->indlast > $this->regex_hint_result->problem_indlast){
                $this->regex_hint_result->problem_indlast = $leaf->position->indlast;
            }
        }

        $this->compare_parent_nodes_of_leafs2($leafs1, $leafs2);
        $this->compare_parent_nodes_of_leafs1($leafs1, $leafs2);
    }

    private function compare_parent_nodes_of_leafs2($leafs1, $leafs2) {
        $parent = $this->get_parent_node($this->tree, $leafs2[0]->id);
        $parent_cur = $this->get_parent_node($this->tree, $leafs1[0]->id);
        if ($parent_cur != null
            && ($parent_cur->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $parent_cur->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                || $parent_cur->type == qtype_preg_node::TYPE_NODE_SUBEXPR)) {

            $parent_curcur = $this->get_parent_node($this->tree, $parent_cur->id);
            if ($parent != null && $parent_curcur != null && $parent_curcur->id == $parent->id) {
                $this->regex_hint_result->problem_indlast = $parent_cur->position->indlast;
            } else {
                $this->regex_hint_result->problem_indlast = $parent_cur->position->indlast;
                /*foreach($leafs1 as $leaf) {
                    if ($leaf->position->indfirst < $this->indfirst){
                        $this->indfirst = $leaf->position->indfirst;
                    }
                    if ($leaf->position->indlast > $this->indlast){
                        $this->indlast = $leaf->position->indlast;
                    }
                }*/
                $this->compare_parent_nodes_of_leafs2($leafs2, array($parent_cur));
            }
        } else {
            foreach($leafs1 as $leaf) {
                if ($leaf->position->indfirst < $this->regex_hint_result->problem_indfirst){
                    $this->regex_hint_result->problem_indfirst = $leaf->position->indfirst;
                }
                if ($leaf->position->indlast > $this->regex_hint_result->problem_indlast) {
                    $this->regex_hint_result->problem_indlast = $leaf->position->indlast;
                }
            }
        }
    }

    private function compare_parent_nodes_of_leafs1($leafs1, $leafs2) {
        $parent = $this->get_parent_node($this->tree, $leafs1[0]->id);
        $parent_cur = $this->get_parent_node($this->tree, $leafs2[0]->id);
        if ($parent_cur != null
            && ($parent_cur->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $parent_cur->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                || $parent_cur->type == qtype_preg_node::TYPE_NODE_SUBEXPR)) {

            $parent_curcur = $this->get_parent_node($this->tree, $parent_cur->id);
            if ($parent != null && $parent_curcur != null && $parent_curcur->id == $parent->id) {
                $this->regex_hint_result->problem_indlast = $parent_cur->position->indlast;
            } else {
                $this->regex_hint_result->problem_indlast = $parent_cur->position->indlast;
                /*foreach($leafs2 as $leaf) {
                    if ($leaf->position->indfirst < $this->indfirst){
                        $this->indfirst = $leaf->position->indfirst;
                    }
                    if ($leaf->position->indlast > $this->indlast){
                        $this->indlast = $leaf->position->indlast;
                    }
                }*/
                $this->compare_parent_nodes_of_leafs1($leafs1, array($parent_cur));
            }
        } else {
            foreach($leafs2 as $leaf) {
                if ($leaf->position->indfirst < $this->regex_hint_result->problem_indfirst){
                    $this->regex_hint_result->problem_indfirst = $leaf->position->indfirst;
                }
                if ($leaf->position->indlast > $this->regex_hint_result->problem_indlast){
                    $this->regex_hint_result->problem_indlast = $leaf->position->indlast;
                }
            }
        }
    }

    /**
     * Compare two arrays with nodes
     */
    private function leafs_compare($leafs1, $leafs2) {
        if (count($leafs1) > 0) {
            if ($leafs1[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $leafs1[0]->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
            ) {
                $leafs1 = array_slice($leafs1, 1, count($leafs1));
            }
        }

        if (count($leafs2) > 0) {
            if ($leafs2[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                || $leafs2[0]->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT
            ) {
                $leafs2 = array_slice($leafs2, 1, count($leafs2));
            }
        }

        if (count($leafs1) != count($leafs2)) {
            return false;
        }

        for ($i = 0; $i < count($leafs1); $i++) {
            if (!$leafs1[$i]->is_equal($leafs2[$i], null)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compare two nodes who are parents
     */
    private function compare_parent_nodes($local_root1, $local_root2, $count_leafs) {
        if ($local_root1 != null && $local_root2 != null) {
            if ($local_root1->is_equal($local_root2, null)) {
                //return $this->is_can_parent_node($local_root1) && $this->is_can_parent_node($local_root2);
                return true;
            } else if (($local_root1->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root1->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                && ($local_root2->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root2->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)) {
//                return $this->compare_quants($local_root1, $local_root2);
                return true;
            } else if (($local_root1->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root1->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                && ($local_root2->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root2->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT)) {
                $new_local_root1 = $this->get_parent_node($this->tree, $local_root1->id);
                if ($new_local_root1 != null && $count_leafs == 1) {
                    return $this->compare_parent_nodes($new_local_root1, $local_root2, $count_leafs);
                } else {
                    return false;
                }
            } else if (($local_root1->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root1->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                && ($local_root2->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $local_root2->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)) {
                $new_local_root2 = $this->get_parent_node($this->tree, $local_root2->id);
                if ($new_local_root2 != null && $count_leafs == 1) {
                    return $this->compare_parent_nodes($local_root1, $new_local_root2, $count_leafs);
                } else {
                    return false;
                }
            } else if ($local_root1->type == qtype_preg_node::TYPE_NODE_CONCAT
                && $local_root2->type == qtype_preg_node::TYPE_NODE_CONCAT
                && count($local_root1->operands) > 1 && count($local_root2->operands) > 1) {
                return $this->compare_concats($local_root1, $local_root2);
            } else {
                return true;
            }
        }
        return false;
    }

    // TODO: delete
    private function compare_concats($node1, $node2) {
        if (is_a($node1, get_class($node2)) // subclass?
            && $node1->type == $node2->type
            && $node1->subtype == $node2->subtype) {
            if (count($node1->operands) < count($node2->operands)) {
                $is_match = true;
                foreach ($node1->operands as $i => $operand) {
                    if ($operand->is_equal($node2->operands[$i], null) === false) {
                        $is_match = false;
                        break;
                    }
                }

                if ($is_match === false) {
                    $is_match = true;
                    for ($j = 0, $i = count($node1->operands) - 1; $i > -1; $i--) {
                        $j++;
                        if ($node1->operands[$i]->is_equal($node2->operands[count($node2->operands) - $j], null) === false) {
                            $is_match = false;
                            break;
                        }
                    }
                }

                return $is_match;
            } else {
                $is_match = true;
                foreach ($node2->operands as $i => $operand) {
                    if ($operand->is_equal($node1->operands[$i], null) === false) {
                        $is_match = false;
                        break;
                    }
                }

                if ($is_match === false) {
                    $is_match = true;
                    for ($j = 0, $i = count($node2->operands) - 1; $i > -1; $i--) {
                        $j++;
                        if ($node2->operands[$i]->is_equal($node1->operands[count($node1->operands) - $j], null) === false) {
                            $is_match = false;
                            break;
                        }
                    }
                }

                return $is_match;
            }

        } else {
            return false;
        }
        return true;
    }

    /**
     * Whether the node is a parent for common sybexpression
     */
    private function is_can_parent_node($local_root) {
        if ($local_root !== null) {
            return !($local_root->type == qtype_preg_node::TYPE_NODE_ALT);
        }
        return true;
    }

    /**
     * Find and sort leafs for associative-commutative operators
     */
    private function associative_commutative_operator_sort($tree_root){
        if ($tree_root !== null) {
            if ($this->is_associative_commutative_operator($tree_root)) {
                for ($j = 0; $j < count($tree_root->operands) - 1; $j++) {
                    for ($i = 0; $i < count($tree_root->operands) - $j - 1; $i++) {
                        if ($tree_root->operands[$i]->get_regex_string() > $tree_root->operands[$i + 1]->get_regex_string()) {
                            $b = $tree_root->operands[$i];
                            $tree_root->operands[$i] = $tree_root->operands[$i + 1];
                            $tree_root->operands[$i + 1] = $b;
                        }
                    }
                }
            }

            if ($this->is_operator($tree_root)) {
                foreach ($tree_root->operands as $operand) {
                    $this->associative_commutative_operator_sort($operand);
                }
            }
        }
    }

    /**
     * Tree normalization
     */
    protected function normalization($tree_root) {
//        $this->deleted_grouping_positions = array();

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_quant_node_1_to_1($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_repeated_assertions($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_single_alternative_node($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_alt_with_question_quant($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_consecutive_quant_nodes($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $this->delete_not_empty_grouping_node($tree_root, $tree_root);

        $problem_exist = true;
        while($problem_exist) {
            $rule = new qtype_preg_regex_hint_single_charset_node($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_grouping_node($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_subpattern_node($tree_root);
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
                $this->deleted_subpattern_positions = array_merge($this->deleted_subpattern_positions, $rule->deleted_subpattern_positions);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        /*$problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            if ($this->search_single_not_repeat_alternative_node($tree_root)) {
                $this->change_alternative_to_charset($tree_root, $tree_root, $this->problem_ids[0]);
                $count++;
            } else {
                $problem_exist = false;
            }
            $this->problem_ids = array();
        }*/

        $this->delete_not_empty_grouping_node($tree_root, $tree_root);

        $this->associative_commutative_operator_sort($tree_root);

        $this->problem_ids = array();
    }

    /**
     * Search alternative node with only charsets operands with one character
     *
    private function search_single_not_repeat_alternative_node($node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_ALT) {
            if ($this->is_single_not_repeat_alternative($node)) {
                $this->regex_hint_result->problem_ids[] = $node->id;
                return true;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                if ($this->search_single_not_repeat_alternative_node($operand)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check found alternative node with only charsets operands with one character
     *
    private function is_single_not_repeat_alternative($node) {
        $repeats_count = 0;
        foreach ($node->operands as $i => $operand) {
            if ($operand->type == qtype_preg_node::TYPE_LEAF_CHARSET && !$operand->negative
                && $operand->userinscription[0]->data != '.') {

                foreach ($node->operands as $j => $tmpoperand) {
                    if ($i !== $j && $tmpoperand->is_equal($operand, null)) {
                        return false;
                    }
                }

                $repeats_count++;
            }
        }
        return $repeats_count > 1;
    }

    // TODO: delete
    private function delete_empty_groping_node($tree_root, $node, $remove_node_id) {
        if ($tree_root != null) {
            if ($node->id == $remove_node_id) {
                if ($node->id == $tree_root->id) {
                    $tree_root = null;
                }
                return true;
            }

            if ($this->is_operator($node)) {
                foreach ($node->operands as $i => $operand) {
                    if ($this->delete_empty_groping_node($tree_root, $operand, $remove_node_id)) {
                        if (count($node->operands) === 1) {
                            return $this->delete_empty_groping_node($tree_root, $tree_root, $node->id);
                        }

                        array_splice($node->operands, $i, 1);
                        if ($this->is_associative_commutative_operator($node) && count($node->operands) < 2) {
                            $node->operands[] = new qtype_preg_leaf_meta(qtype_preg_leaf_meta::SUBTYPE_EMPTY);
                        }

                        return false;
                    }
                }
            }
        }

        return false;
    }*/

    private function delete_not_empty_grouping_node($tree_root, $node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING) {
            $parent = $this->get_parent_node($tree_root, $node->id);
            $group_operand = $node->operands[0];
            if ($parent !== null) {
                if ($node->operands[0]->type !== qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype !== qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    if ($parent->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $parent->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_LEAF_META
                        && $group_operand->subtype != qtype_preg_leaf_meta::SUBTYPE_EMPTY
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                        && $group_operand->id != -1
                    ) {

                        $group_operand->position->indfirst = $node->position->indfirst;
                        $group_operand->position->indlast = $node->position->indlast;

                        $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    } else if (($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                            || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_CONCAT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    ) {

                        if ($node->position !== null) {
                            $group_operand->position->indfirst = $node->position->indfirst;
                            $group_operand->position->indlast = $node->position->indlast;

                            $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);
                        }

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    } else if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                        && $group_operand->type != qtype_preg_node::TYPE_LEAF_CHARSET) {

                        $group_operand->position->indfirst = $node->position->indfirst;
                        $group_operand->position->indlast = $node->position->indlast;

                        $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    }
                }
            } else {
                /*$group_operand->position->indfirst = $node->position->indfirst;
                $group_operand->position->indlast = $node->position->indlast;*/
                $tree_root = $group_operand;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->delete_not_empty_grouping_node($tree_root, $operand);
            }
        }
    }



    public function use_hint($regex_hint_result) {
        $this->regex_hint_result = $regex_hint_result;
        $this->fold_common_subexpressions($this->tree);
//        return $this->tree;
        return $this->regex_from_tree;
    }

    /**
     * Elimination of common subexpressions
     */
    private function fold_common_subexpressions(&$tree_root) {
        if ($tree_root->id != $this->regex_hint_result->problem_ids[1]) {
            if ($this->is_operator($tree_root)) {
                foreach ($tree_root->operands as $operand) {
                    if ($operand->id == $this->regex_hint_result->problem_ids[1]) {
                        $this->tree_folding($operand, $tree_root);
                        return true;
                    }
                    if ($this->fold_common_subexpressions($operand)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Generate new fixed regex
     */
    private function tree_folding($current_leaf, $parent_node) {
        // Old regex string
        $regex_string = $this->tree->get_regex_string();

        // Calculate quantifier borders
        $stooloptions = new qtype_preg_simplification_tool_options();
        $stooloptions->engine = 'fa_matcher';
        $stooloptions->notation = 'native';
        $stooloptions->exactmatch = false;
        $stooloptions->problem_ids = array();
        $stooloptions->problem_ids[0] = '';
        $stooloptions->problem_type = -2;
        $stooloptions->indfirst = -2;
        $stooloptions->indlast = -2;

        $stooloptions->selection = new qtype_preg_position(-2, -2);
        $stooloptions->preserveallnodes = true;

        $tmp_st = new qtype_preg_simplification_tool($regex_string, $stooloptions);
//        $norm = new qtype_preg_tree_normalization();
//        $norm->normalization($tmp_st->get_dst_root());
        $this->normalization($tmp_st->get_dst_root());
        $tmp_del_g_pos = $this->deleted_grouping_positions;
        $tmp_del_s_pos = $this->deleted_subpattern_positions;
//        $tmp_pn = $this->get_node_from_id($tmp_st->get_dst_root(), $parent_node->id);
        $tmp_pn = $this->get_parent_node($tmp_st->get_dst_root(), $current_leaf->id);
        $tmp_cl = $this->get_node_from_id($tmp_st->get_dst_root(), $current_leaf->id);

        $counts = $this->subexpressions_repeats($tmp_st->get_dst_root(), $tmp_cl);

//        var_dump($tmp_st->get_dst_root()->get_regex_string());
//        var_dump($counts);

        // Create new quantifier with needed borders
        $text = $this->get_quant_text_from_borders($counts[0], $counts[1]);

        // New part of regex string
        $new_regex_string_part = '';

        if ($text !== '') {
            $qu = new qtype_preg_node_finite_quant($counts[0], $counts[1]);
            $qu->set_user_info(null, array(new qtype_preg_userinscription($text)));

            // Current operand is operand of quantifier node
            if (/*$this->options->problem_ids[0] > 1 &&*/ $current_leaf->type != qtype_preg_node::TYPE_NODE_SUBEXPR) {
                $se = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_GROUPING, -1, '', false);
                $se->set_user_info(null, array(new qtype_preg_userinscription('(?:...)')));

                // Add needed nodes to grouping node
                if (!$this->is_operator($current_leaf)) {
                    // Get right leafs from current node
                    $is_fount = false;
                    $right_leafs = $this->get_right_leafs($this->tree, $current_leaf, $this->regex_hint_result->problem_ids[0], $is_fount);

                    // Add this nodes while node is not operator
                    if (count($right_leafs) > 1) {
                        $concat = new qtype_preg_node_concat();

                        for ($i = 0; $i < $this->regex_hint_result->problem_ids[0];) {
                            $concat->operands[] = $right_leafs[$i];

                            $count_nodes = 0;
                            $this->get_subtree_nodes_count($right_leafs[$i], $count_nodes);
                            $i += $count_nodes;
                        }

                        $se->operands[] = $concat;
                    } else {
                        //$se->operands[] = $right_leafs[0];
                        if ($right_leafs[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                            || $current_leaf->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                            $se->operands[] = $right_leafs[0]->operands[0];
                        } else {
                            $se->operands[] = $right_leafs[0];
                        }
                    }
                } else {
                    $is_fount = false;
                    $right_leafs = $this->get_right_leafs($this->tree, $current_leaf, $this->regex_hint_result->problem_ids[0], $is_fount);

                    if (count($right_leafs) > 1) {
                        for ($i = 0; $i < $this->regex_hint_result->problem_ids[0];) {
                            $se->operands[] = $right_leafs[$i];

                            $count_nodes = 0;
                            $this->get_subtree_nodes_count($right_leafs[$i], $count_nodes);
                            $i += $count_nodes;
                        }
                    } else {
                        //$se->operands[] = $right_leafs[0];
                        if ($right_leafs[0]->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                            || $current_leaf->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                            $se->operands[] = $right_leafs[0]->operands[0];
                        } else {
                            $se->operands[] = $right_leafs[0];
                        }
                    }
                }

                $qu->operands[] = $se;
            } else {
                if ($current_leaf->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                    || $current_leaf->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                    $qu->operands[] = $current_leaf->operands[0];
                } else {
                    $qu->operands[] = $current_leaf;
                }
            }

//            $this->normalization($qu/*->operands[0]*/);
            $new_regex_string_part = $qu->get_regex_string();
        } else {
//            $this->normalization($current_leaf);
            $new_regex_string_part = $current_leaf->get_regex_string();
        }

//        var_dump($regex_string);
//        var_dump($new_regex_string_part);

        $tmp_st = new qtype_preg_simplification_tool($new_regex_string_part, $stooloptions);
        //$this->normalization($tmp_st->get_dst_root());

        $problem_exist = true;
        $count = 0;
        while ($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_grouping_node($tmp_st->get_dst_root());
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        $count = 0;
        while ($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_subpattern_node($tmp_st->get_dst_root());
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $tmp_del_s_pos = array_merge($rule->deleted_subpattern_positions, $tmp_del_g_pos);
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $problem_exist = true;
        while($problem_exist) {
            $rule = new qtype_preg_regex_hint_single_charset_node($tmp_st->get_dst_root());
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
        }

        $problem_exist = true;
        $count = 0;
        while($problem_exist && $count < 99) {
            $rule = new qtype_preg_regex_hint_quant_node_1_to_1($tmp_st->get_dst_root());
            $rhr = $rule->check_hint();
            if (count($rhr->problem_ids) > 0) {
                $rule->use_hint($rhr);
            } else {
                $problem_exist = false;
            }
            $count++;
        }

        $new_regex_string_part = $tmp_st->get_regex_string();

//        var_dump($new_regex_string_part);
//        var_dump($regex_string);


        // New fixed regex
        // Delete ')' for deleted "(?:)"
//        $tmp_dst_root = $this->tree;

        // Rename all backreference
        $this->rename_all_backreference($this->tree, $regex_string);

        foreach ($tmp_del_g_pos as $item) {
            $is_found = false;
            foreach($this->deleted_grouping_positions as $elem) {
                if ($elem[0] === $item[0] && $elem[1] === $item[1]) {
                    $is_found = true;
                    break;
                }
            }
            if (!$is_found) {
                $this->deleted_grouping_positions[] = $item;
            }
        }
//        $this->deleted_grouping_positions = array_merge($this->deleted_grouping_positions, $tmp_del_g_pos);

//        var_dump($tmp_del_s_pos);
//        var_dump($this->deleted_subpattern_positions);

        foreach ($tmp_del_s_pos as $item) {
            $is_found = false;
            foreach($this->deleted_subpattern_positions as $elem) {
                if ($elem[0] === $item[0] && $elem[1] === $item[1]) {
                    $is_found = true;
                    break;
                }
            }
            if (!$is_found) {
                $this->deleted_subpattern_positions[] = $item;
            }
        }
//        $this->deleted_subpattern_positions = array_merge($this->deleted_subpattern_positions, $tmp_del_s_pos);

        foreach($this->deleted_grouping_positions as $deleted_grouping_position) {
            if ($deleted_grouping_position[1] > $this->regex_hint_result->problem_indlast
                && $deleted_grouping_position[0] < $this->regex_hint_result->problem_indlast
                && $deleted_grouping_position[0] > $this->regex_hint_result->problem_indfirst) {
                $regex_string = substr($regex_string, 0, $deleted_grouping_position[1])
                    . substr($regex_string, $deleted_grouping_position[1] + 1);
            }
        }

        foreach($this->deleted_subpattern_positions as $deleted_grouping_position) {
            if ($deleted_grouping_position[1] > $this->regex_hint_result->problem_indlast
                && $deleted_grouping_position[0] < $this->regex_hint_result->problem_indlast
                && $deleted_grouping_position[0] > $this->regex_hint_result->problem_indfirst) {
                if (strlen($regex_string) > $deleted_grouping_position[1]) {
                    if ($regex_string[$deleted_grouping_position[1]] === ')') {
                        $regex_string = substr($regex_string, 0, $deleted_grouping_position[1])
                            . substr($regex_string, $deleted_grouping_position[1] + 1);
                    }
                }
            }
        }

        // Generate new regex
        $this->regex_from_tree = substr_replace($regex_string, $new_regex_string_part, $this->regex_hint_result->problem_indfirst,
            $this->regex_hint_result->problem_indlast - $this->regex_hint_result->problem_indfirst + 1);

        // Delete '(?:' for deleted "(?:)"
        foreach($this->deleted_grouping_positions as $deleted_grouping_position) {
            if ($deleted_grouping_position[0] < $this->regex_hint_result->problem_indfirst
                && $deleted_grouping_position[1] > $this->regex_hint_result->problem_indfirst
                && $deleted_grouping_position[1] < $this->regex_hint_result->problem_indlast) {
                $this->regex_from_tree = substr($this->regex_from_tree, 0, $deleted_grouping_position[0])
                    . substr($this->regex_from_tree, $deleted_grouping_position[0] + 3);
            }
        }

        foreach($this->deleted_subpattern_positions as $deleted_grouping_position) {
            if ($deleted_grouping_position[0] < $this->regex_hint_result->problem_indfirst
                && $deleted_grouping_position[1] > $this->regex_hint_result->problem_indfirst
                && $deleted_grouping_position[1] < $this->regex_hint_result->problem_indlast) {
                if ($this->regex_from_tree[$deleted_grouping_position[0]] === '('
                    && $this->regex_from_tree[$deleted_grouping_position[0] + 1] !== '?') {
                    $this->regex_from_tree = substr($this->regex_from_tree, 0, $deleted_grouping_position[0])
                        . substr($this->regex_from_tree, $deleted_grouping_position[0] + 1);
                }
            }
        }

        $tmp_regex = substr($this->regex_from_tree, $this->regex_hint_result->problem_indfirst - 3, 3);

//        var_dump($regex_string);

        /*$flag = (strlen($regex_string) <= $this->options->indlast + 1
            || $regex_string[$this->options->indlast + 1] !== ')');*/

//        var_dump($flag);



        if ($tmp_regex === '(?:'
            && substr_count($this->regex_from_tree, '(') !== substr_count($this->regex_from_tree, ')')) {
            $this->regex_from_tree = substr($this->regex_from_tree, 0, $this->regex_hint_result->problem_indfirst - 3)
                . substr($this->regex_from_tree, $this->regex_hint_result->problem_indfirst);
        }

        return $this->regex_from_tree;
    }

    private function rename_all_backreference(&$tree_root, &$regex_string, &$backrefnumb = 0) {
        if ($tree_root->type == qtype_preg_node::TYPE_NODE_SUBEXPR && $tree_root->subtype == qtype_preg_node_subexpr::SUBTYPE_SUBEXPR) {
            if (!($tree_root->position->indlast > $this->regex_hint_result->problem_indlast
                    && $tree_root->position->indfirst < $this->regex_hint_result->problem_indlast
                    && $tree_root->position->indfirst > $this->regex_hint_result->problem_indfirst)
                && !($tree_root->position->indfirst < $this->regex_hint_result->problem_indfirst
                    && $tree_root->position->indlast > $this->regex_hint_result->problem_indfirst
                    && $tree_root->position->indlast < $this->regex_hint_result->problem_indlast)
                && !(($tree_root->position->indfirst > $this->regex_hint_result->problem_indfirst
                    && $tree_root->position->indlast < $this->regex_hint_result->problem_indlast))) {
                ++$backrefnumb;
                if ($tree_root->number !== $backrefnumb) {
                    // Rename all backref, linked with this subexpr
                    $this->rename_backref_in_regex_string($this->tree, $regex_string, $tree_root->number, $backrefnumb);
                    $tree_root->number = $backrefnumb;
                }
            } else {
                $this->deleted_subpattern_positions[] = array($tree_root->position->indfirst, $tree_root->position->indlast);
            }
        }
        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                $this->rename_all_backreference($operand, $regex_string, $backrefnumb);
            }
        }
    }

    private function rename_backref_in_regex_string(&$tree_root, &$regex_string, $oldbackrefnumb, $newbackrefnumb) {
        if ($tree_root->type == qtype_preg_node::TYPE_LEAF_BACKREF && $tree_root->number === $oldbackrefnumb) {
            $tree_root->number = $newbackrefnumb;
            $regex_string = substr_replace($regex_string, "\\{$newbackrefnumb}", $tree_root->position->indfirst,
                $tree_root->position->indlast - $tree_root->position->indfirst + 1);
        }

        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                $this->rename_backref_in_regex_string($operand, $regex_string, $oldbackrefnumb, $newbackrefnumb);
            }
        }
    }

    private function get_subtree_nodes_count($node, &$count_operands) {
        $count_operands++;
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->get_subtree_nodes_count($operand, $count_operands);
            }
        }
    }


    private function get_current_node_repeats($current_root, $node, $current_problem_id) {
        $counts = array(0,0);
        $node = $this->get_node_from_id($current_root, $current_problem_id);
        if ($node != null) {
            if ($node->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
                $node_counts = 0;
                $this->get_subtree_nodes_count($node, $node_counts);
                if ($node_counts < $this->regex_hint_result->problem_ids[0]) {
                    $counts[0] += 1;
                    $counts[1] += 1;
                } else {
                    $counts[0] += $node->leftborder;
                    $counts[1] += $node->rightborder;
                }
            } else if ($node->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
                $node_counts = 0;
                $this->get_subtree_nodes_count($node, $node_counts);
                if ($node_counts < $this->regex_hint_result->problem_ids[0]) {
                    $counts[0] += 1;
                    $counts[1] += 1;
                } else {
                    $counts[0] += $node->leftborder;
                    $counts[1] = -999;
                }
            } else {
//                $parent = $this->get_parent_node($this->get_dst_root(), $current_problem_id);
//                $tmp_counts = $this->get_repeats($current_root, $node, $parent->id);
//                $counts[0] += $tmp_counts[0];
//                $counts[1] += $tmp_counts[1];
                $counts[0] += 1;
                $counts[1] += 1;
            }
        } else {
            $counts[0] += 1;
            $counts[1] += 1;
        }
        return $counts;
    }

    private function get_repeats($current_root, $node, $current_problem_id) {
        $counts = array(0,0);

        $parent = $this->get_parent_node($current_root, $current_problem_id);

        if ($this->is_can_parent_node($parent)) {
            if ($parent != null) {
                if ($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
//                    var_dump('1---');
                    $counts[0] += $parent->leftborder;
                    $counts[1] += $parent->rightborder;
                } else if ($parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
//                    var_dump('2---');
                    $counts[0] += $parent->leftborder;
                    $counts[1] = -999;
                } else if ($parent->type == qtype_preg_node::TYPE_NODE_SUBEXPR) {

                    $parent_tmp = $this->get_parent_node($current_root, $parent->id);
                    if ($parent_tmp != null) {
                        if ($parent_tmp->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT) {
//                            var_dump('3---');
                            $counts[0] += $parent_tmp->leftborder;
                            $counts[1] += $parent_tmp->rightborder;
                        } else if ($parent_tmp->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {
//                            var_dump('4---');
                            $counts[0] += $parent_tmp->leftborder;
                            $counts[1] = -999;
                        } else if ($parent_tmp->type == qtype_preg_node::TYPE_NODE_CONCAT) {
                            if ($this->regex_hint_result->problem_ids[0] > 1) {
                                $tmp_counts = $this->get_repeats($current_root, $node, $parent_tmp->id);
//                                var_dump('5---');
                                $counts[0] += $tmp_counts[0];
                                $counts[1] += $tmp_counts[1];
                            } else {
                                $tmp_counts = $this->get_current_node_repeats($current_root, $node, $current_problem_id);
//                                var_dump('6---');
                                $counts[0] += $tmp_counts[0];
                                $counts[1] += $tmp_counts[1];
                            }
                        } else {
//                            var_dump('7---');
                            $counts[0] += 1;
                            $counts[1] += 1;
                        }
                    } else {
//                        var_dump('8---');
                        $counts[0] += 1;
                        $counts[1] += 1;
                    }
                } else if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT) {
                    if ($this->regex_hint_result->problem_ids[0] > 1) {
                        $tmp_counts = $this->get_repeats($current_root, $node, $parent->id);
//                        var_dump('9---');
                        $counts[0] += $tmp_counts[0];
                        $counts[1] += $tmp_counts[1];
                    } else {
                        $tmp_counts = $this->get_current_node_repeats($current_root, $node, $current_problem_id);
//                        var_dump('10---');
                        $counts[0] += $tmp_counts[0];
                        $counts[1] += $tmp_counts[1];
                    }
                } else {
                    $tmp_counts = $this->get_current_node_repeats($current_root, $node, $current_problem_id);
//                    var_dump('11---');
                    $counts[0] += $tmp_counts[0];
                    $counts[1] += $tmp_counts[1];
                }
            } else {
                $tmp_counts = $this->get_current_node_repeats($current_root, $node, $current_problem_id);
//                var_dump('12---');
                $counts[0] += $tmp_counts[0];
                $counts[1] += $tmp_counts[1];
//                $counts[0] += 1;
//                $counts[1] += 1;
            }
        } else {
//            var_dump('13---');
            $counts[0] -= 1;
        }

        return $counts;
    }

    /**
     * Calculate repeats of subexpression
     */
    public function subexpressions_repeats($current_root, $node) {
        $counts = array(0,0);
        for($i = 1; $i < count($this->regex_hint_result->problem_ids); $i++) {
            $tmp_counts = $this->get_repeats($current_root, $node, $this->regex_hint_result->problem_ids[$i]);
            $counts[0] += $tmp_counts[0];
            $counts[1] = $tmp_counts[1] === -999 ? $tmp_counts[1] : $counts[1] + $tmp_counts[1];
        }

        return $counts;
    }

    private function get_node_from_id($tree_root, $node_id) {
        $local_root = null;
        if ($tree_root->id == $node_id) {
            return $tree_root;
        }
        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($operand->id == $node_id) {
                    return $operand;
                }
                $local_root = $this->get_node_from_id($operand, $node_id);
                if ($local_root !== null) {
                    return $local_root;
                }
            }
        }
        return $local_root;
    }

    /**
     * Get quantifier regex text from borders
     */
    private function get_quant_text_from_borders($left_border, $right_border) {
        if ($left_border == 1 && $right_border == 1) {
            return '';
        }

        if ($left_border < 0) {
            return '';
        }

        if ($left_border == 0 && $right_border == 0) {
            return '';
        }

        if ($right_border < 0) {
            if ($left_border == 0) {
                return '*';
            } else if ($left_border == 1) {
                return '+';
            }
            return '{' . $left_border . ',}';
        }
        if ($left_border == 0 && $right_border == 1) {
            return '?';
        }
        return '{' . $left_border . ($left_border == $right_border ? '' : ',' . $right_border) . '}';
    }
}


/**
 * Tree normalization
 */
class qtype_preg_tree_normalization {
    public $deleted_subpattern_positions = array();
    public $deleted_grouping_positions = array();

    public function __construct() {}
    protected function __clone() {}

    public function normalization($tree_root) {
        $rules_names = array(
            'qtype_preg_regex_hint_quant_node_1_to_1',
            'qtype_preg_regex_hint_repeated_assertions',
            'qtype_preg_regex_hint_single_alternative_node',
            'qtype_preg_regex_hint_alt_with_question_quant',
            'qtype_preg_regex_hint_consecutive_quant_nodes',
        );

        foreach($rules_names as $rule_name) {
            $problem_exist = true;
            $count = 0;
            while ($problem_exist && $count < 99) {
                $rule = new $rule_name($tree_root);
                $rhr = $rule->check_hint();
                if (count($rhr->problem_ids) > 0) {
                    $rule->use_hint($rhr);
                } else {
                    $problem_exist = false;
                }
                $count++;
            }
        }

        $this->delete_not_empty_grouping_node($tree_root, $tree_root);

        $rules_names = array(
            'qtype_preg_regex_hint_single_charset_node',
            'qtype_preg_regex_hint_grouping_node',
            'qtype_preg_regex_hint_subpattern_node'
        );

        foreach($rules_names as $rule_name) {
            $problem_exist = true;
            $count = 0;
            while ($problem_exist && $count < 99) {
                $rule = new $rule_name($tree_root);
                $rhr = $rule->check_hint();
                if (count($rhr->problem_ids) > 0) {
                    $rule->use_hint($rhr);
                } else {
                    $problem_exist = false;
                }
                $count++;
            }
        }

        $this->delete_not_empty_grouping_node($tree_root, $tree_root);

        $this->associative_commutative_operator_sort($tree_root);
    }

    private function delete_not_empty_grouping_node($tree_root, $node) {
        if ($node->type == qtype_preg_node::TYPE_NODE_SUBEXPR
            && $node->subtype == qtype_preg_node_subexpr::SUBTYPE_GROUPING) {
            $parent = $this->get_parent_node($tree_root, $node->id);
            $group_operand = $node->operands[0];
            if ($parent !== null) {
                if ($node->operands[0]->type !== qtype_preg_node::TYPE_LEAF_META
                    && $node->operands[0]->subtype !== qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    if ($parent->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $parent->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_LEAF_META
                        && $group_operand->subtype != qtype_preg_leaf_meta::SUBTYPE_EMPTY
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                        && $group_operand->id != -1
                    ) {

                        $group_operand->position->indfirst = $node->position->indfirst;
                        $group_operand->position->indlast = $node->position->indlast;

                        $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    } else if (($parent->type == qtype_preg_node::TYPE_NODE_FINITE_QUANT
                            || $parent->type == qtype_preg_node::TYPE_NODE_INFINITE_QUANT)
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_CONCAT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_ALT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_FINITE_QUANT
                        && $group_operand->type != qtype_preg_node::TYPE_NODE_INFINITE_QUANT
                    ) {

                        if ($node->position !== null) {
                            $group_operand->position->indfirst = $node->position->indfirst;
                            $group_operand->position->indlast = $node->position->indlast;

                            $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);
                        }

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    } else if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                        && $group_operand->type != qtype_preg_node::TYPE_LEAF_CHARSET) {

                        $group_operand->position->indfirst = $node->position->indfirst;
                        $group_operand->position->indlast = $node->position->indlast;

                        $this->deleted_grouping_positions[] = array($node->position->indfirst, $node->position->indlast);

                        foreach ($parent->operands as $i => $operand) {
                            if ($operand->id == $node->id) {
                                if ($parent->type == qtype_preg_node::TYPE_NODE_CONCAT
                                    && $group_operand->type == qtype_preg_node::TYPE_NODE_CONCAT
                                ) {
                                    //$group_operand->operands[0]->position->indfirst = $group_operand->position->indfirst;
                                    //$group_operand->operands[count($group_operand->operands) - 1]->position->indlast = $group_operand->position->indlast;

                                    $parent->operands = array_merge(array_slice($parent->operands, 0, $i),
                                        $group_operand->operands,
                                        array_slice($parent->operands, $i + 1));
                                } else {
                                    $parent->operands[$i] = $group_operand;
                                }
                                break;
                            }
                        }
                    }
                }
            } else {
                /*$group_operand->position->indfirst = $node->position->indfirst;
                $group_operand->position->indlast = $node->position->indlast;*/
                $tree_root = $group_operand;
            }
        }
        if ($this->is_operator($node)) {
            foreach ($node->operands as $operand) {
                $this->delete_not_empty_grouping_node($tree_root, $operand);
            }
        }
    }

    protected function get_parent_node($tree_root, $node_id) {
        $local_root = null;
        if ($this->is_operator($tree_root)) {
            foreach ($tree_root->operands as $operand) {
                if ($operand->id == $node_id) {
                    return $tree_root;
                }
                $local_root = $this->get_parent_node($operand, $node_id);
                if ($local_root !== null) {
                    return $local_root;
                }
            }
        }
        return $local_root;
    }

    /**
     * Find and sort leafs for associative-commutative operators
     */
    public function associative_commutative_operator_sort($tree_root) {
        if ($tree_root !== null) {
            if ($this->is_associative_commutative_operator($tree_root)) {
                for ($j = 0; $j < count($tree_root->operands) - 1; $j++) {
                    for ($i = 0; $i < count($tree_root->operands) - $j - 1; $i++) {
                        if ($tree_root->operands[$i]->get_regex_string() > $tree_root->operands[$i + 1]->get_regex_string()) {
                            $b = $tree_root->operands[$i];
                            $tree_root->operands[$i] = $tree_root->operands[$i + 1];
                            $tree_root->operands[$i + 1] = $b;
                        }
                    }
                }
            }

            if ($this->is_operator($tree_root)) {
                foreach ($tree_root->operands as $operand) {
                    $this->associative_commutative_operator_sort($operand);
                }
            }
        }
    }

    protected function is_associative_commutative_operator($node) {
        return $node->type == qtype_preg_node::TYPE_NODE_ALT;
    }

    protected function is_operator($node) {
        return !($node->type == qtype_preg_node::TYPE_LEAF_CHARSET
            || $node->type == qtype_preg_node::TYPE_LEAF_ASSERT
            || $node->type == qtype_preg_node::TYPE_LEAF_META
            || $node->type == qtype_preg_node::TYPE_LEAF_BACKREF
            || $node->type == qtype_preg_node::TYPE_LEAF_SUBEXPR_CALL
            || $node->type == qtype_preg_node::TYPE_LEAF_TEMPLATE
            || $node->type == qtype_preg_node::TYPE_LEAF_CONTROL
            || $node->type == qtype_preg_node::TYPE_LEAF_OPTIONS
            || $node->type == qtype_preg_node::TYPE_LEAF_COMPLEX_ASSERT);
    }
}


/**
 * Simplification tool
 */
class qtype_preg_simplification_tool extends qtype_preg_authoring_tool {

    public function __construct($regex = null, $options = null) {
        parent::__construct($regex, $options);
    }

    /**
     * Overloaded from qtype_preg_regex_handler.
     */
    public function name() {
        return 'simplification_tool';
    }

    /**
     * Overloaded from qtype_preg_regex_handler.
     */
    protected function node_infix() {
        return 'simplification';
    }

    /**
     * Overloaded from qtype_preg_regex_handler.
     */
    protected function get_engine_node_name($nodetype, $nodesubtype) {
        return parent::get_engine_node_name($nodetype, $nodesubtype);
    }

    /**
     * Overloaded from qtype_preg_regex_handler.
     */
    protected function is_preg_node_acceptable($pregnode) {
        return true;
    }

    /**
     * Overloaded from qtype_preg_authoring_tool.
     */
    public function json_key() {
        return 'simplification';
    }

    /**
     * Overloaded from qtype_preg_authoring_tool.
     */
    public function generate_html() {
        if ($this->regex->string() == '') {
            return $this->data_for_empty_regex();
        } else if ($this->errors_exist() || $this->get_ast_root() == null) {
            return $this->data_for_unaccepted_regex();
        }
        return $this->data_for_accepted_regex();
    }

    /**
     * Overloaded from qtype_preg_authoring_tool.
     */
    public function data_for_accepted_regex() {
        $data = array();

        if ($this->options->notation === 'native') {
            $data['errors'] = $this->get_errors_description();
            $data['equivalences'] = $this->get_equivalences_description();
            $data['tips'] = $this->get_tips_description();
        } else {
            $data['errors'] = array();
            $data['equivalences'] = array();
            $data['tips'] = array();
        }

        return $data;
    }

    public function data_for_empty_regex() {
        $data = array();

        $data['errors'] = array();
        $data['equivalences'] = array();
        $data['tips'] = array();

        return $data;
    }

    protected function get_error_hints_names() {
        return array(
            'qtype_preg_regex_hint_nullable_regex',
            'qtype_preg_regex_hint_useless_circumflex_assertion',
            'qtype_preg_regex_hint_useless_dollar_assertion'
        );
    }

    protected function get_tips_hints_names() {
        return array(
            'qtype_preg_regex_hint_space_charset',
            'qtype_preg_regex_hint_space_charset_without_quant',
            'qtype_preg_regex_hint_space_charset_with_finit_quant'
        );
    }

    protected function get_equivalences_hints_names() {
        return array(
            'qtype_preg_regex_hint_repeated_assertions',
            'qtype_preg_regex_hint_grouping_node',
            'qtype_preg_regex_hint_subpattern_node',
            'qtype_preg_regex_hint_single_charset_node',
            'qtype_preg_regex_hint_single_alternative_node',
            'qtype_preg_regex_hint_quant_node',
            'qtype_preg_regex_hint_alt_without_question_quant',
            'qtype_preg_regex_hint_alt_with_question_quant',
            'qtype_preg_regex_hint_quant_node_1_to_1',
            'qtype_preg_regex_hint_question_quant_for_alternative_node',
            'qtype_preg_regex_hint_consecutive_quant_nodes',
            'qtype_preg_regex_hint_common_subexpressions',
            'qtype_preg_regex_hint_subpattern_without_backref',
            'qtype_preg_regex_hint_exact_match'
        );
    }

    /**
     * Get array of errors in regex.
     */
    protected function get_errors_description() {
        $errors = array();
        if ($this->options->is_check_errors == true) {

            $i = 0;
            $rules_names = $this->get_error_hints_names();

            foreach($rules_names as $rule_name) {
                $rule = new $rule_name($this->get_dst_root());
                $rhr = $rule->check_hint();

                if (count($rhr->problem_ids) > 0) {
                    $errors[$i] = array();

                    $errors[$i]["problem"] = $rhr->problem;
                    $errors[$i]["solve"] = $rhr->solve;
                    $errors[$i]["problem_ids"] = $rhr->problem_ids;
                    $errors[$i]["problem_type"] = $rhr->problem_type;
                    $errors[$i]["problem_indfirst"] = $rhr->problem_indfirst;
                    $errors[$i]["problem_indlast"] = $rhr->problem_indlast;

                    ++$i;
                }
            }
        }
        return $errors;
    }

    /**
     * Get array of tips in regex.
     */
    protected function get_tips_description() {
        $tips = array();
        if ($this->options->is_check_tips == true) {
            $i = 0;
            $rules_names = $this->get_tips_hints_names();

            foreach($rules_names as $rule_name) {
                $rule = new $rule_name($this->get_dst_root());
                $rhr = $rule->check_hint();

                if (count($rhr->problem_ids) > 0) {
                    $tips[$i] = array();

                    $tips[$i]["problem"] = $rhr->problem;
                    $tips[$i]["solve"] = $rhr->solve;
                    $tips[$i]["problem_ids"] = $rhr->problem_ids;
                    $tips[$i]["problem_type"] = $rhr->problem_type;
                    $tips[$i]["problem_indfirst"] = $rhr->problem_indfirst;
                    $tips[$i]["problem_indlast"] = $rhr->problem_indlast;

                    ++$i;
                }
            }
        }

        return $tips;
    }

    /**
     * Get array of equivalences in regex.
     */
    protected function get_equivalences_description() {
        $equivalences = array();

        if ($this->options->is_check_equivalences == true) {
            $i = 0;
            $rules_names = $this->get_equivalences_hints_names();

            foreach($rules_names as $rule_name) {
                $rule = new $rule_name($this->get_dst_root());
                $rhr = $rule->check_hint();

                if (count($rhr->problem_ids) > 0) {
                    $equivalences[$i] = array();

                    $equivalences[$i]["problem"] = $rhr->problem;
                    $equivalences[$i]["solve"] = $rhr->solve;
                    $equivalences[$i]["problem_ids"] = $rhr->problem_ids;
                    $equivalences[$i]["problem_type"] = $rhr->problem_type;
                    $equivalences[$i]["problem_indfirst"] = $rhr->problem_indfirst;
                    $equivalences[$i]["problem_indlast"] = $rhr->problem_indlast;

                    ++$i;
                }
            }
        }

        return $equivalences;
    }

    public function optimization() {
        $rhr = new qtype_preg_regex_hint_result();

        $rhr->problem_ids = $this->options->problem_ids;
        $rhr->problem_indfirst = $this->options->indfirst;
        $rhr->problem_indlast = $this->options->indlast;

        $rule_name = $this->options->problem_type;

        $rule = new $rule_name($this->get_dst_root());
        $restree = $rule->use_hint($rhr);

        $result_regex = '';

        if ($restree !== null) {
            if ($this->options->problem_type === 'qtype_preg_regex_hint_common_subexpressions') {
                $result_regex = $restree;
            } else {
                $result_regex = $restree->get_regex_string();
            }
        }

        return $result_regex;
    }
}