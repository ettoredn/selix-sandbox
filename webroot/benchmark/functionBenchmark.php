<?php
require_once("Database.php");
require_once("Tracepoint.php");
require_once("Benchmark.php");
require_once("functionTest.php");

class functionBenchmark extends Benchmark
{
    public function LoadFromTable( $table )
    {
        if ($GLOBALS['verbose'])
            echo "Loading tests ...\n";

        $data = NULL;
        $q = "SELECT *
              FROM $table
              WHERE `timestamp` BETWEEN ". $this->GetStartTimestamp() ." AND ". $this->GetFinishTimestamp() ."
                AND `name` IN('PHP_PHP:execute_primary_script_start',
                              'PHP_PHP:execute_primary_script_finish')
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() % 2) throw new ErrorException("Query or data error: $q");

        // Load tests
        while ($row = $r->fetch(PDO::FETCH_ASSOC))
        {
            $trace = new Tracepoint($row);

            switch ($trace->GetName()) {
                case "PHP_PHP:execute_primary_script_start":
                    // Get start timestamp
                    $timestampStart = $trace->GetTimestamp();

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", benchmark = ".$this->GetName().", test_start = ".$timestampStart." }\n";
                    break;
                case "PHP_PHP:execute_primary_script_finish":
                    // Get finish timestamp
                    $timestampFinish = $trace->GetTimestamp();

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", benchmark = ".$this->GetName().", test_finish = ".$timestampFinish." }\n";

                    // Build class name
                    $testClass = $this->GetName() ."Test";
                    if (!class_exists($testClass))
                        throw new ErrorException("Class $testClass is required!");

                    // Instantiate phpinfoTest
                    $t = new functionTest($timestampStart, $timestampFinish);
                    $this->AddTest( $t );
                    $t->LoadFromTable( $table );

                    if ($GLOBALS['verbose'])
                        echo "Test loaded { name = ".$t->GetName().", execution_time = ".$t->GetExecutionTime().
                                ", zend_compile_time = ".$t->GetZendCompileTime().
                                ", zend_execute_time = ".$t->GetZendExecuteTime()." }\n";
                    break;
            }
        }
    }

    /*
     * Returns an array with percentage changes from another functionBenchmark (taken as baseline).
     */
    public function CompareTo( Benchmark $b )
    {
        if ($GLOBALS['verbose_maths'])
            echo "[".get_class($this)."/CompareTo] { benchmark = ".get_class($b).", confName = ".$b->GetConfigurationName()." }\n";

        if (!($b instanceof functionBenchmark))
            throw new ErrorException('!($b instanceof functionBenchmark)');

        $r['zend_compile_time'] = $this->CalculateBenchmarkNumericDelta($b, "GetAverageZendCompileTime");
        $r['zend_execute_time'] = $this->CalculateBenchmarkNumericDelta($b, "GetAverageZendExecuteTime");
        $r['zendvm_internal_fcall_time'] = $this->CalculateBenchmarkNumericDelta($b, "GetAverageZendVMInternalFunctionCallTime");
        $r['zendvm_user_fcall_time'] = $this->CalculateBenchmarkNumericDelta($b, "GetAverageZendVMUserFunctionCallTime");

        return $r;
    }

    /*
     * Returns average zend_compile execution time.
     */
    public function GetAverageZendCompileTime()
    {
        return $this->GetAverageNumeric("GetZendCompileTime");
    }

    /*
     * Returns average zend_execute execution time.
     */
    public function GetAverageZendExecuteTime()
    {
        return $this->GetAverageNumeric("GetZendExecuteTime");
    }

    /*
     * Returns average internal function call execution time.
     */
    public function GetAverageZendVMInternalFunctionCallTime()
    {
        return $this->GetAverageNumeric("GetZendVMInternalFunctionCallTime");
    }

    /*
     * Returns average internal function call execution time.
     */
    public function GetAverageZendVMUserFunctionCallTime()
    {
        return $this->GetAverageNumeric("GetZendVMUserFunctionCallTime");
    }
}

?>
