<html><head>
<style type="text/css">
	body { font-family: Calibri; margin: 10px 6px; }
	.red { color: red; }
	.title { background-color: #DAD8D8; text-align: center; }
</style></head>
<body><?php

check_extension();
check_environment();
write_temp_file();
unlink_temp_file();

function check_extension()
{
	if (extension_loaded("selix"))
		echo "<p>[ OK ] SELinux extension loaded</p>";
	else
		die("<p class='red'>[ ERROR ] SELinux extension not loaded</p>");
}

function check_environment()
{
	if (array_key_exists('SELINUX_DOMAIN', $_SERVER) ||
			array_key_exists('SELINUX_RANGE', $_SERVER) ||
			array_key_exists('REDIRECT_SELINUX_DOMAIN', $_SERVER) ||
			array_key_exists('REDIRECT_SELINUX_RANGE', $_SERVER))
		echo "<p class='red'>[ ERROR ] SELinux security context data exposed in environment variables</p>";
	else
		echo "<p>[ OK ] SELinux security context data not exposed in environment variables</p>";
}

function show_environment()
{
	echo '<p class="title">$_SERVER</p><pre>';
	var_dump( $_SERVER );
	echo '</pre>';
	echo '<p class="title">$_ENV</p><pre>';
	var_dump( $_ENV );
	echo '</pre>';
}

function write_temp_file()
{
	$name = "/tmp/php_test_tmp.php";
	$mode = "a+";
	
	if (!($f = fopen( $name, $mode ))) {
		echo "<p class='red'>[ ERROR ] Cannot open $name with mode $mode</p>";
		return;
	}
	if (!fwrite( $f, "Dummy data" )) {
		echo "<p class='red'>[ ERROR ] Cannot write /tmp/php_test_tmp.php</p>";
		return;
	}
	fclose( $f );	
	echo "<p>[ OK ] Write of $name succeed</p>";
}

function unlink_temp_file()
{
	$name = "/tmp/php_test_tmp.php";

	if (!unlink( $name )) {
		echo "<p class='red'>[ ERROR ] Cannot unlink $name</p>";
		return; 
	}
	echo "<p>[ OK ] Unlink $name succeed</p>";
}
?></body>
</html>
