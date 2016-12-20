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
 * Defines unit-tests for grammar form classe
 *
 * For a complete info, see block_formal_langs_token_base
 *
 * @copyright &copy; 2011  Oleg Sychev
 * @author Oleg Sychev, Dmitriy Mamontov, Volgograd State Technical University
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 * @package questions
 */

global $CFG;

class block_formal_langs_grammar_form_test extends PHPUnit_Framework_TestCase {
    /**
     * Test converting to lower case for word
     */
    public function test_single_word_lowercase() {
        $grammarform = new \block_formal_langs\grammar_form('ru');

        // Почему-то в словаре полагается, что это тесто
        $word = $grammarform->translate_multiple_words('тест', 'именительный,ед. ч.');
        $this->assertTrue($word == 'тесто', $word);

        $word = $grammarform->translate_multiple_words('тест', 'родительный,ед. ч.');
        $this->assertTrue($word == 'теста', $word);

        $word = $grammarform->translate_multiple_words('тест', 'дательный,ед. ч.');
        $this->assertTrue($word == 'тесту', $word);

        $word = $grammarform->translate_multiple_words('тест', 'винительный,ед. ч.');
        $this->assertTrue($word == 'тесто', $word);

        $word = $grammarform->translate_multiple_words('тест', 'творительный,ед. ч.');
        $this->assertTrue($word == 'тестом', $word);

        $word = $grammarform->translate_multiple_words('тест', 'предложный,ед. ч.');
        $this->assertTrue($word == 'тесте', $word);

        $word = $grammarform->translate_multiple_words('тест', 'именительный,мн. ч.');
        $this->assertTrue($word == 'теста', $word);

        $word = $grammarform->translate_multiple_words('тест', 'родительный,мн. ч.');
        $this->assertTrue($word == 'тест', $word);

        $word = $grammarform->translate_multiple_words('тест', 'дательный,мн. ч.');
        $this->assertTrue($word == 'тестам', $word);

        $word = $grammarform->translate_multiple_words('тест', 'винительный,мн. ч.');
        $this->assertTrue($word == 'теста', $word);

        $word = $grammarform->translate_multiple_words('тест', 'творительный,мн. ч.');
        $this->assertTrue($word == 'тестами', $word);

        $word = $grammarform->translate_multiple_words('тест', 'предложный,мн. ч.');
        $this->assertTrue($word == 'тестах', $word);
    }

    /**
     * Test converting to upper case for word
     */
    public function test_single_word_uppercase() {
        $grammarform = new \block_formal_langs\grammar_form('ru');

        // Почему-то в словаре полагается, что это тесто
        $word = $grammarform->translate_multiple_words('ТЕСТ', 'именительный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТО', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'родительный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТА', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'дательный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТУ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'винительный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТО', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'творительный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТОМ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'предложный,ед. ч.');
        $this->assertTrue($word == 'ТЕСТЕ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'именительный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТА', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'родительный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'дательный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТАМ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'винительный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТА', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'творительный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТАМИ', $word);

        $word = $grammarform->translate_multiple_words('ТЕСТ', 'предложный,мн. ч.');
        $this->assertTrue($word == 'ТЕСТАХ', $word);
    }

    /**
     * Test converting to camel case for word
     */
    public function test_single_word_camel_case() {
        $grammarform = new \block_formal_langs\grammar_form('ru');

        // Почему-то в словаре полагается, что это тесто
        $word = $grammarform->translate_multiple_words('Тест', 'именительный,ед. ч.');
        $this->assertTrue($word == 'Тесто', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'родительный,ед. ч.');
        $this->assertTrue($word == 'Теста', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'дательный,ед. ч.');
        $this->assertTrue($word == 'Тесту', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'винительный,ед. ч.');
        $this->assertTrue($word == 'Тесто', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'творительный,ед. ч.');
        $this->assertTrue($word == 'Тестом', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'предложный,ед. ч.');
        $this->assertTrue($word == 'Тесте', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'именительный,мн. ч.');
        $this->assertTrue($word == 'Теста', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'родительный,мн. ч.');
        $this->assertTrue($word == 'Тест', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'дательный,мн. ч.');
        $this->assertTrue($word == 'Тестам', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'винительный,мн. ч.');
        $this->assertTrue($word == 'Теста', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'творительный,мн. ч.');
        $this->assertTrue($word == 'Тестами', $word);

        $word = $grammarform->translate_multiple_words('Тест', 'предложный,мн. ч.');
        $this->assertTrue($word == 'Тестах', $word);
    }

    public function test_multiple_words_1() {
        $grammarform = new \block_formal_langs\grammar_form('ru');
        $word = $grammarform->translate_multiple_words('1. интересная разработка - хорошая работа, которая важна!', 'именительный,ед. ч.');

        // FIXME: This test is broken intentionally! Note, how phpMorphy is unsuitable for this task
        // Current output: 1. интересный разработка - хороший работа, который важна!
        $this->assertTrue($word == '1. интересная разработка - хорошая работа, которая важна!');
    }

    public function test_multiple_words_2() {
        $grammarform = new \block_formal_langs\grammar_form('ru');
        $word = $grammarform->translate_multiple_words('1. интересная разработка - хорошая работа, которая важна!', 'родительный,ед. ч.');

        // FIXME: This test is broken intentionally! Note, how phpMorphy is unsuitable for this task
        // Current output: 1. интересного разработки - хорошего работа, которого важна!
        $this->assertTrue($word == '1. интересной разработкки - хорошей работы, которая важна!');
    }
}