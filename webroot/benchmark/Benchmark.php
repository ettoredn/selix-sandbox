<?php
require_once("Test.php");

abstract class Benchmark
{
    private $name;
    private $configurationName;
    private $startTimestamp;
    private $finishTimestamp;
    // Test[]
    private $tests;

    public function __construct( $config, $start, $finish )
    {
        if (empty($config) || empty($start) || empty($finish))
            throw new ErrorException('empty($config) || empty($start) || empty($finish)');

        $this->name = substr(get_class($this), 0, -9);
        $this->configurationName = $config;
        $this->startTimestamp = $start;
        $this->finishTimestamp = $finish;
    }

    public function GetName()
    { return $this->name; }

    public function GetConfigurationName()
    { return $this->configurationName; }

    public function GetStartTimestamp()
    { return $this->startTimestamp; }

    public function GetFinishTimestamp()
    { return $this->finishTimestamp; }

    public function GetTestCount()
    { return count($this->tests); }

    protected function GetTests()
    { return $this->tests; }

    protected function AddTest( Test $t )
    { $this->tests[] = $t; }

    /*
     * Calculates average of values returned by a method called on every Test loaded.
     */
    protected function CalculateAverageTestsTimeValue( $method )
    {
        $total = 0.0;

        if ($GLOBALS['verbose_maths']) echo "[".get_class($this)."/CalculateAverageTestsTimestampValue] { method = $method }\n";
        foreach($this->tests as $t)
        {
            if (!($t instanceof Test ))
                throw new ErrorException('$t not instanceof Test )');
            if (!is_callable(array($t, $method)))
                throw new ErrorException('!is_callable(array($t, $method))');

            $value = call_user_func(array($t, $method));
            if ($GLOBALS['verbose_maths'])
                echo "[".get_class($this)."/CalculateAverageTestsTimestampValue] { $total += $value }\n";
            $total = bcadd($total, $value, 9);
        }

        $result = bcdiv($total, count($this->tests), 9);
        if ($GLOBALS['verbose_maths'])
            echo "[".get_class($this)."/CalculateAverageTestsTimestampValue] { result = $result = $total/".
                    count($this->tests)." }\n";

        return round($result);
    }

    /*
     * Calculates percentage change between timestamp values returned by a method
     * called on $this and the specified Benchmark.
     * Supplied Benchmark is taken as baseline.
     */
    protected function CalculateBenchmarkTimePercentageChange( $b, $method )
    {
        if (!($b instanceof Benchmark ))
            throw new ErrorException('$b not instanceof Benchmark )');
        if (!is_callable(array($b, $method)) || !is_callable(array($this, $method)))
            throw new ErrorException('!is_callable(array($b, $method)) || !is_callable(array($this, $method))');

        $baseBench = call_user_func(array($b, $method));
        $thisBench = call_user_func(array($this, $method));

        // Overhhead = (new_result - old_result) / old_result
        $diff = bcsub($thisBench, $baseBench, 9);
        $percent = bcdiv($diff, $baseBench, 10);
        $res = bcmul($percent , 100, 1);
        if ($GLOBALS['verbose_maths'])
            echo "[".get_class($this)."/CalculateBenchmarkTimestampPercentageChange] { baseBench = $baseBench".
                    ", thisBench = $thisBench, diff = $diff, percent = $percent, res = $res }\n";

        return $res;
    }

    /*
     * Returns average test execution time.
     */
    public function GetAverageExecutionTime()
    {
        return $this->CalculateAverageTestsTimeValue("GetExecutionTime");
    }

    public abstract function LoadFromTable( $table );
    public abstract function CompareTo( Benchmark $b );
}

?>
