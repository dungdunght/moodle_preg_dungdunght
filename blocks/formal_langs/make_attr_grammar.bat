REM Builds a lexer files, requires installed JLexPHP to run
del classes\attributed_grammar\language_attributed_grammar_lexer.php
java JLexPHP/Main classes/attributed_grammar/attributed_grammar_lexer.lex
ren classes\attributed_grammar\attributed_grammar_lexer.lex.php language_attributed_grammar_lexer.php