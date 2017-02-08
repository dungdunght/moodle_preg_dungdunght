<?php
// This file is part of Preg question type - https://bitbucket.org/oasychev/moodle-plugins/overview
//
// Preg question type is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines finite automata states and transitions classes for regular expression matching.
 * The class is used by FA-based matching engines, provides standartisation to them and enchances testability.
 *
 * @package    qtype_preg
 * @copyright  2012 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev <oasychev@gmail.com>, Valeriy Streltsov, Elena Lepilkina
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace qtype_preg\fa;

use qtype_preg\fa\equivalence\assertion_mismatch;
use qtype_preg\fa\equivalence\path_to_states_group;
use qtype_preg\fa\equivalence\subpattern_mismatch;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/question/type/poasquestion/stringstream/stringstream.php');
require_once($CFG->dirroot . '/question/type/preg/preg_lexer.lex.php');
require_once($CFG->dirroot . '/question/type/preg/preg_dot_lexer.lex.php');
require_once($CFG->dirroot . '/question/type/preg/preg_dot_parser.php');

/**
 * Represents a finite automaton. Inherit to define \qtype_preg_deterministic_fa and \qtype_preg_nondeterministic_fa.
 */
class fa {

    /** @var two-dimensional array of transition objects: first index is "from", second index is "to"*/
    public $adjacencymatrix = array();
    /** @var array with strings with numbers of states, indexed by their ids from adjacencymatrix. */
    public $statenumbers = array();
    /** @var array of int ids of states - start states. */
    public $startstates = array();
    /** @var array of of int ids of states - end states. */
    public $endstates = array();

    public $fastartstates = array();
    public $faendstates = array();
    // Regex handler
    protected $handler;

    // Subexpr references (numbers) existing in the regex.
    protected $subexpr_ref_numbers;

    public $subexpr_recursive_ref_numbers = array();

    /** @var boolean is automaton really deterministic - it can be even if it shoudn't.
     * May be used for optimisation when an FA object actually stores a DFA.
     */
    protected $deterministic = true;

    /** @var boolean whether automaton has epsilon-transtions. */
    protected $haseps = false;
    /** @var boolean whether automaton has simple assertion transtions. */
    protected $hasassertiontransitions = false;

    protected $statecount = 0;
    protected $transitioncount = 0;
    protected $idcounter = 0;

    protected $statelimit;
    protected $transitionlimit;

    public $innerautomata;

    public $intersectedtransitions;

    private $breakpos;

    public $loopstates;

    public function __construct($handler = null, $subexprrefs = array()) {
        $this->handler = $handler;
        $this->subexpr_ref_numbers = array();
        foreach ($subexprrefs as $ref) {
            $this->subexpr_ref_numbers[] = $ref->number;
            if ($ref->type === \qtype_preg_node::TYPE_LEAF_SUBEXPR_CALL && $ref->isrecursive) {
                $this->subexpr_recursive_ref_numbers[] = $ref->number;
            }
        }
        $this->set_limits();
        $this->innerautomata = array();
        $this->intersectedtransitions = array();
        $this->breakpos = null;
        $this->loopstates = array();
    }

    public function handler() {
        return $this->handler;
    }

    public function on_subexpr_added($pregnode, $body) {
        // Copy the node to the starting transitions.
        $start = $body['start'];
        $outgoing = $this->get_adjacent_transitions($start, true);
        foreach ($outgoing as $transition) {
            if (in_array($pregnode->number, $this->subexpr_ref_numbers)) {
                $transition->startsbackrefedsubexprs = true;
            }
        }
    }

    /**
     * The function should set $this->statelimit and $this->transitionlimit properties using $CFG.
     */
    protected function set_limits() {
        global $CFG;
        $this->statelimit = 250;
        $this->transitionlimit = 250;
        if (isset($CFG->qtype_preg_fa_state_limit)) {
            $this->statelimit = $CFG->qtype_preg_fa_state_limit;
        }
        if (isset($CFG->transition_limit)) {
            $this->transitionlimit = $CFG->transition_limit;
        }
    }

    public function transitions_tohr() {
        $result = '';
        foreach ($this->adjacencymatrix as $from => $row) {
            foreach ($row as $to => $transitions) {
                foreach ($transitions as $transition) {
                    $result .= $from . ' -> ' . $transition->pregleaf->leaf_tohr() . ' -> ' . $to . "\n";
                }
            }
        }
        return $result;
    }

    /**
     * Returns whether automaton is really deterministic.
     */
    public function is_deterministic() {
        return $this->deterministic;
    }

    /**
     * Used from qype_preg_fa_state class to signal that automaton become non-deterministic.
     *
     * Note that only methods of the automaton can make it deterministic and set this property to true.
     */
    public function make_nondeterministic() {
        $this->deterministic = false;
    }

    /**
     * Returns whether this implementation support DFA or NFA.
     */
    public function should_be_deterministic() {
        return false;
    }

    /**
     * Returns the start states for automaton.
     */
    public function get_start_states($subpattern = 0) {
        if (!isset($this->fastartstates[$subpattern])) {
            return isset($this->startstates[$subpattern]) ? $this->startstates[$subpattern] : array();
        }
        return $this->fastartstates[$subpattern];
    }

    /**
     * Return the end states of the automaton.
     */
    public function get_end_states($subpattern = 0) {
        if (!isset($this->faendstates[$subpattern])) {
            return isset($this->endstates[$subpattern]) ? $this->endstates[$subpattern] : array();
        }
        return $this->faendstates[$subpattern];
    }

    public function is_empty() {
        return empty($this->adjacencymatrix);
    }

    /**
     * Return array of all state ids of automata.
     */
    public function get_states() {
        return array_keys($this->adjacencymatrix);
    }

    /**
     * Calculates where subexpressions start and end.
     */
    public function calculate_subexpr_start_and_end_states() {
        $result = $this->calculate_start_and_end_states_inner(true);
        $this->startstates = $result[0];
        $this->endstates = $result[1];
    }

    /**
     * Calculates states that cause backtrack when generating strings
     */
    public function calculate_backtrack_states() {
        $subpatterns = $this->calculate_start_and_end_states_inner(false);
        $startstates = $subpatterns[0];
        $endstates = $subpatterns[1];
        $states = $this->get_states();
        $result = array();
        // First kind of backtrack states: backreferenced subexpressions
        foreach ($states as $state) {
            $transitions = $this->get_adjacent_transitions($state, true);
            foreach ($transitions as $transition) {
                // Check if the transition starts a backref'd subexpression
                if ($transition->startsbackrefedsubexprs) {
                    $result[$transition->from] = true;
                }
                // Check if the transition starts a recursive subexpression call
                if ($transition->pregleaf->type == \qtype_preg_node::TYPE_LEAF_SUBEXPR_CALL && $transition->pregleaf->isrecursive) {
                    $result[$transition->from] = true;
                }
            }
        }

        // Second kind of backtrack states: quantifiers have non-empty intersection with next transitions
        $subpattmap = $this->handler->get_subpatt_number_to_node_map();
        foreach ($endstates as $subpatt => $states) {
            // Check if current subpattern is a quantifier
            $node = $subpattmap[$subpatt];
            if ($node->type != \qtype_preg_node::TYPE_NODE_FINITE_QUANT && $node->type != \qtype_preg_node::TYPE_NODE_INFINITE_QUANT) {   // TODO: nullable alternation?
                continue;
            }
            // Get quantifier's end state's inner epsilon closure
            $innerclosure = array();
            foreach ($states as $state) {
                $innerclosure = array_merge($innerclosure, $this->get_epsilon_closure($state, true));
            }
            $innertransitions = array();
            foreach ($innerclosure as $state) {
                $innertransitions = array_merge($innertransitions, $this->get_adjacent_transitions($state, false));

                // Crutch: logically, we do not need outer transitions of the start state, but when FA transformation is on,
                // this leads to lack of backtrack states because of merged transitions
                $innertransitions = array_merge($innertransitions, $this->get_adjacent_transitions($state, true));
            }
            // Get quantifier's end state's outer epsilon closure
            $outerclosure = array();
            foreach ($states as $state) {
                $outerclosure = array_merge($outerclosure, $this->get_epsilon_closure($state, false));
            }
            $outertransitions = array();
            foreach ($outerclosure as $state) {
                $outertransitions = array_merge($outertransitions, $this->get_adjacent_transitions($state, true));

                // Crutch: logically, we do not need inner transitions of the end state, but when FA transformation is on,
                // this leads to lack of backtrack states because of merged transitions
                $outertransitions = array_merge($outertransitions, $this->get_adjacent_transitions($state, false));
            }
            // Check for intersections.
            $add = false;
            // First fast check: backreferences
            foreach ($innertransitions as $transition) {
                if ($add || $transition->pregleaf->type == \qtype_preg_node::TYPE_LEAF_BACKREF) {
                    $add = true;
                    break;
                }
            }
            foreach ($outertransitions as $transition) {
                if ($transition->loopsback) {
                    continue;
                }
                if ($add || $transition->pregleaf->type == \qtype_preg_node::TYPE_LEAF_BACKREF) {
                    $add = true;
                    break;
                }
            }
            // Now check for charset intersections.
            foreach ($innertransitions as $inner) {
                if ($inner->pregleaf->type != \qtype_preg_node::TYPE_LEAF_CHARSET) {
                    continue;
                }
                if ($add) {
                    break;
                }
                //echo "inner: {$inner->from} -> {$inner->pregleaf->leaf_tohr()} -> {$inner->to}\n";
                $innerranges = $inner->pregleaf->ranges();
                foreach ($outertransitions as $outer) {
                    if ($outer->pregleaf->type != \qtype_preg_node::TYPE_LEAF_CHARSET || $outer->loopsback) {
                        continue;
                    }
                    //echo "outer: {$outer->from} -> {$outer->pregleaf->leaf_tohr()} -> {$outer->to}\n";
                    // Finally check for an intersection
                    $outerranges = $outer->pregleaf->ranges();
                    if (\qtype_preg_unicode::intersects($innerranges, $outerranges)) {
                        $add = true;
                        break;
                    }
                }
            }
            if ($add && array_key_exists($subpatt, $startstates)) {
                foreach ($startstates[$subpatt] as $state) {
                    $result[$state] = true;
                }
            }
        }
        return array_keys($result);
    }

    private function states_numbers_to_ids() {
        foreach ($this->statenumbers as $id => &$number) {
            $number = $id;
        }
    }

    /**
     * Calculates start and end states for subpatterns.
     */
    private function calculate_start_and_end_states_inner($subexpronly = false) {
        $startstates = array();
        $endstates = array();
        $states = $this->get_states();
        foreach ($states as $state) {
            $outgoing = $this->get_adjacent_transitions($state, true);
            foreach ($outgoing as $transition) {
                $opentags = $transition->all_open_tags();
                $closetags = $transition->all_close_tags();
                $alltags = array_merge($opentags, $closetags);
                foreach ($alltags as $tag) {
                    // Skip all non-subpatterns
                    if ($tag->subpattern == -1) {
                        continue;
                    }
                    if ($subexpronly && $tag->type != \qtype_preg_node::TYPE_NODE_SUBEXPR && $tag->subpattern != $this->handler->get_ast_root()->subpattern) {
                        continue;
                    }
                    // Do not count duplicate subexpressions
                    if ($subexpronly && $tag->type == \qtype_preg_node::TYPE_NODE_SUBEXPR && $tag->isduplicate) {
                        continue;
                    }
                    $keys = array();

                    if ($subexpronly) {
                        // Add subexpression number as a key
                        if ($tag->type == \qtype_preg_node::TYPE_NODE_SUBEXPR) {
                            $keys[] = $tag->number;
                        }
                        if ($tag->subpattern == $this->handler->get_ast_root()->subpattern) {
                            $keys[] = 0;
                        }
                    } else {
                        // Add subpattern number as a key
                        $keys[] = $tag->subpattern;
                    }

                    $keys = array_values($keys);
                    foreach ($keys as $key) {
                        if (!array_key_exists($key, $startstates)) {
                            $startstates[$key] = array();
                        }
                        if (!array_key_exists($key, $endstates)) {
                            $endstates[$key] = array();
                        }
                        if (in_array($tag, $opentags) && !in_array($transition->from, $startstates[$key])) {
                            $startstates[$key][] = $transition->from;
                        }
                        if (in_array($tag, $closetags) && !in_array($transition->to, $endstates[$key])) {
                            $endstates[$key][] = $transition->to;
                        }
                    }
                }
            }
        }
        return array($startstates, $endstates);
    }

    public function get_epsilon_closure($state, $backwards = false) {
        $result = array($state);
        $current = array($state);
        while (!empty($current)) {
            $cur = array_pop($current);
            $transitions = $this->get_adjacent_transitions($cur, !$backwards);
            foreach ($transitions as $transition) {
                if ($transition->pregleaf->subtype != \qtype_preg_leaf_meta::SUBTYPE_EMPTY) {
                    continue;
                }
                $interesting = $backwards
                             ? $transition->from
                             : $transition->to;
                if (in_array($interesting, $result)) {
                    continue;
                }
                $result[] = $interesting;
                $current[] = $interesting;
            }
        }
        return $result;
    }

    public function has_transition($from, $to) {
        return array_key_exists($from, $this->adjacencymatrix) && array_key_exists($to, $this->adjacencymatrix[$from]) && !empty($this->adjacencymatrix[$from][$to]);
    }

    /**
     * Return outtransitions of state with id $state.
     *
     * @param state - id of state which outtransitions are intresting.
     * @param outgoing - boolean flag which type of transitions to get (true - outtransitions, false - intotransitions).
     */
    public function get_adjacent_transitions($stateid, $outgoing = true) {
        $result = array();
        if ($outgoing && array_key_exists($stateid, $this->adjacencymatrix)) {
            foreach ($this->adjacencymatrix[$stateid] as $transitions) {
                $result = array_merge($result, $transitions);
            }
        }
        if (!$outgoing) {
            foreach ($this->adjacencymatrix as $row) {
                if (array_key_exists($stateid, $row)) {
                    $result = array_merge($result, $row[$stateid]);
                }
            }
        }
        return $result;
    }

    /**
     * Get array with reak numbers of states of this automata.
     */
    public function get_state_numbers() {
        return $this->statenumbers;
    }

    public function state_exists($state) {
        foreach ($this->states as $curstate) {
            if ($curstate === $state) {
                return true;
            }
        }
        return false;
    }

    /**
     * Passing automata in given direction.
     * @return array with ids of passed states.
     */
    public function reachable_states($backwards = false) {
        // Initialization wavefront.
        $front = $backwards
               ? array_values($this->get_end_states())
               : array_values($this->get_start_states());

        $reached = array();

        while (!empty($front)) {
            $curstate = array_pop($front);
            if (in_array($curstate, $reached)) {
                continue;
            }
            $reached[] = $curstate;
            $transitions = $this->get_adjacent_transitions($curstate, !$backwards);
            foreach ($transitions as $transition) {
                $front[] = $backwards
                         ? $transition->from
                         : $transition->to;
            }
        }
        return $reached;
    }

    /**
     * Delete all blind states in automata.
     */
    public function remove_unreachable_states() {
        // Pass automata forward.
        $aregoneforward = $this->reachable_states(false);
        // Pass automata backward.
        $aregoneback = $this->reachable_states(true);
        // Check for each state of atomata was it gone or not.
        $states = $this->get_states();
        foreach ($states as $curstate) {
            // Current state wasn't passed.
            if (!in_array($curstate, $aregoneforward) || !in_array($curstate, $aregoneback)) {
                $this->remove_state($curstate);
                if (array_key_exists($curstate, $this->innerautomata)) {
                    unset($this->innerautomata[$curstate]);
                }
                if (array_key_exists($curstate, $this->intersectedtransitions)) {
                    unset($this->intersectedtransitions[$curstate]);
                }
                if (array_key_exists($curstate, $this->loopstates)) {
                    unset($this->loopstates[$curstate]);
                }
            }
        }
    }

    /**
     * Write automata as a dot-style string.
     * @param type type of the resulting image, should be 'svg', png' or something.
     * @param filename the absolute path to the resulting image file.
     * @return dot_style string with the description of automata.
     */
    public function fa_to_dot($type = null, $filename = null, $usestateids = false) {
        $start = '';
        $end = '';
        $transitions = '';
        if ($this->statecount != 0) {
            // Add start states.
            foreach ($this->get_states() as $id) {
                $realnumber = $usestateids
                            ? $this->statenumbers[$id]
                            : $id;
                $tmp = '"' . $realnumber . '"';
                if (in_array($id, $this->get_start_states())) {
                    $start .= "{$tmp}[shape=rarrow];\n";
                } else if (in_array($id, $this->get_end_states())) {
                    $end .= "{$tmp}[shape=doublecircle];\n";
                }

                $outgoing = $this->get_adjacent_transitions($id, true);
                foreach ($outgoing as $transition) {
                    $from = $transition->from;
                    $to = $transition->to;
                    if ($usestateids) {
                        $from = $this->statenumbers[$from];
                        $to = $this->statenumbers[$to];
                    }
                    $transitions .= '    ' . $transition->get_label_for_dot($from, $to) . "\n";
                }
            }
        }
        $result = "digraph {\n    rankdir=LR;\n    " . $start . $end . $transitions . "}";
        if ($type != null) {
            $result = \qtype_preg_regex_handler::execute_dot($result, $type, $filename);
        }
        return $result;
    }

    /**
     * Add the start state of the automaton to given state.
     */
    public function add_start_state($state, $subpattern = 0) {
        if (!array_key_exists($state, $this->adjacencymatrix)) {
            throw new \qtype_preg_exception('set_start_state error: No state ' . $state . ' in automaton');
        }
        if (!in_array($state, $this->get_start_states($subpattern))) {
            if (isset($this->fastartstates[$subpattern])) {
                $this->fastartstates[$subpattern][] = $state;
            } else {
                $this->fastartstates[$subpattern] = array($state);
            }

        }
    }

    /**
     * Add the end state of the automaton to given state.
     */
    public function add_end_state($state, $subpattern = 0) {
        if (!array_key_exists($state, $this->adjacencymatrix)) {
            throw new \qtype_preg_exception('set_end_state error: No state ' . $state . ' in automaton');
        }
        if (!in_array($state, $this->get_end_states($subpattern))) {
            if (isset($this->faendstates[$subpattern])) {
                $this->faendstates[$subpattern][] = $state;
            } else {
                $this->faendstates[$subpattern] = array($state);
            }

        }
    }

    /**
     * Remove the end state of the automaton.
     */
    public function remove_end_state($state) {
        unset($this->faendstates[0][array_search($state, $this->faendstates[0])]);
        $this->faendstates[0] = array_values($this->faendstates[0]);
    }

    /**
     * Remove the start state of the automaton.
     */
    public function remove_start_state($state) {
        unset($this->fastartstates[0][array_search($state, $this->fastartstates[0])]);
        $this->fastartstates[0] = array_values($this->fastartstates[0]);
    }

    /**
     * Remove all end states of the automaton.
     */
    public function remove_all_end_states() {
        $this->faendstates[0] = array();
    }

    /**
     * Remove all start states of the automaton.
     */
    public function remove_all_start_states() {
        $this->fastartstates[0] = array();
    }

    /**
     * Set state as copied.
     *
     * @param state - state to be copied.
     */
    public function set_copied_state($state) {
        $number = $this->statenumbers[$state];
        $number = '(' . $number;
        $number .= ')';
        $this->statenumbers[$state] = $number;
    }

    /**
     * Change real number of state.
     *
     * @param state - state to change.
     * @param realnumber - new real number.
     */
    public function change_real_number($state, $realnumber) {
        $this->statenumbers[$state] = $realnumber;
    }

    /**
     * Adds a state to the automaton.
     *
     * @param real number of state.
     * @return state id of added state.
     */
    public function add_state($statenumber = null) {
        if ($statenumber === null) {
            $statenumber = $this->idcounter;
        }
        if (!in_array($statenumber, $this->statenumbers)) {
            $this->adjacencymatrix[] = array();
            $this->statenumbers[] = $statenumber;
            $this->statecount++;
            $this->idcounter++;
            if ($this->statecount > $this->statelimit) {
                throw new \qtype_preg_toolargefa_exception('');
            }
        }
        return array_search($statenumber, $this->statenumbers);
    }

    /**
     * Removes a state from the automaton.
     */
    public function remove_state($stateid) {
        // Remove outgoing transitions.
        unset($this->adjacencymatrix[$stateid]);

        // Remove incoming transitions.
        foreach ($this->adjacencymatrix as &$column) {
            if (array_key_exists($stateid, $column)) {
                unset($column[$stateid]);
            }
        }

        // Remove real numbers.
        unset($this->statenumbers[$stateid]);
        $this->statecount--;

        // Remove from start and end states.
        foreach ($this->fastartstates as $subpatt => $states) {
            $key = array_search($stateid, $states);
            if ($key !== false) {
                unset($this->fastartstates[$subpatt][$key]);
            }
            if (empty($this->fastartstates[$subpatt])) {
                unset($this->fastartstates[$subpatt]);
            }
        }
        foreach ($this->faendstates as $subpatt => $states) {
            $key = array_search($stateid, $states);
            if ($key !== false) {
                unset($this->faendstates[$subpatt][$key]);
            }
            if (empty($this->faendstates[$subpatt])) {
                unset($this->faendstates[$subpatt]);
            }
        }

    }

    public function append_inner_automaton($state, $automaton, $direction) {
        if (array_key_exists($state, $this->innerautomata)) {
            $this->innerautomata[$state][] = array($automaton, $direction);
        } else {
            $this->innerautomata[$state] = array(array($automaton, $direction));
        }
    }

    public function change_state_for_intersection($oldkey, $newkeys) {
        if (array_key_exists($oldkey, $this->innerautomata)) {
            foreach ($newkeys as $newkey) {
                foreach ($this->innerautomata[$oldkey] as $innerautomaton) {
                    $this->append_inner_automaton($newkey, $innerautomaton[0], $innerautomaton[1]);
                }
            }
        }
    }

    public function change_loopstate($oldkey, $newkeys) {
        if (array_key_exists($oldkey, $this->loopstates)) {
            foreach ($newkeys as $newkey) {
                $this->loopstates[$newkey] = $this->loopstates[$oldkey];
            }
        }
        foreach ($this->loopstates as $loopstate) {
            if (array_search($oldkey, $loopstate) !== false) {
                $loopstate[array_search($oldkey, $loopstate)] = $newkeys;
            }
        }

    }


    public function change_intersected_transitions($oldkey, $newkeys) {

        if (array_key_exists($oldkey, $this->intersectedtransitions)) {
            foreach ($newkeys as $newkey) {
                $this->add_intersected_transitions($newkey, $this->intersectedtransitions[$oldkey]);

            }
        }
    }

    public function add_intersected_transitions($newkey, $transitions) {
        foreach ($transitions as $intertran) {
            $intertran->isforintersection = true;
        }
        if (array_key_exists($newkey, $this->intersectedtransitions)) {
            $this->intersectedtransitions[$newkey] = array_merge($this->intersectedtransitions[$newkey], $transitions);
        } else {
            $this->intersectedtransitions[$newkey] = $transitions;
        }
    }

    public function change_recursive_start_states($oldkey, $newkeys) {
        foreach ($this->fastartstates as &$subpattern) {
            if (array_search($oldkey, $subpattern) !== false) {
                $subpattern = array_merge($subpattern, $newkeys);
                //unset($subpattern[array_search($oldkey, $subpattern)]);
            }
        }

    }

    public function change_recursive_end_states($oldkey, $newkeys) {
        foreach ($this->faendstates as &$subpattern) {
            if (array_search($oldkey, $subpattern) !== false) {
                $subpattern = array_merge($subpattern, $newkeys);
               // unset($subpattern[array_search($oldkey, $subpattern)]);
            }
        }
    }

    /**
     * Changes states which transitions come to/from.
     */
    public function redirect_transitions($oldstateid, $newstateid) {
        if ($oldstateid == $newstateid) {
            return;
        }

        // Get all transitions.
        $outgoing = $this->get_adjacent_transitions($oldstateid, true);
        $incoming = $this->get_adjacent_transitions($oldstateid, false);
        $transitions = array_merge($outgoing, $incoming);

        // Remember transitions to be added and remove them.
        $toadd = array();
        foreach ($transitions as $transition) {
            $this->remove_transition($transition);
            // Change "from" and "to" and add the transitions again.
            if ($transition->from == $oldstateid) {
                $transition->from = $newstateid;
            }
            if ($transition->to == $oldstateid) {
                $transition->to = $newstateid;
            }
            // Redirect merged transitions too.
            $transition->redirect_merged_transitions();
            $this->add_transition($transition);
        }

        // Delete the old state.
        $this->remove_state($oldstateid);
    }


    /**
     * Adds a transition.
     */
    public function add_transition($transition) {
        if (!array_key_exists($transition->to, $this->adjacencymatrix[$transition->from])) {
            // No transitions from->to yet.
            $this->adjacencymatrix[$transition->from][$transition->to] = array();
        }
        $this->adjacencymatrix[$transition->from][$transition->to][] = $transition;
        $this->transitioncount++;
        if ($this->transitioncount > $this->transitionlimit) {
            throw new \qtype_preg_toolargefa_exception('');
        }
    }

    /**
     * Removes a transition.
     */
    public function remove_transition($transition) {
        $key = array_search($transition, $this->adjacencymatrix[$transition->from][$transition->to]);
        unset($this->adjacencymatrix[$transition->from][$transition->to][$key]);
        $this->transitioncount--;
    }

    /**
     * Check if this state is from intersection part of autmata.
     */
    public function is_intersectionstate($state) {
        return strpos($this->statenumbers[$state], ',') !== false;
    }

    /**
     * Check if this state was copied.
     */
    public function is_copied_state($state) {
        return (strpos($this->statenumbers[$state], ')'));
    }

    /**
     * Check if this state is full intersect state, it means it has two numbers from both automata.
     */
    public function is_full_intersect_state($state) {
        $numbers = $this->statenumbers[$state];
        $number = explode(',', $numbers, 2);
        if (count($number) == 2 && $number[0] != '' && $number[1] != '') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if such state is in array of start states.
     */
    public function has_startstate($state) {
        return in_array($state, $this->get_start_states());
    }

    /**
     * Check if such state is in array of end states.
     */
    public function has_endstate($state) {
        return in_array($state, $this->get_end_states());
    }

    /**
     * Read and create a FA from dot-like language. Mainly used for unit-testing.   TODO: replace subpatt_start with tags
     */
    public static function read_fa($dotstring) {
        \StringStreamController::createRef('dot', $dotstring);
        $pseudofile = fopen('string://dot', 'r');
        $lexer = new \qtype_preg_dot_lexer($pseudofile);
        $parser = new \qtype_preg_dot_parser();
        while (($token = $lexer->nextToken()) !== null) {
            $parser->doParse($token->type, $token->value);
        }
        return $parser->get_automaton();
    }

    /**
     * Compares two FA and returns whether they are equal.
     *
     * @param another - FA to compare.
     * @param withtags - flag to point, if FAs should be compared with subpatterns.
     * @return boolean true if this FA equal to another.
     */
    public function equal($another, &$differences, $withtags = false) {
        global $CFG;

        // Initialize memory and stack - arrays of qtype_preg_fa_pair_of_groups
        $stack = array();
        $memory = array();
        $finalstatepairs = array();

        // Supported assertion classes
        $assertclasses = array('qtype_preg_leaf_assert_esc_a',
            'qtype_preg_leaf_assert_small_esc_z',
            'qtype_preg_leaf_assert_capital_esc_z',
            'qtype_preg_leaf_assert_esc_g',
            'qtype_preg_leaf_assert_circumflex',
            'qtype_preg_leaf_assert_dollar');

        // Generating initial pair of groups
        $groupspair = equivalence\groups_pair::generate_pair(new equivalence\states_group($this, $this->get_start_states(), new equivalence\path_to_states_group()),
                                                            new equivalence\states_group($another, $another->get_start_states(), new equivalence\path_to_states_group()));
        // If first group contains final states and second doesn't or vise versa
        if ($groupspair->first->has_end_states() != $groupspair->second->has_end_states()) {
            return false;
        }

        $stack[] = array($groupspair);
        $memory[] = array($groupspair);

        // While stack is not empty
        while (!empty($stack)) {
            // Taking new array of pairs of groups from bottom of stack
            $arrayofgroupspairs = current($stack);
            array_splice($stack, 0, 1);

            $currentsteppairs = array();

            foreach ($arrayofgroupspairs as $groupspair) {
                // Generate new arrays of equivalence/groups_pair from each pair of current array
                // Result - array, key - matched symbol (character, assert or epsilon), value - array of groups_pair, matched this character
                $newarraysofpairs = transition::divide_intervals($groupspair, $withtags);

                // Combine received pairs with other ones for current step
                foreach ($newarraysofpairs as $matchedsymbol => $arrayofpairs) {
                    // If there were pairs, matching current symbol, put all pairs of current array there
                    if (array_key_exists($matchedsymbol, $currentsteppairs)) {
                        foreach ($arrayofpairs as $newgroupspair) {
                            $isunique = true;
                            foreach ($currentsteppairs[$matchedsymbol] as $existinggroupspair) {
                                if ($newgroupspair->equal($existinggroupspair, false, true)) {
                                    $isunique = false;
                                    break;
                                }
                            }
                            if ($isunique) {
                                $currentsteppairs[$matchedsymbol][] = $newgroupspair;
                            }
                        }
                    }
                    // If current matched symbol is unique for current step, create new array of pairs for that symbol
                    else {
                        $currentsteppairs[$matchedsymbol] = $arrayofpairs;
                    }
                }
            }

            // Count pair of groups to compare with limit
            $pairscount = 0;
            foreach ($currentsteppairs as $arrayofpairs) {
                $pairscount += count($arrayofpairs);
            }
            if ($pairscount > $CFG->qtype_writregex_groups_pairs_limit) {
                throw new \moodle_exception('groupspaircountoverlimit', 'qtype_preg');
            }

            // Check for mismatches for each matched condition
            foreach ($currentsteppairs as $condition => $arrayofpairs) {
                $firstgroup = new equivalence\states_group($this);
                if (!empty($arrayofpairs)) {
                    $firstgroup->path = $arrayofpairs[0]->first->path;
                }
                $secondgroup = new equivalence\states_group($another);
                if (!empty($arrayofpairs)) {
                    $secondgroup->path = $arrayofpairs[0]->second->path;
                }
                // Collect groups of states from each pair in array
                foreach ($arrayofpairs as $pairofgroups) {
                    $firstgroup->add_states($pairofgroups->first->states);
                    $firstgroup->add_merged_transitions($pairofgroups->first->mergedbeforetransitions,
                        $pairofgroups->first->mergedaftertransitions);
                    $secondgroup->add_states($pairofgroups->second->states);
                    $secondgroup->add_merged_transitions($pairofgroups->second->mergedbeforetransitions,
                        $pairofgroups->second->mergedaftertransitions);
                }
                // Check for character mismatch
                if ($firstgroup->is_empty() != $secondgroup->is_empty()) {
                    if (!in_array(strval($condition), $assertclasses) && strval($condition) != 'epsilon') {
                        $differences[] = new equivalence\character_mismatch($firstgroup->is_empty() ? 1 : 0,
                            equivalence\groups_pair::generate_pair($firstgroup, $secondgroup));
                    }
                    elseif (in_array(strval($condition), $assertclasses)) {
                        $differences[] = new equivalence\assertion_mismatch($firstgroup->is_empty() ? 1 : 0,
                            equivalence\groups_pair::generate_pair($firstgroup, $secondgroup));
                    }
                    else {
                        $differences[] = new equivalence\final_state_mismatch($firstgroup->has_end_states() ? 0 : 1,
                            equivalence\groups_pair::generate_pair($firstgroup, $secondgroup));
                    }
                    continue;
                }

                // Check for final state mismatch
                if ($firstgroup->has_end_states() != $secondgroup->has_end_states()) {
                    $differences[] = new equivalence\final_state_mismatch($firstgroup->has_end_states() ? 0 : 1,
                        equivalence\groups_pair::generate_pair($firstgroup, $secondgroup));
                    continue;
                }

                // Check for merged assertions mismatch
                foreach ($assertclasses as $assert) {
                    // Merged before
                    $counter = 0;
                    foreach ($firstgroup->mergedbeforetransitions as $mergedtransition) {
                        if (is_a($mergedtransition->pregleaf, $assert)) {
                            $counter++;
                        }
                    }
                    foreach ($secondgroup->mergedbeforetransitions as $mergedtransition) {
                        if (is_a($mergedtransition->pregleaf, $assert)) {
                            $counter--;
                        }
                    }
                    if ($counter !== 0) {
                        $differences[] = new equivalence\assertion_mismatch($firstgroup->is_empty() ? 1 : 0,
                            equivalence\groups_pair::generate_pair($firstgroup, $secondgroup), $assert,
                            assertion_mismatch::POSITION_BEFORE);
                    }
                    // Merged after
                    $counter = 0;
                    foreach ($firstgroup->mergedaftertransitions as $mergedtransition) {
                        if (is_a($mergedtransition->pregleaf, $assert)) {
                            $counter++;
                        }
                    }
                    foreach ($secondgroup->mergedaftertransitions as $mergedtransition) {
                        if (is_a($mergedtransition->pregleaf, $assert)) {
                            $counter--;
                        }
                    }
                    if ($counter !== 0) {
                        $differences[] = new equivalence\assertion_mismatch($firstgroup->is_empty() ? 1 : 0,
                            equivalence\groups_pair::generate_pair($firstgroup, $secondgroup), $assert,
                            assertion_mismatch::POSITION_AFTER);
                    }
                }

                // Adding pairs to stack and memory
                // If current array of pairs is unique - push it to the stack and memory
                $exists = false;
                foreach ($memory as $memarray) {
                    if (count($memarray) != count($arrayofpairs)) {
                        continue;
                    }
                    $equalarray = true;
                    foreach ($arrayofpairs as $pair) {
                        $equalpair = false;
                        foreach ($memarray as $mempair) {
                            if ($pair->equal($mempair)) {
                                $equalpair = true;
                                break;
                            }
                        }
                        if (!$equalpair) {
                            $equalarray = false;
                            break;
                        }
                    }
                    if ($equalarray) {
                        $exists = true;
                        break;
                    }
                }

                // Push to memory, because there can be different tagsets in the same arrays of groups.
                // That is necessary for complete check of subpattern equivalence.
                array_push($memory, $arrayofpairs);
                if ($firstgroup->has_end_states() && $secondgroup->has_end_states()) {
                    foreach($arrayofpairs as $pair) {
                        if ($pair->first->has_end_states() && $pair->second->has_end_states()) {
                            $finalstatepairs[] = $pair;
                        }
                    }
                }
                if (!$exists) {
                    array_push($stack, $arrayofpairs);
                }
            }

            // Checking mismatches count
            $differences = array_slice($differences, 0, $CFG->qtype_writregex_mismatches_limit);
            if (count($differences) == $CFG->qtype_writregex_mismatches_limit) {
                break;
            }
        }

        // Check for subpattern mismatches in memory arrays
        if ($withtags) {
            // Paths to groups, containing final states
            $equalpaths = array();
            $nonequalpaths = array();
            foreach ($finalstatepairs as $pair) {
                // If both paths are equal, put one of them to equals array
                if ($pair->first->path->equal_path($pair->second->path)) {
                    $equalpaths[] = $pair->first->path;
                } else {
                    $nonequalpaths[] = array($pair->first->path, $pair->second->path, $pair);
                }
            }
            // If there were found equal paths for current nonequal - don't consider them
            for ($i = 0; $i < count($nonequalpaths); $i++) {
                // Find equal path for first path in pair
                $equalexists = false;
                foreach ($equalpaths as $equalpath) {
                    if ($equalpath->equal_path($nonequalpaths[$i][0])) {
                        $equalexists = true;
                        break;
                    }
                }

                // If for first path in pair equal was found, find equal path for second path in pair
                if ($equalexists) {
                    $equalexists = false;
                    foreach ($equalpaths as $equalpath) {
                        if ($equalpath->equal_path($nonequalpaths[$i][1])) {
                            $equalexists = true;
                            break;
                        }
                    }
                    if ($equalexists) {
                        array_splice($nonequalpaths, $i, 1);
                        $i--;
                    }
                }
            }

            // Generate mismatches for each left nonequal path
            foreach ($nonequalpaths as $nonequalpathpair) {
                $mm = new subpattern_mismatch(-1, $nonequalpathpair[2]);
                $mm->matchedsubpatterns = $nonequalpathpair[0]->equal_subpatterns($nonequalpathpair[1]);
                $nonequalpathpair[0]->mismatched_subpatterns($nonequalpathpair[1], $mm->diffpositionsubpatterns, $mm->uniquesubpatterns);
                $differences[] = $mm;
            }

            // Checking mismatches count
            $differences = array_slice($differences, 0, $CFG->qtype_writregex_mismatches_limit);
        }

        return count($differences) == $CFG->qtype_writregex_mismatches_limit;
    }

    /**
     * Checks if there already exists subpattern mismatch before current string
     * @param $mismatches array of mismatches
     * @param $matchedstring string, before which function checks existence of mismatches
     * @return boolean true, if there already exists earlier mismatch
     */
    private function earlier_mismatch_exists($mismatches, $matchedstring) {
        $keys = array_keys($mismatches);
        // For each substring from beginning of current string search it in array of key
        for ($length = 1; $length < count($matchedstring); $length++) {
            if (array_search($keys, substr($matchedstring, 0, $length)) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Decide if the intersection was successful or not.
     *
     * @param fa fa object - first automata taking part in intersection.
     * @param anotherfa fa object - second automata taking part in intersection.
     * @return boolean true if intersection was successful.
     */
    public function has_successful_intersection($fa, $anotherfa, $direction) {
        $issuccessful = false;
        // Analysis of result intersection.
        if ($direction == 0) {
            // Analysis if the end state of intersection includes one of end states of given automata.
            $fastates = $fa->get_end_states();
            $anotherfastates = $anotherfa->get_end_states();
            $states = $this->get_end_states();
        } else {
            // Analysis if the start state of intersection includes one of start states of given automata.
            $fastates = $fa->get_start_states();
            $anotherfastates = $anotherfa->get_start_states();
            $states = $this->get_start_states();
        }
        // Get real numbers.
        $numbers = $fa->get_state_numbers();
        $realfanumbers = array();
        $realanotherfanumbers = array();
        foreach ($fastates as $state) {
            $realfanumbers[] = $numbers[$state];
        }
        $numbers = $anotherfa->get_state_numbers();
        foreach ($anotherfastates as $state) {
            $realanotherfanumbers[] = $numbers[$state];
        }
        $result = array();
        foreach ($states as $state) {
            $result[] = $this->statenumbers[$state];
        }
        // Compare real numbers
        foreach ($realfanumbers as $num1) {
            $num1 = rtrim($num1, ",");
            foreach ($result as $num2) {
                $resnumbers = explode(',', $num2, 2);
                if ($num1 == $resnumbers[0]) {
                    $issuccessful = true;
                }
            }
        }

        foreach ($realanotherfanumbers as $num1) {
            $num1 = ltrim($num1, ",");
            foreach ($result as $num2) {
                $resnumbers = explode(',', $num2, 2);
                if (strpos($resnumbers[1], $num1) === 0 || $resnumbers[1] == $num1) {
                    $issuccessful = true;
                }
            }
        }
        return $issuccessful;
    }


    /**
     * Get connected with given states in given direction.
     *
     * @param state - state for searching connexted.
     * @param direction - direction of searching.
     */
    public function get_connected_states($state, $direction) {
        $result = array();
        $transitions = $this->get_adjacent_transitions($state, !$direction);
        foreach ($transitions as $tran) {
            if ($direction == 0) {
                $result[] = $tran->to;
            } else {
                $result[] = $tran->from;
            }
        }
        return $result;
    }

    /**
     * Modify state for adding to automata which is intersection of two others.
     *
     * @param changedstate - state for modifying.
     * @param origin - origin of automata with this state.
     */
    public function modify_state($changedstate, $origin) {
        $resultstate = $changedstate;
        if ($origin == transition::ORIGIN_TRANSITION_FIRST) {
            $last = substr($changedstate, -1);
            if ($last != ',') {
                $resultstate = $changedstate . ',';
            }
        } else {
            $first = substr($changedstate, 1);
            if ($first != ',') {
                $resultstate = ',' . $changedstate;
            }
        }
        return $resultstate;
    }

    private function has_same_transition($transition) {
        if ($this->has_transition($transition->from, $transition->to)) {

            $innertransitions = $this->adjacencymatrix[$transition->from][$transition->to];
            foreach ($innertransitions as $inner) {
                if ($transition->equal($inner)) {
                    return true;
                }
            }
        }
        return false;
    }
    /**
     * Copy transitions to workstate from automata source in given direction.
     *
     * @param stateswere - states which were in automata.
     * @param statefromsource - state from source automata which transitions are coped.
     * @param memoryfront - states added to automata in last state.
     * @param source - automata-source.
     * @param direction - direction of coping (0 - forward; 1 - back).
     */
    public function copy_transitions($stateswere, $statefromsource, $workstate, $memoryfront, $source, $direction) {
        // Get origin of source automata.
        $states = $source->get_states();
        if (!empty($states)) {
            $keys = array_keys($states);
            $transitions = $source->get_adjacent_transitions($states[$keys[0]], true);
            $keys = array_keys($transitions);
            if (!empty($keys)) {
                $origin = $transitions[$keys[0]]->origin;
            } else {
                $keys = array_keys($states);
                $transitions = $source->get_adjacent_transitions($states[$keys[0]], false);
                $keys = array_keys($transitions);
                if (empty($keys)) {
                    $origin = transition::ORIGIN_TRANSITION_FIRST;
                } else {
                    $origin = $transitions[$keys[0]]->origin;
                }
            }
        }
        // Get transition for analysis.
        if ($direction == 0) {
            $transitions = $source->get_adjacent_transitions($statefromsource, false);
        } else {
            $transitions = $source->get_adjacent_transitions($statefromsource, true);
        }
        $numbers = $source->get_state_numbers();
        // Search transition among states were.
        foreach ($stateswere as $state) {
            // Get real number of source state.
            if ($origin == transition::ORIGIN_TRANSITION_FIRST) {
                $number = rtrim($state, ',');
            } else {
                $number = ltrim($state, ',');
            }
            if (in_array($number, $numbers, true) && $source->is_copied_state($numbers[array_search($number, $numbers)])) {
                foreach ($transitions as $tran) {
                    if ($direction == 0) {
                        $sourcenum = trim($numbers[$tran->from], '()');
                    } else {
                        $sourcenum = trim($numbers[$tran->to], '()');
                    }
                    if ($sourcenum == $number) {
                        // Add transition.
                        $memstate = array_search($state, $this->statenumbers);
                        $transition = clone $tran;
                        if ($direction == 0) {
                            //$transition = new transition($memstate, $tran->pregleaf, $workstate, $tran->origin, $tran->consumeschars);
                            $transition->from = $memstate;
                            $transition->to = $workstate;
                        } else {
                            //$transition = new transition($workstate, $tran->pregleaf, $memstate, $tran->origin, $tran->consumeschars);
                            $transition->to = $memstate;
                            $transition->from = $workstate;
                        }
                        if ($tran->origin == transition::ORIGIN_TRANSITION_SECOND && !($tran->is_eps())) {
                            $transition->consumeschars = false;
                        }
                        $transition->redirect_merged_transitions();
                        $transition->set_transition_type();
                        $hassametransition = $this->has_same_transition($transition);

                        if (!$hassametransition) {
                            $this->add_transition($transition);
                        }
                    }
                }
            }
        }

        // Search transition among states added on last step.
        foreach ($memoryfront as $state) {
            $number = $this->statenumbers[$state];
            $number = trim($number, ',');
            foreach ($transitions as $tran) {
                if ($direction == 0) {
                    $sourcenum = trim($numbers[$tran->from], '()');
                } else {
                    $sourcenum = trim($numbers[$tran->to], '()');
                }
                if ($sourcenum == $number) {
                    $transition = clone $tran;
                    // Add transition.
                    if ($direction == 0) {
                        //$transition = new transition($state, $tran->pregleaf, $workstate, $tran->origin, $tran->consumeschars);
                        $transition->from = $state;
                        $transition->to = $workstate;
                    } else {
                        //$transition = new transition($workstate, $tran->pregleaf, $state, $tran->origin, $tran->consumeschars);
                        $transition->to = $state;
                        $transition->from = $workstate;
                    }
                    if ($tran->origin == transition::ORIGIN_TRANSITION_SECOND && !($tran->is_eps())) {
                        $transition->consumeschars = false;
                    }
                    $transition->redirect_merged_transitions();
                    $transition->set_transition_type();
                    $hassametransition = $this->has_same_transition($transition);
                    if (!$hassametransition) {
                        $this->add_transition($transition);
                    }
                }
            }
        }
    }

    /**
     * Copy and modify automata to stopcoping state or to the end of automata, if stopcoping == NULL.
     *
     * @param source - automata-source for coping.
     * @param oldfront - states from which coping starts.
     * @param stopcoping - array of states to which automata will be copied.
     * @param direction - direction of coping (0 - forward; 1 - back).
     * @return automata after coping.
     */
    public function copy_modify_branches($source, $oldfront, $stopcoping, $direction, $origin = null) {
        $resultstop = array();
        $memoryfront = array();
        $newfront = array();
        $notintersected = array();
        $found = false;
        $newmemoryfront = array();
        // Getting origin of automata.
        $states = $source->get_states();
        if ($origin === null && !empty($states)) {
            $keys = array_keys($states);
            $transitions = $source->get_adjacent_transitions($states[$keys[0]], true);
            $keys = array_keys($transitions);
            if (!empty($keys)) {
                $origin = $transitions[$keys[0]]->origin;
            } else {
                $keys = array_keys($states);
                $transitions = $source->get_adjacent_transitions($states[$keys[0]], false);
                $keys = array_keys($transitions);
                if (empty($keys)) {
                    $origin = transition::ORIGIN_TRANSITION_FIRST;
                } else {
                    $origin = $transitions[$keys[0]]->origin;
                }
            }
        }
        // Getting all states which are in automata for coping.
        $stateswere = $this->get_state_numbers();
        // Cleaning end states.
        $this->remove_all_end_states();
        // Coping.
        while (!empty($oldfront)) {
            foreach ($oldfront as $curstate) {
                if (!$source->is_copied_state($curstate)) {

                    // Modify states.
                    $changedstate = $source->statenumbers[$curstate];
                    $changedstate = $this->modify_state($changedstate, $origin);
                    // Mark state as copied state.
                    $source->set_copied_state($curstate);
                    $isfind = false;
                    // Search among states which were in automata.
                    if (in_array($changedstate, $stateswere)) {
                        $isfind = true;
                        $workstate = array_search($changedstate, $stateswere);
                    }

                    // Hasn't such state.
                    if (!$isfind) {
                        $this->add_state($changedstate);
                        $newstate = $changedstate . 'n';

                        $workstate = array_search($changedstate, $this->statenumbers);
                        $this->copy_transitions($stateswere, $curstate, $workstate, $memoryfront, $source, $direction);

                        if ($stopcoping !== null && array_search($curstate, $stopcoping) !== false) {
                            // Check if all transitions or not should go to intersection part.
                            if (array_key_exists($curstate, $source->intersectedtransitions)) {
                                if ($direction === 0) {
                                    $incoming = $this->get_adjacent_transitions($workstate, false);
                                    $found = false;
                                    foreach ($incoming as $in) {
                                        if ($in->isforintersection) {
                                            if (in_array($newstate, $this->statenumbers)) {
                                                $interworkstate = array_search($newstate, $this->statenumbers);
                                            } else {
                                                $interworkstate = $this->add_state($newstate);
                                            }
                                            $this->remove_transition($in);
                                            $in->to = $interworkstate;
                                            $in->redirect_merged_transitions();
                                            $this->add_transition($in);
                                            $found = true;
                                        }
                                    }
                                    $outgoing = $source->get_adjacent_transitions($curstate, true);
                                    $notintersected = array();
                                    foreach ($outgoing as $out) {
                                        if (!$out->isforintersection) {
                                            $notintersected[] = $out->to;
                                        }
                                    }
                                } else {
                                    $outgoing = $this->get_adjacent_transitions($workstate, true);
                                    $found = false;
                                    foreach ($outgoing as $out) {
                                        if ($out->isforintersection) {

                                            if (in_array($newstate, $this->statenumbers)) {
                                                $interworkstate = array_search($newstate, $this->statenumbers);
                                            } else {
                                                $interworkstate = $this->add_state($newstate);
                                            }
                                            $this->remove_transition($out);
                                            $out->from = $interworkstate;
                                            $out->redirect_merged_transitions();
                                            $this->add_transition($out);
                                            $found = true;
                                        }
                                    }
                                    $incoming = $source->get_adjacent_transitions($curstate, false);
                                    $notintersected = array();
                                    foreach ($incoming as $in) {
                                        if (!$in->isforintersection) {
                                            $notintersected[] = $in->from;
                                        }
                                    }
                                }
                            }
                            // Check end of coping.
                            if ($direction == 0) {
                                $this->add_end_state($workstate);
                            }
                            if (array_key_exists($curstate, $source->intersectedtransitions) && $found) {
                                $resultstop[] = $interworkstate;
                            } else if (!in_array($workstate, $resultstop)) {
                                $resultstop[] = $workstate;
                            }
                            $connectedstates = $source->get_connected_states($curstate, $direction);
                            $connectedstopstates = array();
                            foreach ($connectedstates as $connected) {
                                if (in_array($connected, $stopcoping) && !in_array($connected, $connectedstopstates)) {
                                    $connectedstopstates[] = $connected;
                                }
                            }
                            $newmemoryfront[] = $workstate;
                            if (array_key_exists($curstate, $source->intersectedtransitions) && $found) {
                                $newfront = array_merge($newfront, $connectedstates);
                            }
                            if (!empty($notintersected)) {
                                $newfront = array_merge($newfront, $notintersected);
                            }
                            if (count($connectedstopstates) + 1 == count($stopcoping)) {
                                $connectedstates = $connectedstopstates;

                                // Adding connected states.

                                $newfront = array_merge($newfront, $connectedstates);
                            }
                        } else {
                            $newmemoryfront[] = $workstate;
                            // Adding connected states.

                            $connectedstates = $source->get_connected_states($curstate, $direction);
                            $newfront = array_merge($newfront, $connectedstates);
                        }

                    } else {
                        $this->copy_transitions($stateswere, $curstate, $workstate, $memoryfront, $source, $direction);
                        $newstate = $this->statenumbers[$workstate] . 'n';
                        // Check if all transitions or not should go to intersection part.
                        if ($stopcoping !== null && array_search($curstate, $stopcoping) !== false && array_key_exists($curstate, $source->intersectedtransitions)) {
                            if ($direction === 0) {
                                $incoming = $this->get_adjacent_transitions($workstate, false);
                                $found = false;
                                foreach ($incoming as $in) {
                                    if ($in->isforintersection) {

                                        if (in_array($newstate, $this->statenumbers)) {
                                            $interworkstate = array_search($newstate, $this->statenumbers);
                                        } else {
                                            $interworkstate = $this->add_state($newstate);
                                        }
                                        $this->remove_transition($in);
                                        $in->to = $interworkstate;
                                        $in->redirect_merged_transitions();
                                        $this->add_transition($in);
                                        $found = true;
                                    }
                                }
                            } else {
                                $outgoing = $this->get_adjacent_transitions($workstate, true);
                                $found = false;
                                foreach ($outgoing as $out) {
                                    if ($out->isforintersection) {
                                        if (in_array($newstate, $this->statenumbers)) {
                                            $interworkstate = array_search($newstate, $this->statenumbers);
                                        } else {
                                            $interworkstate = $this->add_state($newstate);
                                        }
                                        $this->remove_transition($out);
                                        $out->from = $interworkstate;
                                        $out->redirect_merged_transitions();
                                        $this->add_transition($out);
                                        $found = true;
                                    }
                                }
                            }
                        }
                        $newmemoryfront[] = $workstate;
                        // Adding connected states.
                        $connectedstates = $source->get_connected_states($curstate, $direction);
                        $newfront = array_merge($newfront, $connectedstates);
                    }
                } else {
                    $changedstate = $source->statenumbers[$curstate];
                    $changedstate = trim($changedstate, '()');
                    $changedstate = $this->modify_state($changedstate, $origin);
                    $workstate = array_search($changedstate, $this->statenumbers);
                    if ($stopcoping === null || !in_array($workstate, $stopcoping) || (in_array($workstate, $stopcoping) && in_array($workstate, $memoryfront))) {
                        $this->copy_transitions($stateswere, $curstate, $workstate, $memoryfront, $source, $direction);
                    }
                }
            }
            $oldfront = $newfront;
            $memoryfront = $newmemoryfront;
            $newfront = array();
            $newmemoryfront = array();
        }
        $sourcenumbers = $source->get_state_numbers();
        // Add start states if fa has no one.
        if (count($this->get_start_states()) == 0) {
            $sourcestart = $source->get_start_states();
            foreach ($sourcestart as $start) {
                $realnumber = $sourcenumbers[$start];
                $realnumber = trim($realnumber, '()');
                $newstart = array_search($this->modify_state($realnumber, $origin), $this->statenumbers);
                if ($newstart !== false) {
                    $this->add_start_state($newstart);
                }
            }
        }

        $sourceend = $source->get_end_states();
        foreach ($sourceend as $end) {
            $realnumber = $sourcenumbers[$end];
            $realnumber = trim($realnumber, '()');
            $newend = array_search($this->modify_state($realnumber, $origin), $this->statenumbers);
            if ($newend !== false) {
                // Get last copied state.
                if (empty($resultstop)) {
                    $resultstop[] = $newend;
                }
                $this->add_end_state($newend);
            }
        }
        // Remove flag of coping from states of source automata.
        $source->remove_flags_of_coping();
        return $resultstop;
    }

    public function merge_end_transitions() {
        $wasadded = false;
        foreach ($this->get_end_states() as $end) {
            $endtransitions = $this->get_adjacent_transitions($end, false);
            foreach ($endtransitions as $endtran) {
                $isforintersection = false;
                $beforeeps = true;
                $intersectedtransitions = array();
                foreach ($endtran->mergedbefore as $before) {
                    $beforeeps = $beforeeps && !$before->is_end_anchor();
                }
                if ($endtran->is_eps() && $endtran->from != $endtran->to && $beforeeps) {
                    $wasadded = false;
                    $canmerge = true;
                    $transitions = $this->get_adjacent_transitions($endtran->from, false);
                    foreach ($transitions as $tran) {
                        if ($tran->from === $tran->to) {
                            $canmerge = false;
                        }
                    }
                    if ($canmerge) {
                        foreach ($transitions as $tran) {
                            if ($tran->from !== $tran->to) {
                                $clonetran = clone $tran;
                                $delclone = clone $endtran;
                                $delclone->mergedafter = array();
                                $delclone->mergedbefore = array();
                                $delclonemerged = clone $endtran;
                                $clonetran->loopsback = $tran->loopsback || $endtran->loopsback;
                                $clonetran->greediness = transition::min_greediness($tran->greediness, $delclone->greediness);
                                $merged = array_merge($delclonemerged->mergedbefore, array($delclone), $delclonemerged->mergedafter);
                                // Work with tags.
                                $merged = array_merge($merged, $clonetran->mergedafter);
                                $clonetran->mergedafter = $merged;
                                $clonetran->to = $endtran->to;
                                $clonetran->redirect_merged_transitions();
                                // If exists than remember only marked as intersected.
                                if (array_key_exists($endtran->from, $this->intersectedtransitions)) {
                                    if (in_array($endtran, $this->intersectedtransitions[$endtran->from])) {
                                        $intersectedtransitions[] = $clonetran;
                                    }
                                } else {
                                    $intersectedtransitions[] = $clonetran;
                                }
                                if ($clonetran->isforintersection) {
                                    $isforintersection = true;
                                }
                                $this->add_transition($clonetran);
                                $wasadded = true;
                            }
                        }
                        if ($wasadded) {
                            /*if  (array_key_exists($endtran->from, $this->innerautomata)) {
                                unset($this->innerautomata[$endtran->from]);
                            }*/
                            if ($endtran->isforintersection || $isforintersection) {
                                $this->change_state_for_intersection($endtran->from, array($endtran->to));
                                $this->change_loopstate($endtran->from, array($endtran->to));
                                if  (array_key_exists($endtran->to, $this->innerautomata) ) {

                                    $this->add_intersected_transitions($endtran->to, $intersectedtransitions);
                                }
                            }
                            $this->remove_transition($endtran);
                        }
                    }
                }

            }
        }
        return $wasadded;
    }

    /**
     * Check if there is such state in intersection part and add modified version of it.
     *
     * @param anotherfa - second automata, which toke part in intersection.
     * @param transition - transition for checking.
     * @param laststate - last added state.
     * @param realnumber - real number of serching state.
     * @param direction - direction of checking (0 - forward; 1 - back).
     * @return flag if it was possible to add another version of state.
     */
    public function has_same_state($anotherfa, $transition, $laststate, &$clones, &$realnumber, $direction) {
        $oldfront = array();
        $isfind = false;
        $hasintersection = false;
        $aregone = array();
        $newfront = array();
        // Get right clones in case of divarication.
        $clones = array();
        $clones[] = $transition;
        $numbers = explode(',', $realnumber, 2);
        $numbertofind = $numbers[0];
        $addnum = $numbers[1];
        $oldfront[] = $laststate;
        $secnumbers = $anotherfa->get_state_numbers();

        // While there are states for analysis.
        while (count($oldfront) != 0 && !$isfind) {
            foreach ($oldfront as $state) {
                $aregone[] = $state;
                $numbers = explode(',', $this->statenumbers[$state], 2);
                // State with same number is found.
                if ($numbers[0] == $numbertofind && $numbers[1] !== '') {
                    // State with same number was found and there is one more.
                    if ($isfind) {
                        $clones[] = $clones[count($clones) - 1];
                        // Get added numbers
                        $tran = $clones[count($clones) - 2];
                    } else {
                        // State wasn't found earlier but this state is a searched state.
                        $isfind = true;
                        $tran = $transition;
                    }
                    if ($direction == 0) {
                        $clone = $tran->to;    // TODO:
                    } else {
                        $clone = $tran->from;  // unused
                    }
                    $addnumber = $numbertofind . ',' . $addnum . '   ' . $numbers[1];
                    foreach ($secnumbers as $num) {
                        if (strpos($numbers[1], $num) === 0 || $numbers[1] == $num) {
                            $statefromsecond = array_search($num, $secnumbers);
                        }
                    }

                    $transitions = $anotherfa->get_adjacent_transitions($statefromsecond, $direction);
                    $transitions = array_values($transitions);

                    // There are transitions for analysis.
                    if (count($transitions) != 0) {
                        $intertran = $tran->intersect($transitions[0]);
                        if ($intertran !== null) {
                            $hasintersection = true;
                            // Form new transition.
                            $addstate = $this->add_state($addnumber);
                            $realnumber = $addnumber;
                            if ($direction == 0) {
                                $tran->to = $addstate;
                            } else {
                                $tran->from = $addstate;
                            }
                        }
                    } else {
                        // Form new transition.
                        $hasintersection = true;
                        $addstate = $this->add_state($addnumber);
                        $realnumber = $addnumber;
                        if ($direction == 0) {
                            $tran->to = $addstate;
                        } else {
                            $tran->from = $addstate;
                        }
                    }
                } else {
                    // Add connected states to new wave front.
                    if ($direction == 0) {
                        $conectstates = $this->get_connected_states($state, 1);
                    } else {
                        $conectstates = $this->get_connected_states($state, 0);
                    }
                    foreach ($conectstates as $conectstate) {
                        if (!in_array($conectstate, $newfront) && !in_array($conectstate, $aregone)) {
                            $newfront[] = $conectstate;
                        }
                    }
                }
            }
            $oldfront = $newfront;
            $newfront = array();
        }
        if (!$isfind) {
            $hasintersection = true;
        }
        return $hasintersection;
    }

    /**
     * Get transitions from automata for intersection.
     *
     * @param workstate state for getting transitions.
     * @param direction direction of intersection.
     * @return array of transitions for intersection.
     */
    public function get_transitions_for_intersection($workstate, $direction) {
        $result = array();
        $transitions = $this->get_adjacent_transitions($workstate, !$direction);
        if (array_key_exists($workstate, $this->intersectedtransitions)) {
            foreach ($transitions as $tran) {
                if ($tran->isforintersection) {
                    $result[] = $tran;
                }
            }
            if (empty($result)) {
                $result = $transitions;
            }
        } else {
            $result = $transitions;
        }
        return $result;
    }


    /**
     * Generate real number of state from intersection part.
     *
     * @param firststate real number of state from first automata.
     * @param secondstate real number of state from second automata.
     * @return real number of state from intersection part.
     */
    public function get_inter_state($firststate, $secondstate) {
        $first = trim($firststate, '(,)');
        $second = trim($secondstate, '()');
        $state = $first . ',' . $second;
        return $state;
    }

    /**
     * Find state which should be added in way of passing cycle.
     *
     * @param anotherfa object automaton to find.
     * @param resulttransitions array of intersected transitions.
     * @param curstate last added state.
     * @param clones transitions appeared in case of several ways.
     * @param realnumber real number of $curstate.
     * @param index index of transition in $resulttransitions for analysis.
     * @return boolean flag if automata has state which should be added in way of passing cycle.
     */
    public function have_add_state_in_cycle($anotherfa, &$resulttransitions, $curstate, &$clones, &$realnumber, $index, $direction) {
        $resnumbers = $this->get_state_numbers();
        $hasalready = false;
        $wasdel = false;
        // No transitions from last state.
        if (count($clones) <= 1) {
            $ispossible = $this->has_same_state($anotherfa, $resulttransitions[$index], $curstate, $clones, $realnumber, $direction);
            // It's possible to add state in case of having state.
            if ($ispossible) {
                // Search same state in result automata.
                $searchnumbers = explode(',', $realnumber, 2);
                $searchnumber = $searchnumbers[0];
                foreach ($resnumbers as $resnum) {
                    $pos = strpos($resnum, $searchnumber);
                    if ($pos !== false && $pos < strpos($resnum, ',') && $searchnumbers[1] == '') {
                        $hasalready = true;
                    }
                }
            } else {
                // It's impossible to add state.
                unset($resulttransitions[$index]);
                $wasdel = true;
            }
        } else {
            // Has transitions from previous states.
            if (in_array($realnumber, $resnumbers)) {
                $hasalready = true;
            }
            unset($clones[count($clones) - 2]);
        }
        if ($hasalready || $wasdel) {
            return true;
        } else {
            // Coping transition copies.
            if (count($clones) > 1) {
                for ($i = count($clones) - 2; $i >= 0; $i--) {
                    // TODO - add after index in array.
                    $resulttransitions[] = $clones[$i];
                }
            }
            return false;
        }
    }

    /**
     * Find cycle in the automata.
     *
     * @return flag if automata has cycle or not.
     */
    public function has_cycle() {// TODO: запоминать есть ли циклы при построении
        $newfront = array();
        $aregone = array();
        $hascycle = false;
        $states = $this->get_state_numbers();
        // Add start states to wave front.
        $oldfront = $this->get_start_states();

        // Analysis sattes from wave front.
        while (count($oldfront) != 0) {
            foreach ($oldfront as $curstate) {
                // State hasn't been  already gone.
                if (!in_array($curstate, $aregone)) {
                    // Mark as gone.
                    $aregone[] = $curstate;
                    // Get connected states if they are.
                    $connectedstates = $this->get_connected_states($curstate, 0);
                    $newfront = array_merge($newfront, $connectedstates);
                } else {
                    // Analysis intotransitions.
                    $transitions = $this->get_adjacent_transitions($curstate, false);
                    foreach ($transitions as $tran) {
                        // Transition has come from state which is far in automata.
                        if ($states[$tran->from] > $states[$curstate]) {
                            $hascycle = true;
                        }
                    }
                }
            }
            $oldfront = $newfront;
            $newfront = array();
        }
        return $hascycle;
    }

    /**
     * Set right start and end states after before completing branches.
     *
     * @param fa object automaton taken part in intersection.
     * @param anotherfa object automaton second automaton taken part in intersection.
     */
    public function set_start_end_states_before_coping($fa, $anotherfa) {
        // Get nessesary data.
        $faends = $fa->get_end_states();
        $anotherfaends = $anotherfa->get_end_states();
        $fastarts = $fa->get_start_states();
        $anotherfastarts = $anotherfa->get_start_states();
        $fastates = $fa->get_state_numbers();
        $anotherfastates = $anotherfa->get_state_numbers();
        $states = $this->get_state_numbers();
        $workstatessecond = array();
        // Set right start and end states.
        foreach ($states as $statenum) {
            $workstatessecond = array();
            // Get states from first and second automata.
            $resnum = preg_replace('/,{2,}/', ',', $statenum);
            $statecount = substr_count($resnum, ',');
            $numbers = explode(',', $resnum, $statecount+1);
            $workstate1 = null;
            if ($numbers[0] !== '') {
                $workstate1 = array_search($numbers[0], $fastates);
            }
            unset($numbers[0]);
            foreach ($numbers as $secnum) {
                if ($secnum != '') {
                    foreach ($anotherfastates as $num) {
                        if (strpos($secnum, $num) === 0 || $secnum == $num) {
                            $workstatessecond[] = array_search($num, $anotherfastates);
                        }
                    }
                }
            }
            $state = array_search($statenum, $this->statenumbers);
            $hasstartstate = false;
            $hasendstate = false;
            foreach ($workstatessecond as $number) {
                if (in_array($number, $anotherfastarts)) {
                    $hasstartstate = true;
                }
                if (in_array($number, $anotherfaends)) {
                    $hasendstate = true;
                }
            }
            // Set start states.
            $isfirststart = $workstate1 !== null && in_array($workstate1, $fastarts);
            $issecstart = $hasstartstate;
            if (($isfirststart || $issecstart) && count($this->get_adjacent_transitions($state, false)) == 0) {
                $this->add_start_state(array_search($statenum, $this->statenumbers));
            }
            // Set end states.
            $isfirstend = $workstate1 !== null && in_array($workstate1, $faends);
            $issecend = $hasendstate;
            if (($isfirstend || $issecend) /*&& count($this->get_adjacent_transitions($state, true)) == 0*/) {
                $this->add_end_state(array_search($statenum, $this->statenumbers));
            }
        }
    }

    /**
     * Set right start and end states after inetrsection two automata.
     *
     * @param fa object automaton taken part in intersection.
     * @param anotherfa object automaton second automaton taken part in intersection.
     */
    public function set_start_end_states_after_intersect($fa, $anotherfa) {
        // Get nessesary data.
        $faends = $fa->get_end_states();
        $anotherfaends = $anotherfa->get_end_states();
        $fastarts = $fa->get_start_states();
        $anotherfastarts = $anotherfa->get_start_states();
        $fastates = $fa->get_state_numbers();
        $anotherfastates = $anotherfa->get_state_numbers();
        $states = $this->get_state_numbers();
        // Set right start and end states.
        foreach ($states as $statenum) {
            $workstatessecond = array();
            // Get states from first and second automata.
            $resnum = preg_replace('/,{2,}/', ',', $statenum);
            $statecount = substr_count($resnum, ',');
            $numbers = explode(',', $resnum, $statecount+1);
            $workstate1 = null;
            if ($numbers[0] !== '') {
                $workstate1 = array_search($numbers[0], $fastates);
            }
            unset($numbers[0]);
            foreach ($numbers as $secnum) {
                if ($secnum != '') {
                    foreach ($anotherfastates as $num) {
                        if (strpos($secnum, $num) === 0 || $secnum == $num) {
                            $workstatessecond[] = array_search($num, $anotherfastates);
                        }
                    }
                }
            }
            $hasstartstate = false;
            $isloopstate = false;
            foreach ($fa->loopstates as $stateloops) {
                if ($workstate1 !== null && $workstate1 !== false && in_array($workstate1, $stateloops))  {
                    $isloopstate = true;
                }
            }
            if ($workstate1 !== null && $workstate1 !== false && (array_key_exists($workstate1, $fa->innerautomata) || $isloopstate)) {
                $hasendstate = false;
                foreach ($workstatessecond as $number) {
                    if (in_array($number, $anotherfaends)) {
                        $hasendstate = true;
                    }
                }
            } else {
                $hasendstate = true;
                foreach ($workstatessecond as $number) {
                    if (!in_array($number, $anotherfaends)) {
                        $hasendstate = false;
                    }
                }
            }
            foreach ($workstatessecond as $number) {
                if (in_array($number, $anotherfastarts)) {
                    $hasstartstate = true;
                }
            }
            // Set start states.
            $isfirststart = ($workstate1 !== null && in_array($workstate1, $fastarts)) || $workstate1 === null;
            $issecstart = $hasstartstate || count($workstatessecond) == 0;
            if ($isfirststart && $issecstart) {
                $this->add_start_state(array_search($statenum, $this->statenumbers));
            }
            // Set end states.
            $isfirstend = ($workstate1 !== null && in_array($workstate1, $faends)) || $workstate1 === null;
            $issecend = $hasendstate || count($workstatessecond) == 0;
            if ($isfirstend && $issecend) {
                $this->add_end_state(array_search($statenum, $this->statenumbers));
            }
        }
    }

    /**
     * Return count of states from second automata which includes state from intersection.
     *
     * @param anotherfa object automaton second automaton taken part in intersection.
     * @param state id of state from intersection for counting.
     */
    public function get_second_numbers_count($anotherfa, $state) {
        $count = 0;
        $numbers = $this->get_state_numbers();
        $anotherfanumbers = $anotherfa->get_state_numbers();
        $realnum = $numbers[$state];
        $realsecond = explode(',', $realnum, 2);
        $realsecond = $realsecond[1];
        foreach ($anotherfanumbers as $curnum) {
            if (strpos($realsecond, $curnum) !== false || $realsecond == $curnum) {
                $count++;
            }
        }
        return $count;
    }

    private function find_state($state) {
        foreach ($this->statenumbers as $key => $curstate) {
            $statenumber = rtrim($curstate, ',');
            $statenumber = ltrim($statenumber, ',');
            if ($statenumber == $state) {
                return $key;
            }
        }
        return false;
    }

    /**
     * Find intersection part of automaton in case of intersection it with another one.
     *
     * @param anotherfa object automaton to intersect.
     * @param result object automaton to write intersection part.
     * @param start array of states of $this automaton with which to start intersection.
     * @param direction boolean intersect by superpose start or end state of anotherfa with stateindex state.
     * @return result automata.
     */
    public function get_intersection_part($anotherfa, &$result, $start, $direction, $interstates) {
        $oldfront = array();
        $newfront = array();
        $clones = array();
        $possibleend = array();
        $workstatessecond = array();
        $oldfront = $start;
        $wavenumber = 0;
        $anotherfaendstates = $anotherfa->get_end_states();
        $anotherfastartstates = $anotherfa->get_start_states();
        // Work with each state.
        while (!empty($oldfront)) {
            foreach ($oldfront as $curstate) {
                $workstatessecond = array();
                // Get states from first and second automata.
                $secondnumbers = $anotherfa->get_state_numbers();
                $resnumbers = $result->get_state_numbers();
                $resultnumber = $resnumbers[$curstate];
                $resultnumber = preg_replace('/,{2,}/', ',', $resultnumber);
                $statecount = substr_count($resultnumber, ',');
                $numbers = explode(',', $resultnumber, $statecount+1);
                $workstate1 = $this->find_state($numbers[0]);
                unset($numbers[0]);
                foreach ($numbers as $secnum) {
                    foreach ($secondnumbers as $num) {
                        if (strpos($secnum, $num) === 0 || $secnum == $num) {
                            $workstatessecond[] = array_search($num, $secondnumbers);
                        }
                    }
                }
                // Get transitions for intersection.
                $intertransitions1 = $this->get_transitions_for_intersection($workstate1, $direction);

                foreach ($intertransitions1 as &$tran) {
                    if ($tran->is_eps() && $tran->origin === transition::ORIGIN_TRANSITION_SECOND) {
                        unset($tran);
                    }
                }
                $to = null;
                $from = null;

                foreach ($workstatessecond as $workstate) {
                    if ($direction == 0) {
                        if (in_array($workstate, $anotherfaendstates) && count($workstatessecond) !== 1) {
                            unset($workstatessecond[array_search($workstate, $workstatessecond)]);
                        }
                    } else {
                        if (in_array($workstate, $anotherfastartstates) && count($workstatessecond) !== 1) {
                            unset($workstatessecond[array_search($workstate, $workstatessecond)]);
                        }
                    }
                }
                $workstatessecond = array_values($workstatessecond);
                // Get transitions for intersection from each second automaton.
                if (count($workstatessecond) > 1) {
                    $firstforinter = $anotherfa->get_transitions_for_intersection($workstatessecond[0], $direction);
                    $i = 1;
                    while ($i < count($workstatessecond)) {
                        $resforinter = array();
                        $secondforinter = $anotherfa->get_transitions_for_intersection($workstatessecond[$i], $direction);
                        foreach ($firstforinter as $first) {
                            foreach ($secondforinter as $second) {
                                $intersection = $first->intersect($second);
                                if ($intersection !== null) {
                                    if ($from === null) {
                                        $from = $result->get_inter_state($anotherfa->statenumbers[$first->from], $anotherfa->statenumbers[$second->from]);
                                    } else {
                                        $from = $result->get_inter_state($from, $anotherfa->statenumbers[$second->from]);
                                    }
                                    if ($to === null) {
                                        $to = $result->get_inter_state($anotherfa->statenumbers[$first->to], $anotherfa->statenumbers[$second->to]);
                                    } else {
                                        $to = $result->get_inter_state($to, $anotherfa->statenumbers[$second->to]);
                                    }
                                    $resforinter[] = $intersection;
                                }
                            }
                        }
                        $firstforinter = $resforinter;
                        $i++;
                    }
                    $intertransitions2 = $resforinter;
                } else {
                    $workstate2 = $workstatessecond[0];
                    $intertransitions2 = $anotherfa->get_transitions_for_intersection($workstate2, $direction);
                }
                // Intersect all possible transitions.
                $resulttransitions = array();
                $resultnumbers = array();
                foreach ($intertransitions1 as $intertran1) {
                    foreach ($intertransitions2 as $intertran2) {
                        $resulttran = $intertran1->intersect($intertran2);
                        if ($resulttran !== null) {
                            if ($direction == 0) {
                                if ($to === null) {
                                    $resnumber = $result->get_inter_state($this->statenumbers[$intertran1->to], $anotherfa->statenumbers[$intertran2->to]);
                                } else {
                                    $resnumber = $result->get_inter_state($this->statenumbers[$intertran1->to], $to);
                                }
                                $loopstates = array();
                                foreach ($interstates as $interstate) {
                                    if (array_key_exists($interstate, $this->loopstates)) {
                                        $loopstates = $this->loopstates[$interstate];
                                    }
                                }
                                if ((in_array($intertran1->to, $interstates) || in_array($intertran1->to, $loopstates)) && $wavenumber && $intertran1->to !== $intertran1->from) {
                                    foreach ($anotherfastartstates as $start) {
                                        $resnumber = $result->get_inter_state($resnumber, $start);
                                        $resultnumbers[] = $resnumber;
                                        $resulttransitions[] = $resulttran;
                                    }
                                } else {
                                    $resultnumbers[] = $resnumber;
                                    $resulttransitions[] = $resulttran;
                                }

                            } else {
                                if ($from === null) {
                                    $resnumber = $result->get_inter_state($this->statenumbers[$intertran1->from], $anotherfa->statenumbers[$intertran2->from]);
                                } else {
                                    $resnumber = $result->get_inter_state($this->statenumbers[$intertran1->from], $from);
                                }
                                $loopstates = array();
                                foreach ($interstates as $interstate) {
                                    if (array_key_exists($interstate, $this->loopstates)) {
                                        $loopstates = $this->loopstates[$interstate];
                                    }
                                }
                                if ((in_array($intertran1->from, $interstates) || in_array($intertran1->from, $loopstates)) && !$wavenumber && $intertran1->to !== $intertran1->from) {
                                    foreach ($anotherfaendstates as $end) {
                                        $resnumber = $result->get_inter_state($resnumber, $end);
                                        $resultnumbers[] = $resnumber;
                                        $resulttransitions[] = $resulttran;
                                    }
                                } else {
                                    $resultnumbers[] = $resnumber;
                                    $resulttransitions[] = $resulttran;
                                }
                            }
                        } else {
                            $result->breakpos = $intertran2->pregleaf->position;
                        }
                    }
                }
                // Analysis result transitions.
                for ($i = 0; $i < count($resulttransitions); $i++) {
                    // Change state if there are many inner states.
                    // Get start/end states.
                    $statecount = substr_count($resultnumbers[$i], ',');
                    $numbers = explode(',', $resultnumbers[$i], $statecount+1);
                    $newnumber = array();
                    $newnumber[] = $numbers[0];
                    unset($numbers[0]);

                    foreach ($numbers as $num) {
                        if ($direction == 0) {
                            if (!in_array($num, $anotherfaendstates)) {
                                $newnumber[] = $num;
                            }
                        } else {
                            if (!in_array($num, $anotherfastartstates)) {
                                $newnumber[] = $num;
                            }
                        }
                    }

                    // Get new number.
                    $newnumberstr = implode(",", $newnumber);
                    if (array_search($newnumber, $resnumbers) !== false) {
                        $result->change_real_number(array_search($newnumber, $resnumbers), $resultnumbers[$i]);
                    }
                    // Search state with the same number in result automata.
                    $searchstate = array_search($resultnumbers[$i], $resnumbers);

                    // State was found.
                    if ($searchstate !== false) {
                        $resnumbers = $result->get_state_numbers();
                        $newstate = array_search($resultnumbers[$i], $resnumbers);
                    } else {
                        // State wasn't found.
                        $newstate = $result->add_state($resultnumbers[$i]);
                        $newfront[] = $newstate;
                    }
                    $resnumbers = $result->get_state_numbers();
                    // Change transitions.
                    if ($direction == 0) {
                        $resulttransitions[$i]->from = $curstate;
                        $resulttransitions[$i]->to = $newstate;
                    } else {
                        $resulttransitions[$i]->from = $newstate;
                        $resulttransitions[$i]->to = $curstate;
                    }
                    $resulttransitions[$i]->redirect_merged_transitions();
                    if (!$result->has_same_transition($resulttransitions[$i]))
                    {
                        $result->add_transition($resulttransitions[$i]);
                    }
                }
                // Removing arrays.
                $intertransitions1 = array();
                $intertransitions2 = array();
                $resulttransitions = array();
                $resultnumbers = array();
            }
            $possibleend = $oldfront;
            $oldfront = $newfront;
            $newfront = array();
            $wavenumber++;
        }

        // Set right start and end states.
        if ($direction == 0) {
            // Cleaning end states.
            $result->remove_all_end_states();
            foreach ($possibleend as $end) {
                $result->add_end_state($end);
            }
        } else {
            // Cleaning start states.
            $startstates = $result->get_start_states();
            foreach ($startstates as $startstate) {
                if ($result->is_full_intersect_state($startstate)) {
                    $result->remove_start_state($startstate);
                }
            }
            // Add new start states.
            $state = $result->get_inter_state(0, 0);
            $resnumbers = $result->get_state_numbers();
            $state = array_search($state, $resnumbers);
            if ($state) {
                $result->add_start_state($state);
            } else {
                foreach ($possibleend as $start) {
                    $result->add_start_state($start);
                }
            }
        }

        // Get cycle if it's nessessary.
        $newfront = array();
        $resultnumbers = $result->get_state_numbers();
        return $result;
    }

    /**
     * Lead all end states to one with epsilon-transitions.
     */
    public function lead_to_one_end() {
        $newleaf = new \qtype_preg_leaf_meta(\qtype_preg_leaf_meta::SUBTYPE_EMPTY);
        $i = count($this->get_end_states()) - 1;
        $endstates = array_values($this->get_end_states());
        if ($i > 0) {
            $to = $endstates[0];
        }
        // Connect end states with first while automata has only one end state.
        while ($i > 0) {
            $exendstate = $endstates[$i];
            $epstran = new transition ($exendstate, $newleaf, $to);
            $this->add_transition($epstran);
            $i--;
            $this->remove_end_state($exendstate);
        }
        /*$i = count($this->get_start_states()) - 1;
        if ($i > 0) {
            $from = $this->fastartstates[0][0];
        }
        // Connect end states with first while automata has only one end state.
        while ($i > 0) {
            $exendstate = $this->fastartstates[0][$i];
            $epstran = new transition ($from, $newleaf, $exendstate);
            $this->add_transition($epstran);
            $i--;
            $this->remove_start_state($exendstate);
        }*/
    }

    /**
     * Intersect automaton with another one.
     *
     * @param anotherfa object automaton to intersect.
     * @param stateindex array of string with real number of state of $this automaton with which to start intersection.
     * @param isstart boolean intersect by superpose start or end state of anotherfa with stateindex state.
     * @return result automata.
     */
    public function intersect($anotherfa, $stateindex, $isstart) {
        // Check right direction.
        if ($isstart != 0 && $isstart !=1) {
            throw new \qtype_preg_exception('intersect error: Wrong direction');
        }
        // Prepare automata for intersection.
        $this->remove_unreachable_states();
        $anotherfa->remove_unreachable_states();
        $numbers = array();
        foreach ($stateindex as $index) {
            $number = array_search($index, $this->statenumbers);
            if ($number !== false && !in_array($number, $numbers)) {
                $numbers[] = $number;
            }
            //$numbers[] = $number;
        }
        foreach ($numbers as $number) {
            if (array_key_exists($number, $this->intersectedtransitions)) {
                $incoming = $this->get_adjacent_transitions($number, false);
                $intransitions = $this->get_transitions_for_intersection($number,1);
                $outgoing = $this->get_adjacent_transitions($number, true);
                $outtransitions = $this->get_transitions_for_intersection($number,0);
                if (count($incoming) == count($intransitions) && count($outgoing) == count($outtransitions)) {
                    unset($this->intersectedtransitions[$number]);
                }
            }
        }

        $this->to_origin(transition::ORIGIN_TRANSITION_FIRST);
        $anotherfa->to_origin(transition::ORIGIN_TRANSITION_SECOND);
        $result = $this->intersect_fa($anotherfa, $numbers, $isstart);
        $result->remove_unreachable_states();
        if (empty($result->adjacencymatrix)) {
            throw new \qtype_preg_empty_fa_exception('', $result->breakpos);
        }
        $result->remove_wrong_end_states();
        $result->lead_to_one_end();
        $result->merge_after_intersection();
        //$result->merge_end_transitions();
        $result->handler = $this->handler;
        $result->update_intersection_states($this);
        $result->states_numbers_to_ids();
        return $result;
    }

    private function to_origin($origin) {
        foreach ($this->adjacencymatrix as $from) {
            foreach ($from as $to) {
                foreach ($to as $transition) {
                    $transition->origin = $origin;
                }
            }
        }
    }

    private function merge_after_intersection() {
        $startstates = $this->get_start_states();
        $front = $startstates;
        $stackitem = array();
        $success = false;
        $newfront = array();
        $states = array();
        while (!empty($front)) {
            foreach ($front as $state) {
                if (!in_array($state, $states)) {
                    $transitions = $this->get_adjacent_transitions($state, true);
                    foreach ($transitions as $transition) {
                        if (($transition->is_start_anchor() || $transition->is_end_anchor() || $transition->is_eps())) {
                            $stackitem['end'] = $this->get_end_states();
                            $success = $this->merge_transitions($transition, $stackitem) || $success;
                        }
                        $newfront[] = $transition->to;
                    }
                    $states[] = $state;
                }
            }

            $front = $newfront;
            $newfront = array();
        }
        if ($success) {
            $this->merge_after_intersection();
        }
    }

    /**
     * Merging transitions without merging states.
     *
     * @param del - uncapturing transition for deleting.
     */
    public function merge_transitions($del, &$stackitem, $back = null) {
        $clonetransitions = array();
        $tagsets = array();
        $fromstates = array();
        $tostates = array();
        $oppositetransitions = array();
        $intersectedtransitions = array();
        $intersection = null;
        $transitionadded = false;
        $flag = new \qtype_preg_charset_flag();
        $flag->set_data(\qtype_preg_charset_flag::TYPE_SET, new \qtype_poasquestion\utf8_string("\n"));
        $charset = new \qtype_preg_leaf_charset();
        $charset->flags = array(array($flag));
        $charset->userinscription = array(new \qtype_preg_userinscription("\n"));
        $righttran = new transition(0, $charset, 1);
        $outtransitions = $this->get_adjacent_transitions($del->to, true);
        if (!is_array($stackitem['end'])) {
            $endstates = array($stackitem['end']);
        } else {
            $endstates = $stackitem['end'];
        }

        // Cycled last states.
        if (!$del->consumeschars) {
            return false;
        }

        if ($back === null) {

            // Get transitions for merging back.
            if (($del->is_unmerged_assert() && $del->is_start_anchor()) || ($del->is_eps() && in_array($del->to, $endstates))) {
                $transitions = $this->get_adjacent_transitions($del->from, false);
                $tostates[] = $del->to;
                $back = true;
            } else {
                // Get transitions for merging forward.
                $transitions = $this->get_adjacent_transitions($del->to, true);
                $fromstates[] = $del->from;
                $back = false;
            }
        } else {

            if ($back) {
                $transitions = $this->get_adjacent_transitions($del->from, false);
            } else {
                $transitions = $this->get_adjacent_transitions($del->to, true);
            }
        }
        $realtransitions = array();
        // Changing leafs in case of merging.
        foreach ($transitions as $transition) {
            if (!($transition->from === $transition->to && ($transition->is_unmerged_assert() || $transition->is_eps()))) {
                $tran = clone $transition;
                $delclone = clone $del;
                $delclone->mergedafter = array();
                $delclone->mergedbefore = array();
                $delclonemerged = clone $del;
                $tran->loopsback = $transition->loopsback || $del->loopsback;
                $tran->greediness = transition::min_greediness($tran->greediness, $del->greediness);
                $merged = array_merge($delclonemerged->mergedbefore, array($delclone), $delclonemerged->mergedafter);
                // Work with tags.
                if (!$tran->consumeschars && $del->is_eps() && $del->from !== $del->to && $tran->origin !== transition::ORIGIN_TRANSITION_SECOND) {
                    if ($back) {
                        $tran->mergedbefore = array_merge($tran->mergedbefore, $merged);
                    } else {
                        $tran->mergedafter = array_merge($merged, $tran->mergedafter);
                    }
                } else if ($back) {
                    $tran->mergedafter = array_merge($tran->mergedafter, $merged);
                } else {
                    $tran->mergedbefore = array_merge($merged, $tran->mergedbefore);
                }

                $clonetransitions[] = $tran;
                $realtransitions[] = $transition;
            }

        }
        // Has deleting or changing transitions.
        if (empty($transitions)) {
            return false;
        }

        $breakpos = null;
        $newkeys = array();
        $isforintersection = false;
        if (!$back) {
            foreach ($clonetransitions as &$tran) {
                $tostates[] = $tran->to;
                if ($del->is_end_anchor() && !$tran->is_unmerged_assert() && !$tran->is_eps()) {
                    $righttran->pregleaf->position = $tran->pregleaf->position;
                    $intersection = $tran->intersect($righttran);
                    if ($intersection !== null) {
                        $tran->pregleaf = $intersection->pregleaf;
                    }
                }

                if (($del->pregleaf->subtype !== \qtype_preg_leaf_assert::SUBTYPE_SMALL_ESC_Z && $intersection !== null) ||
                    !$del->is_end_anchor() || $tran->is_unmerged_assert() || $tran->is_eps()) {
                    $tran->from = $del->from;
                    $tran->redirect_merged_transitions();
                    $this->add_transition($tran);
                    // If exists than remember only marked as intersected.
                    if (array_key_exists($del->to, $this->intersectedtransitions)) {
                        if (in_array($del, $this->intersectedtransitions[$del->to])) {
                           $intersectedtransitions[] = $tran;
                        }
                    } else {
                        $intersectedtransitions[] = $tran;
                    }
                    if ($tran->isforintersection) {
                        $isforintersection = true;
                    }
                    $newkeys[] = $tran->to;
                    $transitionadded = true;
                } else if ($breakpos === null) {
                    $breakpos = $del->pregleaf->position->compose($tran->pregleaf->position);
                }
            }
            if ($del->isforintersection || $isforintersection) {
                $this->change_state_for_intersection($del->to, array($del->from));
                $this->change_loopstate($del->to, array($del->from));
                if  (array_key_exists($del->from, $this->innerautomata)) {

                    $this->add_intersected_transitions($del->from, $intersectedtransitions);
                }
            }
            $this->change_recursive_start_states($del->to, array($del->from));
            $this->change_recursive_end_states($del->to, $newkeys);
        } else {
            foreach ($clonetransitions as &$tran) {
                $fromstates[] = $tran->from;
                if ($del->is_start_anchor() && !$tran->is_unmerged_assert() && !$tran->is_eps()) {
                    $righttran->pregleaf->position = $tran->pregleaf->position;
                    $intersection = $tran->intersect($righttran);
                    if ($intersection !== null) {
                        $tran->pregleaf = $intersection->pregleaf;
                    }
                }
                if (($del->pregleaf->subtype !== \qtype_preg_leaf_assert::SUBTYPE_ESC_A && $intersection !== null) ||
                    !$del->is_start_anchor() || $tran->is_unmerged_assert() || $tran->is_eps()) {
                    $tran->to = $del->to;
                    $tran->redirect_merged_transitions();
                    $newkeys[] = $tran->from;
                    // If exists than remember only marked as intersected.
                    if (array_key_exists($del->from, $this->intersectedtransitions)) {
                        if (in_array($del, $this->intersectedtransitions[$del->from])) {
                           $intersectedtransitions[] = $tran;
                        }
                    } else {
                        $intersectedtransitions[] = $tran;
                    }
                    if ($tran->isforintersection) {
                        $isforintersection = true;
                    }
                    $this->add_transition($tran);
                    $transitionadded = true;
                } else if ($breakpos === null) {
                    $breakpos = $tran->pregleaf->position->compose($del->pregleaf->position);
                }
            }
            unset($tran);
            if ($del->isforintersection || $isforintersection) {
                $this->change_state_for_intersection($del->from, array($del->to));
                $this->change_loopstate($del->from, array($del->to));
                if  (array_key_exists($del->to, $this->innerautomata)) {
                    $this->add_intersected_transitions($del->to, $intersectedtransitions);
                }
            }
            $this->change_recursive_end_states($del->from, array($del->to));
            $this->change_recursive_start_states($del->from, $newkeys);
        }

        if (!($del->is_end_anchor() && in_array($del->to, $endstates)) && !($transition->from === $transition->to && ($transition->is_unmerged_assert() || $transition->is_eps()))) {
            $this->remove_transition($del);
        }

        $hastransitions = $this->check_connection($fromstates, $tostates);
        $stackitem['breakpos'] = ($transitionadded || $hastransitions) ? null : $breakpos;

        return true;
    }

    public function check_connection($fromstates, $tostates) {
        foreach ($fromstates as $from) {
            foreach ($tostates as $to) {
                if ($this->has_transition($from, $to)) {
                    return true;
                }
            }
        }
        return false;
    }

    private function remove_wrong_end_states() {
        $endstates = array_values($this->get_end_states());

        foreach ($endstates as $end) {
            $reached = array();
            $iswrong = true;
            $front = array($end);
            while (!empty($front)) {
                $curstate = array_pop($front);
                if (in_array($curstate, $reached)) {
                    continue;
                }
                $reached[] = $curstate;
                $transitions = $this->get_adjacent_transitions($curstate, false);
                foreach ($transitions as $transition) {
                    $front[] = $transition->from;
                    if ($transition->origin !== transition::ORIGIN_TRANSITION_SECOND) {
                        $iswrong = false;
                    }
                }

            }
            if ($iswrong) {
                $this->remove_end_state($end);
            }
        }
    }

    private function update_intersection_states($sourcefa) {
        $newstates = $this->get_state_numbers();
        foreach ($sourcefa->innerautomata as $state => $inner) {
            foreach ($newstates as $newstate) {
                $numbers = explode(',', $newstate, 2);
                if ($numbers[0] !== '' && $numbers[0] == $state) {
                    $this->innerautomata[$this->get_id_by_state_number($newstate)] = $inner;
                }
            }
        }
    }

    private function get_id_by_state_number($number) {
        return array_search($number, $this->statenumbers);
    }

    /**
     * Complete branches ends with state, one number of which isn't start or end state depending on direction.
     *
     * @param fa object automaton to check start/end states.
     * @param anotherfa object automaton check start/end states.
     * @param durection direction of coping.
     */
    public function complete_non_intersection_branches($fa, $anotherfa, $direction) {

        $front = array();
        $secondnumbers = $anotherfa->get_state_numbers();
        $firstnumbers = $fa->get_state_numbers();
        $numbersfromsecond = array();
        // Find uncompleted branches.
        if ($direction == 0) {
            $states = $this->get_end_states();
            foreach ($states as $state) {
                if ($this->is_full_intersect_state($state)) {
                    $front[] = $state;
                }
            }
            foreach ($front as $state) {
                $numbersfromsecond = array();
                // Get states from first and second automata.
                $resultnumber = preg_replace('/,{2,}/', ',', $this->statenumbers[$state]);
                $statecount = substr_count($resultnumber, ',');
                $numbers = explode(',', $resultnumber, $statecount+1);
                $workstate1 = $fa->find_state($numbers[0]);
                $workstate2 = null;
                $work2 = null;
                unset($numbers[0]);
                foreach ($numbers as $secnum) {
                    if ($secnum !== '') {
                        foreach ($secondnumbers as $num) {
                            if (strpos($secnum, $num) === 0 || $secnum == $num) {
                                $numbersfromsecond[] = $anotherfa->find_state($num);
                            }
                        }
                    }
                }
                $hasendstate = false;
                $noendstate = false;
                foreach ($numbersfromsecond as $number) {
                    if ($anotherfa->has_endstate($number)) {
                        $workstate2 = $number;
                        $hasendstate = true;
                    } else {
                        $noendstate = true;
                        $work2 = $number;
                    }
                }
                $oldfront = array();
                if (!$fa->has_endstate($workstate1) && $hasendstate && count($numbersfromsecond) < 2) {
                    $transitions = $fa->get_adjacent_transitions($workstate1, true);
                    foreach ($transitions as $tran) {
                        $oldfront[] = $tran->to;
                    }
                    $this->copy_modify_branches($fa, $oldfront, null, $direction);
                    // Connect last state of intersection and copied branch.
                    foreach ($transitions as $tran) {
                        // Get number of copied state.
                        $number = $firstnumbers[$tran->to];
                        $number = trim($number, '()');
                        $last = substr($number, -1);
                        if ($last != ',') {
                            $number = $number . ',';
                        }
                        $copiedstate = array_search($number, $this->statenumbers);
                        // Add transition.
                        //$addtran = new transition($state, $tran->pregleaf, $copiedstate, $tran->origin, $tran->consumeschars);
                        $addtran = clone $tran;
                        $addtran->from = $state;
                        $addtran->to = $copiedstate;
                        $addtran->redirect_merged_transitions();
                        if ($copiedstate !== false) {
                            $this->add_transition($addtran);
                        }
                    }
                }
                $oldfront = array();
                if ($noendstate && $fa->has_endstate($workstate1)) {
                    $transitions = $anotherfa->get_adjacent_transitions($work2, true);
                    foreach ($transitions as $tran) {
                        $oldfront[] = $tran->to;
                    }
                    $this->copy_modify_branches($anotherfa, $oldfront, null, $direction, transition::ORIGIN_TRANSITION_SECOND);

                    // Connect last state of intersection and copied branch.
                    foreach ($transitions as $tran) {
                        // Get number of copied state.
                        $number = $secondnumbers[$tran->to];
                        $number = trim($number, '()');
                        $first = substr($number, 1);
                        if ($first != ',') {
                            $number = ',' . $number;
                        }
                        $copiedstate = array_search($number, $this->statenumbers);
                        // Add transition.
                        //$addtran = new transition($state, $tran->pregleaf, $copiedstate, $tran->origin, $tran->consumeschars);
                        $addtran = clone $tran;
                        $addtran->from = $state;
                        $addtran->to = $copiedstate;
                        $addtran->origin = transition::ORIGIN_TRANSITION_SECOND;
                        $addtran->redirect_merged_transitions();
                        $addtran->consumeschars = false;
                        if ($copiedstate !== false) {
                            $this->add_transition($addtran);
                        }
                    }
                }
                // Copy cycled transitions.
                if ($hasendstate && !$noendstate && $workstate1 !== false && $fa->has_endstate($workstate1)) {
                    $transitions = $anotherfa->get_adjacent_transitions($workstate2, true);
                    foreach ($transitions as $transition) {
                        if ($transition->from === $transition->to) {
                            // Get number of copied state.
                            $number = $secondnumbers[$transition->to];
                            $number = trim($number, '()');
                            $last = substr($number, -1);
                            if ($last != ',') {
                                $number = $number . ',';
                            }
                            $copiedstate = array_search($number, $this->statenumbers);
                            if ($copiedstate === false) {
                                $copiedstate = $this->add_state($number);
                                $clonetran = clone $transition;
                                $clonetran->from = $state;
                                $clonetran->to = $copiedstate;
                                $clonetran->redirect_merged_transitions();
                                $clonetran->consumeschars = false;
                                $this->add_transition($clonetran);
                            }
                            $trancycle = clone $transition;
                            $trancycle->consumeschars = false;
                            $trancycle->from = $copiedstate;
                            $trancycle->to = $copiedstate;
                            $trancycle->redirect_merged_transitions();
                            $this->add_transition($trancycle);
                        }
                    }
                    $transitions = $fa->get_adjacent_transitions($workstate1, true);
                    foreach ($transitions as $transition) {
                        if ($transition->from === $transition->to) {
                            // Get number of copied state.
                            $number = $firstnumbers[$transition->to];
                            $number = trim($number, '()');
                            $first = substr($number, 1);
                            if ($first != ',') {
                                $number = ',' . $number;
                            }
                            $copiedstate = array_search($number, $this->statenumbers);
                            if ($copiedstate === false) {
                                $copiedstate = $this->add_state($number);
                                $clonetran = clone $transition;
                                $clonetran->from = $state;
                                $clonetran->to = $copiedstate;
                                $clonetran->redirect_merged_transitions();
                                $this->add_transition($clonetran);
                            }
                            $trancycle = clone $transition;
                            $trancycle->from = $copiedstate;
                            $trancycle->to = $copiedstate;
                            $trancycle->redirect_merged_transitions();
                            $this->add_transition($trancycle);
                        }
                    }
                }
            }
        } else {
            $states = $this->get_start_states();
            foreach ($states as $state) {
                if ($this->is_full_intersect_state($state)) {
                    $front[] = $state;
                }
            }
            foreach ($front as $state) {
                $numbersfromsecond = array();
                $resultnumber = preg_replace('/,{2,}/', ',', $this->statenumbers[$state]);
                $statecount = substr_count($resultnumber, ',');
                $numbers = explode(',', $resultnumber, $statecount+1);
                $workstate1 = $fa->find_state($numbers[0]);
                $workstate2 = null;
                $work2 = null;
                unset($numbers[0]);
                foreach ($numbers as $secnum) {
                    if ($secnum !== '') {
                        foreach ($secondnumbers as $num) {
                            if (strpos($secnum, $num) === 0 || $secnum == $num) {
                                $numbersfromsecond[] = $anotherfa->find_state($num);
                            }
                        }
                    }
                }
                $hasstartstate = false;
                $nostartstate = false;
                foreach ($numbersfromsecond as $number) {
                    if ($anotherfa->has_startstate($number)) {
                        $workstate2 = $number;
                        $hasstartstate = true;
                    } else {
                        $nostartstate = true;
                        $work2 = $number;
                    }
                }

                $oldfront = array();
                if (!$fa->has_startstate($workstate1) && $hasstartstate && count($numbersfromsecond) < 2) {
                    $transitions = $fa->get_adjacent_transitions($workstate1, false);
                    foreach ($transitions as $tran) {
                        $oldfront[] = $tran->from;
                    }
                    $this->copy_modify_branches($fa, $oldfront, null, $direction);
                    // Connect last state of intersection and copied branch.
                    foreach ($transitions as $tran) {
                        // Get number of copied state.
                        $number = $firstnumbers[$tran->from];
                        $number = trim($number, '()');
                        $last = substr($number, -1);
                        if ($last != ',') {
                            $number = $number . ',';
                        }
                        $copiedstate = array_search($number, $this->statenumbers);
                        // Add transition.
                        //$addtran = new transition($copiedstate, $tran->pregleaf, $state);
                        $addtran = clone $tran;
                        $addtran->to = $state;
                        $addtran->from = $copiedstate;
                        $addtran->redirect_merged_transitions();
                        $this->add_transition($addtran);
                    }
                }
                $oldfront = array();
                if ($nostartstate && $fa->has_startstate($workstate1)) {
                    $transitions = $anotherfa->get_adjacent_transitions($work2, false);
                    $oldfront = array();
                    foreach ($transitions as $tran) {
                        $oldfront[] = $tran->from;
                    }
                    $this->copy_modify_branches($anotherfa, $oldfront, null, $direction, transition::ORIGIN_TRANSITION_SECOND);
                    // Connect last state of intersection and copied branch.
                    foreach ($transitions as $tran) {
                        // Get number of copied state.
                        $number = $secondnumbers[$tran->from];
                        $number = trim($number, '()');
                        $first = substr($number, 1);
                        if ($first != ',') {
                            $number = ',' . $number;
                        }
                        $copiedstate = array_search($number, $this->statenumbers);
                        // Add transition.
                        //$addtran = new transition($copiedstate, $tran->pregleaf, $state, $tran->origin, $tran->consumeschars);
                        $addtran = clone $tran;
                        $addtran->to = $state;
                        $addtran->from = $copiedstate;
                        $addtran->redirect_merged_transitions();
                        if ($tran->origin == transition::ORIGIN_TRANSITION_SECOND) {
                            $addtran->consumeschars = false;
                        }
                        $this->add_transition($addtran);
                    }
                }
                // Copy cycled transitions.
                if ($hasstartstate && !$nostartstate && $fa->has_startstate($workstate1)) {
                    $transitions = $anotherfa->get_adjacent_transitions($workstate2, false);
                    foreach ($transitions as $transition) {
                        if ($transition->from === $transition->to) {
                            // Get number of copied state.
                            $number = $firstnumbers[$transition->to];
                            $number = trim($number, '()');
                            $last = substr($number, -1);
                            if ($last != ',') {
                                $number = $number . ',';
                            }
                            $copiedstate = array_search($number, $this->statenumbers);
                            if ($copiedstate === false) {
                                $copiedstate = $this->add_state($number);
                                $clonetran = clone $transition;
                                $clonetran->to = $state;
                                $clonetran->from = $copiedstate;
                                $clonetran->consumeschars = false;
                                $clonetran->redirect_merged_transitions();
                                $this->add_transition($clonetran);
                            }
                            $trancycle = clone $transition;
                            $trancycle->consumeschars = false;
                            $trancycle->from = $copiedstate;
                            $trancycle->to = $copiedstate;
                            $trancycle->redirect_merged_transitions();
                            $this->add_transition($trancycle);
                        }
                    }
                    $transitions = $fa->get_adjacent_transitions($workstate1, false);
                    foreach ($transitions as $transition) {
                        if ($transition->from === $transition->to) {
                            // Get number of copied state.
                            $number = $secondnumbers[$transition->to];
                            $number = trim($number, '()');
                            $first = substr($number, 1);
                            if ($first != ',') {
                                $number = ',' . $number;
                            }
                            $copiedstate = array_search($number, $this->statenumbers);
                            if ($copiedstate === false) {
                                $copiedstate = $this->add_state($number);
                                $clonetran = clone $transition;
                                $clonetran->to = $state;
                                $clonetran->from = $copiedstate;
                                $clonetran->redirect_merged_transitions();
                                $this->add_transition($clonetran);
                            }
                            $trancycle = clone $transition;
                            $trancycle->from = $copiedstate;
                            $trancycle->to = $copiedstate;
                            $trancycle->redirect_merged_transitions();
                            $this->add_transition($trancycle);
                        }
                    }
                }
            }
        }
    }

    /**
     * Remove flags that state was copied from all states of the automaton.
     */
    public function remove_flags_of_coping() {
        // Remove flag of coping from states of automata.
        $states = $this->get_states();
        $numbers = $this->get_state_numbers();
        foreach ($states as $statenum) {
            $backnumber = trim($numbers[$statenum], '()');
            $this->change_real_number($statenum, $backnumber);
        }
    }

    private function intersect_uncapturing_in_another_direction($anotherfa, $isstart, $result, $stop) {
        $uncapturingclone = new fa();
        $hasuncapturing = false;
        $copedstates = array();
        $newfront = array();
        $newstop = array();
        if ($isstart == 0) {
            $states = $anotherfa->get_start_states();
        } else {
            $states = $anotherfa->get_end_states();
        }

        $oldfront = $states;
        $assotiatedstates = array();

        // Get automaton only with uncapturing transitions.
        while (!empty($oldfront)) {
            foreach ($oldfront as $state) {
                if (!in_array($state, $copedstates)) {
                    if (!array_key_exists($state, $assotiatedstates)) {
                        if ($isstart == 0) {
                            $from = $uncapturingclone->add_state($state);
                            $assotiatedstates[$state] = $from;
                        } else {
                            $to = $uncapturingclone->add_state($state);
                            $assotiatedstates[$state] = $to;
                        }
                    } else {
                        if ($isstart == 0) {
                            $from = $assotiatedstates[$state];
                        } else {
                            $to = $assotiatedstates[$state];
                        }

                    }

                    if (in_array($state, $states) && $isstart == 0) {
                        $uncapturingclone->add_start_state($from);
                    } else if (in_array($state, $states)) {
                        $uncapturingclone->add_end_state($to);
                    }

                    $transitions = $anotherfa->get_adjacent_transitions($state, !$isstart);
                    foreach ($transitions as $transition) {
                        if (!$transition->consumeschars) {
                            $hasuncapturing = true;
                            if ($isstart == 0) {
                                if (!array_key_exists($transition->to, $assotiatedstates)) {
                                    $to = $uncapturingclone->add_state($transition->to);
                                    $assotiatedstates[$state] = $to;
                                } else {
                                    $to = $assotiatedstates[$transition->to];
                                }
                            } else {
                                if (!array_key_exists($transition->from, $assotiatedstates)) {
                                    $from = $uncapturingclone->add_state($transition->from);
                                    $assotiatedstates[$state] = $from;
                                } else {
                                    $from = $assotiatedstates[$transition->from];
                                }
                            }

                            $clonetran = clone $transition;
                            $clonetran->from = $from;
                            $clonetran->to = $to;
                            $clonetran->redirect_merged_transitions();
                            $clonetran->consumeschars = true;
                            $uncapturingclone->add_transition($clonetran);
                            $anotherfa->remove_transition($transition);
                            $copedstates[] = $state;
                            if ($isstart == 0) {
                                $newfront[] = $transition->to;
                            } else {
                                $newfront[] = $transition->from;
                            }
                        }
                    }
                }
            }
            $oldfront = $newfront;
            $newfront = array();
        }

        if ($hasuncapturing) {
            // Set its start/end states.
            $states = $uncapturingclone->get_states();
            foreach ($states as $state) {
                $transitions = $uncapturingclone->get_adjacent_transitions($state, !$isstart);

                // Check if there are only loopsback transitions.
                $loopsback = true;
                foreach ($transitions as $transition) {
                    if (!$transition->loopsback) {
                        $loopsback = false;
                    }
                }
                if ($loopsback) {
                    if ($isstart == 0) {
                        $uncapturingclone->add_end_state($state);
                    } else {
                        $uncapturingclone->add_start_state($state);
                    }
                }
            }
            // Set start states for coped automaton.
            $states = $result->get_states();
            foreach ($states as $state) {
                $transitionsinto = $result->get_adjacent_transitions($state, false);
                $transitionsout = $result->get_adjacent_transitions($state, true);
                // Check if there are only loopsback transitions.
                $loopsbackinto = true;
                foreach ($transitionsinto as $transition) {
                    if (!$transition->loopsback) {
                        $loopsbackinto = false;
                    }
                }
                $loopsbackout = true;
                foreach ($transitionsout as $transition) {
                    if (!$transition->loopsback) {
                        $loopsbackout = false;
                    }
                }

                if ($loopsbackinto) {
                    $result->add_start_state($state);
                }
                if ($loopsbackout) {
                    $result->add_end_state($state);
                }
            }

            // Intersect it in another direction.
            $resultpart = $result->intersect_fa($uncapturingclone, $stop, !$isstart);

            // Add this automaton as branch of result automaton.
            $resultpart->remove_all_end_states();
            $resultpart->remove_all_start_states();
            $states = $resultpart->get_states();
            foreach ($states as $state) {
                $transitionsout = $resultpart->get_adjacent_transitions($state, true);
                $transitionsinto = $resultpart->get_adjacent_transitions($state, false);
                // Check if there are only loopsback transitions.
                $loopsbackout = true;
                $loopsbackinto = true;
                foreach ($transitionsout as $transition) {
                    if (!$transition->loopsback) {
                        $loopsbackout = false;
                    }
                }
                foreach ($transitionsinto as $transition) {
                    if (!$transition->loopsback) {
                        $loopsbackinto = false;
                    }
                }
                if ($loopsbackout) {
                    $resultpart->add_end_state($state);
                }
                if ($loopsbackinto) {
                        $resultpart->add_start_state($state);
                }
            }

            $resultpart->remove_unreachable_states();
            if ($isstart == 0) {
                $oldfront = $resultpart->get_start_states();
            } else {
                $oldfront = $resultpart->get_end_states();
            }
            $copedstates = array();
            $newfront = array();
            $assotiatedstates = array();
            while (!empty($oldfront)) {
                foreach ($oldfront as $state) {
                    if (!in_array($state, $copedstates)) {
                        if (!array_key_exists($state, $assotiatedstates)) {
                            if ($isstart == 0) {
                                $from = $result->add_state($resultpart->get_state_numbers()[$state]);
                                $assotiatedstates[$state] = $from;
                            } else {
                                $to = $result->add_state($resultpart->get_state_numbers()[$state]);
                                $assotiatedstates[$state] = $to;
                            }
                        } else {
                            if ($isstart == 0) {
                                $from = $assotiatedstates[$state];
                            } else {
                                $to = $assotiatedstates[$state];
                            }
                        }

                        $copedstates[] = $state;
                        $transitions = $resultpart->get_adjacent_transitions($state, !$isstart);
                        foreach ($transitions as $transition) {
                            if ($isstart == 0) {
                                if (!array_key_exists($transition->to, $assotiatedstates)) {
                                    $to = $result->add_state($resultpart->get_state_numbers()[$transition->to]);
                                    $assotiatedstates[$transition->to] = $to;
                                } else {
                                    $to = $assotiatedstates[$transition->to];
                                }
                            } else {
                                if (!array_key_exists($transition->from, $assotiatedstates)) {
                                    $from = $result->add_state($resultpart->get_state_numbers()[$transition->from]);
                                    $assotiatedstates[$transition->from] = $from;
                                } else {
                                    $from = $assotiatedstates[$transition->from];
                                }
                            }
                            $clonetran = clone $transition;
                            $clonetran->from = $from;
                            $clonetran->to = $to;
                            $clonetran->redirect_merged_transitions();
                            $result->add_transition($clonetran);
                            if ($isstart == 0) {
                                $newfront[] = $transition->to;
                            } else {
                                $newfront[] = $transition->from;
                            }
                        }
                    }
                }
                $oldfront = $newfront;
                $newfront = array();
            }
                // Redirect start states of getting part to real start state.
               /* $resultstart = $result->get_start_states()[0];
                $startstates = $resultpart->get_start_states();
                foreach ($startstates as $start) {
                    if (!in_array($assotiatedstates[$start], $result->get_start_states())) {
                        $result->redirect_transitions($assotiatedstates[$start], $resultstart);
                    }
                }*/
                // Form new stop states.
                // Set its end states.
            if ($isstart == 0) {
                $endstates = $resultpart->get_end_states();
                foreach ($endstates as $end) {
                    $newstop[] = $assotiatedstates[$end];
                }
            } else {
                $startstates = $resultpart->get_start_states();
                foreach ($startstates as $start) {
                    $newstop[] = $assotiatedstates[$start];
                }
            }
        }
        return $newstop;
    }

    private function skip_uncapturing_transitions($anotherfa, $stateindex, $isstart, $result, $stop) {
        $startstates = $this->get_start_states();
        $endstates = $this->get_end_states();
        // Change state first from intersection.
        $secondnumbers = $anotherfa->get_state_numbers();
        $firstnumbers = $this->get_state_numbers();
        $resnumbers = $result->get_state_numbers();
        $newstop = array();
        // Skip transitions from first automaton.
        foreach ($stateindex as $number) {
            // If eps - copy it to result automaton.
            if ($isstart == 0 && in_array($number, $startstates)) {
                $transitions = $this->get_adjacent_transitions($number, true);
                foreach ($transitions as $transition) {
                    if ($transition->is_start_anchor() || $transition->is_eps())  {
                        foreach ($stop as $stopindex) {
                            $tran = clone $transition;
                            $tran->from = $stopindex;
                            $addednumber = $result->get_inter_state($firstnumbers[$transition->to], $secondnumbers[$anotherfa->get_start_states()[0]]);
                            $addednumber = trim($addednumber, ",");
                            $addedstate = $result->add_state($addednumber);
                            $tran->to = $addedstate;
                            $tran->redirect_merged_transitions();
                            $result->add_transition($tran);
                            $newstop[] = $addedstate;
                        }
                    }
                }
            } else if ($isstart == 1 && in_array($number, $endstates)) {
                $transitions = $this->get_adjacent_transitions($number, false);
                foreach ($transitions as $transition) {
                    if ($transition->is_end_anchor() || $transition->is_eps()) {
                        foreach ($stop as $stopindex) {
                            $tran = clone $transition;
                            $tran->to = $stopindex;
                            $addednumber = $result->get_inter_state($firstnumbers[$transition->from],$secondnumbers[$anotherfa->get_end_states()[0]]);
                            $addednumber = trim($addednumber, ",");
                            $addedstate = $result->add_state($addednumber);
                            $tran->from = $addedstate;
                            $tran->redirect_merged_transitions();
                            $result->add_transition($tran);
                            $newstop[] = $addedstate;
                        }
                    }
                }
            }
        }
        $intersectedstop = $this->intersect_uncapturing_in_another_direction($anotherfa, $isstart, $result, $stop);
        $newstop = array_merge($newstop, $intersectedstop);
        // Skip transitions from second automaton.
        if ($isstart == 0) {
            $states = $anotherfa->get_start_states();
            // If eps - copy it to result automaton.
            foreach ($states as $state) {
                $transitions = $anotherfa->get_adjacent_transitions($state, true);
                foreach ($transitions as $transition) {
                    if ($transition->is_start_anchor() || $transition->is_eps()) {
                        foreach ($stop as $stopindex) {
                            $tran = clone $transition;
                            $tran->from = $stopindex;
                            if (substr($resnumbers[$stopindex], -2) === 'n,') {
                                $rightstopnumber = substr($resnumbers[$stopindex], 0, -2);
                            } else {
                                $rightstopnumber = rtrim($resnumbers[$stopindex], 'n');
                            }
                            $addednumber = $result->get_inter_state($rightstopnumber, $secondnumbers[$transition->to]);
                            $addedstate = $result->add_state($addednumber);
                            $tran->to = $addedstate;
                            $tran->redirect_merged_transitions();
                            $result->add_transition($tran);
                            $tran->origin = transition::ORIGIN_TRANSITION_SECOND;
                            $newstop[] = $addedstate;
                        }
                        //$anotherfa->remove_transition($transition);
                    }
                }
            }

        } else {
            $states = $anotherfa->get_end_states();
            foreach ($states as $state) {
                $transitions = $anotherfa->get_adjacent_transitions($state, false);
                foreach ($transitions as $transition) {
                    if ($transition->is_end_anchor() || $transition->is_eps()) {
                        foreach ($stop as $stopindex) {
                            $tran = clone $transition;
                            $tran->to = $stopindex;
                            if (substr($resnumbers[$stopindex], -2) === 'n,') {
                                $rightstopnumber = substr($resnumbers[$stopindex], 0, -2);
                            } else {
                                $rightstopnumber = rtrim($resnumbers[$stopindex], 'n');
                            }
                            $addednumber = $result->get_inter_state($rightstopnumber, $secondnumbers[$transition->from]);
                            $addedstate = $result->add_state($addednumber);
                            $tran->from = $addedstate;
                            $tran->redirect_merged_transitions();
                            $tran->origin = transition::ORIGIN_TRANSITION_SECOND;
                            $result->add_transition($tran);
                            $newstop[] = $addedstate;
                        }
                        //$anotherfa->remove_transition($transition);
                    }
                }
            }
        }
        return $newstop;
    }

    /**
     * Intersect automaton with another one.
     *
     * @param anotherfa object automaton to intersect.
     * @param stateindex array of integer indexes of states of $this automaton with which to start intersection.
     * @param isstart boolean intersect by superpose start or end state of anotherfa with stateindex state.
     * @return result automata without blind states with one end state and with merged asserts.
     */
    public function intersect_fa($anotherfa, $stateindex, $isstart) {
        $result = new fa();
        $stopcoping = $stateindex;
        // Get states for starting coping.
        if ($isstart == 0) {
            $oldfront = $this->get_start_states();
        } else {
            $oldfront = $this->get_end_states();
        }
        // Copy branches.
        $stop = $result->copy_modify_branches($this, $oldfront, $stopcoping, $isstart);
        $transitions = array();
        $startstates = $this->get_start_states();
        $endstates = $this->get_end_states();
        // Change state first from intersection.
        $secondnumbers = $anotherfa->get_state_numbers();
        $firstnumbers = $this->get_state_numbers();
        $resnumbers = $result->get_state_numbers();
        $newstop = array();
        // Skip uncapturing transitions.
        if ($isstart == 0) {
            $states = array_values($anotherfa->get_start_states());
        } else {
            $states = array_values($anotherfa->get_end_states());
        }
        $newstop = $this->skip_uncapturing_transitions($anotherfa, $stateindex, $isstart, $result, $stop);
        $secforinter = $secondnumbers[$states[0]];
        $addedstop = array();
        foreach ($stop as $stopnumber) {
            if (substr($resnumbers[$stopnumber], -2) === 'n,') {
                $rightstopnumber = substr($resnumbers[$stopnumber], 0, -2);
            } else {
                $rightstopnumber = rtrim($resnumbers[$stopnumber], 'n');
            }
            //$rightstopnumber = rtrim($resnumbers[$stopnumber], 'n');
            $state = $result->get_inter_state($rightstopnumber, $secforinter);
            $result->change_real_number($stopnumber, $state);
            $i = count($states) - 1;
            while ($i > 0) {
                $state = $result->get_inter_state($rightstopnumber, $secondnumbers[$states[$i]]);
                $added = $result->add_state($state);
                $addedstop[] = $added;
                $transitions = $result->get_adjacent_transitions($stopnumber, true);
                foreach ($transitions as $transition) {
                    $clone = clone $transition;
                    $clone->from = $added;
                    $clone->redirect_merged_transitions();
                    $result->add_transition($clone);
                }
                $transitions = $result->get_adjacent_transitions($stopnumber, false);
                foreach ($transitions as $transition) {
                    $clone = clone $transition;
                    $clone->to = $added;
                    $clone->redirect_merged_transitions();
                    $result->add_transition($clone);
                }
                $i--;
            }
        }
        $stop = array_merge($stop, $addedstop, $newstop);
        // Find intersection part.
        $this->get_intersection_part($anotherfa, $result, $stop, $isstart, $stateindex);
        // Set right start and end states for completing branches.
        $result->set_start_end_states_before_coping($this, $anotherfa);

        if ($result->has_successful_intersection($this, $anotherfa, $isstart)) {
            // Cleaning end states.
            $result->remove_all_end_states();
            // Cleaning start states.
            $result->remove_all_start_states();
            // Set right start and end states for completing branches.
            $result->set_start_end_states_before_coping($this, $anotherfa);
            $result->complete_non_intersection_branches($this, $anotherfa, $isstart);
            // Cleaning end states.
            $result->remove_all_end_states();
            // Cleaning start states.
            $result->remove_all_start_states();
            $result->set_start_end_states_after_intersect($this, $anotherfa);

        } else {
            $result = new fa();
        }
        return $result;
    }

    /**
     * Return set substraction: $this - $anotherfa. Used to get negation.
     */
    public function substract_fa($anotherfa) {
        // TODO
    }

    /**
     * Return inversion of fa.
     */
    public function invert_fa() {
        // TODO
    }

    public function __clone() {
        // TODO - clone automaton.
    }
}
