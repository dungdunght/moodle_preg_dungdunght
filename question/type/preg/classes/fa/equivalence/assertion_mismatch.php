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
 * Represents assertion mismatch while fa equivalence check
 */
class assertion_mismatch extends mismatched_pair {
    /** Positions of merged assertions */
    const POSITION_BEFORE = 0x0022;
    const POSITION_AFTER = 0x0024;
    /** @var bool whether assertion was merged in automaton or not */
    public $merged = false;
    /** @var string description of mismatched assert if it was merged */
    public $mergedassert;
    /** @var int constant of current class - merged assertion position */
    public $position;

    public function __construct($matchedautomaton, $pair, $mergedassert = null, $position = null)
    {
        parent::__construct(mismatched_pair::ASSERT, $matchedautomaton, $pair);
        $this->mergedassert = $mergedassert;
        $this->position = $position;
    }

    public function mismatched_assertion() {
        if ($this->merged) {
            return $this->mergedassert;
        }

        return $this->first->path->assert;
    }
}