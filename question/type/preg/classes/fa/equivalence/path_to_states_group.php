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

/**
 * Represents path to states group from initial state of finite automaton.
 */
class path_to_states_group {
    const CHARACTER = 0x0002;
    const ASSERT = 0x0004;
    const EPSILON = 0x0006;
    /** @var type of current path step. Set to one of constants of current class */
    public $type;
    /** @var char character, matched in automaton to get this group. */
    public $character;
    /** @var string assert, matched in automaton to get this group. */
    public $assert;
    /** @var array of integer opentags, matched before character. */
    public $beforeopentags = array();
    /** @var array of integer closetags, matched before character. Necessary for merged transitions. */
    public $beforeclosetags = array();
    /** @var array of integer opentags, matched after character. Necessary for merged transitions. */
    public $afteropentags = array();
    /** @var array of integer closetags, matched after character. */
    public $afterclosetags = array();
    /** @var path_to_states_group path to previous group. */
    public $prev;
    /** @var boolean flag, showing if current path was already normalized */
    private $normalizedpath = false;

    public function __construct($type = path_to_states_group::CHARACTER, $value = '') {
        $this->type = $type;
        switch ($type) {
            case path_to_states_group::CHARACTER:
                $this->character = $value;
                break;
            case path_to_states_group::ASSERT:
                $this->assert = $value;
                break;
        }
    }

    /**
     * Returns string description of condition symbol
     */
    public function get_condition_symbol_description() {
        switch ($this->type) {
            case path_to_states_group::CHARACTER:
                return $this->character;
            case path_to_states_group::ASSERT:
                return $this->assert;
            default:
                return 'epsilon';
        }
    }

    /**
     * Returns array of numbers of equal in both paths subpatterns
     */
    public function equal_subpatterns($other) {
        $equals = array();
        $equalclose = array();

        $curfirstpath = $this;
        $cursecondpath = $other;

        while ($curfirstpath !== null && $cursecondpath != null) {
            // Get current step equal close tags
            foreach ($curfirstpath->afterclosetags as $tag) {
                if (in_array($tag, $cursecondpath->afterclosetags)) {
                    $equalclose[] = $tag;
                }
            }
            // Check, which close tags has equal open in both paths
            for ($i = 0; $i < count($equalclose); $i++) {
                if (in_array($equalclose[$i], $curfirstpath->beforeopentags) && in_array($equalclose[$i], $cursecondpath->beforeopentags)) {
                    $equals[] = $equalclose[$i];
                    array_splice($equalclose, $i, 1);
                    $i--;
                }
            }
            // Go to previous steps
            $curfirstpath = $curfirstpath->prev;
            $cursecondpath = $cursecondpath->prev;
        }

        return $equals;
    }

    /**
     * @param $other path to compare with
     * @param $indiffpositions array of subpatterns, which exist in both pathes, but in different positions
     * @param $different array of subpatterns, which exist only in one path
     */
    public function mismatched_subpatterns($other, &$indiffpositions, &$different) {
        $nonequaltags = array(array(), array());
        $indiffpositions = array();
        $different = array(array(), array());

        $paths = array($this, $other);

        while ($paths[0] !== null && $paths[1] != null) {
            // For both paths
            for ($i = 0; $i <= 1; $i++) {
                // Check open tags
                foreach ($paths[$i]->beforeopentags as $tag) {
                    if (!in_array($tag, $paths[($i + 1) % 2]->beforeopentags)) {
                        $nonequaltags[$i][] = array($tag, true, $paths[$i]->matched_string());
                    }
                }
                // Check close tags
                foreach ($paths[$i]->afterclosetags as $tag) {
                    if (!in_array($tag, $paths[($i + 1) % 2]->afterclosetags)) {
                        $nonequaltags[$i][] = array($tag, false, $paths[$i]->matched_string());
                    }
                }
            }
            // Go to previous steps
            $paths[0] = $paths[0]->prev;
            $paths[1] = $paths[1]->prev;
        }

        // Sort founded mismatches to returning arrays
        for ($i = 0; $i <= 1; $i++) {
            foreach ($nonequaltags[$i] as $nonequaltag) {
                // If current tag is pair for one already found as different
                if (in_array($nonequaltag[0], $different[$i])) {
                    continue;
                }
                $found = false;
                foreach ($nonequaltags[($i + 1) % 2] as $othernonequaltag) {
                    if ($nonequaltag[0] == $othernonequaltag[0] && $nonequaltag[1] == $othernonequaltag[1]) {
                        $newdiffposition = array('subexpression' => $nonequaltag[0], 'isopen' => $nonequaltag[1],
                            'firstmatchedstring' => $i == 0 ? $nonequaltag[2] : $othernonequaltag[2],
                            'secondmatchedstring' => $i == 1 ? $nonequaltag[2] : $othernonequaltag[2]);
                        $alreadyexists = false;
                        foreach ($indiffpositions as $diffposition) {
                            if ($diffposition == $newdiffposition) {
                                $alreadyexists = true;
                            }
                        }
                        if (!$alreadyexists) {
                            $indiffpositions[] = $newdiffposition;
                        }
                        $found = true;
                    }
                }
                if (!$found) {
                    $different[$i][] = $nonequaltag[0];
                }
            }
        }
    }

    /**
     * Get subexpression tags from given and set to named array
     */
    public function filter_subexpression_tags($arrayoftags, $nameofarraytofill) {
        foreach ($arrayoftags as $tag) {
            if (is_a($tag, 'qtype_preg_node_subexpr')) {
                array_push($this->$nameofarraytofill, $tag->number);
            }
        }
    }

    /**
     * Compares two paths without history
     * @param $other path_to_states_group with which to compare
     * @return bool result of comparison
     */
    public function equal_step($other) {
        // Compare type and symbol
        $res = $this->type == $other->type;
        switch ($this->type) {
            case path_to_states_group::CHARACTER:
                $res = $res && $this->character == $other->character;
                break;
            case path_to_states_group::ASSERT:
                $res = $res && $this->assert == $other->assert;
                break;
        }

        // Compare tagsets
        $this->normalize_tagsets();
        $other->normalize_tagsets();

        $res = $res && $this->beforeopentags == $other->beforeopentags
            && $this->afterclosetags == $other->afterclosetags
            && $this->beforeclosetags == $other->beforeclosetags
            && $this->afteropentags == $other->afteropentags;

        return $res;
    }

    /**
     * Compares two paths with history
     * @param $other path_to_states_group with which to compare
     * @return bool result of comparison
     */
    public function equal_path($other) {
        // Normalize paths
        if (!$this->normalizedpath) {
            $this->normalize_path();
        }
        if (!$other->normalizedpath) {
            $other->normalize_path();
        }
        // Compare each step
        $curfirststep = $this;
        $cursecondstep = $other;
        while ($curfirststep !== null && $cursecondstep !== null) {
            if (!$curfirststep->equal_step($cursecondstep)) {
                return false;
            }
            $curfirststep = $curfirststep->prev;
            $cursecondstep = $cursecondstep->prev;
        }

        if ($curfirststep == null && $cursecondstep == null) {
            return true;
        }
        return false;
    }

    /**
     * Normalizes path
     */
    public function normalize_path() {
        if ($this->prev != null) {
            $this->beforeopentags = array_merge($this->beforeopentags, $this->prev->afteropentags);
            $this->prev->afteropentags = array();
            $this->prev->afterclosetags = array_merge($this->beforeclosetags, $this->prev->afterclosetags);
            $this->beforeclosetags = array();
            $this->prev->normalize_path();
        }
        $this->normalize_tagsets();
        $this->normalizedpath = true;
    }

    /**
     * Removes duplicate tags from each array and sorts values
     */
    public function normalize_tagsets() {
        $this->normalize_tagset($this->beforeopentags);
        $this->normalize_tagset($this->afterclosetags);
        $this->normalize_tagset($this->beforeclosetags);
        $this->normalize_tagset($this->afteropentags);
    }

    /**
     * Removes duplicate tags from array and sorts values
     */
    public function normalize_tagset(&$tagset) {
        // Remove duplicate values
        $tagset = array_unique($tagset, SORT_NUMERIC);
        sort($tagset);
    }

    /**
     * Returns full character path.
     * @return string full character path.
     */
    public function matched_string() {
        if ($this->prev == null) {
            if ($this->type == path_to_states_group::CHARACTER) {
                return $this->character;
            }
            return '';
        }
        if ($this->type == path_to_states_group::CHARACTER) {
            return $this->prev->matched_string() . $this->character;
        }
        return $this->prev->matched_string();
    }

    /**
     * Returns clone of current path
     * @param $prev path_to_states_group previouse step of path, if is necessary to set it not by clone value
     * @return path_to_states_group clone of current path
     */
    public function clone_path($prev = null) {
        $path = new path_to_states_group();

        $path->type = $this->type;
        $path->character = $this->character;
        $path->assert = $this->assert;
        $path->beforeopentags = $this->beforeopentags;
        $path->beforeclosetags = $this->beforeclosetags;
        $path->afteropentags = $this->afteropentags;
        $path->afterclosetags = $this->afterclosetags;
        if ($prev !== null) {
            $path->prev = $prev;
        }
        else {
            $path->prev = $this->prev;
        }

        return $path;
    }
}