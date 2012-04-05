<?php
require_once("Database.php");
require_once("BenchmarkResult.php");
require_once("functionBenchmark.php");
require_once("phpinfoBenchmark.php");

class Session
{
    private $id;
    // Name of benchmarks executed in the session
    private $benchsName;
    // Name of configurations used in the session
    private $configsName;
    // Benchmark[]
    private $benchmarks;

    function __construct( $sessionid )
    {
        $this->id = $sessionid;

        // Get benchmarks executed in the session
        $q = "SELECT *
              FROM ". Database::SESSION_TABLE ."
              WHERE session=$sessionid";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() < 1) throw new ErrorException("Session $sessionid doesn't exist!");

        $session = $r->fetch();
        $this->benchsName = explode(" ", $session['benchmarks']);

        // Get configurations used in the session
        $q = "SELECT DISTINCT configuration
              FROM ". Database::TRACEDATA_TABLE ."
              WHERE session=$sessionid";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() < 1) throw new ErrorException("No configurations found for session $sessionid!");

        while ($config = $r->fetch())
            $this->configsName[] = $config['configuration'];
    }

    function GetId()
    { return $this->id; }

    function GetTimestamp()
    { return date("Ymd H:i:s", $this->id); }

    function GetConfigurations()
    { return $this->configsName; }

    function GetBenchmarks()
    { return $this->benchsName; }

    protected function AddBenchmark( $b )
    { $this->benchmarks[] = $b; }

    function LoadBenchmarks()
    {
        foreach ($this->configsName as $conf )
            $this->LoadBenchmarksByConfiguration( $conf );
    }

    private function LoadBenchmarksByConfiguration( $configuration, $table = Database::TRACEDATA_TABLE )
    {
        foreach ($this->benchsName as $benchName)
        {
            if ($GLOBALS['verbose'])
                echo "<b>Loading benchmark</b> '$benchName' with '$configuration' configuration ...\n";

            $q = "(SELECT *
                   FROM $table
                   WHERE session=". $this->id ."
                       AND `configuration`='$configuration'
                       AND `name`='PHP_Zend:execute_primary_script_start'
                       AND args LIKE 'file = \"$benchName.php\"%'
                   ORDER BY `timestamp` ASC
                   LIMIT 1
                  ) UNION (
                   SELECT *
                   FROM $table
                   WHERE session=". $this->id ."
                       AND `configuration`='$configuration'
                       AND `name`='PHP_Zend:execute_primary_script_finish'
                       AND args LIKE 'file = \"$benchName.php\"%'
                   ORDER BY `timestamp` DESC
                   LIMIT 1)";
            $r = Database::GetConnection()->query($q);
            if (!$r || $r->rowCount() != 2) throw new ErrorException("Query or data error: $q");

            $trace = new Tracepoint( $r->fetch(PDO::FETCH_ASSOC) );
            $startTimestamp = $trace->GetTimestamp();
            $trace = new Tracepoint( $r->fetch(PDO::FETCH_ASSOC) );
            $finishTimestamp = $trace->GetTimestamp();

            if ($GLOBALS['verbose'])
                echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { bench_name = $benchName".
                        ", bench_start = $startTimestamp, bench_finish = $finishTimestamp }\n";

            // Build benchmark's class name
            $benchClass = $benchName ."Benchmark";
            if (!class_exists($benchClass))
                throw new ErrorException("Class $benchClass is required!");

            // Instantiate benchmark
            $b = new $benchClass($configuration, $startTimestamp, $finishTimestamp);
            $this->AddBenchmark( $b );
            $b->LoadFromTable( $table );

            if ($GLOBALS['verbose'])
                echo "Benchmark loaded { name = ".$b->GetName().", test_count = ".$b->GetTestCount().
                        ", avg_execution_time = ".$b->GetAverageExecutionTime()." }\n";
        }
    }

    public function GetResults()
    {
        $result = new BenchmarkResult( $this->benchmarks );
        return $result->CompareBenchmarks( array("php", "selix") );
    }
}
?>
