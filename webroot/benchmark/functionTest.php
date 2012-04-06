<?php
require_once("Database.php");
require_once("Test.php");
require_once("Tracepoint.php");

class functionTest extends Test
{
    public function LoadFromTable( $table )
    {
        $data = NULL;
        $q = "SELECT *
              FROM $table
              WHERE `timestamp` BETWEEN ". $this->GetTimestampStart() ." AND ". $this->GetTimestampFinish() ."
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() != 6) throw new ErrorException("Query or data error: $q");

        while ($row = $r->fetch(PDO::FETCH_ASSOC))
        {
            $trace = new Tracepoint($row);

            switch ($trace->GetName()) {
                case 'PHP_PHP:execute_primary_script_start':
                    $this->SetTimeStart($trace->GetCPUTime());
                    break;
                case 'PHP_Zend:compile_start':
                    $compileStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:compile_finish':
                    $compileFinishTime = $trace->GetCPUTime();

                    if (empty($compileStartTime))
                        throw new ErrorException('empty($compileStartTime)');
                    $time = $compileFinishTime - $compileStartTime;
                    if ($time < 10)
                        throw new ErrorException('zend_compile time < 10 for '. $trace->GetConfiguration() .'/'. $trace->GetTimestamp());

                    $this->AddData("zend_compileTime", $time);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zend_compile_time = ".$this->GetData("zend_compileTime")." }\n";
                    break;
                case 'PHP_Zend:execute_start':
                    $executeStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:execute_finish':
                    $executeFinishTime = $trace->GetCPUTime();

                    if (empty($executeStartTime))
                        throw new ErrorException('empty($executeStartTime)');
                    $time = $executeFinishTime - $executeStartTime;
                    if ($time < 10)
                        throw new ErrorException('zend_execute time < 10 for '. $trace->GetConfiguration() .'/'. $trace->GetTimestamp());

                    $this->AddData("zend_executeTime", $time);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zend_execute_time = ".$this->GetData("zend_executeTime")." }\n";
                    break;
                case 'PHP_PHP:execute_primary_script_finish':
                    $this->SetTimeFinish($trace->GetCPUTime());
                    break;
            }
        }
    }

    public function GetZendCompileTime()
    { return $this->GetData("zend_compileTime"); }

    public function GetZendExecuteTime()
    { return $this->GetData("zend_executeTime"); }
}

?>
