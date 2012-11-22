<?php
/**
 * Created by JetBrains PhpStorm.
 * @author: pomaxa none <pomaxa@gmail.com>
 * @date: 10/19/12
 */

namespace Pmx\Bundle\RrdBundle\Component;

use RuntimeException;
use Symfony\Component\DependencyInjection\Container;

class PmxRrdGraph
{
    public $filename;

    public $title;
    public $verticalLabel;
    public $lowerLimit = null;
    public $start = "-14d";
    public $end = "now";

    //todo: maybe group them into one array?
    public $defs = array();
    public $cdefs = array();
    public $vdefs = array();

    public $displayDataRules = array();

    public $graphWidth = 400;
    public $graphHeight = 100;

    public $onlyGraph = false;
    public $fullSizeMode = false;
    public $aliases = '';
    public $imagePath;
    public $imageName = null;

    private $container = null;

    /**
     * @param $dbLocation
     * @param $imageLocation
     */
    public function __construct($dbLocation = null, $imageLocation = null, $webRoot = null, Container $container)
    {
        $this->dbPath = $dbLocation;
        $this->imagePath = $imageLocation;
        $this->webRoot = $webRoot;
        $this->container = $container;
    }

    protected function getDataSourceFromDb($filename)
    {
        $rrdInfo = new PmxRrdInfo($filename);

        return $rrdInfo->getDSNames();
    }

    public function setTitle($text)
    {
        $this->title = $text;

        return $this;
    }

    public function setImagePath($imagePath)
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getImagePath()
    {
        return $this->imagePath;
    }

    public function setVerticalLabel($text)
    {
        $this->verticalLabel = $text;

        return $this;
    }

    public function setLowerLimit($lowerLimit = 0)
    {
        $this->lowerLimit = $lowerLimit;

        return $this;
    }

    public function setStart($start = '-14d')
    {
        $this->start = $start;

        return $this;
    }

    public function setEnd($end = 'now')
    {
        $this->end = $end;

        return $this;
    }

    /**
     * @param string $filename Absolute file location path. please.
     * @return PmxRrdGraph
     */
    public function setFileName($filename)
    {
        $this->filename = $filename;

        return $this;
    }

    public function setImageName($imageName)
    {
        $this->imageName = $imageName;

        return $this;
    }

    public function getImageName()
    {
        if (null == $this->imageName) {
            $info = pathinfo($this->filename);
            $this->imageName = $info['filename'];
        }

        return $this->imageName;
    }

    public function getImageUrl()
    {
        return sprintf('%s%s/%s.png', $this->container->get('request')->getBasePath(), $this->imagePath, $this->getImageName());
    }

    public function setGraphWidth($width)
    {
        $this->graphWidth = $width;

        return $this;
    }

    public function setGraphHeight($height)
    {
        $this->graphHeight = $height;

        return $this;
    }

    public function setFullSizeMode($fullSizeMode)
    {
        $this->fullSizeMode = $fullSizeMode;

        return $this;
    }

    //graph display definition functions

    /**
     * @param $varName
     * @param $legend
     * @param int $hexColor
     * @param int $width
     * @param bool $stack
     * @return PmxRrdGraph
     */
    public function line($varName, $legend, $hexColor = 380470, $width = 2, $stack = false)
    {
        if ($stack) {
            $stack = ':STACK';
        } else {
            $stack = '';
        }

        $this->displayDataRules[] = "LINE$width:$varName#$hexColor:$legend" . $stack;

        return $this;
    }

    /**
     * @param $varName
     * @param $offsetTime
     * @return PmxRrdGraph
     */
    public function shift($varName, $offsetTime)
    {
        $this->displayDataRules[] = "SHIFT:$varName:$offsetTime";

        return $this;
    }

    /**
     * @param $varName
     * @param $legend
     * @param int $hexColor
     * @param bool $stack
     * @return PmxRrdGraph
     */
    public function area($varName, $legend, $hexColor = 380470, $stack = false)
    {
        if ($stack) {
            $stack = ':STACK';
        } else {
            $stack = '';
        }

        $this->displayDataRules[] = "AREA:$varName#$hexColor:$legend" . $stack;

        return $this;
    }

    /**
     * TICK:имя-переменной#rrggbbaa[:доля-от-оси-Y[:легенда]] (вертикальные засечки на месте каждого определённого и ненулевого значения переменной)
     * @param $varName
     * @param int $hexColor
     * @param null $legend
     * @param null $yAxeTick
     * @return PmxRrdGraph
     */
    public function tick($varName, $hexColor = 001122, $legend = null, $yAxeTick = null)
    {
        if (!empty($yAxeTick)) {
            $suffix = ":$yAxeTick:$legend";
        } else {
            $suffix = '';
        }

        $this->displayDataRules[] = "TICK:$varName#$hexColor" . $suffix;

        return $this;
    }

    //end graph display definition functions

    // data definition

    /**
     * @param string $varName
     * @param string $dsName
     * @param string $consolidationFunction
     * @param null $fileName
     * @param null $step
     * @param null $start
     * @param null $end
     * @param null $reduceConsolidationFunction
     * @return PmxRrdGraph
     * @throws \RuntimeException
     */
    public function addDef($varName, $dsName, $consolidationFunction = 'AVERAGE', $fileName = null, $step = null, $start = null, $end = null, $reduceConsolidationFunction = null)
    {
        if (empty($fileName)) {
            $fileName = $this->filename;
        }

        if (!file_exists($fileName)) {
            throw new RuntimeException('FUck Ya! try to use existed db');
        }

        if (!empty($start)) {
            $start = ":start=$start";
        }

        if (!empty($end)) {
            $end = ':end=' . $end;
        }

        if (empty($end) && !empty($start)) {
            $end = ':end=now';
        }

        //check if this DS exist in selected database file.
        if (!in_array($dsName, $this->getDataSourceFromDb($fileName))) {
            throw new RuntimeException('Fyuck Ya! try to use existed DS');
        }

        $this->defs[] = "DEF:$varName=$fileName:$dsName:$consolidationFunction" . $end . $start;

        return $this;
    }

    /**
     * @param string $varName
     * @param string $reversePolishNotation
     * @return PmxRrdGraph
     */
    public function addCDef($varName, $reversePolishNotation)
    {
        //todo: validate $varName
        $this->cdefs[] = "CDEF:$varName=$reversePolishNotation";
        return $this;
    }

    /**
     * @param string $varName
     * @param string $reversePolishNotation
     * @return PmxRrdGraph
     */
    public function addVDef($varName, $reversePolishNotation)
    {
        //todo: validate $varName
        $this->vdefs[] = "VDEF:$varName=$reversePolishNotation";
        return $this;
    }

    // end data definition

    public function getOptions()
    {
        $opt = array(
            '--start',
            $this->start,
            '--end=' . $this->end,
            '--width=' . $this->graphWidth,
            '--height=' . $this->graphHeight,
        );

        if ($this->fullSizeMode) {
            $opt[] = '-D';
        }

        if ($this->onlyGraph) {
            $opt[] = '--only-graph';
        }

        if (null !== $this->title) {
            $opt[] = '--title=' . $this->title;
        }

        if (null !== $this->verticalLabel) {
            $opt[] = '--vertical-label=' . $this->verticalLabel;
        }

        if (null !== $this->lowerLimit) {
            $opt[] = '--lower-limit=' . $this->lowerLimit;
        }

        foreach ($this->defs as $def) {
            $opt[] = $def;
        }

        foreach ($this->cdefs as $def) {
            $opt[] = $def;
        }

        foreach ($this->vdefs as $def) {
            $opt[] = $def;
        }

        foreach ($this->displayDataRules as $lines) {
            $opt[] = $lines;
        }

        return $opt;
    }

    public function doDraw()
    {
        $outputFileName = sprintf('%s%s/%s.png', $this->webRoot, $this->imagePath, $this->getImageName());
        $parameters = rrd_graph($outputFileName, $this->getOptions());

        if (false !== $error = rrd_error()) {
            throw new RuntimeException(sprintf('rrd_graph() ERROR: %s', $error));
        }

        return $parameters;
    }
}



