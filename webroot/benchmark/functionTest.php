<?php
require_once("Database.php");
require_once("Test.php");

class functionTest extends Test
{
    public function LoadFromTable( $table )
    {
        $data = NULL;
        $q = "SELECT *
              FROM $table
              WHERE `timestamp` > ". $this->GetStartTimestamp() ." AND `timestamp` < ". $this->GetFinishTimestamp() ."
              ORDER BY timestamp ASC";
        $r = Database::GetConnection()->query($q);
        if (!$r || $r->rowCount() != 4) throw new ErrorException("Query or data error: $q");

        while ($trace = $r->fetch())
        {
            switch ($trace['name']) {
                case 'PHP_Zend:compile_finish':
                    $this->AddData("zend_compileTime", $trace['delta']);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace['session']."/".$trace['configuration']."] { timestamp = ".$trace['timestamp'].
                                ", test = ".get_class($this).", zend_compile_time = ".$trace['delta']." }\n";
                    break;
                case 'PHP_Zend:execute_finish':
                    $this->AddData("zend_executeTime", $trace['delta']);

                    if ($GLOBALS['verbose'])
                        echo "[".$trace['session']."/".$trace['configuration']."] { timestamp = ".$trace['timestamp'].
                                ", test = ".get_class($this).", zend_execute_time = ".$trace['delta']." }\n";
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
