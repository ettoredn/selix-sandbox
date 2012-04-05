<?php

abstract class Test
{
    private $name;
    private $timestampStart;
    private $timestampFinish;
    private $timeStart;
    private $timeFinish;
    // Test implementation's data
    private $data;

    public function __construct( $start, $finish )
    {
        if (empty($start) || empty($finish))
            throw new ErrorException('empty($start) || empty($finish)');

        $this->name = substr(get_class($this), 0, -4);
        $this->timestampStart = $start;
        $this->timestampFinish = $finish;
    }

    public function GetName()
    { return $this->name; }

    public function GetTimestampStart()
    { return $this->timestampStart; }

    public function GetTimestampFinish()
    { return $this->timestampFinish; }

    protected function SetTimeStart( $time )
    { $this->timeStart = $time; }

    protected function SetTimeFinish( $time )
    { $this->timeFinish = $time; }

    /*
     * Returns test script execution time (i.e. zend_execute_scripts).
     */
    public function GetExecutionTime()
    { return $this->timeFinish - $this->timeStart; }

    protected function AddData( $key, $value )
    { $this->data[$key] = $value; }

    protected function GetData( $key )
    { return $this->data[$key]; }

    protected function GetDataKeys()
    { return array_keys( $this->data ); }

    public abstract function LoadFromTable( $table );
}

?>
