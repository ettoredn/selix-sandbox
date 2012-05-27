<?php
// define() must be used at the beginning to identify first internal function call
define('LOOPS', 10);

function first( $arg )
{ return $arg ."\n"; }
function test( $arg1, $arg2 )
{ 
	echo "test: $arg1 $arg2\n";
	if ($arg1 == "dummy")
		$realDummy = 4;
	else
		$realDummy = 203 + 42;
	return $realDummy;
}

// user function call
first( "Hello" );
for ($i=0; $i < 10; $i++)
{
	test( "Hello".$i, "World".$i );
	// internal function call
	bcadd( "394.29394", $i, 2 );
}
?>
