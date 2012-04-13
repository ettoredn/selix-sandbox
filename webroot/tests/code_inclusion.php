<?php
$path = "/home/sephp/sephp-sandbox/webroot/tests/";

require($path ."included_script.php");
include($path ."included_script.php");
require_once($path ."included_script.php");
include_once($path ."included_script.php");

eval('if (0) echo 1;');

?>
