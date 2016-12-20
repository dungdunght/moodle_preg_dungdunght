<?php
// This file is part of Preg question type - https://code.google.com/p/oasychev-moodle-plugins/
//
// Poasquestion question type is free software: you can redistribute it and/or modify
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
 * Defines authors tool widgets class.
 *
 * @copyright  2015 Oleg Sychev, Volgograd State Technical University
 * @author Terechov Grigory, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/form/textarea.php');

MoodleQuickForm::registerElementType('preg_collapsible_info_block',
    $CFG->dirroot.'/question/type/preg/authoring_tools/preg_collapsible_info_block.php',
    'qtype_preg_collapsible_info_block');

class qtype_preg_collapsible_info_block extends MoodleQuickForm_textarea {

    public function qtype_preg_collapsible_info_block() {
        global $PAGE;

        parent::MoodleQuickForm_textarea();

//        $this->buttonName = $buttonName;
//        $this->linkToPage = $elementLinks['link_to_page'];
//        $this->linkToBtnImage = $elementLinks['link_to_button_image'];
//        if ($dialogWidth === null) {
//            $dialogWidth = '90%';
//        }
//
//        $PAGE->requires->jquery();
//        $PAGE->requires->jquery_plugin('ui');
//        $PAGE->requires->jquery_plugin('ui-css');
//
//        $PAGE->requires->string_for_js('savechanges', 'moodle');
//        $PAGE->requires->string_for_js('cancel', 'moodle');
//        $PAGE->requires->string_for_js('close', 'editor');
//
//        // dependencies
//        $PAGE->requires->js('/question/type/poasquestion/jquery.elastic.1.6.11.js');
//
//        if (!self::$_poasquestion_text_and_button_included) {
//            $jsargs = array(
//                $dialogWidth,
//                $this->getDialogTitle()
//            );
//            $PAGE->requires->js_init_call('M.poasquestion_text_and_button.init', $jsargs, true, $this->jsmodule);
//            self::$_poasquestion_text_and_button_included = true;
//        }
    }

//    public function getDialogTitle() {
//        return 'someone forgot to set the title :(';
//    }
//
//    public function getTextareaId() {
//        return $this->getAttribute('id');
//    }
//
//    public function getButtonId() {
//        return $this->getAttribute('id') . '_btn';
//    }
//
//    public function getTooltip() {
//        return '';
//    }

    /**
     * Returns HTML for this form element.
     */
    public function toHtml() {
        global $PAGE;

        return '<div class="accordion" id="healp_accordion" style="overflow: hidden; position: relative; top: auto; padding-right: 7px;">
                  <div class="accordion-group">
                    <div class="accordion-heading">

                      <a class="accordion-toggle collapsed" data-toggle="collapse" data-parent="#healp_accordion" href="#healpAccordion" style="display:inline-block;" id="simplification_tool_collapse_btn">
                        <i id="collapse_block_toggle" style="background-image: url(/moodle/theme/image.php/clean/core/1461098461/t/collapsed); display: inline-block; width: 14px; height: 14px;"></i>
                        <strong>' . get_string('simplification_tool', 'qtype_preg') . '</strong>
                      </a>

                        <span class="label label-important">' . get_string('simplification_tool_error', 'qtype_preg') . '<span id="simplification_tool_errors_count">0</span></span>
                        <span class="label label-warning">' . get_string('simplification_tool_tip', 'qtype_preg') . '<span id="simplification_tool_tips_count">0</span></span>
                        <span class="label label-info">' . get_string('simplification_tool_equivalence', 'qtype_preg') . '<span id="simplification_tool_equivalences_count">0</span></span>

                    </div>
                    <div id="healpAccordion" class="accordion-body collapse">
                      <div class="accordion-inner">
                        <div style="height: auto; max-height: 150px; overflow: auto; margin-bottom: 10px;">
                            <table class="table table-hover" id="simplification_tool_hints">
                              <tbody>
                              </tbody>
                            </table>
                        </div>

                        <div class="fitem">
                            <div class="felement" style="margin-left: 155px;">
                                <p><span id="simplification_tool_hint_text"></span></p>
                                <input type="hidden" id="problem_ids"/>
                                <input type="hidden" id="problem_type"/>
                                <input type="hidden" id="problem_indfirst"/>
                                <input type="hidden" id="problem_indlast"/>
                                <button class="btn btn-primary" id="simplification_tool_apply_btn">' . get_string('simplification_tool_apply', 'qtype_preg') . '</button>
                                <button class="btn btn-default" id="simplification_tool_cancel_btn">' . get_string('simplification_tool_cancel', 'qtype_preg') . '</button>
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>';
    }
}
