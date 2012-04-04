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
$rtrace = Database::GetConnection()->query($q) or die("Query error: $q");

if ($rtrace->rowCount() < 1)
    echo '<div>No session present in the database</div>';
else
{
    echo '
    <form method="get">
        <select name="bench" size="6" onchange="this.form.submit()">';

    while($s = $rtrace->fetch())
    {
        $q = "SELECT *
              FROM ". Database::SESSION_TABLE ."
              WHERE session=". $s['id'];
        $r = Database::GetConnection()->query($q) or die("Query error: $q");
        $info = $r->fetch();

        echo '<option value="'. $s['id'] .
                ( !empty($_GET['bench']) && $_GET['bench'] == $s['id'] ? '" selected="selected">' : '">' ).
                date("Ymd H:i:s", $s['id']) .' ('. $info['runs'] .' runs)</option>';
    }

    echo '
        </select>
        <!-- <input type="submit" value="Show benchmark" /> -->
    </form>';
}

// Show benchmark if requested
if (!empty($_GET['bench']))
{
    echo '<pre>';

    // Retrieve start and finish timestamps for each benchmark run
    $id = $_GET['bench'];

    $s = new Session( $id );
    $s->LoadBenchmarks();
    $results = $s->GetResults();

    print_r( $results );

    echo '</pre>';
}


// Close the connection
$db = null;
?>
 </body>
 </html>
