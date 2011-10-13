<html><head>
<style type="text/css">
	body { font-family: Calibri; margin: 10px 6px; }
	.error { color: red; }
	.warning { color: green; }
	.title { background-color: #DAD8D8; text-align: center; }
</style></head>
<body><?php

check_extension();
check_environment();
write_temp_file();
unlink_temp_file();
// httpd_vhost_content_t files
read_httpd_content_file();
write_httpd_content_file();
// append_httpd_content_file();
// php_vhost_script_t files
write_php_script_file();
// httpd_vhost_content_t links
read_httpd_content_2_httpd_content_link();
read_httpd_content_2_php_script_link();
// php_vhost_script_t links
read_php_script_2_php_script_link();
read_php_script_2_httpd_content_link();

// create_httpd_content_file();

function check_extension()
{
	if (extension_loaded("selix"))
		echo "<p>[ OK ] SELinux extension loaded</p>";
	else
		die("<p class='error'>[ ERROR ] SELinux extension not loaded</p>");
}

function check_environment()
{
	if (array_key_exists('SELINUX_DOMAIN', $_SERVER) ||
			array_key_exists('SELINUX_RANGE', $_SERVER) ||
			array_key_exists('REDIRECT_SELINUX_DOMAIN', $_SERVER) ||
			array_key_exists('REDIRECT_SELINUX_RANGE', $_SERVER))
		echo "<p class='error'>[ ERROR ] SELinux security context data exposed in environment variables</p>";
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
	$mode = "w+";
	
	if (!($f = fopen( $name, $mode ))) {
		echo "<p class='error'>[ ERROR ] Cannot open $name with mode $mode</p>";
		return;
	}
	if (!fwrite( $f, "Dummy data" )) {
		echo "<p class='error'>[ ERROR ] Cannot write $name</p>";
		return;
	}
	fclose( $f );	
	echo "<p>[ OK ] Write of $name succeed</p>";
}

function unlink_temp_file()
{
	$name = "/tmp/php_test_tmp.php";

	if (!unlink( $name )) {
		echo "<p class='error'>[ ERROR ] Cannot unlink $name</p>";
		return; 
	}
	echo "<p>[ OK ] Unlink $name succeed</p>";
}

function read_httpd_content_file()
{
	// -rw-rw-rw- httpd_vhost_content_t:s0 static_content.txt
	$name = "static_content.txt";
	$mode = "r";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ file/httpd_vhost_content_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	if (!fread( $f, 1 )) {
		echo "<p class='warning'>[ WARN ][ file/httpd_vhost_content_t ] Cannot read $name</p>";
		return;
	}
	fclose( $f );
	echo "<p>[ OK ][ file/httpd_vhost_content_t ] Can read $name</p>";
}
function write_httpd_content_file()
{
	// -rw-rw-rw- httpd_vhost_content_t:s0 static_content.txt
	$name = "static_content.txt";
	$mode = "w";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ file/httpd_vhost_content_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	fwrite( $f, "Hello World!\n" );
	fclose( $f );
	echo "<p class='error'>[ ERROR ][ file/httpd_vhost_content_t ] Can open $name with mode '$mode'</p>";
}
function append_httpd_content_file()
{
	// -rw-rw-rw- httpd_vhost_content_t:s0 static_content.txt
	$name = "static_content.txt";
	$mode = "a";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ file/httpd_vhost_content_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	fwrite( $f, "Hello ". time() ."\n" );
	fclose( $f );
	echo "<p class='warning'>[ WARN ][ file/httpd_vhost_content_t ] Can open $name with mode '$mode' (ok if you want PHP process write httpd_vhost_content_t files)</p>";
}
function write_php_script_file()
{
	// -rw-rw-rw- php_sephp_script_t compile_error.php
	$name = "phpinfo.php";
	$mode = "w";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ file/php_vhost_script_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	fwrite( $f, "<?php phpinfo(); ?>\n");
	echo "<p class='error'>[ ERROR ][ file/php_vhost_script_t ] Can open $name with mode '$mode'</p>";
	fclose( $f );
}
function read_httpd_content_2_httpd_content_link()
{
	// lrwxrwxrwx httpd_sephp_content_t:s0 link_httpd2httpd.txt -> static_content.txt
	$name = "link_httpd2httpd.txt";
	$mode = "r";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ link/httpd_vhost_content_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	echo "<p class='error'>[ ERROR ][ link/httpd_vhost_content_t ] Can open $name with mode '$mode'</p>";
	fclose( $f );
}
function read_httpd_content_2_php_script_link()
{
	// lrwxrwxrwx httpd_vhost_content_t:s0 link_httpd2php.php -> phpinfo.php
	$name = "link_httpd2php.php";
	$mode = "r";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p class='error'>[ ERROR ][ link/httpd_vhost_content_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	echo "<p>[ OK ][ link/httpd_vhost_content_t ] Can open $name with mode '$mode'</p>";
	fclose( $f );
}
function read_php_script_2_php_script_link()
{
	// lrwxrwxrwx php_sephp_script_t:s0 link_php2php.php -> phpinfo.php
	$name = "link_php2php.php";
	$mode = "r";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p class='error'>[ ERROR ][ link/php_vhost_script_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	echo "<p>[ OK ][ link/php_vhost_script_t ] Can open $name with mode '$mode'</p>";
	fclose( $f );
}
function read_php_script_2_httpd_content_link()
{
	// lrwxrwxrwx php_sephp_script_t:s0 link_php2httpd.txt -> static_content.txt
	$name = "link_php2httpd.txt";
	$mode = "r";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p>[ OK ][ link/php_vhost_script_t ] Cannot open $name with mode '$mode'</p>";
		return;
	}
	echo "<p class='error'>[ ERROR ][ link/php_vhost_script_t ] Can open $name with mode '$mode'</p>";
	fclose( $f );
}
function create_httpd_content_file()
{
	$name = "created_file.txt";
	$mode = "w+";
	
	if (!($f = @fopen( $name, $mode ))) {
		echo "<p class='error'>[ ERROR ][ file/httpd_vhost_content_t ] Cannot create $name</p>";
		return;
	}
	echo "<p>[ OK ][ file/httpd_vhost_content_t ] Can create $name</p>";
	fclose( $f );
}
?></body>
</html>
