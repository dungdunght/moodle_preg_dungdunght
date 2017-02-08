<?php
// This file is part of Preg question type - https://code.google.com/p/oasychev-moodle-plugins/
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
namespace qtype_preg\fa\equivalence;

use qtype_preg\fa\transition;

defined('MOODLE_INTERNAL') || die();

/**
 * Represents a finite automaton states group.
 */
class states_group {
    /** @var array of states indexes in the group */
    public $states;
    /** @var path_to_states_group path to current group */
    public $path;
    /** @var fa, the states of which are included in the group */
    public $fa;
    /** @var transitions which were merged in other transitions, by which current group was reached */
    public $mergedbeforetransitions = array();
    public $mergedaftertransitions = array();

    public function __construct($fa, $states = array(), $path = null) {
        $this->fa = $fa;
        $this->states = $states;
        $this->path = $path;
    }

    /**
     * Sets states to group.
     *
     * @param states - array of state, which include in this group.
     */
    public function set_states($states) {
        $this->states = $states;
    }

    /**
     * Adds new state to group
     * @param $state int state number
     */
    public function add_state($state) {
        if (!$this->contains($state)) {
            $this->states[] = $state;
        }
    }

    /**
     * Adds new states to group
     * @param $states array of states to add
     */
    public function add_states($states) {
        foreach ($states as $state) {
            $this->add_state($state);
        }
    }

    /**
     * Adds merged transitions to current group
     */
    public function add_merged_transitions($mergedbeforetransitions, $mergedaftertransitions) {
        $this->mergedbeforetransitions = array_merge($this->mergedbeforetransitions, $mergedbeforetransitions);
        $this->mergedaftertransitions = array_merge($this->mergedaftertransitions, $mergedaftertransitions);
    }

    /**
     * Checks if given state already exists in current group
     * @param $state int state number
     * @return boolean whether given state exists in current group or not
     */
    public function contains($state) {
        return in_array($state, $this->states);
    }

    /**
     * Returns states of this group
     */
    public function get_states() {
        return $this->states;
    }

    /**
     * Returns fa, to which belong this group
     */
    public function get_fa() {
        return $this->fa;
    }

    /**
     * Returns outgoing transitions from states in current group
     */
    public function get_outgoing_transitions() {
        $transitions = array();
        foreach ($this->states as $curstate) {
            $transitions = array_merge($transitions, $this->fa->get_adjacent_transitions($curstate));
        }
        return $transitions;
    }

    /**
     * Returns character path to this group
     */
    public function matched_string() {
        if ($this->path !== null) {
            return $this->path->matched_string();
        }
        return '';
    }

    /**
     * Compares two groups
     */
    public function equal($other, $withmatchedstring = false, $withpath = false) {
        // Check if all states of this group are included in the given one
        foreach ($this->states as $state) {
            if (!in_array($state, $other->states)) {
                return false;
            }
        }

        // Check if all states of other group are included in this (for the case of repeated state indexes in one of groups)
        foreach ($other->states as $state) {
            if (!in_array($state, $this->states)) {
                return false;
            }
        }

        // If need to compare with matched string - compare them
        if ($withmatchedstring) {
            if ($this->path->matched_string() != $other->path->matched_string()) {
                return false;
            }
        }

        // If need to compare with paths - compare them
        if ($withpath) {
            return $this->path->equal_path($other->path);
        }

        return true;
    }

    /**
     * Checks if there are end states in group
     */
    public function has_end_states() {
        foreach ($this->states as $state)
            if ($this->fa->has_endstate($state))
                return true;

        return false;
    }

    /**
     * Checks if group is empty
     */
    public function is_empty() {
        return count($this->states) == 0;
    }
}