<?php

/**
 * Unit tests for TFA equivalence tests.
 *
 * @package    qtype_preg
 * @copyright  2012 Oleg Sychev, Volgograd State Technical University
 * @author     Kamo Spertsian <spertsiankamo@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/poasquestion/stringstream/stringstream.php');
require_once($CFG->dirroot . '/question/type/preg/preg_regex_handler.php');
require_once($CFG->dirroot . '/question/type/preg/question.php');

class qtype_preg_equivalence_test extends PHPUnit_Framework_TestCase {

    /**
     * Generates array of \qtype_preg\fa\fa_transitions from given fa.
     */
    function create_array_of_transitions_from_fa($fa) {
        $arr = array();
        foreach ($fa->adjacencymatrix as $matr) {
            foreach ($matr as $marray) {
                foreach ($marray as $tr) {
                    $arr[] = $tr;
                }
            }
        }

        for ($i = 0; $i < count($arr) / 2; ++$i) {
            $tmp = $arr[$i];
            $arr[$i] = $arr[count($arr) - $i - 1];
            $arr[count($arr) - $i - 1] = $tmp;
        }

        return $arr;
    }
    /**
     * Generates pair of qtype_preg_fa_pair_of_groups with given parameters
     */
    function create_pair_of_groups($ffastates, $sfastates, $firstchar = 97, $lastchar = 97, $ffaendstates = array(), $sfaendstates = array(), $tags = array()) {
        // Allocation
        $ffa = new \qtype_preg\fa\fa();
        $ffa->endstates = array();
        $sfa = new \qtype_preg\fa\fa();
        $sfa->endstates = array();
        $pair = new qtype_preg_fa_pair_of_groups();
        $pair->first = new qtype_preg_fa_group();
        $pair->second = new qtype_preg_fa_group();
        // Setting states
        $ffa->statenumbers = $ffastates;
        $ffa->endstates[0] = $ffaendstates;
        $pair->first->set_states($ffastates);
        $sfa->statenumbers = $sfastates;
        $sfa->endstates[0] = $sfaendstates;
        $pair->second->set_states($sfastates);
        // Setting fas
        $pair->first->set_fa($ffa);
        $pair->second->set_fa($sfa);
        // Setting tags
        $pair->tags = $tags;
        // Setting character
        $pair->char = $firstchar;
        $pair->first->set_char($firstchar);
        $pair->second->set_char($firstchar);

        return $pair;
    }
    /**
     * Generates pair of qtype_preg_fa_pair_of_groups with initial states and given fas
     */
    function create_initial_pair_of_groups($ffa, $sfa) {
        $pair = new qtype_preg_fa_pair_of_groups();
        $pair->first = new qtype_preg_fa_group($ffa);
        $pair->second = new qtype_preg_fa_group($sfa);
        $pair->first->add_state(0);
        $pair->second->add_state(0);
        return $pair;
    }
    /**
     * Creates \qtype_preg\fa\equivalence\mismatched_pair with given parameters
     */
    function create_mismatch($type, $matchedautomaton, $matchedstring, $firstfastates = array(), $secondfastates = array()) {
        $pair = \qtype_preg\fa\equivalence\groups_pair::generate_pair(new \qtype_preg\fa\equivalence\states_group(null, $firstfastates),
                                                                      new \qtype_preg\fa\equivalence\states_group(null, $secondfastates));

        $prevpath = new \qtype_preg\fa\equivalence\path_to_states_group();
        $path = $prevpath;
        for ($i = 0; $i < strlen($matchedstring); $i++) {
            $path = new \qtype_preg\fa\equivalence\path_to_states_group(\qtype_preg\fa\equivalence\path_to_states_group::CHARACTER, $matchedstring[$i]);
            $path->prev = $prevpath;
            $prevpath = $path;
        }
        $pair->first->path = $path;
        $pair->second->path = $path;

        switch ($type) {
            case \qtype_preg\fa\equivalence\mismatched_pair::CHARACTER:
                $mismatch = new \qtype_preg\fa\equivalence\character_mismatch($matchedautomaton, $pair);
                break;
            case \qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE:
                $mismatch = new \qtype_preg\fa\equivalence\final_state_mismatch($matchedautomaton, $pair);
                break;
            case \qtype_preg\fa\equivalence\mismatched_pair::ASSERT:
                $mismatch = new \qtype_preg\fa\equivalence\assertion_mismatch($matchedautomaton, $pair);
                break;
            case \qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN:
                $mismatch = new \qtype_preg\fa\equivalence\subpattern_mismatch($matchedautomaton, $pair);
                break;
        }
        return $mismatch;
    }
    /**
     * Switches qtype_preg_leaf_meta tags to qtype_preg_leaf_subexpr
     */
    function switch_meta_to_subexpr($automaton) {
        foreach ($automaton->adjacencymatrix as $state) {
            foreach ($state as $arrayoftransitions) {
                foreach ($arrayoftransitions as $transition) {
                    for ($i = 0; $i < count($transition->opentags); $i++) {
                        if (is_a($transition->opentags[$i], 'qtype_preg_leaf_meta')) {
                            $subexpr = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_SUBEXPR, $transition->opentags[$i]->subpattern);
                            $transition->opentags[$i] = $subexpr;
                        }
                    }
                    for ($i = 0; $i < count($transition->closetags); $i++) {
                        if (is_a($transition->closetags[$i], 'qtype_preg_leaf_meta')) {
                            $subexpr = new qtype_preg_node_subexpr(qtype_preg_node_subexpr::SUBTYPE_SUBEXPR, $transition->closetags[$i]->subpattern);
                            $transition->closetags[$i] = $subexpr;
                        }
                    }
                }
            }
        }
        return $automaton;
    }
    /**
     * Compares two arrays of mismatches
     */
    function compare_mismatches($resmm, $expmm, $withstates = true, $usecase = false) {
        $res = count($resmm) == count($expmm);
        for ($i = 0; $i < count($resmm) && $res; $i++) {
            if ($withstates) {
                $resfisrtstatenumbers = array();
                $ressecondstatenumbers = array();
                foreach ($resmm[$i]->first->get_states() as $stateind) {
                    $resfisrtstatenumbers[] = $resmm[$i]->first->get_fa()->statenumbers[$stateind];
                }
                foreach ($resmm[$i]->second->get_states() as $stateind) {
                    $ressecondstatenumbers[] = $resmm[$i]->second->get_fa()->statenumbers[$stateind];
                }
                $res = $expmm[$i]->first->get_states() == $resfisrtstatenumbers
                    && $expmm[$i]->second->get_states() == $ressecondstatenumbers;
            }
            $res = $res && $resmm[$i]->type == $expmm[$i]->type
                 && $resmm[$i]->matchedautomaton == $expmm[$i]->matchedautomaton
                 && ($usecase ? $resmm[$i]->matched_string() : strtolower($resmm[$i]->matched_string()))
                    == ($usecase ? $expmm[$i]->matched_string() : strtolower($expmm[$i]->matched_string()));
            if ($res) {
                switch ($resmm[$i]->type) {
                    case \qtype_preg\fa\equivalence\mismatched_pair::ASSERT:
                        $res = $res && $resmm[$i]->merged == $expmm[$i]->merged
                            && $resmm[$i]->mergedassert == $expmm[$i]->mergedassert
                            && $resmm[$i]->position == $expmm[$i]->position;
                        break;
                    case \qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN:
                        $res = $res && $resmm[$i]->matchedsubpatterns == $expmm[$i]->matchedsubpatterns
                            && $resmm[$i]->diffpositionsubpatterns == $expmm[$i]->diffpositionsubpatterns
                            && $resmm[$i]->uniquesubpatterns == $expmm[$i]->uniquesubpatterns;
                        break;
                }
            }
        }
        return $res;
    }
    /**
     * Compares two arrays with pairs of groups
     */
    function compare_pairs($pairs, $exppairs) {
        $ans = count($pairs) == count($exppairs);
        for ($i = 0; $i < count($pairs) && $ans; $i++) {
            $ans = $pairs[$i]->compare($exppairs[$i]);
        }
        return $ans;
    }

    /**
     * Compares two regular expressions
     */
    function compare_regexes($first, $second, &$differences, $usecase = false) {
        global $CFG;
        $CFG->qtype_preg_assertfailmode = true;
        $pregquestionstd = new \qtype_preg_question();
        $matchingoptions = $pregquestionstd->get_matching_options($usecase, $pregquestionstd->get_modifiers(false), null, 'native');
        $matchingoptions->extensionneeded = false;
        $matchingoptions->capturesubexpressions = true;

        $firstmatcher = $pregquestionstd->get_matcher('fa_matcher', $first, $matchingoptions);
        $firstautomaton = $firstmatcher->automaton;

        $secondmatcher = $pregquestionstd->get_matcher('fa_matcher', $second, $matchingoptions);
        $secondautomaton = $secondmatcher->automaton;

        $differences = array();
        $res = $firstautomaton->equal($secondautomaton, $differences, true);

        return $res;
    }

    // Tests for automaton equivalence check function
    public function test_equal_minimal_automata() {
        $firstfadescription = 'digraph {
                          0;
                          1;
                          0->1[label=<<B>o: [ab] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          1;
                          0->1[label=<<B>o: [a] c:</B>>];
                          0->1[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_same_automata() {
        $fadescription = 'digraph {
                      0;
                      1;
                      0->1[label=<<B>o: [ab] c:</B>>];
                      }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($fadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($fadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_one_and_some_way_automata() {
        $firstfadescription = 'digraph {
                          0;
                          1;
                          0->1[label=<<B>o: [a-f] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          1;
                          0->1[label=<<B>o: [ab] c:</B>>];
                          0->1[label=<<B>o: [cf] c:</B>>];
                          0->1[label=<<B>o: [ed] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_some_and_some_way_automata() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->4[label=<<B>o: [a-c] c:</B>>];
                          1->3[label=<<B>o: [d-f] c:</B>>];
                          4->2[label=<<B>o: [w] c:</B>>];
                          3->2[label=<<B>o: [w] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          2;
                          0->1[label=<<B>o: [ab] c:</B>>];
                          0->1[label=<<B>o: [cf] c:</B>>];
                          0->1[label=<<B>o: [ed] c:</B>>];
                          1->2[label=<<B>o: [w] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription); // 3 2 4 1
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription); // 1 2 0

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_long_automata() {
        $firstfadescription = 'digraph {
                          1;
                          15;
                          1->2[label=<<B>o: [e] c:</B>>];
                          2->4[label=<<B>o: [q] c:</B>>];
                          4->7[label=<<B>o: [u] c:</B>>];
                          7->6[label=<<B>o: [a] c:</B>>];
                          6->15[label=<<B>o: [l] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          6;
                          1->2[label=<<B>o: [e] c:</B>>];
                          2->3[label=<<B>o: [q] c:</B>>];
                          3->4[label=<<B>o: [u] c:</B>>];
                          4->5[label=<<B>o: [a] c:</B>>];
                          5->6[label=<<B>o: [l] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription); // 6 15 7 4 2 1
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription); // 5 6 4 3 2 1

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_automata_with_loop() {
        $firstfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [e] c:</B>>];
                          2->3[label=<<B>o: [q] c:</B>>];
                          2->1[label=<<B>o: [q] c:</B>>];
                          3->7[label=<<B>o: [e] c:</B>>];
                          7->1[label=<<B>o: [q] c:</B>>];
                          1->4[label=<<B>o:1, [a] c:</B>>];
                          4->5[label=<<B>o: [l] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          6;
                          1->2[label=<<B>o: [e] c:</B>>];
                          2->1[label=<<B>o: [q] c:</B>>];
                          1->3[label=<<B>o:1, [a] c:</B>>];
                          3->6[label=<<B>o: [l] c:2,</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($secondfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_automaton_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o:1, [e] c:</B>>];
                          2->3[label=<<B>o: [q] c:2,</B>>];
                          2->3[label=<<B>o: [g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          30;
                          1->20[label=<<B>o:1, [e] c:</B>>];
                          20->30[label=<<B>o: [q] c:2,</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'eg', array("3"), array())); // g

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_both_automaton_and_tag_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o:1, [e] c:</B>>];
                          2->3[label=<<B>o: [qg] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          30;
                          1->20[label=<<B>o:1, [e] c:</B>>];
                          20->30[label=<<B>o: [q] c:2,</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'eg', array("3"), array())); // g

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_automaton_mismatches_overlimit() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->4[label=<<B>o: [b] c:</B>>];
                          1->5[label=<<B>o: [c] c:</B>>];
                          2->3[label=<<B>o: [d] c:</B>>];
                          5->4[label=<<B>o: [e] c:</B>>];
                          4->3[label=<<B>o: [f] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [e] c:</B>>];
                          1->3[label=<<B>o: [q] c:</B>>];
                          1->4[label=<<B>o: [u] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a', array("2"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'b', array("4"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'c', array("5"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'e', array(), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'q', array(), array("3")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_loop_and_line_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->1[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->4[label=<<B>o: [a] c:</B>>];
                          4->3[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'b', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ab', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'aaa', array("1"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_loop_start_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->1[label=<<B>o: [c] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [d] c:</B>>];
                          2->1[label=<<B>o: [c] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a', array("2"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'd', array(), array("2")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_in_loop_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o:1, [a] c:</B>>];
                          2->4[label=<<B>o: [c] c:</B>>];
                          4->1[label=<<B>o: [d] c:2,</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->4[label=<<B>o:1, [a] c:</B>>];
                          4->8[label=<<B>o: [b] c:</B>>];
                          8->1[label=<<B>o: [d] c:2,</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'ab', array(), array("8")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ac', array("4"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_after_loop_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o:1, [a] c:</B>>];
                          2->4[label=<<B>o: [b] c:</B>>];
                          4->1[label=<<B>o: [d] c:2,</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->4[label=<<B>o:1, [a] c:</B>>];
                          4->8[label=<<B>o: [b] c:</B>>];
                          8->1[label=<<B>o: [d] c:2,</B>>];
                          1->3[label=<<B>o: [c] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'b', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'c', array(), array("3")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_char_loop_count_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->1[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->4[label=<<B>o: [a] c:</B>>];
                          4->1[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ab', array("3"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_equal_automata_with_pre_and_post_loop_transitions() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->1[label=<<B>o: [a] c:</B>>];
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->3[label=<<B>o: [a] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->2[label=<<B>o: [a] c:</B>>];
                          2->3[label=<<B>o: [a] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_automata_with_initial_and_final_states_loops() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->1[label=<<B>o: [a] c:</B>>];
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->3[label=<<B>o: [a] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->3[label=<<B>o: [a] c:</B>>];
                          3->3[label=<<B>o: [a] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_loop_branching_mismatch() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->4[label=<<B>o: [b] c:</B>>];
                          2->5[label=<<B>o: [c] c:</B>>];
                          4->1[label=<<B>o: [d] c:</B>>];
                          5->1[label=<<B>o: [e] c:</B>>];
                          1->3[label=<<B>o: [a] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->2[label=<<B>o: [a] c:</B>>];
                          2->4[label=<<B>o: [b] c:</B>>];
                          2->5[label=<<B>o: [c] c:</B>>];
                          4->1[label=<<B>o: [d] c:</B>>];
                          5->1[label=<<B>o: [f] c:</B>>];
                          1->3[label=<<B>o: [a] c:</B>>];
                          }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ace', array("1"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'acf', array(), array("1")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_equal_automata_with_transitions_from_final_state() {
        $firstfadescription = 'digraph {
                          1;
                          3;
                          1->3[label=<<B>o: [a] c:</B>>];
                          3->4[label=<<B>o: [qr] c:</B>>];
                          4->3[label=<<B>o: [t] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          3;
                          1->3[label=<<B>o: [a] c:</B>>];
                          3->4[label=<<B>o: [q] c:</B>>];
                          3->5[label=<<B>o: [r] c:</B>>];
                          4->3[label=<<B>o: [t] c:</B>>];
                          5->3[label=<<B>o: [t] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equal_nfa_and_dfa() {
        $firstfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [a-f] c:</B>>];
                          1->3[label=<<B>o: [a-t] c:</B>>];
                          2->5[label=<<B>o: [k-p] c:</B>>];
                          2->4[label=<<B>o: [k-s] c:</B>>];
                          3->4[label=<<B>o: [0-9] c:</B>>];
                          4->5[label=<<B>o: [op] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;7;
                          1->2[label=<<B>o: [a-f] c:</B>>];
                          1->3[label=<<B>o: [g-t] c:</B>>];
                          2->4[label=<<B>o: [k-s] c:</B>>];
                          2->5[label=<<B>o: [k-p] c:</B>>];
                          2->6[label=<<B>o: [0-9] c:</B>>];
                          3->6[label=<<B>o: [0-9] c:</B>>];
                          6->7[label=<<B>o: [op] c:</B>>];
                          4->7[label=<<B>o: [op] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equiv_dfas() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m05-8] c:</B>>];
                                1->3[label=<<B>o: [0-9a-h] c:</B>>];
                                2->3[label=<<B>o: [0-9a-h] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-dh-m0-35-8] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equiv_dfas_with_direct_loop() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m05-8] c:</B>>];
                                1->2[label=<<B>o: [z] c:</B>>];
                                2->1[label=<<B>o: [z] c:</B>>];
                                1->3[label=<<B>o: [0-9a-h] c:</B>>];
                                2->3[label=<<B>o: [0-9a-h] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-dh-m0-35-8] c:</B>>];
                                1->1[label=<<B>o: [z] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equiv_dfas_with_indirect_loop() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m05-8] c:</B>>];
                                1->3[label=<<B>o: [0-9a-h] c:</B>>];
                                2->3[label=<<B>o: [0-9a-h] c:</B>>];
                                3->0[label=<<B>o: [z] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-dh-m0-35-8] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                3->0[label=<<B>o: [z] c:</B>>];
                                }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equiv_dfa_and_nfa_without_empty_transition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->12[label=<<B>o: [a-h] c:</B>>];
                                0->2[label=<<B>o: [i-z] c:</B>>];
                                12->2[label=<<B>o: [e-s] c:</B>>];
                                12->3[label=<<B>o: [a-d0-9] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->2[label=<<B>o: [e-s] c:</B>>];
                                1->3[label=<<B>o: [a-d] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_equiv_dfa_and_nfa_with_direct_loop() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [c-h] c:</B>>];
                                2->2[label=<<B>o: [0-5] c:</B>>];
                                2->3[label=<<B>o: [3-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;23;
                                0->2[label=<<B>o: [abi-z] c:</B>>];
                                0->12[label=<<B>o: [c-h] c:</B>>];
                                2->2[label=<<B>o: [0-2] c:</B>>];
                                2->23[label=<<B>o: [3-5] c:</B>>];
                                2->3[label=<<B>o: [6-9] c:</B>>];
                                12->2[label=<<B>o: [0-2] c:</B>>];
                                12->23[label=<<B>o: [3-5] c:</B>>];
                                12->3[label=<<B>o: [6-9c-h] c:</B>>];
                                23->23[label=<<B>o: [3-5] c:</B>>];
                                23->2[label=<<B>o: [0-2] c:</B>>];
                                23->3[label=<<B>o: [6-9] c:</B>>];
                                }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $this->assertTrue($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    public function test_not_equiv_dfas_with_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m05-8] c:</B>>];
                                1->3[label=<<B>o: [0-9] c:</B>>];
                                2->3[label=<<B>o: [a-h] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m0] c:</B>>];
                                0->3[label=<<B>o: [5-8] c:</B>>];
                                1->3[label=<<B>o: [0-9] c:</B>>];
                                2->3[label=<<B>o: [a-h] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, '5', array("2"), array("3")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfas_with_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m05-8] c:</B>>];
                                1->3[label=<<B>o: [0-9] c:</B>>];
                                2->3[label=<<B>o: [a-h] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                0->2[label=<<B>o: [h-m0] c:</B>>];
                                1->3[label=<<B>o: [0-9] c:</B>>];
                                2->3[label=<<B>o: [a-h] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '5', array("2"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfas_with_direct_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                1->1[label=<<B>o: [z] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                2;4;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                1->2[label=<<B>o: [a-h0-9] c:</B>>];
                                1->3[label=<<B>o: [z] c:</B>>];
                                3->4[label=<<B>o: [z] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '1z0', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, '1zz', array("1"), array("4")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfas_with_direct_loop_and_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                1->1[label=<<B>o: [z] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-d1-3] c:</B>>];
                                1->3[label=<<B>o: [a-h0-9] c:</B>>];
                                1->2[label=<<B>o: [z] c:</B>>];
                                2->4[label=<<B>o: [z] c:</B>>];
                                4->2[label=<<B>o: [z] c:</B>>];
                                2->3[label=<<B>o: [a-h0-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '1zz0', array("3"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfas_with_indirect_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                4;
                                0->1[label=<<B>o: [a-d] c:</B>>];
                                1->2[label=<<B>o: [1-3] c:</B>>];
                                1->3[label=<<B>o: [h-m] c:</B>>];
                                2->0[label=<<B>o: [a-h] c:</B>>];
                                2->4[label=<<B>o: [i-n] c:</B>>];
                                3->4[label=<<B>o: [0-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                4;
                                0->1[label=<<B>o: [a-d] c:</B>>];
                                1->2[label=<<B>o: [1-3] c:</B>>];
                                1->3[label=<<B>o: [h-m] c:</B>>];
                                2->0[label=<<B>o: [i-n] c:</B>>];
                                2->4[label=<<B>o: [a-h] c:</B>>];
                                3->4[label=<<B>o: [0-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'a1a', array("0"), array("4")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'a1i', array("4"), array("0")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfas_with_indirect_loop_and_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                4;
                                0->1[label=<<B>o: [a-d] c:</B>>];
                                1->2[label=<<B>o: [1-3] c:</B>>];
                                1->3[label=<<B>o: [h-m] c:</B>>];
                                2->4[label=<<B>o: [a-h] c:</B>>];
                                3->4[label=<<B>o: [0-9] c:</B>>];
                                4->0[label=<<B>o: [i-n] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                4;
                                0->1[label=<<B>o: [a-d] c:</B>>];
                                1->2[label=<<B>o: [1-3] c:</B>>];
                                1->3[label=<<B>o: [h-m] c:</B>>];
                                2->4[label=<<B>o: [a-h] c:</B>>];
                                3->4[label=<<B>o: [0-9] c:</B>>];
                                4->1[label=<<B>o: [i-n] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'a1ai1', array(), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a1aia', array("1"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'a1aih', array(), array("3")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfa_and_nfa_with_direct_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                2;3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                1->1[label=<<B>o: [xz] c:</B>>];
                                1->2[label=<<B>o: [xz] c:</B>>];
                                1->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                2;3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                1->1[label=<<B>o: [xz] c:</B>>];
                                1->2[label=<<B>o: [5-9] c:</B>>];
                                1->3[label=<<B>o: [0-4] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'cx', array("2", "1"), array("1")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfa_and_nfa_with_direct_loop_and_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->12[label=<<B>o: [a-z] c:</B>>];
                                12->3[label=<<B>o: [c-h3-9] c:</B>>];
                                12->4[label=<<B>o: [0-2] c:</B>>];
                                4->3[label=<<B>o: [c-h3-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [c-h] c:</B>>];
                                2->2[label=<<B>o: [0-5] c:</B>>];
                                2->3[label=<<B>o: [3-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ac', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'a00', array(), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a0c', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'a30', array(), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, 'a33', array(), array("3", "2")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfa_and_nfa_with_indirect_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->12[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [abi-z] c:</B>>];
                                12->3[label=<<B>o: [c-h3-9] c:</B>>];
                                2->0[label=<<B>o: [3-9] c:</B>>];
                                3->0[label=<<B>o: [k-o] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [c-h] c:</B>>];
                                2->3[label=<<B>o: [3-9] c:</B>>];
                                3->0[label=<<B>o: [k-o] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'a3', array("0"), array("3")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_dfa_and_nfa_with_indirect_loop_and_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                5;
                                0->1[label=<<B>o: [0-9] c:</B>>];
                                0->4[label=<<B>o: [abix1-4] c:</B>>];
                                4->1[label=<<B>o: [kln] c:</B>>];
                                1->5[label=<<B>o: [09] c:</B>>];
                                1->2[label=<<B>o: [a-h] c:</B>>];
                                2->3[label=<<B>o: [i-n0-5] c:</B>>];
                                3->1[label=<<B>o: [zxkt] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                5;
                                0->1[label=<<B>o: [05-9] c:</B>>];
                                0->4[label=<<B>o: [abix] c:</B>>];
                                0->14[label=<<B>o: [1-4] c:</B>>];
                                4->1[label=<<B>o: [kln] c:</B>>];
                                14->1[label=<<B>o: [kln] c:</B>>];
                                14->5[label=<<B>o: [09] c:</B>>];
                                1->2[label=<<B>o: [a-h] c:</B>>];
                                2->3[label=<<B>o: [i-n0-5] c:</B>>];
                                3->14[label=<<B>o: [zxkt] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '00', array("5"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '1a', array("2"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '0a0ka', array("2"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, '0a0kk', array(), array("1")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_nfas_with_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-z] c:</B>>];
                                0->2[label=<<B>o: [a-h] c:</B>>];
                                1->2[label=<<B>o: [a-s] c:</B>>];
                                1->3[label=<<B>o: [a-d] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-z] c:</B>>];
                                0->2[label=<<B>o: [a-h] c:</B>>];
                                1->2[label=<<B>o: [a-s] c:</B>>];
                                1->3[label=<<B>o: [a-s] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'ae', array("2"), array("3", "2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'ie', array("2"), array("3", "2")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_nfas_with_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-z] c:</B>>];
                                0->2[label=<<B>o: [a-h] c:</B>>];
                                1->2[label=<<B>o: [a-s] c:</B>>];
                                1->3[label=<<B>o: [a-d] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [a-z] c:</B>>];
                                0->2[label=<<B>o: [a-h] c:</B>>];
                                1->2[label=<<B>o: [g-s] c:</B>>];
                                1->3[label=<<B>o: [a-d] c:</B>>];
                                2->3[label=<<B>o: [0-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ae'));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ie'));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'aa0'));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_not_equiv_nfas_with_direct_loop_and_difftransition() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [c-h] c:</B>>];
                                2->2[label=<<B>o: [0-5] c:</B>>];
                                2->3[label=<<B>o: [3-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [c-h] c:</B>>];
                                0->2[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [c-h] c:</B>>];
                                2->4[label=<<B>o: [0-5] c:</B>>];
                                4->2[label=<<B>o: [0-5] c:</B>>];
                                2->3[label=<<B>o: [3-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'a03', array("3", "2"), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a06', array("3"), array()));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'a33', array("3", "2"), array("2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'a36', array("3"), array()));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_nfas_with_direct_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [0-5a-h] c:</B>>];
                                0->2[label=<<B>o: [3-8c-z] c:</B>>];
                                2->2[label=<<B>o: [z] c:</B>>];
                                2->3[label=<<B>o: [a-y] c:</B>>];
                                1->3[label=<<B>o: [3-9xy] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                3;
                                0->1[label=<<B>o: [0-5a-h] c:</B>>];
                                0->2[label=<<B>o: [3-8c-z] c:</B>>];
                                2->2[label=<<B>o: [z] c:</B>>];
                                2->3[label=<<B>o: [a-z] c:</B>>];
                                1->3[label=<<B>o: [3-9xy] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, '3z', array("2"), array("3", "2")));
        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, '6z', array("2"), array("3", "2")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }
    public function test_not_equiv_nfas_with_indirect_loop_and_early_endstate() {
        $firstfadescription = 'digraph {
                                0;
                                6;
                                0->1[label=<<B>o: [0-5a-h] c:</B>>];
                                1->2[label=<<B>o: [3-8c-z] c:</B>>];
                                1->6[label=<<B>o: [c-z] c:</B>>];
                                1->4[label=<<B>o: [1-7] c:</B>>];
                                2->3[label=<<B>o: [abkl] c:</B>>];
                                4->5[label=<<B>o: [hklo] c:</B>>];
                                3->1[label=<<B>o: [yz] c:</B>>];
                                5->1[label=<<B>o: [1-9] c:</B>>];
                                }';
        $secondfadescription = 'digraph {
                                0;
                                6;
                                0->1[label=<<B>o: [0-5a-h] c:</B>>];
                                1->2[label=<<B>o: [3-8c-z] c:</B>>];
                                1->6[label=<<B>o: [h-z] c:</B>>];
                                1->4[label=<<B>o: [1-7] c:</B>>];
                                2->3[label=<<B>o: [abkl] c:</B>>];
                                4->5[label=<<B>o: [hklo] c:</B>>];
                                3->1[label=<<B>o: [yz] c:</B>>];
                                5->1[label=<<B>o: [1-9] c:</B>>];
                                }';
        $mismatches = array();
        $expmismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        array_push($expmismatches, $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, '0c', array("6", "2"), array("2")));

        $this->assertFalse($firstfa->equal($secondfa, $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches));
    }

    // Tests for subpattern dividing
    public function test_2x2_only_subpatterns_differ() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:2, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [b] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [b] c:</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'));
        $expmismatches[0]->uniquesubpatterns[0][] = 2;
        $expmismatches[1]->uniquesubpatterns[0][] = 2;
        $expmismatches[1]->uniquesubpatterns[1][] = 1;
        $expmismatches[2]->uniquesubpatterns[0][] = 1;
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_2x1_subpattern_differ() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:2, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [b] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'));
        $expmismatches[0]->uniquesubpatterns[0][] = 2;
        $expmismatches[0]->uniquesubpatterns[1][] = 1;
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_2x2_after_subpatterns_dividing_differ() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->4[label=<<B>o:7, [b] c:7,</B>>];
                          2->5[label=<<B>o:5, [b] c:5,</B>>];
                          4->3[label=<<B>o: [c] c:1,</B>>];
                          5->3[label=<<B>o: [c] c:3,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->4[label=<<B>o:5, [b] c:5,</B>>];
                          2->5[label=<<B>o:8, [b] c:8,</B>>];
                          4->3[label=<<B>o: [c] c:1,</B>>];
                          5->3[label=<<B>o: [c] c:3,</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'abc'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'abc'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'abc'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'abc'));
        $expmismatches[0]->matchedsubpatterns[] = 3;
        $expmismatches[0]->uniquesubpatterns[0][] = 5;
        $expmismatches[0]->uniquesubpatterns[1][] = 8;
        $expmismatches[1]->matchedsubpatterns[] = 5;
        $expmismatches[1]->uniquesubpatterns[0][] = 3;
        $expmismatches[1]->uniquesubpatterns[1][] = 1;
        $expmismatches[2]->uniquesubpatterns[0] = array(1, 7);
        $expmismatches[2]->uniquesubpatterns[1] = array(3, 8);
        $expmismatches[3]->matchedsubpatterns[] = 1;
        $expmismatches[3]->uniquesubpatterns[0][] = 7;
        $expmismatches[3]->uniquesubpatterns[1][] = 5;
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_2x2_after_subpatterns_dividing_character_mismatch() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [c] c:3,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [b] c:3,</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, 'ac'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'));
        $expmismatches[1]->uniquesubpatterns[0][] = 1;
        $expmismatches[1]->uniquesubpatterns[1][] = 3;
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_2x2_after_subpatterns_dividing_final_state_mismatch() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->4[label=<<B>o: [c] c:</B>>];
                          4->3[label=<<B>o: [d] c:3,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [c] c:3,</B>>];
                          2->4[label=<<B>o: [c] c:</B>>];
                          4->3[label=<<B>o: [d] c:3,</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'ac'));
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    public function test_2x2_subpatterns_dividing_mismatch() {
        $firstfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [c] c:1,</B>>];
                          2->3[label=<<B>o: [b] c:3,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          0;
                          3;
                          0->1[label=<<B>o:1, [a] c:</B>>];
                          0->2[label=<<B>o:3, [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:1,</B>>];
                          2->3[label=<<B>o: [c] c:3,</B>>];
                          }';

        $mismatches = array();
        $firstfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($firstfadescription));
        $secondfa = $this->switch_meta_to_subexpr(\qtype_preg\fa\fa::read_fa($secondfadescription));
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ab'),
            $this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, 'ac'));
        $expmismatches[0]->uniquesubpatterns[0][] = 3;
        $expmismatches[0]->uniquesubpatterns[1][] = 1;
        $expmismatches[1]->uniquesubpatterns[0][] = 1;
        $expmismatches[1]->uniquesubpatterns[1][] = 3;
        $this->assertFalse($firstfa->equal($secondfa, $mismatches, true));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }

    // Tests for regex equivalence check with automata.
    function test_equal_simple_regexes() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('[0-9]{4}', '[0-9][0-9][0-9][0-9]', $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }
    function test_regexes_with_character_mismatch() {
        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '000'));
        $this->assertFalse($this->compare_regexes('[0-9]{4}', '[0-9][0-9][1-9][0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 0, '9'));
        $this->assertFalse($this->compare_regexes('[0-9]{4}', '[0-8][0-9][0-9][0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, '9'));
        $this->assertFalse($this->compare_regexes('[0-8]{4}', '[0-9][0-8][0-8][0-8]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::CHARACTER, 1, '000a'));
        $this->assertFalse($this->compare_regexes('[0-9]{4}', '[0-9][0-9][0-9][0-9a]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_regexes_with_final_state_mismatch() {
        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, '0000'));
        $this->assertFalse($this->compare_regexes('[0-9]{4}', '[0-9][0-9][0-9][0-9][0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, '0000'));
        $this->assertFalse($this->compare_regexes('[0-9]{5}', '[0-9][0-9][0-9][0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_regexes_with_subpattern_mismatch() {
        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, '0000'));
        $expmismatches[0]->matchedsubpatterns[] = 1;
        $expmismatches[0]->uniquesubpatterns[0][] = 1;
        $this->assertFalse($this->compare_regexes('([0-9]){4}', '[0-9]([0-9][0-9])[0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::SUBPATTERN, -1, '0000'));
        $expmismatches[0]->uniquesubpatterns[1][] = 1;
        $this->assertFalse($this->compare_regexes('[0-9]{4}', '[0-9]([0-9][0-9])[0-9]', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_plus_quantifier() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('a+', 'a+', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'a'));
        $this->assertFalse($this->compare_regexes('a+', 'aa+', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_star_quantifier() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('qa*', 'qa*', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 1, 'q'));
        $this->assertFalse($this->compare_regexes('qa+', 'qa*', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_numerical_quantifiers() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('q{3,5}', 'qqq{1,3}', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $this->assertTrue($this->compare_regexes('q{3,}', 'qqq+', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, 'qqq'));
        $this->assertFalse($this->compare_regexes('q{3,5}', 'qqq{2,4}', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_epsilon_transition() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('q*', 'q*', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $expmismatches = array($this->create_mismatch(\qtype_preg\fa\equivalence\mismatched_pair::FINAL_STATE, 0, ''));
        $this->assertFalse($this->compare_regexes('q*', 'q+', $mismatches));
        $this->assertTrue($this->compare_mismatches($mismatches, $expmismatches, false));
    }
    function test_assertions() {
        $mismatches = array();
        $this->assertTrue($this->compare_regexes('^qwerty', '^qwerty', $mismatches));
        $this->assertTrue(count($mismatches) == 0);

        $mismatches = array();
        $this->assertTrue($this->compare_regexes('qwerty$', 'qwerty$', $mismatches));
        $this->assertTrue(count($mismatches) == 0);
    }

/*
    // Tests for function divide_crossed_intervals
    public function test_non_crossed_groups() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o:1, [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(3), array(), array(), array(1), 99), // c
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 101)); // e

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_adjoining_intervals_in_one_group() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [b] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(3), array(), array(), array(), 98), // b
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 101)); // e

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_adjoining_intervals_between_groups() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [d] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(3), array(), array(), array(), 99), // c
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 100)); // d

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_adjoining_intervals_between_groups_with_tags() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [c] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o:1, [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(3), array(), array(), array(1), 99), // c/1
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(2), 99)); // c/2

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_crossed_intervals_in_one_group() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [d] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a-c] c:</B>>];
                          1->3[label=<<B>o: [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(2, 3), array(), array(), array(), 99), // c
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 100)); // d

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_crossed_intervals_between_groups() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [cd] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o: [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(2), array(3), array(), array(), array(), 99), // c
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 100)); // d

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_crossed_intervals_between_groups_with_tags() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o:1,3,5, [c] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o:1,3, [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(2), array(3), array(), array(), array(1, 3), 99), // c/1,3
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(5), 99)); // c/5

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_crossed_intervals_with_single_tag() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o:3, [c] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o:3, [c] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(2), array(3), array(), array(), array(3), 99)); // c/3

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_crossed_intervals_with_some_tags() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o:1,3,5, [c] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [c] c:5,</B>>];
                          1->3[label=<<B>o: [c] c:1,3,</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(2), array(3), array(), array(), array(1, 3), 99), // c/1,3
                          $this->create_pair_of_groups(array(2), array(2), array(), array(), array(5), 99)); // c/5

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_some_transitions_in_both_groups() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->3[label=<<B>o:3, [d-u] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [a-h] c:</B>>];
                          1->3[label=<<B>o: [s] c:</B>>];
                          1->4[label=<<B>o:3, [s] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(2), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(), array(2), array(), array(), array(), 98), // b
                          $this->create_pair_of_groups(array(), array(2), array(), array(), array(), 100), // d
                          $this->create_pair_of_groups(array(3), array(), array(), array(), array(3), 100), // d/3
                          $this->create_pair_of_groups(array(3), array(), array(), array(), array(3), 105), // i/3
                          $this->create_pair_of_groups(array(), array(3), array(), array(), array(), 115), // s
                          $this->create_pair_of_groups(array(3), array(4), array(), array(), array(3), 115), // s/3
                          $this->create_pair_of_groups(array(3), array(), array(), array(), array(3), 116)); // t/3

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_multiple_transitions_with_single_characters_cross() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a-dh-j] c:</B>>];
                          1->3[label=<<B>o: [ae-gk-m] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [abehikl] c:</B>>];
                          1->3[label=<<B>o: [acfhjkm] c:</B>>];
                          1->4[label=<<B>o: [adgijlm] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(2, 3), array(2, 3, 4), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(2), array(2), array(), array(), array(), 98), // b
                          $this->create_pair_of_groups(array(2), array(3), array(), array(), array(), 99), // c
                          $this->create_pair_of_groups(array(2), array(4), array(), array(), array(), 100), // d
                          $this->create_pair_of_groups(array(3), array(2), array(), array(), array(), 101), // e
                          $this->create_pair_of_groups(array(3), array(3), array(), array(), array(), 102), // f
                          $this->create_pair_of_groups(array(3), array(4), array(), array(), array(), 103), // g
                          $this->create_pair_of_groups(array(2), array(2, 3), array(), array(), array(), 104), // h
                          $this->create_pair_of_groups(array(2), array(2, 4), array(), array(), array(), 105), // i
                          $this->create_pair_of_groups(array(2), array(3, 4), array(), array(), array(), 106), // j
                          $this->create_pair_of_groups(array(3), array(2, 3), array(), array(), array(), 107), // k
                          $this->create_pair_of_groups(array(3), array(2, 4), array(), array(), array(), 108), // l
                          $this->create_pair_of_groups(array(3), array(3, 4), array(), array(), array(), 109)); // m

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }
    public function test_interval_and_enumeration_with_non_tag_in_middle() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [a-c] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [ac] c:</B>>];
                          1->2[label=<<B>o:3, [b] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        $firstfa->set_ranges();
        $secondfa->set_ranges();
        $pairofgroups = $this->create_initial_pair_of_groups($firstfa, $secondfa);
        $intervals = array();

        divide_crossed_intervals($pairofgroups, $intervals);

        $exppairs = array($this->create_pair_of_groups(array(2), array(2), array(), array(), array(), 97), // a
                          $this->create_pair_of_groups(array(2), array(), array(), array(), array(), 98), // b
                          $this->create_pair_of_groups(array(), array(2), array(), array(), array(3), 98), // b/3
                          $this->create_pair_of_groups(array(2), array(2), array(), array(), array(), 99)); // c

        $this->assertTrue($this->compare_pairs($intervals, $exppairs));
    }

    // Tests for charset intervals dividing function
    function create_lexer($regex, $options = null) {
        if ($options === null) {
            $options = new qtype_preg_handling_options();
            $options->preserveallnodes = true;
        }
        StringStreamController::createRef('regex', $regex);
        $pseudofile = fopen('string://regex', 'r');
        $lexer = new qtype_preg_lexer($pseudofile);
        $lexer->set_options($options);
        return $lexer;
    }

    function leaf_by_regex($regex, $options = null) {
        $lexer = $this->create_lexer($regex, $options);
        return $lexer->nextToken()->value;
    }

    function test_x1_non_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[4-6h-io-u]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b), $indexes);

        $expres = array('[0-37-9a-g]',
                        '[4-6h-io-u]');
        $expindexes = array(array(array(0), array( )),
                            array(array( ), array(0)));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x1_part_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[2-9a-io-u]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b), $indexes);

        $expres = array('[0-1]',
                        '[2-37-9a-g]',
                        '[4-6h-io-u]');
        $expindexes = array(array(array(0), array( )),
                            array(array(0), array(0)),
                            array(array( ), array(0)));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x1_full_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[0-37-9a-g]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b), $indexes);

        $expres = array('[0-37-9a-g]');
        $expindexes = array(array(array(0), array(0)));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x2_non_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[4-5o-qx-z]');
        $c = $this->leaf_by_regex('[4-6i-ko-v]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c), $indexes);

        $expres = array('[0-37-9a-g]',
                        '[4-5o-q]',
                        '[6i-kr-v]',
                        '[x-z]');
        $expindexes = array(array(array(0), array(    )),
                            array(array( ), array(0, 1)),
                            array(array( ), array(1   )),
                            array(array( ), array(0   )));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x2_part_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[4-8o-qx-z]');
        $c = $this->leaf_by_regex('[0-6a-ko-v]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c), $indexes);

        $expres = array('[0-3a-g]',
                        '[4-6o-q]',
                        '[7-8]',
                        '[9]',
                        '[h-kr-v]',
                        '[x-z]');
        $expindexes = array(array(array(0), array(1   )),
                            array(array( ), array(0, 1)),
                            array(array(0), array(0   )),
                            array(array(0), array(    )),
                            array(array( ), array(1   )),
                            array(array( ), array(0   )));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x2_full_crossed() {
        $a = $this->leaf_by_regex('[0-47-9c-n]');
        $b = $this->leaf_by_regex('[0-37-9d-g]');
        $c = $this->leaf_by_regex('[2-4c-fh-n]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c), $indexes);

        $expres = array('[0-17-9g]',
                        '[2-3d-f]',
                        '[4ch-n]');
        $expindexes = array(array(array(0), array(0   )),
                            array(array(0), array(0, 1)),
                            array(array(0), array(1   )));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x3_non_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[4-5o-qx-z]');
        $c = $this->leaf_by_regex('[4-6i-ko-v]');
        $d = $this->leaf_by_regex('[j-lrw]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c, $d), $indexes);

        $expres = array('[0-37-9a-g]',
                        '[4-5o-q]',
                        '[6is-v]',
                        '[j-kr]',
                        '[lw]',
                        '[x-z]');
        $expindexes = array(array(array(0), array(    )),
                            array(array( ), array(0, 1)),
                            array(array( ), array(1   )),
                            array(array( ), array(1, 2)),
                            array(array( ), array(2   )),
                            array(array( ), array(0   )));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x3_part_crossed() {
        $a = $this->leaf_by_regex('[0-37-9a-g]');
        $b = $this->leaf_by_regex('[4-5o-qx-z]');
        $c = $this->leaf_by_regex('[0-6a-ko-v]');
        $d = $this->leaf_by_regex('[j-lrw]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c, $d), $indexes);

        $expres = array('[0-3a-g]',
                        '[4-5o-q]',
                        '[6h-is-v]',
                        '[7-9]',
                        '[j-kr]',
                        '[lw]',
                        '[x-z]');
        $expindexes = array(array(array(0), array(1   )),
                            array(array( ), array(0, 1)),
                            array(array( ), array(1   )),
                            array(array(0), array(    )),
                            array(array( ), array(1, 2)),
                            array(array( ), array(2   )),
                            array(array( ), array(0   )));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }

    function test_x3_full_crossed() {
        $a = $this->leaf_by_regex('[0-8a-kn-y]');
        $b = $this->leaf_by_regex('[4-8b-hj-k]');
        $c = $this->leaf_by_regex('[0-6a-cn-v]');
        $d = $this->leaf_by_regex('[irw-y]');
        $indexes = array();

        $res = qtype_preg_leaf_charset::divide_intervals(array($a), array($b, $c, $d), $indexes);

        $expres = array('[0-3an-qs-v]',
                        '[4-6b-c]',
                        '[7-8d-hj-k]',
                        '[iw-y]',
                        '[r]');
        $expindexes = array(array(array(0), array(1   )),
                            array(array(0), array(0, 1)),
                            array(array(0), array(0   )),
                            array(array(0), array(2   )),
                            array(array(0), array(1, 2)));

        $this->assertEquals($expres, $res);
        $this->assertEquals($expindexes, $indexes);
    }
*/
/*    // Tests for transitions intervals dividing function
    private $without_tags = true;
    private $with_tags = false;
    function compare_tagsets($first, $second) {
        if (count($first) != count($second))
            return false;

        for($i = 0; $i < count($first); ++$i) {
            if ($first[$i]->subpattern != $second[$i]->subpattern)
                return false;
        }

        return true;
    }
    function compare_conditions_of_transitions($first, $second) {
        if (count($first) != count($second))
            return false;

        for($i = 0; $i < count($first); ++$i) {
            if ($first[$i]->pregleaf->excluding_ranges() != $second[$i]->pregleaf->excluding_ranges() ||
                !$this->compare_tagsets($first[$i]->opentags, $second[$i]->opentags) ||
                !$this->compare_tagsets($first[$i]->closetags, $second[$i]->closetags))
                return false;
        }

        return true;
    }
    function test_tr_1x1_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o:1, [e] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array(), array(0)),
                                array(array(0), array()));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o: [e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(), array(0)),
                                array(array(0), array()));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    function test_tr_1x1_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [a-e] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o:1, [a-d] c:</B>>];
                              1->2[label=<<B>o:1, [e] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array( ), array(0)),
                                array(array(0), array(0)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a-d] c:</B>>];
                              1->2[label=<<B>o: [e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array( ), array(0)),
                                array(array(0), array(0)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_1x1_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [a-e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [a-e] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o:1, [a-e] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a-e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }

    public function test_tr_1x2_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [e] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [ab] c:</B>>];
                          1->2[label=<<B>o: [c] c:2,</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o:1, [ab] c:</B>>];
                              1->2[label=<<B>o: [c] c:2,</B>>];
                              1->2[label=<<B>o: [e] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array( ), array(0)),
                                array(array( ), array(1)),
                                array(array(0), array( )));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [ab] c:</B>>];
                              1->2[label=<<B>o: [c] c:</B>>];
                              1->2[label=<<B>o: [e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array( ), array(0)),
                                array(array( ), array(1)),
                                array(array(0), array( )));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_1x2_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [e] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [ab] c:</B>>];
                          1->2[label=<<B>o: [c-e] c:2,</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o:1, [ab] c:</B>>];
                              1->2[label=<<B>o: [cd] c:2,</B>>];
                              1->2[label=<<B>o: [e] c:2,</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array( ), array(0)),
                                array(array( ), array(1)),
                                array(array(0), array(1)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [ab] c:</B>>];
                              1->2[label=<<B>o: [cd] c:</B>>];
                              1->2[label=<<B>o: [e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array( ), array(0)),
                                array(array( ), array(1)),
                                array(array(0), array(1)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_1x2_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a-e] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [ab] c:2,</B>>];
                          1->2[label=<<B>o: [c-e] c:2,</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [ab] c:2,</B>>];
                              1->2[label=<<B>o: [c-e] c:2,</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array(0), array(0)),
                                array(array(0), array(1)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [ab] c:</B>>];
                              1->2[label=<<B>o: [c-e] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)),
                                array(array(0), array(1)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_2x3_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->2[label=<<B>o: [c-e] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,</B>>];
                          1->2[label=<<B>o: [f-h] c:2,</B>>];
                          1->2[label=<<B>o:1, [i] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o: [a] c:4,</B>>];
                              1->2[label=<<B>o: [c-e] c:2,</B>>];
                              1->2[label=<<B>o: [f-h] c:2,</B>>];
                              1->2[label=<<B>o:1, [i] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array(0), array(0)),
                                array(array( ), array(0)),
                                array(array(1), array( )),
                                array(array( ), array(1)),
                                array(array( ), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o: [c-e] c:</B>>];
                              1->2[label=<<B>o: [f-h] c:</B>>];
                              1->2[label=<<B>o: [i] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)),
                                array(array(1), array( )),
                                array(array( ), array(1)),
                                array(array( ), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_2x3_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [a] c:4,</B>>];
                          1->2[label=<<B>o: [c-e] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,</B>>];
                          1->2[label=<<B>o: [d-h] c:2,</B>>];
                          1->2[label=<<B>o:1, [i] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o:1, [a] c:4,</B>>];
                              1->2[label=<<B>o: [a] c:4,</B>>];
                              1->2[label=<<B>o: [c] c:2,</B>>];
                              1->2[label=<<B>o: [d-e] c:2,</B>>];
                              1->2[label=<<B>o: [f-h] c:2,</B>>];
                              1->2[label=<<B>o:1, [i] c:</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array(0), array( )),
                                array(array(0), array(0)),
                                array(array(1), array( )),
                                array(array(1), array(1)),
                                array(array( ), array(1)),
                                array(array( ), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o: [c] c:</B>>];
                              1->2[label=<<B>o: [d-e] c:</B>>];
                              1->2[label=<<B>o: [f-h] c:</B>>];
                              1->2[label=<<B>o: [i] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)),
                                array(array(1), array( )),
                                array(array(1), array(1)),
                                array(array( ), array(1)),
                                array(array( ), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_2x3_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,</B>>];
                          1->2[label=<<B>o: [d-i] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,</B>>];
                          1->2[label=<<B>o: [d-h] c:2,</B>>];
                          1->2[label=<<B>o: [i] c:2,</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        if ($this->with_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:4,</B>>];
                              1->2[label=<<B>o: [d-h] c:2,</B>>];
                              1->2[label=<<B>o: [i] c:2,</B>>];
                              }';
            $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);
            $expindexes = array(array(array(0), array(0)),
                                array(array(1), array(1)),
                                array(array(1), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
            $this->assertEquals($expindexes, $indexes);
        }
        if ($this->without_tags) {
            $indexes = array();
            $resfadescription = 'digraph {
                              1;
                              2;
                              1->2[label=<<B>o: [a] c:</B>>];
                              1->2[label=<<B>o: [d-h] c:</B>>];
                              1->2[label=<<B>o: [i] c:</B>>];
                              }';
            $resfawithouttags = \qtype_preg\fa\fa::read_fa($resfadescription);

            $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes);
            $expindexes = array(array(array(0), array(0)),
                                array(array(1), array(1)),
                                array(array(1), array(2)));

            $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfawithouttags), $res));
            $this->assertEquals($expindexes, $indexes);
        }
    }
    public function test_tr_2x3_open_and_close_tags_in_one_tr() {
        if (!$this->with_tags)
            return;

        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:3, [a] c:4,</B>>];
                          1->2[label=<<B>o:1, [d-i] c:2,</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:3, [a] c:4,</B>>];
                          1->2[label=<<B>o:1, [d-h] c:2,</B>>];
                          1->2[label=<<B>o: [i] c:2,</B>>];
                          }';
        $resfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:3, [a] c:4,</B>>];
                          1->2[label=<<B>o:1, [d-h] c:2,</B>>];
                          1->2[label=<<B>o:1, [i] c:2,</B>>];
                          1->2[label=<<B>o: [i] c:2,</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);
        $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);
        $indexes = array();

        $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);

        $expindexes = array(array(array(0), array(0)),
                            array(array(1), array(1)),
                            array(array(1), array( )),
                            array(array(1), array(2)));

        $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
        $this->assertEquals($expindexes, $indexes);
    }
    public function test_tr_2x3_multiple_tags_in_one_tr() {
        if (!$this->with_tags)
            return;

        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,6,8,</B>>];
                          1->2[label=<<B>o:1,3,5, [d-i] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:3, [a] c:4,</B>>];
                          1->2[label=<<B>o:1,3, [d-h] c:</B>>];
                          1->2[label=<<B>o:1,3,5, [i] c:</B>>];
                          }';
        $resfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,6,8,</B>>];
                          1->2[label=<<B>o:3, [a] c:4,</B>>];
                          1->2[label=<<B>o: [a] c:4,</B>>];
                          1->2[label=<<B>o:1,3,5, [d-h] c:</B>>];
                          1->2[label=<<B>o:1,3, [d-h] c:</B>>];
                          1->2[label=<<B>o:1,3,5, [i] c:</B>>];
                          }';
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);
        $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);
        $indexes = array();

        $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);

        $expindexes = array(array(array(0), array( )),
                            array(array( ), array(0)),
                            array(array(0), array(0)),
                            array(array(1), array( )),
                            array(array(1), array(1)),
                            array(array(1), array(2)));

        $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
        $this->assertEquals($expindexes, $indexes);
    }
    public function test_tr_2x3_non_crossed_tags() {
        if (!$this->with_tags)
            return;

        $firstfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,6,</B>>];
                          1->2[label=<<B>o:1, [d-i] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o:1, [a] c:</B>>];
                          1->2[label=<<B>o:3, [d-h] c:</B>>];
                          1->2[label=<<B>o:5, [i] c:</B>>];
                          }';
        $resfadescription = 'digraph {
                          1;
                          2;
                          1->2[label=<<B>o: [a] c:4,6,</B>>];
                          1->2[label=<<B>o:1, [a] c:</B>>];
                          1->2[label=<<B>o: [a] c:</B>>];
                          1->2[label=<<B>o:1, [d-h] c:</B>>];
                          1->2[label=<<B>o:3, [d-h] c:</B>>];
                          1->2[label=<<B>o: [d-h] c:</B>>];
                          1->2[label=<<B>o:1, [i] c:</B>>];
                          1->2[label=<<B>o:5, [i] c:</B>>];
                          1->2[label=<<B>o: [i] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);
        $resfa = \qtype_preg\fa\fa::read_fa($resfadescription);
        $indexes = array();

        $res = \qtype_preg\fa\fa_transition::divide_intervals($this->create_array_of_transitions_from_fa($firstfa), $this->create_array_of_transitions_from_fa($secondfa), $indexes, true);

        $expindexes = array(array(array(0), array( )),
                            array(array( ), array(0)),
                            array(array(0), array(0)),
                            array(array(1), array( )),
                            array(array( ), array(1)),
                            array(array(1), array(1)),
                            array(array(1), array( )),
                            array(array( ), array(2)),
                            array(array(1), array(2)));

        $this->assertTrue($this->compare_conditions_of_transitions($this->create_array_of_transitions_from_fa($resfa), $res));
        $this->assertEquals($expindexes, $indexes);
    }


    /*function test_1x1_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-6h-io-u] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array( ), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('4'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array( ), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array( ), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('h'), ord('i'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('o'), ord('u'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    /*function test_1x1_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [2-9a-io-u] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array( ), array(ord('0'), ord('1'))),
                        $this->create_pair_of_groups(array(2), array(2), array(ord('2'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('4'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(2), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(2), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('h'), ord('i'))),
                        $this->create_pair_of_groups(array( ), array(2), array(ord('o'), ord('u'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x1_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(2), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array(2), array(2), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(2), array(ord('a'), ord('g'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x2_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-5o-qx-z] c:</B>>];
                          1->3[label=<<B>o: [4-6i-ko-v] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(    ), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('4'), ord('5'))),
                        $this->create_pair_of_groups(array( ), array(   3), array(ord('6'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(   3), array(ord('i'), ord('k'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('o'), ord('q'))),
                        $this->create_pair_of_groups(array( ), array(   3), array(ord('r'), ord('v'))),
                        $this->create_pair_of_groups(array( ), array(2   ), array(ord('x'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x2_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-8o-qx-z] c:</B>>];
                          1->3[label=<<B>o: [0-6a-ko-v] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(   3), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('4'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('7'), ord('8'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('9'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(   3), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(   3), array(ord('h'), ord('k'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('o'), ord('q'))),
                        $this->create_pair_of_groups(array( ), array(   3), array(ord('r'), ord('v'))),
                        $this->create_pair_of_groups(array( ), array(2   ), array(ord('x'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x2_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-47-9c-n] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0-37-9d-g] c:</B>>];
                          1->3[label=<<B>o: [2-4c-fh-n] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(2   ), array(ord('0'), ord('1'))),
                        $this->create_pair_of_groups(array(2), array(2, 3), array(ord('2'), ord('3'))),
                        $this->create_pair_of_groups(array(2), array(   3), array(ord('4'), ord('4'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(   3), array(ord('c'), ord('c'))),
                        $this->create_pair_of_groups(array(2), array(2, 3), array(ord('d'), ord('f'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('g'), ord('g'))),
                        $this->create_pair_of_groups(array(2), array(   3), array(ord('h'), ord('n'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x3_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-5o-qx-z] c:</B>>];
                          1->3[label=<<B>o: [4-6i-ko-v] c:</B>>];
                          1->4[label=<<B>o: [j-irw] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(    ), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('4'), ord('5'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('6'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('i'), ord('i'))),
                        $this->create_pair_of_groups(array( ), array(3, 4), array(ord('j'), ord('k'))),
                        $this->create_pair_of_groups(array( ), array(4   ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('o'), ord('q'))),
                        $this->create_pair_of_groups(array( ), array(3, 4), array(ord('r'), ord('r'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('s'), ord('v'))),
                        $this->create_pair_of_groups(array( ), array(4   ), array(ord('w'), ord('w'))),
                        $this->create_pair_of_groups(array( ), array(2   ), array(ord('x'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x3_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-37-9a-g] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-5o-qx-z] c:</B>>];
                          1->3[label=<<B>o: [0-6a-ko-v] c:</B>>];
                          1->4[label=<<B>o: [j-lrw] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(3   ), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('4'), ord('5'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('6'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(    ), array(ord('7'), ord('9'))),
                        $this->create_pair_of_groups(array(2), array(3   ), array(ord('a'), ord('g'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('h'), ord('i'))),
                        $this->create_pair_of_groups(array( ), array(3, 4), array(ord('j'), ord('k'))),
                        $this->create_pair_of_groups(array( ), array(4   ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array( ), array(2, 3), array(ord('o'), ord('q'))),
                        $this->create_pair_of_groups(array( ), array(3, 4), array(ord('r'), ord('r'))),
                        $this->create_pair_of_groups(array( ), array(3   ), array(ord('s'), ord('v'))),
                        $this->create_pair_of_groups(array( ), array(4   ), array(ord('w'), ord('w'))),
                        $this->create_pair_of_groups(array( ), array(2   ), array(ord('x'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_1x3_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [0-8a-kn-y] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [4-8b-hj-k] c:</B>>];
                          1->3[label=<<B>o: [0-6a-cn-v] c:</B>>];
                          1->4[label=<<B>o: [irw-y] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2), array(3   ), array(ord('0'), ord('3'))),
                        $this->create_pair_of_groups(array(2), array(2, 3), array(ord('4'), ord('6'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('7'), ord('8'))),
                        $this->create_pair_of_groups(array(2), array(3   ), array(ord('a'), ord('a'))),
                        $this->create_pair_of_groups(array(2), array(2, 3), array(ord('b'), ord('c'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('d'), ord('h'))),
                        $this->create_pair_of_groups(array(2), array(4   ), array(ord('i'), ord('i'))),
                        $this->create_pair_of_groups(array(2), array(2   ), array(ord('j'), ord('k'))),
                        $this->create_pair_of_groups(array(2), array(3   ), array(ord('n'), ord('q'))),
                        $this->create_pair_of_groups(array(2), array(3, 4), array(ord('r'), ord('r'))),
                        $this->create_pair_of_groups(array(2), array(3   ), array(ord('s'), ord('v'))),
                        $this->create_pair_of_groups(array(2), array(4   ), array(ord('w'), ord('y'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x2_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0m-py] c:</B>>];
                          1->3[label=<<B>o: [8-9o-rw] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(    ), array(2   ), array(ord('0'), ord('0'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('1'), ord('4'))),
                        $this->create_pair_of_groups(array(2, 3), array(    ), array(ord('5'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('8'), ord('9'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(    ), array(ord('g'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(    ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(    ), array(2   ), array(ord('m'), ord('n'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3), array(ord('o'), ord('p'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('q'), ord('r'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('w'), ord('w'))),
                        $this->create_pair_of_groups(array(3   ), array(    ), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(    ), array(2   ), array(ord('y'), ord('y'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x2_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0-5m-px] c:</B>>];
                          1->3[label=<<B>o: [8-9o-rz] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(    ), array(2   ), array(ord('0'), ord('0'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('1'), ord('4'))),
                        $this->create_pair_of_groups(array(2, 3), array(2   ), array(ord('5'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('8'), ord('9'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(    ), array(ord('g'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(    ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(    ), array(2   ), array(ord('m'), ord('n'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3), array(ord('o'), ord('p'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('q'), ord('r'))),
                        $this->create_pair_of_groups(array(3   ), array(2   ), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(2   ), array(3   ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x2_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [5g-lx] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2   ), array(2   ), array(ord('1'), ord('4'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('5'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('g'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(3   ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(3   ), array(3   ), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x3_non_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0m-py] c:</B>>];
                          1->3[label=<<B>o: [89o-rw] c:</B>>];
                          1->4[label=<<B>o: [mnor-u] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(    ), array(2      ), array(ord('0'), ord('0'))),
                        $this->create_pair_of_groups(array(2   ), array(       ), array(ord('1'), ord('4'))),
                        $this->create_pair_of_groups(array(2, 3), array(       ), array(ord('5'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(       ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(    ), array(3      ), array(ord('8'), ord('9'))),
                        $this->create_pair_of_groups(array(2   ), array(       ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(       ), array(ord('g'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(       ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(    ), array(2, 4   ), array(ord('m'), ord('n'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3, 4), array(ord('o'), ord('o'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3   ), array(ord('p'), ord('p'))),
                        $this->create_pair_of_groups(array(    ), array(3, 4   ), array(ord('q'), ord('r'))),
                        $this->create_pair_of_groups(array(    ), array(4      ), array(ord('s'), ord('u'))),
                        $this->create_pair_of_groups(array(    ), array(3      ), array(ord('w'), ord('w'))),
                        $this->create_pair_of_groups(array(3   ), array(       ), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(    ), array(2      ), array(ord('y'), ord('y'))),
                        $this->create_pair_of_groups(array(2   ), array(       ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x3_part_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [2-5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [0-7m-py] c:</B>>];
                          1->3[label=<<B>o: [2-5o-rw] c:</B>>];
                          1->4[label=<<B>o: [h-noz] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(    ), array(2   ), array(ord('0'), ord('0'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('1'), ord('1'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('2'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(2   ), array(    ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(    ), array(ord('g'), ord('g'))),
                        $this->create_pair_of_groups(array(2, 3), array(4   ), array(ord('h'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(4   ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(    ), array(2, 4), array(ord('m'), ord('m'))),
                        $this->create_pair_of_groups(array(    ), array(2, 4), array(ord('n'), ord('n'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3), array(ord('o'), ord('o'))),
                        $this->create_pair_of_groups(array(    ), array(2, 3), array(ord('p'), ord('p'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('q'), ord('r'))),
                        $this->create_pair_of_groups(array(    ), array(3   ), array(ord('w'), ord('w'))),
                        $this->create_pair_of_groups(array(3   ), array(    ), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(    ), array(2   ), array(ord('y'), ord('y'))),
                        $this->create_pair_of_groups(array(2   ), array(4   ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }

    function test_2x3_full_crossed() {
        $firstfadescription = 'digraph {
                          1;
                          4;
                          1->2[label=<<B>o: [1-7a-kz] c:</B>>];
                          1->3[label=<<B>o: [2-5g-lx] c:</B>>];
                          }';
        $secondfadescription = 'digraph {
                          1;
                          5;
                          1->2[label=<<B>o: [1-3a-gz] c:</B>>];
                          1->3[label=<<B>o: [2-5g-jx] c:</B>>];
                          1->4[label=<<B>o: [67klx] c:</B>>];
                          }';
        $mismatches = array();
        $firstfa = \qtype_preg\fa\fa::read_fa($firstfadescription);
        $secondfa = \qtype_preg\fa\fa::read_fa($secondfadescription);

        // Creating arrays of transitions of each automata
        $firstfatransitions = array($firstfa->adjacencymatrix[0][1][0],
                        $firstfa->adjacencymatrix[0][2][0]);
        $secondfatransitions = array($secondfa->adjacencymatrix[0][1][0],
                        $secondfa->adjacencymatrix[0][2][0],
                        $secondfa->adjacencymatrix[0][3][0]);

        // Generating result array
        $expres = array($this->create_pair_of_groups(array(2   ), array(2   ), array(ord('1'), ord('1'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('2'), ord('2'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('3'), ord('3'))),
                        $this->create_pair_of_groups(array(2, 3), array(3   ), array(ord('4'), ord('5'))),
                        $this->create_pair_of_groups(array(2   ), array(4   ), array(ord('6'), ord('7'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('a'), ord('f'))),
                        $this->create_pair_of_groups(array(2, 3), array(2, 3), array(ord('g'), ord('g'))),
                        $this->create_pair_of_groups(array(2, 3), array(3   ), array(ord('h'), ord('j'))),
                        $this->create_pair_of_groups(array(2, 3), array(4   ), array(ord('k'), ord('k'))),
                        $this->create_pair_of_groups(array(3   ), array(4   ), array(ord('l'), ord('l'))),
                        $this->create_pair_of_groups(array(3   ), array(3, 4), array(ord('x'), ord('x'))),
                        $this->create_pair_of_groups(array(2   ), array(2   ), array(ord('z'), ord('z'))));

        // Calling testing function
        $res = divide_intervals($firstfatransitions, $secondfatransitions);

        // Comparing result and expecting arrays
        $this->assertTrue($this->compare_pairs($res, $expres));
    }*/
}