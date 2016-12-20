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
 * A class for getting grammar forms for specified words
 *
 * @package    formal_langs
 * @copyright  2012 Sychev Oleg
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_formal_langs;
/** @var stdClass $CFG */
require_once($CFG->dirroot.'/blocks/formal_langs/phpmorphy/src/common.php');
require_once($CFG->dirroot.'/lib/moodlelib.php');

class grammar_form {

    /**
     * Construct language to work with
     * @param null|string $lang language
     */
    public function __construct($lang = null) {
        if ($lang == null) {
            $lang = current_language();
        }
        $this->language = $lang;
        $this->phpmorphy = $this->make_phpmorphy($lang);
    }

    /**
     * Translates multiple words into specified form, or original if needed
     * @param string $words words
     * @param string $form a specified word
     * @return string
     */
    public function translate_multiple_words($words, $form) {
        global $DB;
        $result = null;
        if ($result == null) {
            $maybeform = self::get_field_for_forms('form', array(
                'language' => $this->language,
                'formname' => $form,
                'originalform' => $words
            ));
            if ($maybeform !== false) {
                $result = $maybeform;
            } else {
                $maybeoriginalform = self::get_field_for_forms('originalform', array(
                    'language' => $this->language,
                    'formname' => $form,
                    'form' => $words
                ));
                if ($maybeoriginalform !== false) {
                    $maybeform =  self::get_field_for_forms('form', array(
                        'language' => $this->language,
                        'formname' => $form,
                        'originalform' => $maybeoriginalform
                    ));
                    if ($maybeform !== false) {
                        $result = $maybeform;
                    }
                }
            }
        }

        if ($result == null) {
            $wordslist = explode(' ', $words);
            $resultinglist = array();
            if (count($wordslist) != 0) {
                foreach($wordslist as $oneword) {
                    $resultinglist[] = $this->translate_complex_word($oneword, $form);
                }
                $result = implode(' ', $resultinglist);
            }
        }

        if ($result == null) {
            $result = $words;
        }

        return $result;
    }

    /**
     * Translates complex word
     * @param string $word word
     * @param string $form form
     * @return string
     */
    public function translate_complex_word($word, $form) {
        $alphabets = array(
            'lowereng' => 'abcdefghijklmnopqrstuvwxyz',
            'uppereng' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'lowerru' => 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя',
            'upperru' => 'АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ"'
        );
        $alphabetmaps = array();
        foreach($alphabets as $key => $alphabet) {
            $alphabetmaps[$key] = self::flip_string($alphabet);
        }
        $tokens  = array();
        $currenttoken = '';
        $currenttokentype = 0;
        // Tokenize word, convert it to parts
        for($i = 0; $i < \core_text::strlen($word); $i++) {
            $c = \core_text::substr($word, $i, 1);
            $currentctype = 'en';
            if (!(array_key_exists($c, $alphabetmaps['lowereng']) || array_key_exists($c, $alphabetmaps['uppereng']))) {
                if (array_key_exists($c, $alphabetmaps['lowerru']) || array_key_exists($c, $alphabetmaps['upperru'])) {
                    $currentctype = 'ru';
                } else {
                    $currentctype = 'other';
                }
            }
            if (mb_strlen($currenttoken) == 0) {
                $currenttoken = $c;
                $currenttokentype = $currentctype;
            } else {
                if ($currentctype == $currenttokentype) {
                    $currenttoken .= $c;
                } else {
                    $tokens[] = (object)array('string' => $currenttoken, 'type' => $currentctype);
                    $currenttoken = $c;
                    $currenttokentype = $currentctype;
                }
            }
        }

        if (\core_text::strlen($currenttoken)) {
            $tokens[] = (object)array('string' => $currenttoken, 'type' => $currenttokentype);
        }
        // Translate all tokens sequentially
        $result = '';
        if (count($tokens)) {
            foreach($tokens as $token) {
                /** @var \stdClass $token */
                $partresult = $token->string;
                if ($token->type == $this->language) {
                    $partresult = self::translate_single_word($partresult, $form);
                }
                $result .= $partresult;
            }
        }
        return $result;
    }

    /**
     * Flips string and transforms it to map
     * @param string $string a string version
     * @return array
     */
    protected static function flip_string($string) {
        $result = array();
        for($i = 0; $i < \core_text::strlen($string); $i++) {
            $c = \core_text::substr($string, $i, 1);
            $result[$c] = $i;
        }
        return $result;
    }

    /**
     * Inserts new grammar form into table
     * @param string $language a language for form
     * @param string $formname a form
     * @param string $originalform an original form
     * @param string $form a new form
     */
    public static function insert_new_form(
        $language,
        $formname,
        $originalform,
        $form
    ) {
        global $DB;
        $DB->insert_record('block_formal_langs_word_frms',(object)array(
            'language' => $language,
            'formname' => $formname,
            'originalform' => $originalform,
            'form' => $form
        ));
    }

    /**
     * Translate single word into other words
     * @param string $word word
     * @param string $form a form
     * @return string
     */
    public function translate_single_word($word, $form) {
        global $DB;
        $result = null;
        $parts = explode(',', $form);
        if (count($parts) >= 1) {
            $clause = null;
            $num = null;
            if (array_key_exists($parts[0], self::$declensiontophpmorphy)) {
                $clause = self::$declensiontophpmorphy[$parts[0]];
            }
            if (count($parts) >= 2) {
                if (array_key_exists($parts[1], self::$declensiontophpmorphy)) {
                    $num = self::$declensiontophpmorphy[$parts[1]];
                }
            } else {
                $num = 'ЕД';
            }

            $casing = $this->get_casing($word);
            if ($this->phpmorphy != null && $clause != null && $num != null) {
                try {
                    $upperform = \core_text::strtoupper($word);
                    $allforms = $this->phpmorphy->findWord($upperform);
                    if (count($allforms)) {
                        foreach($allforms as $paradigm) {
                            $grammems = $paradigm->getWordFormsByGrammems(array($num, $clause));
                            if (count($grammems)) {
                                /** @var \phpMorphy_WordForm $m */
                                $m = $grammems[0];
                                $result = $m->getWord();
                                $result = $this->to_casing($result, $casing);
                            }
                        }
                    }
                } catch (phpMorphy_Exception $e) {

                }
            }
        }

        if ($result == null) {
            $maybeform = self::get_field_for_forms('form', array(
                'language' => $this->language,
                'formname' => $form,
                'originalform' => $word
            ));
            if ($maybeform !== false) {
                $result = $maybeform;
            } else {
                $maybeoriginalform =  self::get_field_for_forms('originalform', array(
                    'language' => $this->language,
                    'formname' => $form,
                    'form' => $word
                ));
                if ($maybeoriginalform !== false) {
                    $maybeform =  self::get_field_for_forms('form', array(
                        'language' => $this->language,
                        'formname' => $form,
                        'originalform' => $maybeoriginalform
                    ));
                    if ($maybeform !== false) {
                        $result = $maybeform;
                    }
                }
            }
        }

        if ($result == null) {
            $result = $word;
        }
        return $result;
    }

    /**
     * Returns casing for specified word
     * @param string $word returns word
     * @return int casing
     */
    protected function get_casing($word) {
        $allupper = true;
        $alphabetical = true;
        for($i = 0; $i < \core_text::strlen($word); $i++) {
            $c = \core_text::substr($word, $i, 1);
            $lowerc = \core_text::strtolower($c);
            if ($c != $lowerc) { // Symbol is in upper case
                if ($i != 0) {
                    $alphabetical = false;
                }
            } else {
                $allupper = false;
                if ($i == 0) {
                    $alphabetical = false;
                }
            }
        }
        // In random casing default to all lower
        $result = self::$isinlowercase;
        if ($alphabetical) {
            $result = self::$isalphabetical;
        }
        if ($allupper) {
            $result = self::$isinuppercase;
        }
        return $result;
    }

    /**
     * Converts grammar form to specified casing
     * @param string $word a word
     * @param int $casing casing value
     * @return result
     */
    protected  function to_casing($word, $casing) {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $result = $word;
        if ($casing == self::$isinuppercase) {
            $result = \core_text::strtoupper($word);
        } else {
            if ($casing == self::$isinlowercase) {
                $result = \core_text::strtolower($word);
            } else {
                $part1 = \core_text::substr($word, 0, 1);
                $part2 = \core_text::substr($word, 1);
                $result =  \core_text::strtoupper($part1) . \core_text::strtolower($part2);
            }
        }
        return $result;
    }

    /**
     * Makes a phpMorphy connection for language
     * @param string $lang lang name
     * @return null|phpMorphy
     */
    public function make_phpmorphy($lang) {
        if (array_key_exists($lang, self::$languagetophpmorphy) == false) {
            return null;
        }
        $lng = self::$languagetophpmorphy[$lang];
        try {
            $dict_bundle = new \phpMorphy_FilesBundle(self::dictionary_path(), $lng);
            $morphy = new \phpMorphy($dict_bundle, self::$phpmorphyopts);
            return $morphy;
        } catch(phpMorphy_Exception $e) {
            return null;
        }
    }


    /**
     * Returns path to dictionaries for phpMorphy
     * @return string
     */
    public static function dictionary_path() {
        return dirname(dirname(__FILE__)) . '/phpmorphy/dicts';
    }

    /**
     * Fetches field for forms
     * @param string $field field name
     * @param array $conditions a list of specified conditions
     * @throws \dml_missing_record_exception
     * @throws \dml_multiple_records_exception
     * @return null|array
     */
    protected static function get_field_for_forms($field, $conditions) {
        global $DB;
        $conditionlist = array();
        $values  = array();
        foreach($conditions as $name => $text) {
            $conditionlist[] = $DB->sql_compare_text($name, 512) . ' = ' . $DB->sql_compare_text('?', 512);
            $values[] = $text;
        }
        $conds = implode(' AND ', $conditionlist);
        return $DB->get_field_sql("SELECT {$field} FROM {block_formal_langs_word_frms} WHERE $conds LIMIT 1;", $values);
    }
    /**
     * Options for converting language to phpMorphy
     * @var array
     */
    public static $declensiontophpmorphy = array(
        'именительный' => 'ИМ',
        'родительный'   => 'РД',
        'дательный'    => 'ДТ',
        'винительный'  => 'ВН',
        'творительный' => 'ТВ',
        'предложный'  => 'ПР',
        'ед. ч.' => 'ЕД',
        'мн. ч.' => 'МН',
        'ИМ' => 'ИМ',
        'РД' => 'РД',
        'ДТ' => 'ДТ',
        'ВН' => 'ВН',
        'ТВ' => 'ТВ',
        'ПР' => 'ПР',
        'ЕД' => 'ЕД',
        'МН' => 'МН',
    );


    /**
     * An inner phpmorphy object if any
     * @var \phpMorphy|null
     */
    protected $phpmorphy;

    /**
     * A language name, where declension is performed
     * @var string
     */
    protected $language;

    /**
     * Options for converting language to phpmorphy language
     * @var array
     */
    public static $languagetophpmorphy = array(
        'ru' => 'rus'
    );
    /**
     * Options for connecting to phpmorphy options
     * @var array
     */
    public static $phpmorphyopts =  array(
        // storage type, follow types supported
        // PHPMORPHY_STORAGE_FILE - use file operations(fread, fseek) for dictionary access, this is very slow...
        // PHPMORPHY_STORAGE_SHM - load dictionary in shared memory(using shmop php extension), this is preferred mode
        // PHPMORPHY_STORAGE_MEM - load dict to memory each time when phpMorphy intialized, this useful when shmop ext. not activated. Speed same as for PHPMORPHY_STORAGE_SHM type
        'storage' => PHPMORPHY_STORAGE_FILE,
        // Extend graminfo for getAllFormsWithGramInfo method call
        'with_gramtab' => false,
        // Enable prediction by suffix
        'predict_by_suffix' => true,
        // Enable prediction by prefix
        'predict_by_db' => true
    );

    /**
     * Determines if word is in upper case
     * @var int
     */
    protected static  $isinuppercase = 0;
    /**
     * Determines if word is in lower case
     * @var int
     */
    protected static  $isinlowercase = 1;
    /**
     * Determines if first part of word is in upper case and second is in lower case
     * @var int
     */
    protected static  $isalphabetical = 2;
}