<?php
$namespacetree = array(
	'vector' => array(
        
	),
    'std' => array(
        'vector' => array(
            'inner' => array(
            )
        )
    )
);

$string = "
	class A {
		virtual void C() = 0;
		virtual void c() const = 0;
		virtual int operator~() = 0;
		virtual int operator~() const = 0;
	};
";