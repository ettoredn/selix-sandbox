<?php

abstract class Test
{
    private $name;
    private $startTimestamp;
    private $finishTimestamp;
    private $execTime;
    // Test implementation's data
    private $data;

    public function __construct( $start, $finish )
    {
        if (empty($start) || empty($finish))
            throw new ErrorException('empty($start) || empty($finish)');

        $this->name = substr(get_class($this), 0, -4);
        $this->startTimestamp = $start;
        $this->finishTimestamp = $finish;
    }

    public function GetName()
    { return $this->name; }

    public function GetStartTimestamp()
    { return $this->startTimestamp; }

    public function GetFinishTimestamp()
    { return $this->finishTimestamp; }

    /*
     * Returns test script execution time (i.e. zend_execute_scripts).
     */
    public function GetExecutionTime()
    {
        if (empty($this->execTime))
            $this->execTime = bcsub($this->finishTimestamp, $this->startTimestamp, 9);

        return $this->execTime;
    }

    protected function AddData( $key, $value )
    { $this->data[$key] = $value; }

    protected function GetData( $key )
    { return $this->data[$key]; }

    protected function GetDataKeys()
    { return array_keys( $this->data ); }

    public abstract function LoadFromTable( $table );
}

?>
