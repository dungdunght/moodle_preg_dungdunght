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
 * Defines generic node position.
 *
 * @package    blocks
 * @subpackage formal_langs
 * @copyright &copy; 2011 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev, Mamontov Dmitriy, Maria Birukova
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */


/**
 * Describes a position of AST node (terminal or non-terminal) in the original text
 */
class block_formal_langs_node_position {
    /**
     * Starting line of node
     * @var int
     */
    protected $linestart;
    /**
     * Ending line of node
     * @var int
     */
    protected $lineend;
    /**
     * Starting column of node
     * @var int
     */
    protected $colstart;
    /**
     * Ending column of node
     * @var int
     */
    protected $colend;
    /** A starting position in string, as sequence of characters
     *  @var int
     */
    protected $stringstart;
    /** An end position in string, as sequence of characters
     *  @var int
     */
    protected $stringend;

    /**
     * Returns starting line for node
     * @return int
     */
    public function linestart() {
        return $this->linestart;
    }

    /**
     * Returns ending line for node
     * @return int
     */
    public function lineend(){
        return $this->lineend;
    }

    /**
     * Returns starting column for node
     * @return int
     */
    public function colstart(){
        return $this->colstart;
    }

    /**
     * Returns ending column for node
     * @return int
     */
    public function colend(){
        return $this->colend;
    }

    /**
     * Returns starting position for node in string
     * @return int
     */
    public function stringstart() {
        return $this->stringstart;
    }

    /**
     * Returns ending position for node in string
     * @return int
     */
    public function stringend() {
        return $this->stringend;
    }

    /**
     * Constructs new position
     * @param int $linestart starting line
     * @param int $lineend ending line
     * @param int $colstart starting column
     * @param int $colend ending column
     * @param int $stringstart starting position of node in string as offset
     * @param int $stringend ending position of node in string as offset
     */
    public function __construct($linestart, $lineend, $colstart, $colend, $stringstart = 0, $stringend = 0) {
        $this->linestart = $linestart;
        $this->lineend = $lineend;
        $this->colstart = $colstart;
        $this->colend = $colend;
        $this->stringstart = $stringstart;
        $this->stringend = $stringend;
    }

    /**
     * Summ positions of array of nodes into one position
     *
     * Resulting position is defined from minimum to maximum postion of nodes
     *
     * @param array $nodepositions positions of adjanced nodes
     * @return block_formal_langs_token_position
     */
    public function summ($nodepositions) {
        $minlinestart = $nodepositions[0]->linestart;
        $maxlineend = $nodepositions[0]->lineend;
        $mincolstart = $nodepositions[0]->colstart;
        $maxcolend = $nodepositions[0]->colend;
        $minstringstart = $nodepositions[0]->stringstart;
        $maxstringend = $nodepositions[0]->stringend;

        foreach ($nodepositions as $node) {
            if ($node->linestart < $minlinestart) {
                $minlinestart = $node->linestart;
                $mincolstart = $node->colstart;
            }

            if ($node->linestart == $minlinestart) {
                $mincolstart = min($mincolstart, $node->colstart);
            }
            if ($node->lineend > $maxlineend) {
                $maxlineend = $node->lineend;
                $maxcolend = $node->colend;
            }

            if ($node->lineend == $maxlineend) {
                $maxcolend = max($maxcolend, $node->colend);
            }

            $minstringstart = min($minstringstart, $node->stringstart);
            $maxstringend = max($maxstringend, $node->stringend);
        }

        return new block_formal_langs_node_position($minlinestart, $maxlineend, $mincolstart, $maxcolend, $minstringstart, $maxstringend);
    }
}