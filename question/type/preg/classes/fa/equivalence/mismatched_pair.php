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
 * Represents pair of states_group for two automatons with the same path with mismatch found
 */
class mismatched_pair extends groups_pair {
    /** Possible types of the mismatch */
    const CHARACTER = 0x0002;
    const FINAL_STATE = 0x0004;
    const ASSERT = 0x0006;
    const SUBPATTERN = 0x0008;
    /** @var type of the mismatch - should be equal to a constant defined in this class. */
    public $type;
    /** @var integer index of automaton, which matched to path (and tags) */
    public $matchedautomaton;
    
    public function __construct($type, $matchedautomaton, $pair) {
        parent::__construct($pair);
        $this->type = $type;
        $this->matchedautomaton = $matchedautomaton;
    }
}