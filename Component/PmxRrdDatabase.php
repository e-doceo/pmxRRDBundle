<?php

namespace Pmx\Bundle\RrdBundle\Component;

use JMS\AopBundle\Exception\RuntimeException;
use Pmx\Bundle\RrdBundle\Component\DSType;
use Pmx\Bundle\RrdBundle\Component\RRAConsolidationFunction;

class PmxRrdDatabase
{
    /**
     * для получения текущего значения отсчета предыдущее значение счетчика
     * вычитается из текущего и делится на интервал между отсчетами
     * (например, счетчик переданных байт для измерения скорости).
     * Переполнение счетчика обрабатывается только для типа COUNTER.
     * Счетчики могут хранить только целые 32-х или 64-х битные числа
     */

    /** @var array DataSource */
    public $dsa = array();
    public $rraa = array();

    public $path;
    public $dbname;
    /** @var int step in seconds */
    public $step = 300;

    /** @var array of data,to update with timestamp as key */
    public $dataToUpdate = array();

    /**
     * Can be string or unix_timestamp
     *
     * @var string start date of database
     */
    public $start = 'now';

    /**
     * @param string $dbname database name(same as filename)
     * @param string $path Location of rrdDatabase
     */
    public function __construct($dbname = 'test.rrd', $path = null)
    {
        $this->mkpath($path);
        $this->path = $path;

        if (!empty($dbname)) {
            $this->setDbName($dbname);
        }
    }

    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @param $path
     * @return bool
     */
    protected function mkpath($path)
    {
        if (@mkdir($path) or file_exists($path)) {
            return true;
        }

        return ($this->mkpath(dirname($path)) and mkdir($path));
    }

    /**
     * @param string $dbname
     * @return PmxRrdDatabase
     */
    public function setDbName($dbname)
    {
        if (substr($dbname, -4) != '.rrd') {
            $dbname = $dbname . '.rrd';
        }

        $this->dbname = $dbname;

        return $this;
    }

    public function getDatabaseName()
    {
        return $this->path . '/' . $this->dbname;
    }

    /**
     * @param $dbFileName
     * @param array $options
     * @return PmxRrdDatabase
     */
    public function tune($dbFileName, array $options)
    {
        //todo: simple setters for most features.
        rrd_tune($dbFileName, $options);

        return $this;
    }

    /**
     * Write down  file
     *
     * @param bool $overwriteFile
     * @return PmxRrdDatabase
     * @throws \JMS\AopBundle\Exception\RuntimeException
     * @throws \Exception
     */
    public function create($overwriteFile = false)
    {
        if (file_exists($this->getDatabaseName())) {
            if ($overwriteFile == false) {
                throw new RuntimeException('Database with filename = ' . $this->getDataBaseName() . ' already exist.');
            } else {
                unlink($this->getDatabaseName());
            }
        }

        if (count($this->dsa) < 1) {
            throw new RuntimeException('Database must have at least one DataSource ');
        }

        if (count($this->rraa) < 1) {
            throw new RuntimeException('Database must have at least one RRA');
        }

        $opts = array(
            "--step",
            $this->step,
            "--start",
            $this->start,
        );

        foreach ($this->dsa as $ds) {
            $opts[] = $ds;
        }

        foreach ($this->rraa as $rra) {
            $opts[] = $rra;
        }

        //try to create db file
        $ret = rrd_create($this->getDatabaseName(), $opts);

        if ($ret == 0) {
            $err = rrd_error();
            throw new \Exception("Create error: $err\n");
        }

        return $this;
    }

    /**
     * @param $dataSource
     * @param $value
     * @param null|timestamp $time
     * @return PmxRrdDatabase
     */
    public function update($dataSource, $value, $time = null)
    {
        if (empty($time)) {
            $time = time();
        }

        $this->dataToUpdate[$this->getDatabaseName()][$time] = array($dataSource => $value);

        return $this;
    }

    /**
     * Actually update database
     *
     * @return PmxRrdDatabase
     * @throws \RuntimeException
     */
    public function doUpdate()
    {
        $updater = new \RRDUpdater($this->getDatabaseName());

        foreach ($this->dataToUpdate as $dbName => $data) {
            if ($dbName != $this->getDatabaseName()) {
                $updater = new \RRDUpdater($dbName);
            }

            foreach ($data as $timestamp => $value) {
                $isOk = $updater->update($value, $timestamp);

                if (!$isOk) {
                    throw new \RuntimeException('Ebala');
                }
            }
        }

        return $this;
    }

    /**
     * @param $name
     * @param DSType $type
     * @param $heartbeat interval; by default it is calculated as 2*step
     * @param string $min Default U, if defined, then any value lower then defined, will be ignored in calculations
     * @param string $max Default U, if defined, then any value grater then defined, will be ignored in calculations
     * @return PmxRrdDatabase
     * @throws \RuntimeException
     */
    public function addDataSource($name, $type, $heartbeat = null, $min = 'U', $max = 'U')
    {
        if (empty($heartbeat)) {
            $heartbeat = $this->step * 2;
        }

        if (strlen($name) > 19) {
            throw new \RuntimeException('Data source name can\'t be longer then 19 symbols');
        }
        /**
        DS:имя_источника:тип_источника:интервал_определенности:min:max \
         */
        $this->dsa[] = "DS:$name:$type:$heartbeat:$min:$max";

        return $this;
    }

    /**
     * @param RRAConsolidationFunction $type
     * @param float $reliability xдоля определяет долю неопределённых значений в интервале консолидации, при которой консолидированное значение ещё считается определённым (от 0 до 1).
     * @param $reportsOnCell
     * @param $cellCount
     * @return PmxRrdDatabase
     * @throws \RuntimeException
     */
    public function addRoundRobinArchive($type, $reliability, $reportsOnCell, $cellCount)
    {
        if ($reliability > 1 || $reliability < 0) {
            throw new \RuntimeException('reliability or x-доля, must be between 0 and 1');
        }

//        x-доля определяет долю неопределённых значений в интервале консолидации, при которой консолидированное значение ещё считается определённым (от 0 до 1).
        /**
        RRA:функция_конс:достоверность:отсчетов_на_ячейку:число_ячеек
         */
        /**
         *  RRA:функция-консолидации:x-доля:отсчетов-на-ячейку:число-ячеек
         */
        $this->rraa[] = "RRA:$type:$reliability:$reportsOnCell:$cellCount";

        return $this;
    }
}
