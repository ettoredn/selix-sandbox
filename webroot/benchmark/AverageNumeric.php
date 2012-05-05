<?php

class AverageNumeric
{
    const PRECISION = 6;

    private $values;
    private $numValues;
    private $sum;
    private $mean;
    private $stddev;
    private $stderr;
    private $stderrPercent;

    public function __construct($values)
    {
        if (!is_array($values) || count($values) < 1)
            throw new ErrorException('!is_array($values) || count($values) < 1');

        $this->values = $values;
    }

    protected function GetValues()
    {
        if (!is_array($this->values) || count($this->values) < 1)
            throw new ErrorException('!is_array($this->values) || count($this->values) < 1');

        return $this->values;
    }

    protected function CountValues()
    {
        if (!isset($this->numValues))
            $this->numValues = count($this->GetValues());

        return $this->numValues;
    }

    /*
     * Returns the sum of all values.
     */
    protected function GetSum( $power = 1 )
    {
        if (!isset($this->sum[$power]))
        {
            $sum = 0.0;
            foreach($this->GetValues() as $v)
                $sum = bcadd(bcpow($v, $power, self::PRECISION), $sum, self::PRECISION);
            $this->sum[$power] = $sum;
        }

        return $this->sum[$power];
    }

    /*
     * http://en.wikipedia.org/wiki/Arithmetic_mean
     */
    public function GetMean()
    {
        if (!isset($this->mean))
            $this->mean = bcdiv($this->GetSum(), $this->CountValues(), self::PRECISION);

        return $this->mean;
    }

    /*
     * http://en.wikipedia.org/wiki/Standard_deviation
     */
    public function GetStandardDeviation()
    {
        if (!isset($this->stddev))
        {
            $a = bcdiv($this->GetSum(2), $this->CountValues(), self::PRECISION);
            $b = bcpow( bcdiv($this->GetSum(), $this->CountValues(), self::PRECISION), 2, self::PRECISION);
            $sub = bcsub($a, $b, self::PRECISION);
            $this->stddev = bcsqrt( abs($sub), self::PRECISION);
        }

        return $this->stddev;
    }

    /*
     * http://en.wikipedia.org/wiki/Standard_error
     */
    public function GetStandardError()
    {
        if (!isset($this->stderr))
        {
            $d = bcsqrt($this->CountValues(), self::PRECISION);
            $this->stderr = bcdiv($this->GetStandardDeviation(), $d, self::PRECISION);
        }

        return $this->stderr;
    }

    /*
     * http://en.wikipedia.org/wiki/Relative_standard_error
     */
    public function GetRelativeStandardError()
    {
        if (!isset($this->stderrPercent))
            $this->stderrPercent = bcmul(bcdiv($this->GetStandardError(), $this->GetMean(), self::PRECISION), 100, self::PRECISION);

        return $this->stderrPercent;
    }

    /*
     * Writes all values into the given file.
     */
    public function WriteValuesToFile( $filename )
    {
        $h = fopen($filename, "w");
        if (!$h)
            throw new ErrorException("Cannot open file $filename");

        foreach($this->GetValues() as $v)
            fwrite($h, "$v\n");

        fclose($h);
    }
}
