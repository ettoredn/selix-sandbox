<?php
require_once("Database.php");
require_once("Benchmark.php");
require_once("phpinfoTest.php");

class phpinfoBenchmark extends Benchmark
{
    public function LoadFromTable( $table )
    {
        if ($GLOBALS['verbose'])
            echo "Loading tests ...\n";

        $data = NULL;
        $q = "SELECT *
              FROM $table
              WHERE `timestamp` BETWEEN ". $this->GetStartTimestamp() ." AND ". $this->GetFinishTimestamp() ."
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() % 4) throw new ErrorException("Query or data error!");

        // Load tests
        while ($trace = $r->fetch())
        {
            // Get start timestamp
            if ($trace['name'] == "PHP_Zend:execute_primary_script_start")
            {
                $timestampStart = $trace['timestamp'];

                if ($GLOBALS['verbose'])
                    echo "[".$trace['session']."/".$trace['configuration']."] { timestamp = ".$trace['timestamp'].
                            ", benchmark = ".$this->GetName().", test_start = ".$timestampStart." }\n";
            }

            // Get finish timestamp
            if ($trace['name'] == "PHP_Zend:execute_primary_script_finish")
            {
                $timestampFinish = $trace['timestamp'];

                if ($GLOBALS['verbose'])
                    echo "[".$trace['session']."/".$trace['configuration']."] { timestamp = ".$trace['timestamp'].
                            ", benchmark = ".$this->GetName().", test_finish = ".$timestampFinish." }\n";

                // Build class name
                $testClass = $this->GetName() ."Test";
                if (!class_exists($testClass))
                    throw new ErrorException("Class $testClass is required!");

                // Instantiate phpinfoTest
                $t = new phpinfoTest($timestampStart, $timestampFinish);
                $this->AddTest( $t );
                $t->LoadFromTable( $table );

                if ($GLOBALS['verbose'])
                    echo "Test loaded { name = ".$t->GetName().", execution_time = ".$t->GetExecutionTime().
                            ", zend_compile_time = ".$t->GetZendCompileTime().
                            ", zend_execute_time = ".$t->GetZendExecuteTime()." }\n";
            }
        }
    }

    /*
     * Returns an array with percentage changes from another phpinfoBenchmark (taken as baseline).
     */
    public function CompareTo( Benchmark $b )
    {
        if ($GLOBALS['verbose_maths'])
            echo "[".get_class($this)."/CompareTo] { benchmark = ".get_class($b)." }\n";

        if (!($b instanceof phpinfoBenchmark))
            throw new ErrorException('!($b instanceof phpinfoBenchmark)');

        $r['zend_compile_time'] = $this->CalculateBenchmarkTimestampPercentageChange($b, "GetAverageZendCompileTime");
        $r['zend_execute_time'] = $this->CalculateBenchmarkTimestampPercentageChange($b, "GetAverageZendExecuteTime");

        return $r;
    }

    /*
     * Returns average zend_compile execution time.
     */
    public function GetAverageZendCompileTime()
    {
        return $this->CalculateAverageTestsTimestampValue("GetZendCompileTime");
    }

    /*
     * Returns average zend_execute execution time.
     */
    public function GetAverageZendExecuteTime()
    {
        return $this->CalculateAverageTestsTimestampValue("GetZendExecuteTime");
    }
}

?>
