<pre>
<?php

$module = 'sephp';
$functions = get_extension_funcs($module);
echo "Functions available in the test extension:\n";
foreach($functions as $func) {
    echo $func."\n";
}
echo "\n";
$function = 'confirm_' . $module . '_compiled';
if (extension_loaded($module)) {
	$str = $function($module);
} else {
	$str = "Module $module is not compiled into PHP";
}

echo "\n".'$_ENV'."\n";
var_dump( $_ENV );
echo "\n".'$_SERVER'."\n";
var_dump( $_SERVER );

?>
</pre>
