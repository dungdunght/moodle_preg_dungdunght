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
 * Creates interactive tree, explaining graph and description.
 *
 * @copyright &copy; 2012 Oleg Sychev, Volgograd State Technical University
 * @author Terechov Grigory, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

define('AJAX_SCRIPT', true);

require_once(dirname(__FILE__) . '/../../../../config.php');
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_description_tool.php');
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_explaining_graph_tool.php');
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_syntax_tree_tool.php');
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_simplification_tool.php');

/**
 * Generates json array which stores authoring tools' content.
 */
function qtype_preg_get_json_array() {
    $regex = optional_param('regex', '', PARAM_RAW);
    $engine = optional_param('engine', '', PARAM_RAW);
    $notation = optional_param('notation', '', PARAM_RAW);
    $exactmatch = (bool)optional_param('exactmatch', '', PARAM_INT);
    $usecase = (bool)optional_param('usecase', '', PARAM_INT);
    $indfirst = optional_param('indfirst', null, PARAM_INT);
    $indlast = optional_param('indlast', null, PARAM_INT);
    $treeorientation = optional_param('treeorientation', '', PARAM_TEXT);
    $displayas = optional_param('displayas', '', PARAM_RAW);
    $foldcoords = optional_param('foldcoords', '', PARAM_RAW);
    $treeisfold = (bool)optional_param('treeisfold', '', PARAM_INT);
    $problem_ids = explode(',', optional_param('problem_ids', null, PARAM_RAW));
    $problem_type = optional_param('problem_type', null, PARAM_RAW);

    // Array with authoring tools
    $options = new qtype_preg_authoring_tools_options();
    $options->engine = $engine;
    $options->notation = $notation;
    $options->treeorientation = $treeorientation;
    $options->displayas = $displayas;
    $options->exactmatch = $exactmatch;
    if (!$usecase) {
        $options->set_modifier(qtype_preg_handling_options::MODIFIER_CASELESS);
    }
    $options->selection = new qtype_preg_position($indfirst, $indlast);

    /*$options->treeisfold = array();
    $tmppoints = split(',', $treeisfold);
    foreach ($tmppoints as $value) {
        $options->treeisfold[] = (int)$value;
    }*/

    $options->foldcoords = $foldcoords;
    $options->treeisfold = $treeisfold;

    /*$stooloptions = new qtype_preg_simplification_tool_options();
    $stooloptions->engine = $engine;
    $stooloptions->notation = $notation;
    $stooloptions->exactmatch = $exactmatch;
    $stooloptions->problem_ids = $problem_ids;
    $stooloptions->problem_type = $problem_type;
    $stooloptions->indfirst = $indfirst;
    $stooloptions->indlast = $indlast;

    if (count($stooloptions->problem_ids) > 0 && $stooloptions->problem_type != -2) {
        $stooloptions->exactmatch = false;
        $simplified_regex = new qtype_preg_simplification_tool($regex, $stooloptions);
        $regex = $simplified_regex->optimization();
        $options->selection = new qtype_preg_position(-2, -2);
        $stooloptions->exactmatch = $exactmatch;
    }*/

    $tools = array(
        //'simplification' => new qtype_preg_simplification_tool($regex, $stooloptions),
        'tree' => new qtype_preg_syntax_tree_tool($regex, $options),
        'graph' => new qtype_preg_explaining_graph_tool($regex, $options),
        'description' => new qtype_preg_description_tool($regex, $options)
    );

    // Fill the json array.
    $json = array();
    foreach($tools as $tool) {
        $json = array_merge($json, $tool->generate_json());
    }

    $json['indfirstorig'] = $indfirst;
    $json['indlastorig'] = $indlast;

    return $json;
}

$json = qtype_preg_get_json_array();
echo json_encode($json);
