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
 * Defines generic error, generated in stage of parsing
 *
 * @package    blocks
 * @subpackage formal_langs
 * @copyright &copy; 2011 Oleg Sychev, Volgograd State Technical University
 * @author     Oleg Sychev, Mamontov Dmitriy, Maria Birukova
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

/**
 * @class block_formal_langs_parsing_error
 * A special kind of error, which is used, when parsing of token stream occurs and error is found
 */
class block_formal_langs_parsing_error extends block_formal_langs_lexical_error {

}