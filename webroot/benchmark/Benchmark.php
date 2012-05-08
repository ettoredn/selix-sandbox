<?php
require_once("AverageNumeric.php");
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
     * Calculates percentage change between timestamp values returned by a method
     * called on $this and the specified Benchmark.
     * Supplied Benchmark is taken as baseline.
     */
    protected function CalculateBenchmarkNumericDelta( $b, $method )
    {
        if (!($b instanceof Benchmark ))
            throw new ErrorException('$b not instanceof Benchmark )');
        if (!is_callable(array($b, $method)) || !is_callable(array($this, $method)))
            throw new ErrorException('!is_callable(array($b, $method)) || !is_callable(array($this, $method))');

        if ($GLOBALS['verbose_maths']) echo "[".get_class($this)."/CalculateBenchmarkNumericDelta] { method = $method }\n";
        $baseAvg = call_user_func(array($b, $method));
        $thisAvg = call_user_func(array($this, $method));

        if (!($baseAvg instanceof AverageNumeric) || !($thisAvg instanceof AverageNumeric))
            throw new ErrorException('!($baseAverage instanceof AverageNumeric) || !($thisAverage instanceof AverageNumeric)');

        // Overhhead = (new_result - old_result) / old_result
        $delta = bcsub($thisAvg->Median(), $baseAvg->Median(), AverageNumeric::PRECISION);
        $percent = bcdiv($delta, $baseAvg->Median(), AverageNumeric::PRECISION);
        $res = bcmul($percent , 100, AverageNumeric::PRECISION);
        if ($GLOBALS['verbose_maths'])
            echo "[".get_class($this)."/CalculateBenchmarkNumericDelta] { baseBench = ".$baseAvg->Median().
                    ", thisBench = ".$thisAvg->Median().", diff = $delta, percent = $percent, res = $res }\n";

        return array(
            'median' => $thisAvg->Median(),
            'quartiles' => $thisAvg->Quartiles(),
            'iqr' => $thisAvg->IQR(),
            'delta' => array(
                'absolute' => round($delta),
                'relative' => round($res, 1),
            )
        );
    }

    /*
     * Instantiates a new Average object filled with values returned by a method called on every Test loaded.
     */
    protected function GetAverageNumeric( $method )
    {
        if ($GLOBALS['verbose_maths']) echo "[".get_class($this)."/CalculateTestsAverageNumeric] { method = $method }\n";

        $items = array();
        foreach($this->tests as $t)
        {
            if (!($t instanceof Test ))
                throw new ErrorException('$t not instanceof Test )');
            if (!is_callable(array($t, $method)))
                throw new ErrorException('!is_callable(array($t, $method))');
            $items[] = call_user_func(array($t, $method));
        }

        $avg = new AverageNumeric($items);

        return $avg;
    }

    /*
     * Returns AverageNumeric test execution time.
     */
    public function GetAverageExecutionTime()
    {
        return $this->GetAverageNumeric("GetExecutionTime");
    }

    public abstract function LoadFromTable( $table );
    public abstract function CompareTo( Benchmark $b );
}

?>
