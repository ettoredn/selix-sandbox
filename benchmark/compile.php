<?php
if (!isset($GLOBALS['nesting']))
	$GLOBALS['nesting'] = 0;
else
	$GLOBALS['nesting']++;
	
if ($GLOBALS['nesting'] < 1) // NOTE: compileTest class must be changed if nesting > 1
	include __FILE__;
?>
