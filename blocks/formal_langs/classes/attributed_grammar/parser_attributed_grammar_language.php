<?php
/* Driver template for the PHP_block_formal_langs_parser_attributed_grammar_languagerGenerator parser generator. (PHP port of LEMON)
*/

/**
 * This can be used to store both the string representation of
 * a token, and any useful meta-data associated with the token.
 *
 * meta-data should be stored as an array
 */
class block_formal_langs_parser_attributed_grammar_languageyyToken implements ArrayAccess
{
    public $string = '';
    public $metadata = array();

    function __construct($s, $m = array())
    {
        if ($s instanceof block_formal_langs_parser_attributed_grammar_languageyyToken) {
            $this->string = $s->string;
            $this->metadata = $s->metadata;
        } else {
            $this->string = (string) $s;
            if ($m instanceof block_formal_langs_parser_attributed_grammar_languageyyToken) {
                $this->metadata = $m->metadata;
            } elseif (is_array($m)) {
                $this->metadata = $m;
            }
        }
    }

    function __toString()
    {
        return $this->string;
    }

    function offsetExists($offset)
    {
        return isset($this->metadata[$offset]);
    }

    function offsetGet($offset)
    {
        return $this->metadata[$offset];
    }

    function offsetSet($offset, $value)
    {
        if ($offset === null) {
            if (isset($value[0])) {
                $x = ($value instanceof block_formal_langs_parser_attributed_grammar_languageyyToken) ?
                    $value->metadata : $value;
                $this->metadata = array_merge($this->metadata, $x);
                return;
            }
            $offset = count($this->metadata);
        }
        if ($value === null) {
            return;
        }
        if ($value instanceof block_formal_langs_parser_attributed_grammar_languageyyToken) {
            if ($value->metadata) {
                $this->metadata[$offset] = $value->metadata;
            }
        } elseif ($value) {
            $this->metadata[$offset] = $value;
        }
    }

    function offsetUnset($offset)
    {
        unset($this->metadata[$offset]);
    }
}

/** The following structure represents a single element of the
 * parser's stack.  Information stored includes:
 *
 *   +  The state number for the parser at this level of the stack.
 *
 *   +  The value of the token stored at this level of the stack.
 *      (In other words, the "major" token.)
 *
 *   +  The semantic value stored at this level of the stack.  This is
 *      the information used by the action routines in the grammar.
 *      It is sometimes called the "minor" token.
 */
class block_formal_langs_parser_attributed_grammar_languageyyStackEntry
{
    public $stateno;       /* The state-number */
    public $major;         /* The major token value.  This is the code
                     ** number for the token at this stack level */
    public $minor; /* The user-supplied minor token value.  This
                     ** is the value of the token  */
};

// code external to the class is included here
#line 3 "classes\attributed_grammar\attributed_grammar.y"


#line 102 "classes\attributed_grammar\attributed_grammar.php"

// declare_class is output here
#line 2 "classes\attributed_grammar\attributed_grammar.y"
class block_formal_langs_parser_attributed_grammar_language#line 107 "classes\attributed_grammar\attributed_grammar.php"
{
/* First off, code is included which follows the "include_class" declaration
** in the input file. */
#line 6 "classes\attributed_grammar\attributed_grammar.y"

    // Root of the Abstract Syntax Tree (AST).
    public $root;
	// Current id for language
	public $currentid;
	// A mapper for parser
	public $mapper;
	// Test, whether parsing error occured
	public $error = false;
    // A current rule for a parser
	public $currentrule = null;

	protected function create_node($type, $children) {
		$result = new block_formal_langs_ast_node_base($type, null, $this->currentid, false);
		$this->currentid = $this->currentid + 1;
		$result->set_children($children);
		$result->rule = $this->currentrule;
		return $result;
	}

	public function perform_repeat_lookup($oldmajor, $token) {
		if (is_object($token) == false)
		{
			return $oldmajor;
		}
		if ($token->type() == 'identifier')
		{
			return $this->mapper->major_code_for($token);
		}
		return $oldmajor;
	}

#line 145 "classes\attributed_grammar\attributed_grammar.php"

/* Next is all token values, as class constants
*/
/* 
** These constants (all generated automatically by the parser generator)
** specify the various kinds of tokens (terminals) that the parser
** understands. 
**
** Each symbol here is a terminal symbol in the grammar.
*/
    const DOT                            =  1;
    const RULE_PART                      =  2;
    const OPENING_FIGURE_BRACE           =  3;
    const CLOSING_FIGURE_BRACE           =  4;
    const LEXEME_NAME                    =  5;
    const START_OF_DESCRIPTION           =  6;
    const END_OF_DESCRIPTION             =  7;
    const TEXT                           =  8;
    const NUMBER                         =  9;
    const COMMA                          = 10;
    const SPECIFIER                      = 11;
    const OPENING_BRACE                  = 12;
    const CLOSING_BRACE                  = 13;
    const YY_NO_ACTION = 62;
    const YY_ACCEPT_ACTION = 61;
    const YY_ERROR_ACTION = 60;

/* Next are that tables used to determine what action to take based on the
** current state and lookahead token.  These tables are used to implement
** functions that take a state number and lookahead value and return an
** action integer.  
**
** Suppose the action integer is N.  Then the action is determined as
** follows
**
**   0 <= N < self::YYNSTATE                              Shift N.  That is,
**                                                        push the lookahead
**                                                        token onto the stack
**                                                        and goto state N.
**
**   self::YYNSTATE <= N < self::YYNSTATE+self::YYNRULE   Reduce by rule N-YYNSTATE.
**
**   N == self::YYNSTATE+self::YYNRULE                    A syntax error has occurred.
**
**   N == self::YYNSTATE+self::YYNRULE+1                  The parser accepts its
**                                                        input. (and concludes parsing)
**
**   N == self::YYNSTATE+self::YYNRULE+2                  No such action.  Denotes unused
**                                                        slots in the yy_action[] table.
**
** The action table is constructed as a single large static array $yy_action.
** Given state S and lookahead X, the action is computed as
**
**      self::$yy_action[self::$yy_shift_ofst[S] + X ]
**
** If the index value self::$yy_shift_ofst[S]+X is out of range or if the value
** self::$yy_lookahead[self::$yy_shift_ofst[S]+X] is not equal to X or if
** self::$yy_shift_ofst[S] is equal to self::YY_SHIFT_USE_DFLT, it means that
** the action is not in the table and that self::$yy_default[S] should be used instead.  
**
** The formula above is for computing the action when the lookahead is
** a terminal symbol.  If the lookahead is a non-terminal (as occurs after
** a reduce action) then the static $yy_reduce_ofst array is used in place of
** the static $yy_shift_ofst array and self::YY_REDUCE_USE_DFLT is used in place of
** self::YY_SHIFT_USE_DFLT.
**
** The following are the tables generated in this section:
**
**  self::$yy_action        A single table containing all actions.
**  self::$yy_lookahead     A table containing the lookahead for each entry in
**                          yy_action.  Used to detect hash collisions.
**  self::$yy_shift_ofst    For each state, the offset into self::$yy_action for
**                          shifting terminals.
**  self::$yy_reduce_ofst   For each state, the offset into self::$yy_action for
**                          shifting non-terminals after a reduce.
**  self::$yy_default       Default action for each state.
*/
    const YY_SZ_ACTTAB = 46;
static public $yy_action = array(
 /*     0 */    30,   24,   33,   32,    8,   61,    3,   17,   14,   12,
 /*    10 */    24,   33,   32,    8,   28,   14,   12,   20,    5,   22,
 /*    20 */    18,   21,    4,   19,   31,   13,   12,   15,   23,   20,
 /*    30 */    10,   26,    6,   16,    9,   34,   31,    9,    2,   25,
 /*    40 */    29,   27,   35,    1,   11,    7,
    );
    static public $yy_lookahead = array(
 /*     0 */     7,    8,    9,   10,   11,   15,   16,   17,   18,   19,
 /*    10 */     8,    9,   10,   11,   17,   18,   19,   19,   20,   21,
 /*    20 */     8,    9,   23,   24,   25,   18,   19,    8,    9,   19,
 /*    30 */    10,   21,    3,   13,    5,   24,   25,    5,    2,    4,
 /*    40 */    22,    1,   26,    6,   27,   12,
);
    const YY_SHIFT_USE_DFLT = -8;
    const YY_SHIFT_MAX = 14;
    static public $yy_shift_ofst = array(
 /*     0 */    32,    2,   29,   32,   -7,   29,   32,   12,   33,   37,
 /*    10 */    19,   20,   36,   35,   40,
);
    const YY_REDUCE_USE_DFLT = -11;
    const YY_REDUCE_MAX = 9;
    static public $yy_reduce_ofst = array(
 /*     0 */   -10,   -1,   -2,   -3,   11,   10,    7,   17,   16,   18,
);
    static public $yyExpectedTokens = array(
        /* 0 */ array(5, ),
        /* 1 */ array(8, 9, 10, 11, ),
        /* 2 */ array(3, 5, ),
        /* 3 */ array(5, ),
        /* 4 */ array(7, 8, 9, 10, 11, ),
        /* 5 */ array(3, 5, ),
        /* 6 */ array(5, ),
        /* 7 */ array(8, 9, ),
        /* 8 */ array(12, ),
        /* 9 */ array(6, ),
        /* 10 */ array(8, 9, ),
        /* 11 */ array(10, 13, ),
        /* 12 */ array(2, ),
        /* 13 */ array(4, ),
        /* 14 */ array(1, ),
        /* 15 */ array(),
        /* 16 */ array(),
        /* 17 */ array(),
        /* 18 */ array(),
        /* 19 */ array(),
        /* 20 */ array(),
        /* 21 */ array(),
        /* 22 */ array(),
        /* 23 */ array(),
        /* 24 */ array(),
        /* 25 */ array(),
        /* 26 */ array(),
        /* 27 */ array(),
        /* 28 */ array(),
        /* 29 */ array(),
        /* 30 */ array(),
        /* 31 */ array(),
        /* 32 */ array(),
        /* 33 */ array(),
        /* 34 */ array(),
        /* 35 */ array(),
);
    static public $yy_default = array(
 /*     0 */    60,   60,   60,   36,   60,   40,   60,   60,   53,   60,
 /*    10 */    60,   60,   60,   60,   60,   58,   55,   37,   56,   47,
 /*    20 */    44,   57,   41,   59,   49,   43,   42,   39,   38,   45,
 /*    30 */    46,   52,   51,   50,   48,   54,
);
/* The next thing included is series of defines which control
** various aspects of the generated parser.
**    self::YYNOCODE      is a number which corresponds
**                        to no legal terminal or nonterminal number.  This
**                        number is used to fill in empty slots of the hash 
**                        table.
**    self::YYFALLBACK    If defined, this indicates that one or more tokens
**                        have fall-back values which should be used if the
**                        original value of the token will not parse.
**    self::YYSTACKDEPTH  is the maximum depth of the parser's stack.
**    self::YYNSTATE      the combined number of states.
**    self::YYNRULE       the number of rules in the grammar
**    self::YYERRORSYMBOL is the code number of the error symbol.  If not
**                        defined, then do no error processing.
*/
    const YYNOCODE = 29;
    const YYSTACKDEPTH = 100;
    const block_formal_langs_parser_attributed_grammar_languageARG_DECL = '0';
    const YYNSTATE = 36;
    const YYNRULE = 24;
    const YYERRORSYMBOL = 14;
    const YYERRSYMDT = 'yy0';
    const YYFALLBACK = 0;
    /** The next table maps tokens into fallback tokens.  If a construct
     * like the following:
     * 
     *      %fallback ID X Y Z.
     *
     * appears in the grammer, then ID becomes a fallback token for X, Y,
     * and Z.  Whenever one of the tokens X, Y, or Z is input to the parser
     * but it does not parse, the type of the token is changed to ID and
     * the parse is retried before an error is thrown.
     */
    static public $yyFallback = array(
    );
    /**
     * Turn parser tracing on by giving a stream to which to write the trace
     * and a prompt to preface each trace message.  Tracing is turned off
     * by making either argument NULL 
     *
     * Inputs:
     * 
     * - A stream resource to which trace output should be written.
     *   If NULL, then tracing is turned off.
     * - A prefix string written at the beginning of every
     *   line of trace output.  If NULL, then tracing is
     *   turned off.
     *
     * Outputs:
     * 
     * - None.
     * @param resource
     * @param string
     */
    static function Trace($TraceFILE, $zTracePrompt)
    {
        if (!$TraceFILE) {
            $zTracePrompt = 0;
        } elseif (!$zTracePrompt) {
            $TraceFILE = 0;
        }
        self::$yyTraceFILE = $TraceFILE;
        self::$yyTracePrompt = $zTracePrompt;
    }

    /**
     * Output debug information to output (php://output stream)
     */
    static function PrintTrace()
    {
        self::$yyTraceFILE = fopen('php://output', 'w');
        self::$yyTracePrompt = '';
    }

    /**
     * @var resource|0
     */
    static public $yyTraceFILE;
    /**
     * String to prepend to debug output
     * @var string|0
     */
    static public $yyTracePrompt;
    /**
     * @var int
     */
    public $yyidx = -1;                    /* Index of top element in stack */
    /**
     * @var int
     */
    public $yyerrcnt;                 /* Shifts left before out of the error */
    /**
     * @var array
     */
    public $yystack = array();  /* The parser's stack */

    /**
     * For tracing shifts, the names of all terminals and nonterminals
     * are required.  The following table supplies these names
     * @var array
     */
    static public $yyTokenName = array( 
  '$',             'DOT',           'RULE_PART',     'OPENING_FIGURE_BRACE',
  'CLOSING_FIGURE_BRACE',  'LEXEME_NAME',   'START_OF_DESCRIPTION',  'END_OF_DESCRIPTION',
  'TEXT',          'NUMBER',        'COMMA',         'SPECIFIER',   
  'OPENING_BRACE',  'CLOSING_BRACE',  'error',         'result',      
  'rule_list',     'rule',          'rule_unbounded_part',  'node_description_strict',
  'node_description_list',  'node_description',  'string_description',  'text_of_descriptions',
  'text_or_description',  'specifier',     'arguments',     'list_of_arguments',
    );

    /**
     * For tracing reduce actions, the names of all rules are required.
     * @var array
     */
    static public $yyRuleName = array(
 /*   0 */ "result ::= rule_list",
 /*   1 */ "rule_list ::= rule",
 /*   2 */ "rule_list ::= rule_list rule",
 /*   3 */ "rule ::= rule_unbounded_part DOT",
 /*   4 */ "rule_unbounded_part ::= node_description_strict RULE_PART node_description_list",
 /*   5 */ "node_description_list ::= node_description",
 /*   6 */ "node_description_list ::= node_description_list node_description",
 /*   7 */ "node_description ::= OPENING_FIGURE_BRACE rule_unbounded_part CLOSING_FIGURE_BRACE",
 /*   8 */ "node_description ::= node_description_strict",
 /*   9 */ "node_description_strict ::= LEXEME_NAME string_description",
 /*  10 */ "string_description ::= START_OF_DESCRIPTION text_of_descriptions END_OF_DESCRIPTION",
 /*  11 */ "text_of_descriptions ::= text_or_description",
 /*  12 */ "text_of_descriptions ::= text_of_descriptions text_or_description",
 /*  13 */ "text_or_description ::= TEXT",
 /*  14 */ "text_or_description ::= NUMBER",
 /*  15 */ "text_or_description ::= COMMA",
 /*  16 */ "text_or_description ::= specifier",
 /*  17 */ "specifier ::= SPECIFIER",
 /*  18 */ "specifier ::= SPECIFIER arguments",
 /*  19 */ "arguments ::= OPENING_BRACE list_of_arguments CLOSING_BRACE",
 /*  20 */ "list_of_arguments ::= TEXT",
 /*  21 */ "list_of_arguments ::= NUMBER",
 /*  22 */ "list_of_arguments ::= list_of_arguments COMMA TEXT",
 /*  23 */ "list_of_arguments ::= list_of_arguments COMMA NUMBER",
    );

    /**
     * This function returns the symbolic name associated with a token
     * value.
     * @param int
     * @return string
     */
    function tokenName($tokenType)
    {
        if ($tokenType === 0) {
            return 'End of Input';
        }
        if ($tokenType > 0 && $tokenType < count(self::$yyTokenName)) {
            return self::$yyTokenName[$tokenType];
        } else {
            return "Unknown";
        }
    }

    /**
     * The following function deletes the value associated with a
     * symbol.  The symbol can be either a terminal or nonterminal.
     * @param int the symbol code
     * @param mixed the symbol's value
     */
    static function yy_destructor($yymajor, $yypminor)
    {
        switch ($yymajor) {
        /* Here is inserted the actions which take place when a
        ** terminal or non-terminal is destroyed.  This can happen
        ** when the symbol is popped from the stack during a
        ** reduce or during error processing or when a parser is 
        ** being destroyed before it is finished parsing.
        **
        ** Note: during a reduce, the only symbols destroyed are those
        ** which appear on the RHS of the rule, but which are not used
        ** inside the C code.
        */
            default:  break;   /* If no destructor action specified: do nothing */
        }
    }

    /**
     * Pop the parser's stack once.
     *
     * If there is a destructor routine associated with the token which
     * is popped from the stack, then call it.
     *
     * Return the major token number for the symbol popped.
     * @param block_formal_langs_parser_attributed_grammar_languageyyParser
     * @return int
     */
    function yy_pop_parser_stack()
    {
        if (!count($this->yystack)) {
            return;
        }
        $yytos = array_pop($this->yystack);
        if (self::$yyTraceFILE && $this->yyidx >= 0) {
            fwrite(self::$yyTraceFILE,
                self::$yyTracePrompt . 'Popping ' . self::$yyTokenName[$yytos->major] .
                    "\n");
        }
        $yymajor = $yytos->major;
        self::yy_destructor($yymajor, $yytos->minor);
        $this->yyidx--;
        return $yymajor;
    }

    /**
     * Deallocate and destroy a parser.  Destructors are all called for
     * all stack elements before shutting the parser down.
     */
    function __destruct()
    {
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        if (is_resource(self::$yyTraceFILE)) {
            fclose(self::$yyTraceFILE);
        }
    }

    /**
     * Based on the current state and parser stack, get a list of all
     * possible lookahead tokens
     * @param int
     * @return array
     */
    function yy_get_expected_tokens($token)
    {
        $state = $this->yystack[$this->yyidx]->stateno;
        $expected = self::$yyExpectedTokens[$state];
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return $expected;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return array_unique($expected);
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate])) {
                        $expected += self::$yyExpectedTokens[$nextstate];
                            if (in_array($token,
                                  self::$yyExpectedTokens[$nextstate], true)) {
                            $this->yyidx = $yyidx;
                            $this->yystack = $stack;
                            return array_unique($expected);
                        }
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new block_formal_langs_parser_attributed_grammar_languageyyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return array_unique($expected);
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return $expected;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        return array_unique($expected);
    }

    /**
     * Based on the parser state and current parser stack, determine whether
     * the lookahead token is possible.
     * 
     * The parser will convert the token value to an error token if not.  This
     * catches some unusual edge cases where the parser would fail.
     * @param int
     * @return bool
     */
    function yy_is_expected_token($token)
    {
        if ($token === 0) {
            return true; // 0 is not part of this
        }
        $state = $this->yystack[$this->yyidx]->stateno;
        if (in_array($token, self::$yyExpectedTokens[$state], true)) {
            return true;
        }
        $stack = $this->yystack;
        $yyidx = $this->yyidx;
        do {
            $yyact = $this->yy_find_shift_action($token);
            if ($yyact >= self::YYNSTATE && $yyact < self::YYNSTATE + self::YYNRULE) {
                // reduce action
                $done = 0;
                do {
                    if ($done++ == 100) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // too much recursion prevents proper detection
                        // so give up
                        return true;
                    }
                    $yyruleno = $yyact - self::YYNSTATE;
                    $this->yyidx -= self::$yyRuleInfo[$yyruleno]['rhs'];
                    $nextstate = $this->yy_find_reduce_action(
                        $this->yystack[$this->yyidx]->stateno,
                        self::$yyRuleInfo[$yyruleno]['lhs']);
                    if (isset(self::$yyExpectedTokens[$nextstate]) &&
                          in_array($token, self::$yyExpectedTokens[$nextstate], true)) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        return true;
                    }
                    if ($nextstate < self::YYNSTATE) {
                        // we need to shift a non-terminal
                        $this->yyidx++;
                        $x = new block_formal_langs_parser_attributed_grammar_languageyyStackEntry;
                        $x->stateno = $nextstate;
                        $x->major = self::$yyRuleInfo[$yyruleno]['lhs'];
                        $this->yystack[$this->yyidx] = $x;
                        continue 2;
                    } elseif ($nextstate == self::YYNSTATE + self::YYNRULE + 1) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        if (!$token) {
                            // end of input: this is valid
                            return true;
                        }
                        // the last token was just ignored, we can't accept
                        // by ignoring input, this is in essence ignoring a
                        // syntax error!
                        return false;
                    } elseif ($nextstate === self::YY_NO_ACTION) {
                        $this->yyidx = $yyidx;
                        $this->yystack = $stack;
                        // input accepted, but not shifted (I guess)
                        return true;
                    } else {
                        $yyact = $nextstate;
                    }
                } while (true);
            }
            break;
        } while (true);
        $this->yyidx = $yyidx;
        $this->yystack = $stack;
        return true;
    }

    /**
     * Find the appropriate action for a parser given the terminal
     * look-ahead token iLookAhead.
     *
     * If the look-ahead token is YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return YY_NO_ACTION.
     * @param int The look-ahead token
     */
    function yy_find_shift_action($iLookAhead)
    {
        $stateno = $this->yystack[$this->yyidx]->stateno;
     
        /* if ($this->yyidx < 0) return self::YY_NO_ACTION;  */
        if (!isset(self::$yy_shift_ofst[$stateno])) {
            // no shift actions
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_shift_ofst[$stateno];
        if ($i === self::YY_SHIFT_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            if (count(self::$yyFallback) && $iLookAhead < count(self::$yyFallback)
                   && ($iFallback = self::$yyFallback[$iLookAhead]) != 0) {
                if (self::$yyTraceFILE) {
                    fwrite(self::$yyTraceFILE, self::$yyTracePrompt . "FALLBACK " .
                        self::$yyTokenName[$iLookAhead] . " => " .
                        self::$yyTokenName[$iFallback] . "\n");
                }
                return $this->yy_find_shift_action($iFallback);
            }
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Find the appropriate action for a parser given the non-terminal
     * look-ahead token $iLookAhead.
     *
     * If the look-ahead token is self::YYNOCODE, then check to see if the action is
     * independent of the look-ahead.  If it is, return the action, otherwise
     * return self::YY_NO_ACTION.
     * @param int Current state number
     * @param int The look-ahead token
     */
    function yy_find_reduce_action($stateno, $iLookAhead)
    {
        /* $stateno = $this->yystack[$this->yyidx]->stateno; */

        if (!isset(self::$yy_reduce_ofst[$stateno])) {
            return self::$yy_default[$stateno];
        }
        $i = self::$yy_reduce_ofst[$stateno];
        if ($i == self::YY_REDUCE_USE_DFLT) {
            return self::$yy_default[$stateno];
        }
        if ($iLookAhead == self::YYNOCODE) {
            return self::YY_NO_ACTION;
        }
        $i += $iLookAhead;
        if ($i < 0 || $i >= self::YY_SZ_ACTTAB ||
              self::$yy_lookahead[$i] != $iLookAhead) {
            return self::$yy_default[$stateno];
        } else {
            return self::$yy_action[$i];
        }
    }

    /**
     * Perform a shift action.
     * @param int The new state to shift in
     * @param int The major token to shift in
     * @param mixed the minor token to shift in
     */
    function yy_shift($yyNewState, $yyMajor, $yypMinor)
    {
        $this->yyidx++;
        if ($this->yyidx >= self::YYSTACKDEPTH) {
            $this->yyidx--;
            if (self::$yyTraceFILE) {
                fprintf(self::$yyTraceFILE, "%sStack Overflow!\n", self::$yyTracePrompt);
            }
            while ($this->yyidx >= 0) {
                $this->yy_pop_parser_stack();
            }
            /* Here code is inserted which will execute if the parser
            ** stack ever overflows */
            return;
        }
        $yytos = new block_formal_langs_parser_attributed_grammar_languageyyStackEntry;
        $yytos->stateno = $yyNewState;
        $yytos->major = $yyMajor;
        $yytos->minor = $yypMinor;
        array_push($this->yystack, $yytos);
        if (self::$yyTraceFILE && $this->yyidx > 0) {
            fprintf(self::$yyTraceFILE, "%sShift %d\n", self::$yyTracePrompt,
                $yyNewState);
            fprintf(self::$yyTraceFILE, "%sStack:", self::$yyTracePrompt);
            for ($i = 1; $i <= $this->yyidx; $i++) {
                fprintf(self::$yyTraceFILE, " %s",
                    self::$yyTokenName[$this->yystack[$i]->major]);
            }
            fwrite(self::$yyTraceFILE,"\n");
        }
    }

    /**
     * The following table contains information about every rule that
     * is used during the reduce.
     *
     * <pre>
     * array(
     *  array(
     *   int $lhs;         Symbol on the left-hand side of the rule
     *   int $nrhs;     Number of right-hand side symbols in the rule
     *  ),...
     * );
     * </pre>
     */
    static public $yyRuleInfo = array(
  array( 'lhs' => 15, 'rhs' => 1 ),
  array( 'lhs' => 16, 'rhs' => 1 ),
  array( 'lhs' => 16, 'rhs' => 2 ),
  array( 'lhs' => 17, 'rhs' => 2 ),
  array( 'lhs' => 18, 'rhs' => 3 ),
  array( 'lhs' => 20, 'rhs' => 1 ),
  array( 'lhs' => 20, 'rhs' => 2 ),
  array( 'lhs' => 21, 'rhs' => 3 ),
  array( 'lhs' => 21, 'rhs' => 1 ),
  array( 'lhs' => 19, 'rhs' => 2 ),
  array( 'lhs' => 22, 'rhs' => 3 ),
  array( 'lhs' => 23, 'rhs' => 1 ),
  array( 'lhs' => 23, 'rhs' => 2 ),
  array( 'lhs' => 24, 'rhs' => 1 ),
  array( 'lhs' => 24, 'rhs' => 1 ),
  array( 'lhs' => 24, 'rhs' => 1 ),
  array( 'lhs' => 24, 'rhs' => 1 ),
  array( 'lhs' => 25, 'rhs' => 1 ),
  array( 'lhs' => 25, 'rhs' => 2 ),
  array( 'lhs' => 26, 'rhs' => 3 ),
  array( 'lhs' => 27, 'rhs' => 1 ),
  array( 'lhs' => 27, 'rhs' => 1 ),
  array( 'lhs' => 27, 'rhs' => 3 ),
  array( 'lhs' => 27, 'rhs' => 3 ),
    );

    /**
     * The following table contains a mapping of reduce action to method name
     * that handles the reduction.
     * 
     * If a rule is not set, it has no handler.
     */
    static public $yyReduceMap = array(
        0 => 0,
        1 => 1,
        2 => 2,
        6 => 2,
        12 => 2,
        3 => 3,
        10 => 3,
        19 => 3,
        4 => 4,
        5 => 5,
        7 => 7,
        8 => 8,
        13 => 8,
        14 => 8,
        15 => 8,
        16 => 8,
        9 => 9,
        11 => 11,
        17 => 17,
        18 => 18,
        20 => 20,
        21 => 20,
        22 => 22,
        23 => 22,
    );
    /* Beginning here are the reduction cases.  A typical example
    ** follows:
    **  #line <lineno> <grammarfile>
    **   function yy_r0($yymsp){ ... }           // User supplied code
    **  #line <lineno> <thisfile>
    */
#line 73 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r0(){
	$stack = $this->yystack[$this->yyidx + 0]->minor;
	if (is_array($this->root)) {
			if (count($this->root)) {
				$this->root = array_merge($this->root, $stack);
			}
			else {
				$this->root  = $stack;
			}
	} else {
			$this->root = $stack;
	}
	$this->_retvalue = $stack;
    }
#line 878 "classes\attributed_grammar\attributed_grammar.php"
#line 88 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r1(){
	$this->_retvalue = $this->create_node('rule_list', array( $this->yystack[$this->yyidx + 0]->minor ) ) ;
    }
#line 883 "classes\attributed_grammar\attributed_grammar.php"
#line 92 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r2(){
	$this->yystack[$this->yyidx + -1]->minor->add_child($this->yystack[$this->yyidx + 0]->minor);
	$this->_retvalue = $this->yystack[$this->yyidx + -1]->minor;
    }
#line 889 "classes\attributed_grammar\attributed_grammar.php"
#line 98 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r3(){
	$this->_retvalue = $this->yystack[$this->yyidx + -1]->minor;
    }
#line 894 "classes\attributed_grammar\attributed_grammar.php"
#line 102 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r4(){
	$this->_retvalue = $this->create_node('rule', array($this->yystack[$this->yyidx + -2]->minor, $this->yystack[$this->yyidx + 0]->minor));
    }
#line 899 "classes\attributed_grammar\attributed_grammar.php"
#line 106 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r5(){
	$this->_retvalue = $this->create_node('node_description_list', array($this->yystack[$this->yyidx + 0]->minor));
    }
#line 904 "classes\attributed_grammar\attributed_grammar.php"
#line 115 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r7(){
    $this->_retvalue = $this->yystack[$this->yyidx + -1]->minor;
    }
#line 909 "classes\attributed_grammar\attributed_grammar.php"
#line 119 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r8(){
	$this->_retvalue = $this->yystack[$this->yyidx + 0]->minor;
    }
#line 914 "classes\attributed_grammar\attributed_grammar.php"
#line 123 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r9(){
	$this->_retvalue = $this->create_node('node_description', array($this->yystack[$this->yyidx + -1]->minor, $this->yystack[$this->yyidx + 0]->minor));
    }
#line 919 "classes\attributed_grammar\attributed_grammar.php"
#line 132 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r11(){
	$this->_retvalue = $this->create_node('text_of_descriptions', array($this->yystack[$this->yyidx + 0]->minor));
    }
#line 924 "classes\attributed_grammar\attributed_grammar.php"
#line 157 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r17(){
	$this->_retvalue = $this->create_node('specifier', array($this->yystack[$this->yyidx + 0]->minor));
    }
#line 929 "classes\attributed_grammar\attributed_grammar.php"
#line 161 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r18(){
	$this->_retvalue = $this->create_node('specifier', array($this->yystack[$this->yyidx + -1]->minor, $this->yystack[$this->yyidx + 0]->minor));
    }
#line 934 "classes\attributed_grammar\attributed_grammar.php"
#line 169 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r20(){
	$this->_retvalue = $this->create_node('args', array($this->yystack[$this->yyidx + 0]->minor));
    }
#line 939 "classes\attributed_grammar\attributed_grammar.php"
#line 178 "classes\attributed_grammar\attributed_grammar.y"
    function yy_r22(){
	$this->yystack[$this->yyidx + -2]->minor->add_child($this->yystack[$this->yyidx + 0]->minor);
	$this->_retvalue = $this->yystack[$this->yyidx + -2]->minor;
    }
#line 945 "classes\attributed_grammar\attributed_grammar.php"

    /**
     * placeholder for the left hand side in a reduce operation.
     * 
     * For a parser with a rule like this:
     * <pre>
     * rule(A) ::= B. { A = 1; }
     * </pre>
     * 
     * The parser will translate to something like:
     * 
     * <code>
     * function yy_r0(){$this->_retvalue = 1;}
     * </code>
     */
    private $_retvalue;

    /**
     * Perform a reduce action and the shift that must immediately
     * follow the reduce.
     * 
     * For a rule such as:
     * 
     * <pre>
     * A ::= B blah C. { dosomething(); }
     * </pre>
     * 
     * This function will first call the action, if any, ("dosomething();" in our
     * example), and then it will pop three states from the stack,
     * one for each entry on the right-hand side of the expression
     * (B, blah, and C in our example rule), and then push the result of the action
     * back on to the stack with the resulting state reduced to (as described in the .out
     * file)
     * @param int Number of the rule by which to reduce
     */
    function yy_reduce($yyruleno)
    {
        //int $yygoto;                     /* The next state */
        //int $yyact;                      /* The next action */
        //mixed $yygotominor;        /* The LHS of the rule reduced */
        //block_formal_langs_parser_attributed_grammar_languageyyStackEntry $yymsp;            /* The top of the parser's stack */
        //int $yysize;                     /* Amount to pop the stack */
        $yymsp = $this->yystack[$this->yyidx];
        if (self::$yyTraceFILE && $yyruleno >= 0 
              && $yyruleno < count(self::$yyRuleName)) {
            fprintf(self::$yyTraceFILE, "%sReduce (%d) [%s].\n",
                self::$yyTracePrompt, $yyruleno,
                self::$yyRuleName[$yyruleno]);
        }

        $this->_retvalue = $yy_lefthand_side = null;
        if (array_key_exists($yyruleno, self::$yyReduceMap)) {
            // call the action
            $this->_retvalue = null;
            $this->{'yy_r' . self::$yyReduceMap[$yyruleno]}();
            $yy_lefthand_side = $this->_retvalue;
        }
        $yygoto = self::$yyRuleInfo[$yyruleno]['lhs'];
        $yysize = self::$yyRuleInfo[$yyruleno]['rhs'];
        $this->yyidx -= $yysize;
        for ($i = $yysize; $i; $i--) {
            // pop all of the right-hand side parameters
            array_pop($this->yystack);
        }
        $yyact = $this->yy_find_reduce_action($this->yystack[$this->yyidx]->stateno, $yygoto);
        if ($yyact < self::YYNSTATE) {
            /* If we are not debugging and the reduce action popped at least
            ** one element off the stack, then we can push the new element back
            ** onto the stack here, and skip the stack overflow test in yy_shift().
            ** That gives a significant speed improvement. */
            if (!self::$yyTraceFILE && $yysize) {
                $this->yyidx++;
                $x = new block_formal_langs_parser_attributed_grammar_languageyyStackEntry;
                $x->stateno = $yyact;
                $x->major = $yygoto;
                $x->minor = $yy_lefthand_side;
                $this->yystack[$this->yyidx] = $x;
            } else {
                $this->yy_shift($yyact, $yygoto, $yy_lefthand_side);
            }
        } elseif ($yyact == self::YYNSTATE + self::YYNRULE + 1) {
            $this->yy_accept();
        }
    }

    /**
     * The following code executes when the parse fails
     * 
     * Code from %parse_fail is inserted here
     */
    function yy_parse_failed()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sFail!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser fails */
    }

    /**
     * The following code executes when a syntax error first occurs.
     * 
     * %syntax_error code is inserted here
     * @param int The major type of the error token
     * @param mixed The minor type of the error token
     */
    function yy_syntax_error($yymajor, $TOKEN)
    {
#line 40 "classes\attributed_grammar\attributed_grammar.y"

    $this->error = true;
    $stack = array();
    foreach($this->yystack as $entry) {
        if ($entry->minor != null) {
            $stack[] = $entry->minor;
        }
    }
     // var_dump(array_map(function($a) { return $a->type() . ' ';  }, $stack));
    if (is_array($this->root)) {
        if (count($this->root)) {
            $this->root = array_merge($this->root, $stack);
        }
        else {
            $this->root  = $stack;
        }
    } else {
        $this->root = $stack;
    }
    /*
    echo "Syntax Error on line " . $this->lex->line . ": token '" .
        $this->lex->value . "' while parsing rule:\n";
    echo "Stack: ";
	foreach ($this->yystack as $entry) {
        echo self::$yyTokenName[$entry->major] . "\n";
    }
    foreach ($this->yy_get_expected_tokens($yymajor) as $token) {
        $expect[] = self::$yyTokenName[$token];
    }
	throw new Exception(implode(',', $expect));
	*/
#line 1090 "classes\attributed_grammar\attributed_grammar.php"
    }

    /**
     * The following is executed when the parser accepts
     * 
     * %parse_accept code is inserted here
     */
    function yy_accept()
    {
        if (self::$yyTraceFILE) {
            fprintf(self::$yyTraceFILE, "%sAccept!\n", self::$yyTracePrompt);
        }
        while ($this->yyidx >= 0) {
            $stack = $this->yy_pop_parser_stack();
        }
        /* Here code is inserted which will be executed whenever the
        ** parser accepts */
    }

	public $repeatlookup = false;
	
    /**
     * The main parser program.
     * 
     * The first argument is the major token number.  The second is
     * the token value string as scanned from the input.
     *
     * @param int   $yymajor      the token number
     * @param mixed $yytokenvalue the token value
     * @param mixed ...           any extra arguments that should be passed to handlers
     *
     * @return void
     */
    function doParse($yymajor, $yytokenvalue)
    {
//        $yyact;            /* The parser action. */
//        $yyendofinput;     /* True if we are at the end of input */
        $yyerrorhit = 0;   /* True if yymajor has invoked an error */
        
        /* (re)initialize the parser, if necessary */
        if ($this->yyidx === null || $this->yyidx < 0) {
            /* if ($yymajor == 0) return; // not sure why this was here... */
            $this->yyidx = 0;
            $this->yyerrcnt = -1;
            $x = new block_formal_langs_parser_attributed_grammar_languageyyStackEntry;
            $x->stateno = 0;
            $x->major = 0;
            $this->yystack = array();
            array_push($this->yystack, $x);
        }
        $yyendofinput = ($yymajor==0);
        
        if (self::$yyTraceFILE) {
            fprintf(
                self::$yyTraceFILE,
                "%sInput %s\n",
                self::$yyTracePrompt,
                self::$yyTokenName[$yymajor]
            );
        }
        
        do {
			if ($this->repeatlookup)
			{
				$oldmajor = $yymajor;
				$yymajor = $this->perform_repeat_lookup($yymajor, $yytokenvalue);
				/*
				if ($oldmajor != $yymajor)
				{				
					echo "Replaced value " 
					   . $yytokenvalue->value() 
					   . " of type \"" .  $yytokenvalue->type() 
					   . "\" to \""      . (int)$yymajor . "\"\r\n";
				}
				*/
			}
			$yyact = $this->yy_find_shift_action($yymajor);
            
			if ($yymajor < self::YYERRORSYMBOL
                && !$this->yy_is_expected_token($yymajor)
            ) {
                // force a syntax error
                $yyact = self::YY_ERROR_ACTION;
            }
            if ($yyact < self::YYNSTATE) {
                $this->yy_shift($yyact, $yymajor, $yytokenvalue);
                $this->yyerrcnt--;
                if ($yyendofinput && $this->yyidx >= 0) {
                    $yymajor = 0;
                } else {
                    $yymajor = self::YYNOCODE;
                }
            } elseif ($yyact < self::YYNSTATE + self::YYNRULE) {
                $this->yy_reduce($yyact - self::YYNSTATE);
            } elseif ($yyact == self::YY_ERROR_ACTION) {
                if (self::$yyTraceFILE) {
                    fprintf(
                        self::$yyTraceFILE,
                        "%sSyntax Error!\n",
                        self::$yyTracePrompt
                    );
                }
                if (self::YYERRORSYMBOL) {
                    /* A syntax error has occurred.
                    ** The response to an error depends upon whether or not the
                    ** grammar defines an error token "ERROR".  
                    **
                    ** This is what we do if the grammar does define ERROR:
                    **
                    **  * Call the %syntax_error function.
                    **
                    **  * Begin popping the stack until we enter a state where
                    **    it is legal to shift the error symbol, then shift
                    **    the error symbol.
                    **
                    **  * Set the error count to three.
                    **
                    **  * Begin accepting and shifting new tokens.  No new error
                    **    processing will occur until three tokens have been
                    **    shifted successfully.
                    **
                    */
                    if ($this->yyerrcnt < 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $yymx = $this->yystack[$this->yyidx]->major;
                    if ($yymx == self::YYERRORSYMBOL || $yyerrorhit ) {
                        if (self::$yyTraceFILE) {
                            fprintf(
                                self::$yyTraceFILE,
                                "%sDiscard input token %s\n",
                                self::$yyTracePrompt,
                                self::$yyTokenName[$yymajor]
                            );
                        }
                        $this->yy_destructor($yymajor, $yytokenvalue);
                        $yymajor = self::YYNOCODE;
                    } else {
                        while ($this->yyidx >= 0
                            && $yymx != self::YYERRORSYMBOL
                            && ($yyact = $this->yy_find_shift_action(self::YYERRORSYMBOL)) >= self::YYNSTATE
                        ) {
                            $this->yy_pop_parser_stack();
                        }
                        if ($this->yyidx < 0 || $yymajor==0) {
                            $this->yy_destructor($yymajor, $yytokenvalue);
                            $this->yy_parse_failed();
                            $yymajor = self::YYNOCODE;
                        } elseif ($yymx != self::YYERRORSYMBOL) {
                            $u2 = 0;
                            $this->yy_shift($yyact, self::YYERRORSYMBOL, $u2);
                        }
                    }
                    $this->yyerrcnt = 3;
                    $yyerrorhit = 1;
                } else {
                    /* YYERRORSYMBOL is not defined */
                    /* This is what we do if the grammar does not define ERROR:
                    **
                    **  * Report an error message, and throw away the input token.
                    **
                    **  * If the input token is $, then fail the parse.
                    **
                    ** As before, subsequent error messages are suppressed until
                    ** three input tokens have been successfully shifted.
                    */
                    if ($this->yyerrcnt <= 0) {
                        $this->yy_syntax_error($yymajor, $yytokenvalue);
                    }
                    $this->yyerrcnt = 3;
                    $this->yy_destructor($yymajor, $yytokenvalue);
                    if ($yyendofinput) {
                        $this->yy_parse_failed();
                    }
                    $yymajor = self::YYNOCODE;
                }
            } else {
                $this->yy_accept();
                $yymajor = self::YYNOCODE;
            }            
        } while ($yymajor != self::YYNOCODE && $this->yyidx >= 0);
    }
}
