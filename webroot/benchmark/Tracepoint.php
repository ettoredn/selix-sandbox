<?php
class Tracepoint
{
    private $session;
    private $configuration;
    private $timestamp;
    private $delta; //useless
    private $loglevel;
    private $name;
    private $args;

    function __construct( $trace )
    {
        if (!is_array($trace) || count($trace) != 7)
            throw new ErrorException('!is_array($trace) || count($trace) != 7)');

        $this->session = $trace['session'];
        $this->configuration = $trace['configuration'];
        $this->timestamp = $trace['timestamp'];
        $this->delta = $trace['delta'];
        $this->loglevel = $trace['loglevel'];
        $this->name = $trace['name'];
        $this->args = $trace['args'];
    }

    public function GetSession()
    { return $this->session; }

    public function GetConfiguration()
    { return $this->configuration; }

    public function GetTimestamp()
    { return $this->timestamp; }

    public function GetLoglevel()
    { return $this->loglevel; }

    public function GetName()
    { return $this->name; }

    public function GetCPUTime()
    { return $this->GetArgument("cputime"); }

    public function GetArgument( $name )
    {
        if (empty($name))
            throw new ErrorException('empty($name)');

        $str = strstr($this->args, "$name =");
        if ($str === FALSE)
            throw new ErrorException('$str === FALSE');
        $clever = explode(",", $str);
//        print_r( $clever );
        $clever = explode(" = ", $clever[0]);
//        print_r( $clever[1] );
        return $clever[1];
    }

    public function GetArgumentString( $name )
    {
        return trim($this->GetArgument($name), '"');
    }
}
