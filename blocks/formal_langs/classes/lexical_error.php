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
 * Defines generic error, generated in stages of lexical analysis or parsing
 *
 * @package    blocks
 * @subpackage formal_langs
 * @copyright &copy; 2011 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev, Mamontov Dmitriy, Maria Birukova
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/**
 * Represents a lexical error in the token
 *
 * A lexical error is a rare case where single lexeme violates the rules of the language
 * and can not be interpreted.
 */
class  block_formal_langs_lexical_error {
    /**
     * An index for token, where error is occured
     * @var int
     */
    public $tokenindex;

    /**
     * User interface string (i.e. received using get_string) describing error to the user
     * @var string
     */
    public $errormessage;

    /**
     * Corrected token object if possible, null otherwise
     * @var block_formal_langs_token_base
     */
    public $correctedtoken;
    /**
     * A string, which determines a specific error kind.
     * Can be used by external interface (like CorrectWriting's lexical analyzer)
     * to handle specifical lexical error
     * @var string
     */
    public $errorkind = null;
}