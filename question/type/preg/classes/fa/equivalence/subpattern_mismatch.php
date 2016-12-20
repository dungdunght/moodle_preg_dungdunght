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
 * Represents subpattern mismatch while fa equivalence check
 */
class subpattern_mismatch extends mismatched_pair {
    /** @var array of numbers of matched subpatterns */
    public $matchedsubpatterns = array();
    /** @var array of 4 values: subpatterns in different positions
     * first - subpattern number, second - flag of behavior (true if open, false if close), third and fours -
     * matched string for first and second groups respectively */
    public $diffpositionsubpatterns = array();
    /** @var array of subpattern numbers, matched only in single automaton
     * first array for first automaton and second - for second one */
    public $uniquesubpatterns = array(array(), array());

    public function __construct($matchedautomaton, $pair)
    {
        parent::__construct(mismatched_pair::SUBPATTERN, $matchedautomaton, $pair);
    }
}