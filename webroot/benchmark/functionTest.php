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
	            AND `name` IN('PHP_PHP:execute_primary_script_start',
				              'PHP_Zend:scripts_compile_start',
				              'PHP_Zend:scripts_compile_finish',
				              'PHP_Zend:scripts_execute_start',
				              'PHP_Zend:vm_user_fcall_start',
				              'PHP_Zend:vm_user_fcall_finish',
				              'PHP_Zend:vm_leave_nested',
				              'PHP_Zend:vm_internal_fcall_start',
				              'PHP_Zend:vm_internal_fcall_finish',
				              'PHP_Zend:scripts_execute_finish',
				              'PHP_PHP:execute_primary_script_finish')
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() != 10) throw new ErrorException("Query or data error: $q");

        while ($row = $r->fetch(PDO::FETCH_ASSOC))
        {
            $trace = new Tracepoint($row);

            switch ($trace->GetName()) {
                case 'PHP_PHP:execute_primary_script_start':
                    $this->SetTimeStart($trace->GetCPUTime());
                    break;
                case 'PHP_Zend:scripts_compile_start':
                    $compileStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:scripts_compile_finish':
                    $compileFinishTime = $trace->GetCPUTime();

                    if (empty($compileStartTime))
                        throw new ErrorException('empty($compileStartTime)');
                    $delta = $compileFinishTime - $compileStartTime;
                    if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zend_compileTime < 1\n";

                    $this->AddData("zend_compileTime", $delta);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zend_compileTime = ".$this->GetData("zend_compileTime")." }\n";
                    break;
                case 'PHP_Zend:scripts_execute_start':
                    $executeStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:vm_user_fcall_start':
                    $userCallStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:vm_user_fcall_finish':
                    $userCallFinishTime = $trace->GetCPUTime();

                    if (empty($userCallStartTime))
                        throw new ErrorException('empty($userCallStartTime)');
                    $delta = $userCallFinishTime - $userCallStartTime;
                    if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_userFcallTime < 1\n";

                    $this->AddData("zendvm_userFcallTime", $delta);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zendvm_userFcallTime = ".$this->GetData("zendvm_userFcallTime")." }\n";
                    break;
                case 'PHP_Zend:vm_leave_nested':
                    $vmLeaveNested = $trace->GetCPUTime();

                    if (empty($userCallStartTime))
                        throw new ErrorException('empty($userCallStartTime)');
                    $delta = $vmLeaveNested - $userCallStartTime;
                    if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_userFcallTime < 1\n";

                    $this->AddData("zendvm_userFcallTime", $delta);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zendvm_userFcallTime = ".$this->GetData("zendvm_userFcallTime")." }\n";
                    break;
                case 'PHP_Zend:vm_internal_fcall_start':
                    $internalCallStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:vm_internal_fcall_finish':
                    $internalCallFinishTime = $trace->GetCPUTime();

                    if (empty($internalCallStartTime))
                        throw new ErrorException('empty($internalCallStartTime)');
                    $delta = $internalCallFinishTime - $internalCallStartTime;
                    if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_internalFcallTime < 1\n";

                    $this->AddData("zendvm_internalFcallTime", $delta);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zendvm_internalFcallTime = ".$this->GetData("zendvm_internalFcallTime")." }\n";
                    break;
                case 'PHP_Zend:scripts_execute_finish':
                    $executeFinishTime = $trace->GetCPUTime();

                    if (empty($executeStartTime))
                        throw new ErrorException('empty($executeStartTime)');
                    $delta = $executeFinishTime - $executeStartTime;
                    if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zend_executeTime < 1\n";

                    $this->AddData("zend_executeTime", $delta);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", zend_executeTime = ".$this->GetData("zend_executeTime")." }\n";
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

    public function GetZendVMInternalFunctionCallTime()
    { return $this->GetData("zendvm_internalFcallTime"); }

    public function GetZendVMUserFunctionCallTime()
    { return $this->GetData("zendvm_userFcallTime"); }

}

?>
