%name block_formal_langs_parser_attributed_grammar_language
%declare_class {class block_formal_langs_parser_attributed_grammar_language}
%include {

}
%include_class {
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

}

%syntax_error {
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
}

result(R) ::= rule_list(A) . {
	$stack = A;
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
	R = $stack;
}

rule_list(R) ::= rule(A) .  {
	R = $this->create_node('rule_list', array( A ) ) ;
}

rule_list(R) ::= rule_list(A) rule(B) .  {
	A->add_child(B);
	R = A;
}


rule(R) ::= rule_unbounded_part(A) DOT (D) . {
	R = A;
}

rule_unbounded_part(R) ::= node_description_strict(A) RULE_PART(B) node_description_list (C) . {
	R = $this->create_node('rule', array(A, C));
}

node_description_list(R) ::= node_description(A) . {
	R = $this->create_node('node_description_list', array(A));
}

node_description_list(R) ::= node_description_list(A) node_description(B) . {
	A->add_child(B);
	R = A;
}

node_description(R) ::= OPENING_FIGURE_BRACE(A) rule_unbounded_part(B) CLOSING_FIGURE_BRACE(C) . {
    R = B;
}

node_description(R) ::= node_description_strict(A) . {
	R = A;
}

node_description_strict(R) ::= LEXEME_NAME(A) string_description(B) . {
	R = $this->create_node('node_description', array(A, B));
}

string_description(R) ::= START_OF_DESCRIPTION(A) text_of_descriptions(B) END_OF_DESCRIPTION(C) . {
	R = B;
}


text_of_descriptions(R) ::= text_or_description(A) . {
	R = $this->create_node('text_of_descriptions', array(A));
}

text_of_descriptions(R) ::= text_of_descriptions(A) text_or_description(B) . {
	A->add_child(B);
	R = A;
}

text_or_description(R) ::= TEXT(A) . {
	R = A;
}

text_or_description(R) ::= NUMBER(A) . {
	R = A;
}

text_or_description(R) ::= COMMA(A) . {
	R = A;
}

text_or_description(R) ::= specifier(A) . {
	R = A;
}

specifier(R) ::= SPECIFIER(A) . {
	R = $this->create_node('specifier', array(A));
}

specifier(R) ::= SPECIFIER(A) arguments(B) . {
	R = $this->create_node('specifier', array(A, B));
}

arguments(R) ::= OPENING_BRACE(A) list_of_arguments(B) CLOSING_BRACE(C) . {
	R = B;
}

list_of_arguments(R) ::= TEXT(A) . {
	R = $this->create_node('args', array(A));
}

list_of_arguments(R) ::= NUMBER(A) . {
	R = $this->create_node('args', array(A));
}


list_of_arguments(R) ::= list_of_arguments(A) COMMA(B) TEXT(C) . {
	A->add_child(C);
	R = A;
}

list_of_arguments(R) ::= list_of_arguments(A) COMMA(B)  NUMBER(C). {
	A->add_child(C);
	R = A;
}
