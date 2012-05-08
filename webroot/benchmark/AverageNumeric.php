<?php

class AverageNumeric
{
    const PRECISION = 6;

    private $values;
    private $sortedValues;
    // Cached results
    private $numValues;
    private $sum;
    private $median;
    private $quartiles;
    private $iqr;

    public function __construct($values)
    {
        if (!is_array($values) || count($values) < 1)
            throw new ErrorException('!is_array($values) || count($values) < 1');

        $this->values = $values;
    }

    public function GetValues( $sort = false )
    {
        if (!is_array($this->values) || count($this->values) < 1)
            throw new ErrorException('!is_array($this->values) || count($this->values) < 1');

        if ($sort)
        {
            // Check for cached result
            if (!isset($this->sortedValues))
            {
                // Copy values in a new array
                $copy = $this->GetValues();

                if (!sort($copy, SORT_NUMERIC))
                    throw new ErrorException('!sort($copy, SORT_NUMERIC)');
                $this->sortedValues = $copy;
            }

            return $this->sortedValues;
        }

        return $this->values; // Returns a copy
    }

    protected function Count()
    {
        // Check for cached result
        if (!isset($this->numValues))
            $this->numValues = count($this->GetValues());

        return $this->numValues;
    }

    /*
     * Returns the sum of all values.
     */
    protected function Sum()
    {
        // Check for cached result
        if (!isset($this->sum))
        {
            $sum = 0.0;
            foreach($this->GetValues() as $v)
                $sum = bcadd($v, $sum, self::PRECISION);
            $this->sum = $sum;
        }

        return $this->sum;
    }

    /*
     * http://en.wikipedia.org/wiki/Median
     * $data is an array containing *sorted* values for which the median is calculated.
     */
    protected function GetMedianOf( $data )
    {
        $count = count($data);
        if ($count == 0)
            throw new ErrorException('$count == 0');

        $middle = $count / 2;
        if ($count < 2)
            return $data[0];
        elseif (is_int($middle))
            return bcdiv(($data[$middle-1] + $data[$middle]), 2, self::PRECISION);
        else
            return bcadd($data[floor($middle)], 0, self::PRECISION); // convert to string for uniformity with bcmath
    }
    public function Median()
    {
        // Check for cached result
        if (!isset($this->median))
            $this->median = $this->GetMedianOf($this->GetValues(true));

        return $this->median;
    }

    /*
     * Uses Hyndman and Fan method.
     * $data must be already *sorted*
     * http://www.nesug.org/proceedings/nesug07/po/po08.pdf
     * http://www.amherst.edu/media/view/129116/original/Sample%2BQuantiles.pdf
     */
    protected function GetQuartilesOf( $data )
    {
        $count = count($data);
        if ($count < 4)
            throw new ErrorException('$count < 4');

        if ($count == 4)
            return array("".$data[1], $this->GetMedianOf($data), "".$data[2]); // convert to string for uniformity with bcmath

        $lq = ($count + 2) / 4;
        $uq = ($count * 3 + 2) / 4;
        $lq_int = floor($lq); $lq_dec = $lq - $lq_int;
        $uq_int = floor($uq); $uq_dec = $uq - $uq_int;
        $dataLQ = bcadd(
            bcmul(1 - $lq_dec, $data[$lq_int-1], self::PRECISION),
            bcmul($lq_dec, $data[$lq_int], self::PRECISION), // $data[$lq_int-1+1]
            self::PRECISION
        );
        $dataUQ = bcadd(
            bcmul(1 - $uq_dec, $data[$uq_int-1], self::PRECISION),
            bcmul($uq_dec, $data[$uq_int], self::PRECISION), // $data[$uq_int-1+1]
            self::PRECISION
        );

//        echo "LQ: $lq_int + $lq_dec\n";
//        echo "UQ: $uq_int + $uq_dec\n";
//        echo "YLQ_OP1: ". bcmul(1 - $lq_dec, $data[$lq_int-1], self::PRECISION) ."\n";
//        echo "YLQ_OP2: ". bcmul($lq_dec, $data[$lq_int], self::PRECISION) ."\n";
//        echo "YUQ_OP1: ". bcmul(1 - $uq_dec, $data[$uq_int-1], self::PRECISION) ."\n";
//        echo "YUQ_OP2: ". bcmul($uq_dec, $data[$uq_int], self::PRECISION) ."\n";
//        echo "YLQ: $dataLQ\n";
//        echo "YUQ: $dataUQ\n\n";

        return array($dataLQ, $this->GetMedianOf($data), $dataUQ);
    }
    /*
     * Returns array( first, median, third )
     */
    public function Quartiles()
    {
        // Check for cached result
        if (!isset($this->quartiles))
            $this->quartiles = $this->GetQuartilesOf($this->GetValues(true));

        return $this->quartiles;
    }

    /*
     * http://en.wikipedia.org/wiki/Interquartile_range
     */
    public function IQR()
    {
        // Check for cached result
        if (!isset($this->iqr))
        {
            $quarts = $this->Quartiles();
            $this->iqr = bcsub($quarts[2], $quarts[0], self::PRECISION);
        }

        return $this->iqr;
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

class AverageNumericTest extends AverageNumeric
{
    public static function Test( $times = 1)
    {
        $data0 = array( 6, 47, 49, 15, 42, 41, 7, 39, 43, 40, 36 );
        $data1 = array( 6, 7, 15, 36, 39, 40, 41, 42, 43, 47, 49 ); // Same as 0 but sorted
        $data2 = array( 85,70,17,72,99,45,99,35,2,41,72,38,95,0,91,27,83,98,35,0,91,65,60,27,35,64,99,74,15,45,22,96,90,32,80,42,51,84,11, 99);
        $count0 = count($data0);
        $count1 = count($data1);
        $count2 = count($data2);
        $sort0 = $data0; sort($sort0, SORT_NUMERIC);
        $sort1 = $data1; sort($sort1, SORT_NUMERIC);
        $sort2 = $data2; sort($sort2, SORT_NUMERIC);
        echo "Dataset 0 ($count0): "; foreach ($sort0 as $v) echo "$v,"; echo "\n";
        echo "Dataset 1 ($count1): "; foreach ($sort1 as $v) echo "$v,"; echo "\n";
        echo "Dataset 2 ($count2): "; foreach ($sort2 as $v) echo "$v,"; echo "\n\n";

        $avg1 = new AverageNumeric($data0);
        $avg2 = new AverageNumeric($data1);
        $avg3 = new AverageNumeric($data2);

        for ($i=0; $i<$times; $i++)
        {
            // Test GetValues()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array($data0, $data1, $data2),
                "GetValues",
                array()
            );
            // Test Count()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array($count0, $count1, $count2),
                "Count",
                array()
            );
            // Test GetValues( true )
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array($sort0, $sort1, $sort2),
                "GetValues",
                array(true)
            );
            // Test Sum()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array("365.000000", "365.000000", "2286.000000"),
                "Sum",
                array()
            );
            // Test Median()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array("40.000000", "40.000000", "62.000000"),
                "Median",
                array()
            );
            // Test Quartiles()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array(
                    array("20.250000", "40.000000", "42.750000"),
                    array("20.250000", "40.000000", "42.750000"),
                    array("33.500000", "62.000000", "87.500000")
                ),
                "Quartiles",
                array()
            );
            // Test IQR()
            self::TestMethod(
                array($avg1, $avg2, $avg3),
                array("22.500000", "22.500000", "54.000000"),
                "IQR",
                array()
            );
        }
    }

     private static function TestMethod( $averages, $expected, $method, $args )
     {
         foreach ($averages as $index => $avg)
         {
             if (!is_callable(array($avg, $method)))
                 throw new ErrorException('!is_callable(array($b, $method))');

             $avgResult = call_user_func(array($avg, $method), $args);

             if ($expected[$index] === $avgResult)
                 echo "->$method( $index ) OK\n";
             else{
                 echo "->$method( $index ) FAILED\n";
                 echo "== Expected ==\n";
                 print_r($expected[$index]);
                 echo "\n== Result ==\n";
                 print_r($avgResult);
                 echo "\n";
             }
         }
     }
}

//AverageNumericTest::Test(3);
