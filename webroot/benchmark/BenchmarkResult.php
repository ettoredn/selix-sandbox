<?php

class BenchmarkResult
{
    private $benchmarks;

    public function __construct( $benchmarks )
    {
        if (count($benchmarks) < 2)
            throw new ErrorException('count($benchmarks) < 2');

        foreach ($benchmarks as $b)
            if (!($b instanceof Benchmark))
                throw new ErrorException('!($b instanceof Benchmark)');

        $this->benchmarks = $benchmarks;
    }

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
        foreach ($this->benchmarks as $b)
            if ($b->GetConfigurationName() == $confName)
                $res[] = $b;

        return $res;
    }

    /*
     * Compare benchmarks configurations.
     * $configs holds an array of configuration names, first element is taken as baseline.
     *
     * Returns: array( configName => array( benchmarkName => array( benchmarkItem => %overhead ) ) )
     */
    public function CompareBenchmarks( $configs )
    {
        // First element is taken as baseline
        $baseBenchs = $this->GetBenchmarksByConfiguration( array_shift( $configs ) );
        if (empty($baseBenchs)) throw new ErrorException('empty($baseBenchs)');

        foreach ($configs as $conf)
        {
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
}

?>
