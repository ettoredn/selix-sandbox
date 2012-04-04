<?php
/* A PHP script into webroot generates fancy graphs
   http://people.iola.dk/olau/flot/examples/stacking.html
   http://pchart.sourceforge.net/screenshots.php?ID=8 */
$verbose = false;
$verbose_maths = false;
require_once("Database.php");
require_once("Session.php");

?>
<!DOCTYPE html>
 <html>
 <head>
 <title>Benchmark Viewer</title>
 </head>
 <body>
<?php

// Generate benchmarks list
$q = "SELECT session AS id
      FROM ". Database::TRACEDATA_TABLE ."
      GROUP BY session
      HAVING COUNT(DISTINCT configuration) > 1
      ORDER BY session DESC";
$r = Database::GetConnection()->query($q) or die("Query error!");

echo '
<form method="get">
    <select name="bench">';

while($s = $r->fetch())
    echo '<option value="'. $s['id'] .
            ( !empty($_GET['bench']) && $_GET['bench'] == $s['id'] ? '" selected="selected">' : '">' ).
            date("Ymd H:i:s", $s['id']) .'</option>';

echo '
    </select>
    <input type="submit" value="Show benchmark" />
</form>';

echo '<pre>';
// Show benchmark if requested
if (!empty($_GET['bench']))
{
    // Retrieve start and finish timestamps for each benchmark run
    $id = $_GET['bench'];

    $s = new Session( $id );
    $s->LoadBenchmarks();
    $results = $s->GetResults();

    print_r( $results );
}
echo '</pre>';

// Close the connection
$db = null;
?>
 </body>
 </html>
