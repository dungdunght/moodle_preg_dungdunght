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
 * Defines button-with-text-input widget, parent of abstract poasquestion
 * text-and-button widget. This class extends parent class with javascript
 * callbacks for button clicks.
 *
 * @package    qtype_preg
 * @copyright  &copy; 2012 Oleg Sychev, Volgograd State Technical University
 * @author     Pahomov Dmitry <topt.iiiii@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/question/type/poasquestion/poasquestion_text_and_button.php');

MoodleQuickForm::registerElementType('preg_text_and_button',
    $CFG->dirroot.'/question/type/preg/authoring_tools/preg_text_and_button.php',
    'qtype_preg_text_and_button');

class qtype_preg_text_and_button extends qtype_poasquestion_text_and_button {

    private static $_preg_authoring_tools_script_included = false;

    public function qtype_preg_text_and_button($textareaName = null, $textareaLabel = null, $buttonName = null) {
        global $CFG;
        global $PAGE;

        $attributes = array('rows' => 1, 'cols' => 80, 'style' => 'width: 95%');
        $elementLinks = array(
            'link_to_button_image' => $CFG->wwwroot . '/theme/image.php/clean/core/1410350174/t/edit',
            'link_to_page' => $CFG->wwwroot . '/question/type/preg/authoring_tools/preg_authoring.php'
        );
        $dialogWidth = '90%';

        parent::qtype_poasquestion_text_and_button($textareaName, $textareaLabel, $attributes, $buttonName, $elementLinks, $dialogWidth);

        if (!self::$_preg_authoring_tools_script_included) {
            $jsmodule = array(
                'name' => 'preg_authoring_tools_script',
                'fullpath' => '/question/type/preg/authoring_tools/preg_authoring_tools_script.js'
            );
            $jsargs = array(
                $CFG->wwwroot,
                'TODO - poasquestion_text_and_button_objname',  // 'M.poasquestion_text_and_button' ?
            );
            $PAGE->requires->js_init_call('M.preg_authoring_tools_script.init', $jsargs, true, $jsmodule);
            self::$_preg_authoring_tools_script_included = true;
        }
    }

    public function getDialogTitle() {
        return get_string('authoring_form_page_header', 'qtype_preg');
    }

    public function getTooltip() {
        return get_string('authoring_form_tooltip', 'qtype_preg');
    }
}
