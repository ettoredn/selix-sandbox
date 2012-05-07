<?php
require_once("Database.php");
require_once("Gnuplot.php");
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
    // Results cache
    private $results;

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

    protected function GetBenchmarksByName( $name, $set = null )
    {
        if (empty($set))
            $set = $this->benchmarks;

        $result = array();
        foreach ($set as $b)
            if ($b->GetName() == $name)
                $result[] = $b;

        return $result;
    }

    protected function GetBenchmarksByConfiguration( $confName )
    {
        $res = null;
        foreach ($this->benchmarks as $b)
            if ($b->GetConfigurationName() == $confName)
                $res[] = $b;

        return $res;
    }

    protected function AddBenchmark( $b )
    { $this->benchmarks[] = $b; }

    function LoadBenchmarks()
    {
        foreach ($this->configsName as $conf )
            $this->LoadBenchmarksByConfiguration( $conf );
    }

    protected function LoadBenchmarksByConfiguration( $configuration, $table = Database::TRACEDATA_TABLE )
    {
        foreach ($this->benchsName as $benchName)
        {
            if ($GLOBALS['verbose'])
                echo "<b>Loading benchmark</b> '$benchName' with '$configuration' configuration ...\n";

            $q = "(SELECT *
                   FROM $table
                   WHERE session=". $this->id ."
                       AND `configuration`='$configuration'
                       AND `name`='PHP_PHP:execute_primary_script_start'
                       AND args LIKE 'path = \"$benchName.php\"%'
                   ORDER BY `timestamp` ASC
                   LIMIT 1
                  ) UNION (
                   SELECT *
                   FROM $table
                   WHERE session=". $this->id ."
                       AND `configuration`='$configuration'
                       AND `name`='PHP_PHP:execute_primary_script_finish'
                       AND args LIKE 'path = \"$benchName.php\"%'
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
                        ", avg_execution_mean = ".$b->GetAverageExecutionTime()->GetMean().
                        ", avg_execution_stddev = ".$b->GetAverageExecutionTime()->GetStandardDeviation()." }\n";

//            $b->GetAverageExecutionTime()->WriteValuesToFile("bench_".$b->GetName()."_".$b->GetConfigurationName().".txt");
        }
    }

    /*
     * Compare benchmarks configurations.
     * $configs holds an array of configuration names, first element is taken as baseline.
     *
     * Returns: array( configName => array( benchmarkName => array( benchmarkItem => %overhead ) ) )
     */
    protected function CompareBenchmarks( $baseConf )
    {
        // First element is taken as baseline
        $baseBenchs = $this->GetBenchmarksByConfiguration($baseConf);
        if (empty($baseBenchs)) throw new ErrorException('empty($baseBenchs)');

        $res = array();
        foreach ($this->configsName as $conf)
        {
            if ($conf == $baseConf)
                continue;

            $benchs = $this->GetBenchmarksByConfiguration( $conf );
            if (empty($benchs)) throw new ErrorException('empty($benchs)');

            $res[$conf] = $this->CompareBenchmarkSets( $baseBenchs, $benchs );
        }

        return $res;
    }

    /*
     * Compare two sets of benchmarks.
     * Each comparing set's (benchmark) object must have an object of the same class in the compared set.
     *
     * Returns: array( benchmarkName => comparisonResult )
     */
    protected function CompareBenchmarkSets( $baseSet, $set )
    {
        $res = array();
        foreach ($set as $bench)
        {
            if (!($bench instanceof Benchmark))
                throw new ErrorException('!($bench instanceof Benchmark)');
            $benchName = $bench->GetName();

            // Each set must have only one benchmark for a given name
            $baseBench = $this->GetBenchmarksByName( $benchName, $baseSet );
            if (count($baseBench) > 1)
                throw new ErrorException('count($baseBench) > 1');
            $baseBench = $baseBench[0];

            $res[$benchName] = $bench->CompareTo( $baseBench );
        }

        return $res;
    }

    /*
     * $configs holds an array of configuration names, first element is taken as baseline.
     */
    public function GetRawResults( $baseConf )
    {
        if (empty($this->results) || !is_array($this->results))
            $this->results = $this->CompareBenchmarks( $baseConf );

        // Sort results by benchmark
        $res = array();
        foreach ($this->benchsName as $benchName)
            foreach (array_keys($this->results) as $confName)
                $res[$benchName][$confName] = $this->results[$confName][$benchName];

        return $res;
    }

    /*
     * Plot results of a given benchmark into a PNG file and returns its filename.
     */
    public function GetBenchmarkResult( $benchName, $baseConf )
    {
        if (empty($benchName))
            throw new ErrorException('empty($benchName)');

        $results = $this->GetRawResults($baseConf);
        if (empty($results[$benchName]) || !is_array($results[$benchName]))
            throw new ErrorException('empty($results[$benchName]) || !is_array($results[$benchName])');

        $filename = "bench_$benchName.png";
        $title = "$benchName.php Benchmark";

        // Build plot data for gnuplot
        $results = $results[$benchName];
        $configs = array_keys($results);
        $tests = array_keys($results[$configs[0]]); // Tests are the same across configs

        $plotData = array(array());

        // Write header
        $plotData[0][0] = "Benchmark";
        $plotData[0][1] = "Test";
        for ($c=0; $c < count($configs); $c++)
            $plotData[0][$c+2] = $configs[$c];

        // Write entries
        $row = 1;
        foreach ($tests as $test)
        {
            $plotData[$row][0] = $benchName;
            $plotData[$row][1] = $test;
            for ($c=0; $c < count($configs); $c++)
                        $plotData[$row][$c+2] = $results[$configs[$c]][$test]['delta'];
            $row++;
        }

        // Implode entries
        foreach ($plotData as $key => $entry)
            $plotData[$key] = implode(" ", $entry);

        $plot = new Gnuplot();
        $plot->Open();
        $plot->SetPlotStyle(new ClusteredHistogramPlotStyle()); // Reset
        $plot->SetYLabel("overhead [microseconds]");
        $plot->SetYRange(0, "*");
        $plot->PlotDataToPNG($title, $filename,
            'using ($3/1000.0):xtic(2) title columnhead(3)',
            array($plotData),
            "1280,768"
        );
        $plot->Close();

        return Gnuplot::DATAPATH."bench_$benchName.png";
    }
}
?>
