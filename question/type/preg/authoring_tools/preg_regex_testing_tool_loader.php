<?php
// This file is part of Preg question type - https://bitbucket.org/oasychev/moodle-plugins/overview
//
// Moodle is free software: you can redistribute it and/or modify
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
 * Creates regex testing tool.
 *
 * @copyright &copy; 2012 Oleg Sychev, Volgograd State Technical University
 * @author Terechov Grigory <grvlter@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_preg
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../../config.php');

global $CFG;
global $PAGE;
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_regex_testing_tool.php');

$PAGE->set_context(context_system::instance());

/**
 * Generates json array which stores regex testing content.
 */
function qtype_preg_get_json_array() {
    $regex = optional_param('regex', '', PARAM_RAW);
    $engine = optional_param('engine', '', PARAM_RAW);
    $notation = optional_param('notation', '', PARAM_RAW);
    $exactmatch = (bool)optional_param('exactmatch', '', PARAM_INT);
    $usecase = (bool)optional_param('usecase', '', PARAM_INT);
    $indfirst = optional_param('indfirst', null, PARAM_INT);
    $indlast = optional_param('indlast', null, PARAM_INT);
    $strings = optional_param('strings', '', PARAM_RAW);

    $selection = new qtype_preg_position($indfirst, $indlast);

    $regex_testing_tool = new qtype_preg_regex_testing_tool($regex, $strings, $usecase, $exactmatch, $engine, $notation, $selection);
    return $regex_testing_tool->generate_json();
}

$json = qtype_preg_get_json_array();
echo json_encode($json);
