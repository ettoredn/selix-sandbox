<?php
require_once("Database.php");
require_once("Gnuplot.php");
require_once("functionBenchmark.php");
require_once("helloworldBenchmark.php");
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

    protected function AddBenchmark( Benchmark $b )
    { $this->benchmarks[] = $b; }

    protected function LoadBenchmarks()
    {
        unset($this->benchmarks);
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
                        ", avg_execution_mean = ".$b->GetAverageExecutionTime()->Median()." }\n";

//            $b->GetAverageExecutionTime()->WriteValuesToFile("bench_".$b->GetName()."_".$b->GetConfigurationName().".txt");
        }
    }

    /*
     * Compare benchmarks configurations.
     * $configs holds an array of configuration names, first element is taken as baseline.
     *
     * Returns: array( configName => array( benchmarkName => array( benchmarkItem => %overhead ) ) )
     */
    protected function CompareBenchmarks( $baseConfArg = null )
    {
        if ($baseConfArg == null)
        {
            // Defaults to first configuration
            $baseConf = $this->GetConfigurations();
            $baseConf = $baseConf[0];
        }
        else
            $baseConf = $baseConfArg;

        // First element is taken as baseline
        $baseBenchs = $this->GetBenchmarksByConfiguration($baseConf);
        if (empty($baseBenchs)) throw new ErrorException('empty($baseBenchs)');

        $res = array();
        foreach ($this->configsName as $conf)
        {
            if ($baseConfArg != null && $conf == $baseConfArg)
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
     * Returns null if no cache entry is found
     */
    protected function GetCachedRawResult( $config )
    {
        if (empty($config))
            throw new ErrorException('!empty($config)');

            $q = "SELECT data
              FROM ". Database::SESSION_CACHE_TABLE ."
              WHERE session=". $this->GetId() ."
              AND baseline_configuration='". $config ."'";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() > 1) throw new ErrorException("Too much cache entries for session ".$this->GetId());

        if ($r->rowCount() == 0)
            // No cache
            return null;

        $result = $r->fetch(PDO::FETCH_NUM); $result = unserialize($result[0]);

        if (empty($result))
            throw new ErrorException('!$result');

        return $result;
    }

    protected function AddRawResultToCache( $config, $result )
    {
        if (empty($config) || empty($result))
            throw new ErrorException('!empty($config) || empty($result)');

        $serialized = serialize($result);

        $q = "INSERT INTO ". Database::SESSION_CACHE_TABLE ."
              (session, baseline_configuration, data)
              VALUES(:session, :baseconf, :data)";
        $st = Database::GetConnection()->prepare($q);
        $st->bindParam(':session', $this->GetId(), PDO::PARAM_INT);
        $st->bindParam(':baseconf', $config, PDO::PARAM_STR, strlen($config));
        $st->bindParam(':data', $serialized, PDO::PARAM_STR, strlen($serialized));
        if ($st->execute() != 1)
            throw new ErrorException('Error adding session raw result to cache!');
    }

    /*
     * $configs holds an array of configuration names, first element is taken as baseline.
     */
    public function GetRawResults( $baseConfArg = null )
    {
        if ($baseConfArg == null)
            $baseConf = "__nobaseline__"; // Not a shiny choice but does the job
        else
            $baseConf = $baseConfArg;

        // Check local cache
        if (empty($this->results) || !array_key_exists($baseConf, $this->results) || empty($this->results[$baseConf]))
        {
            // Check database cache
            $this->results[$baseConf] = $this->GetCachedRawResult($baseConf);
            if ($this->results[$baseConf] == null)
            {
                // Load benchmarks to generate requested results
                $this->LoadBenchmarks();

                if ($baseConfArg == null)
                    $this->results[$baseConf] = $this->CompareBenchmarks(); // __nobaseline__ not know to CompareBenchmarks
                else
                    $this->results[$baseConf] = $this->CompareBenchmarks($baseConf);

                $this->AddRawResultToCache($baseConf, $this->results[$baseConf]);
            }
        }

        // Sort results by benchmark
        $res = array();
        foreach ($this->benchsName as $benchName)
            foreach (array_keys($this->results[$baseConf]) as $confName)
                $res[$benchName][$confName] = $this->results[$baseConf][$confName][$benchName];

        return $res;
    }

    /*
     * $results raw results *for a single benchmark* obtained with GetRawResults()[benchmark]
     * $properties array( header => array(propertyIndex1, propertyIndex2, ...) )
     */
    protected function BuildBenchmarkPlotData( $benchName, $results, $tests, $properties )
    {
        if (empty($benchName) || empty($results) || empty($tests) || empty($properties))
            throw new ErrorException('empty($benchName) || empty($results) || empty($tests) || empty($properties)');

            // Build plot data for gnuplot
        $configs = array_keys($results);
        $plotData = array(array());

        // Write header
        $plotData[0][0] = "Benchmark";
        $plotData[0][1] = "Test";
        foreach ($configs as $config)
        {
            foreach ($properties as $header => $p)
            {
                switch ($header)
                {
                    case '%configName%':
                        $plotData[0][] = $config; break;
                    default:
                        $plotData[0][] = $header;
                }
            }
        }

        // Write entries
        $row = 1;
        foreach ($tests as $test)
        {
            // Write columns for the entry
            $plotData[$row][0] = $benchName;
            $plotData[$row][1] = $test;
            foreach ($configs as $config)
            {
                foreach ($properties as $prop)
                {
                    if (empty($results[$config][$test]))
                        throw new ErrorException('$results[$config][$test]');
                    if (!isset($prop[0]))
                        throw new ErrorException('!isset($prop[0])');

                    $data = $results[$config][$test][$prop[0]];
                    for ($propIndex=1; $propIndex < count($prop); $propIndex++)
                        $data = $data[$prop[$propIndex]];

                    $plotData[$row][] = $data;
                }
            }
            $row++;
        }

        // Implode entries
        foreach ($plotData as $key => $entry)
            $plotData[$key] = implode(" ", $entry);

        return $plotData;
    }

    /*
     * Plot results of a given benchmark into a PNG file and returns its filename.
     */
    public function PlotBenchmark( $benchName, $tests, $scaleName = "microseconds" )
    {
        if (empty($benchName) || !is_array($tests))
            throw new ErrorException('empty($benchName) || !is_array($properties)');

        $filename = $this->GetId()."_bench_$benchName.png";
        $title = "Benchmark for $benchName.php";
        switch ($scaleName)
        {
            case 'milliseconds': $scale = "1000000.0"; break;
            case 'microseconds': $scale = "1000.0"; break;
            case 'nanoseconds': $scale = "1.0"; break;
            default: throw new ErrorException("Unknown scale name $scaleName");
        }

        // If already generated returns it
        if (file_exists(Gnuplot::DATAPATH.$filename))
            return Gnuplot::DATAPATH.$filename;

        $results = $this->GetRawResults();
        if (empty($results[$benchName]) || !is_array($results[$benchName]))
        {
            // Perhaps the requested benchmark was not ran in this session, so it just logs a warning.
            echo "[".__METHOD__."] WARNING: $benchName benchmark not found in session ".$this->GetId()."\n";
            return Gnuplot::DATAPATH."notfound.png";

        }

        // Build plot data for gnuplot
        $results = $results[$benchName];
        $plotData = $this->BuildBenchmarkPlotData($benchName, $results, $tests, array(
                '%configName%' => array('median'),
                'LQ' => array('quartiles', 0),
                'UQ' => array('quartiles', 2),
        ));
//        print_r($plotData);

        // Build plot arguments
        $configs = array_keys($results);
        $plotArgs = array();
        $plotDataArgs = array();
        $i = 3; // Properties number
        foreach ($configs as $c)
        {
            $plotArgs[] =
                    'using ($'.$i++.'/'.$scale.'):'.
                          '($'.$i++.'/'.$scale.'):'.
                          '($'.$i++.'/'.$scale.'):'.
                          'xtic(2) title columnhead('.($i-3).')';
            $plotDataArgs[] = $plotData; // $plotData must be repeated for each configuration
        }
//        print_r(implode("\n", $plotArgs));

        $plot = new Gnuplot();
        $plot->Open();
        $plot->SetPlotStyle(new ErrorHistogramPlotStyle()); // Reset
        $plot->SetYLabel("time [$scaleName]");
        $plot->SetYRange(0, "*");
        $plot->PlotDataToPNG($title, $filename, $plotArgs, $plotDataArgs, "1024,768");
        $plot->Close();
//        print_r(implode("\n",$plot->GetLog()));

        return Gnuplot::DATAPATH.$filename;
    }

    /*
     * Plot results of a given benchmark into a PNG file and returns its filename.
     */
    public function PlotBenchmarkDelta( $benchName, $tests, $baseConf, $scaleName = "microseconds" )
    {
        if (empty($benchName) || empty($baseConf) || !is_array($tests))
            throw new ErrorException('empty($benchName) || empty($baseConf) || !is_array($properties)');

        $filename = $this->GetId()."_bench_$benchName"."_delta_$baseConf.png";
        $title = "Benchmark delta for $benchName.php with $baseConf configuration as baseline";
        switch ($scaleName)
        {
            case 'milliseconds': $scale = "1000000.0"; break;
            case 'microseconds': $scale = "1000.0"; break;
            case 'nanoseconds': $scale = "1.0"; break;
            default: throw new ErrorException("Unknown scale name $scaleName");
        }

        // If already generated returns it
        if (file_exists(Gnuplot::DATAPATH.$filename))
            return Gnuplot::DATAPATH.$filename;

        $results = $this->GetRawResults($baseConf);
        if (empty($results[$benchName]) || !is_array($results[$benchName]))
        {
            // Perhaps the requested benchmark was not ran in this session, so it just logs a warning.
            echo "[".__METHOD__."] WARNING: $benchName benchmark not found in session ".$this->GetId()."\n";
            return Gnuplot::DATAPATH."notfound.png";

        }

        // Build plot data for gnuplot
        $results = $results[$benchName];
        $plotData = $this->BuildBenchmarkPlotData($benchName, $results, $tests, array(
                '%configName%' => array('delta', 'absolute')
        ));
//        print_r($plotData);

        // Build plot arguments
        $configs = array_keys($results);
        $plotArgs = array();
        $plotDataArgs = array();
        $i = 3; // Properties number
        foreach ($configs as $c)
        {
            $plotArgs[] =
                    'using ($'.$i++.'/'.$scale.'):'.
                          'xtic(2) title columnhead('.($i-1).')';
            $plotDataArgs[] = $plotData; // $plotData must be repeated for each configuration
        }
//        print_r(implode("\n", $plotArgs));

        $plot = new Gnuplot();
        $plot->Open();
        $plot->SetPlotStyle(new ClusteredHistogramPlotStyle()); // Reset
        $plot->SetYLabel("time [$scaleName]");
        $plot->SetYRange(0, "*");
        $plot->PlotDataToPNG($title, $filename, $plotArgs, $plotDataArgs, "1024,768");
        $plot->Close();
//        print_r(implode("\n",$plot->GetLog()));

        return Gnuplot::DATAPATH.$filename;
    }
}
?>
