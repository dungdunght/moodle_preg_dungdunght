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
 * Defines class for regex testing tool.
 *
 * @copyright &copy; 2012 Oleg Sychev, Volgograd State Technical University
 * @author Terechov Grigory <grvlter@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package qtype_preg
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/states.php');
require_once($CFG->dirroot . '/question/type/rendererbase.php');
require_once($CFG->dirroot . '/question/type/preg/preg_hints.php');
require_once($CFG->dirroot . '/question/type/preg/preg_matcher.php');
require_once($CFG->dirroot . '/question/type/preg/question.php');
require_once($CFG->dirroot . '/question/type/preg/authoring_tools/preg_authoring_tool.php');

/*
 * Defines a tool for testing regex against strings.
 */
class qtype_preg_regex_testing_tool implements qtype_preg_i_authoring_tool {

    //TODO - PHPDoc comments!
    private $regex = '';
    private $engine = null;
    private $notation = null;
    private $exactmatch = null;
    private $usecase = null;
    private $strings = null;

    private $question = null;
    private $matcher = null;
    private $errormsgs = null;

    public function __construct($regex, $strings, $usecase, $exactmatch, $engine, $notation, $selection) {
        global $CFG;

        $this->regex = $regex;
        $this->engine = $engine;
        $this->notation = $notation;
        $this->exactmatch = $exactmatch;
        $this->usecase = $usecase;
        $this->strings = $strings;

        if ($this->regex == '') {
            return;
        }

        $regular = new qtype_preg_question;
        // Fill engine field that is used by the hint to determine whether colored string should be shown.
        $regular->engine = $engine;
        // Creating query matcher will require necessary matcher code.
        $regular->get_query_matcher($engine);

        // Create matcher to use for testing regexes.
        $this->question = $regular;
        $matchingoptions = $regular->get_matching_options($exactmatch, $regular->get_modifiers($usecase), null, $notation);
        $matchingoptions->extensionneeded = false; // No need to generate next characters there.
        $matchingoptions->capturesubexpressions = true;
        $matchingoptions->selection = $selection;
        $matcher = $regular->get_matcher($engine, $regex, $matchingoptions, null, false);
        $this->matcher = $matcher;
        if ($matcher->errors_exist()) {
            $this->errormsgs = $matcher->get_error_messages();
        }
    }

    public function errors_exist() {
        return $this->matcher->errors_exist();
    }

    public function get_error_messages() {
        return $this->errormsgs();
    }

    public function json_key() {
        return 'regex_test';
    }

    public function generate_json() {
        $selectednode = $this->matcher !== null ? $this->matcher->get_selected_node() : null;

        $json = array();
        $json['regex'] = $this->regex;
        $json['engine'] = $this->engine;
        $json['notation'] = $this->notation;
        $json['exactmatch'] = (int)$this->exactmatch;
        $json['usecase'] = (int)$this->usecase;
        $json['indfirst'] = $selectednode !== null ? $selectednode->position->indfirst : -2;
        $json['indlast'] = $selectednode !== null ? $selectednode->position->indlast : -2;
        $json['strings'] = $this->strings;

        if ($this->regex == '') {
            $json[$this->json_key()] = $this->data_for_empty_regex();
        } else if ($this->errormsgs !== null) {
            $json[$this->json_key()] = $this->data_for_unaccepted_regex();
        } else {
            $json[$this->json_key()] = $this->data_for_accepted_regex();
        }

        return $json;
    }

    public function generate_html() {
        if ($this->regex->string() == '') {
            return $this->data_for_empty_regex();
        } else if ($this->errormsgs !== null) {
            return $this->data_for_unaccepted_regex();
        }
        return $this->data_for_accepted_regex();
    }

    public function data_for_accepted_regex() {
        global $PAGE;
        // Generate colored string showing matched and non-matched parts of response.
        $renderer = $PAGE->get_renderer('qtype_preg');
        $hintmatch = $this->question->hint_object('hintmatchingpart');
        $strings = explode("\n", $this->strings);
        $result = '';
        foreach ($strings as $string) {
            $matchresults = $this->matcher->match($string);
            $hintmessage = $hintmatch->render_colored_string_by_matchresults($renderer, $matchresults, true);
            if (!empty($string)) {
                $hintmessage = html_writer::tag('span', $hintmessage, array('id' => 'qtype-preg-colored-string'));
            }
            $result .=  $hintmessage . html_writer::empty_tag('br');
        }
        return $result;
    }

    public function data_for_unaccepted_regex() {
        return '<br />' . implode('<br />', $this->errormsgs);
    }

    public function data_for_empty_regex() {
        return '';
    }
}
