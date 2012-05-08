<?php
/* A PHP script into webroot generates fancy graphs
   http://people.iola.dk/olau/flot/examples/stacking.html
   http://pchart.sourceforge.net/screenshots.php?ID=8 */
$verbose = true;
$verbose_maths = false;
require_once("Database.php");
require_once("Session.php");

?>
<!DOCTYPE html>
 <html>
 <head>
 <title>Benchmark Viewer</title>
     <script type="text/javascript">
         function switchVerbose()
         {
             var e = document.getElementById('verbose');
             if (e.style.display == "none") e.style.display = "block";
             else e.style.display = "none";
         }
     </script>
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
    echo '<p>No session present in the database</p>';
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
    // Retrieve start and finish timestamps for each benchmark run
    $id = $_GET['bench'];

    // Catch verbose output
    ob_start();

    try {
        $s = new Session( $id );
    } catch (ErrorException $e)
    { die("<p>Session $id doesn't exist</p>"); }
    $s->LoadBenchmarks();
    $raw = $s->GetRawResults( "php" );
    $functionBenchmarkImage = $s->PlotBenchmark("function", array(
//        "zend_execute_time",
        "zendvm_user_fcall_time",
        "zendvm_internal_fcall_time",
    ));
    $phpinfoBenchmarkImage = $s->PlotBenchmark("phpinfo", array(
        "zend_compile_time",
        "zend_execute_time",
    ));

    // Get verbose output produced
    $verbose = ob_get_clean();

    echo "<img src='$functionBenchmarkImage' width='100%'/>";
    echo "<img src='$phpinfoBenchmarkImage' width='100%'/>";
    echo '<pre>';
    print_r( $raw );
    echo '</pre>';

    echo '<p><a href="#" onclick="switchVerbose(); return false;">Show/hide verbose</a></p>';
    echo "<pre id='verbose' style='display: none;'>$verbose</pre>";
}
?>
 </body>
 </html>
