<?php

abstract class PlotStyle
{
    abstract public function GetRawCommands();
}

class ClusteredHistogramPlotStyle extends PlotStyle
{
    public function GetRawCommands()
    {
        return '
            set style histogram clustered gap 2
            set style data histograms
            set style fill solid 0.5
            set xtics border nomirror out
        ';
    }
}

class PointsPlotStyle extends PlotStyle
{
    private $pointSize;

    public function __construct( $size = 1 )
    {
        if (!empty($size))
            $this->pointSize = $size;
    }

    public function GetRawCommands()
    {
        return '
            set pointsize '. $this->pointSize .'
            set style data points
        ';
    }
}
