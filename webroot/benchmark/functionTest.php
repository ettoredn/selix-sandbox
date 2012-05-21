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
				              'PHP_Zend:vm_user_fcall_start',
				              'PHP_Zend:vm_user_fcall_finish',
				              'PHP_Zend:vm_leave_nested',
				              'PHP_Zend:vm_internal_fcall_start',
				              'PHP_Zend:vm_internal_fcall_finish',
				              'PHP_PHP:execute_primary_script_finish')
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() < 6) throw new ErrorException("Query or data error: $q");

        $userCalls = array();
        $internalCalls = array();
        while ($row = $r->fetch(PDO::FETCH_ASSOC))
        {
            $trace = new Tracepoint($row);

            switch ($trace->GetName()) {
                case 'PHP_PHP:execute_primary_script_start':
                    $this->SetTimeStart($trace->GetCPUTime());
                    break;
                case 'PHP_Zend:vm_user_fcall_start':
                    if ($trace->GetArgumentString("name") == "first")
                        $firstUserCallStartTime = $trace->GetCPUTime();
                    else
                        $userCallStartTime = $trace->GetCPUTime();
                    break;
                case 'PHP_Zend:vm_user_fcall_finish':
                    if ($trace->GetArgumentString("name") == "first")
                    {
                        $firstUserCallFinishTime = $trace->GetCPUTime();

                        if (empty($firstUserCallStartTime))
                            throw new ErrorException('empty($firstUserCallStartTime)');
                        $delta = $firstUserCallFinishTime - $firstUserCallStartTime;
                        if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_firstUserFcallTime < 1\n";

                        $this->AddData("zendvm_firstUserFcallTime", $delta);

                        if ($GLOBALS['verbose'])
                            echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                    ", test = ".$this->GetName().", zendvm_firstUserFcallTime = ".$this->GetData("zendvm_firstUserFcallTime")." }\n";
                    }
                    else
                    {
                        $userCallFinishTime = $trace->GetCPUTime();

                        if (empty($userCallStartTime))
                            throw new ErrorException('empty($userCallStartTime)');
                        $delta = $userCallFinishTime - $userCallStartTime;
                        if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_userFcallTime < 1\n";

                        $userCalls[] = $delta;

                        if ($GLOBALS['verbose'])
                            echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                    ", test = ".$this->GetName().", userCall = $delta }\n";
                    }
                    break;
                case 'PHP_Zend:vm_leave_nested':
                    if ($trace->GetArgumentString("function_name") == "first")
                    {
                        $vmLeaveNested = $trace->GetCPUTime();

                         if (empty($firstUserCallStartTime))
                             throw new ErrorException('empty($firstUserCallStartTime)');
                         $delta = $vmLeaveNested - $firstUserCallStartTime;
                         if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_firstUserFcallTime < 1\n";

                         $this->AddData("zendvm_firstUserFcallTime", $delta);

                         if ($GLOBALS['verbose'])
                             echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                     ", test = ".$this->GetName().", zendvm_firstUserFcallTime = ".$this->GetData("zendvm_firstUserFcallTime")." }\n";
                    }
                    else
                    {
                        $vmLeaveNested = $trace->GetCPUTime();

                        if (empty($userCallStartTime))
                            throw new ErrorException('empty($userCallStartTime)');
                        $delta = $vmLeaveNested - $userCallStartTime;
                        if ($delta < 1) echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] WARNING: zendvm_userFcallTime < 1\n";

                        $userCalls[] = $delta;

                        if ($GLOBALS['verbose'])
                            echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                    ", test = ".$this->GetName().", userCall = $delta }\n";
                    }
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

                    $internalCalls[] = $delta;

                    if ($GLOBALS['verbose'])
                        echo "[".$trace->GetSession()."/".$trace->GetConfiguration()."] { timestamp = ".$trace->GetTimestamp().
                                ", test = ".$this->GetName().", internalCall = $delta }\n";
                    break;
                case 'PHP_PHP:execute_primary_script_finish':
                    $this->SetTimeFinish($trace->GetCPUTime());
                    break;
            }
        }

        $avg = new AverageNumeric($userCalls);
        $this->AddData("zendvm_userFcallTime", $avg->Median());
        $avg = new AverageNumeric($internalCalls);
        $this->AddData("zendvm_internalFcallTime", $avg->Median());
    }

    public function GetZendVMInternalFunctionCallTime()
    { return $this->GetData("zendvm_internalFcallTime"); }

    public function GetZendVMFirstUserFunctionCallTime()
    { return $this->GetData("zendvm_firstUserFcallTime"); }

    public function GetZendVMUserFunctionCallTime()
    { return $this->GetData("zendvm_userFcallTime"); }

}

?>
