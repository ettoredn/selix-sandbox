<html><head>
<style type="text/css">
	body { font-family: Calibri; margin: 10px 6px; }
	.red { color: red; }
	.title { background-color: #DAD8D8; text-align: center; }
</style></head>
<body><?php

check_extension();
show_environment();

function check_extension()
{
	if (extension_loaded("sephp"))
		echo "<p>[ OK ] SePHP extension loaded</p>";
	else
		die("<p class='red'>[ERROR] SePHP extension not loaded</p>");
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
?></body>
</html>
