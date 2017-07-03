<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Class Import
 */
class Import {

    private $_availableDrivers = array('csv', 'xls');
    private $_selectedDriver;
    /**
     * @var $_driver ImportDriver
     */
    private $_driver;

    public function __construct($config)
    {
        $filePath = '';
        if (is_array($config)) {
            reset($config);
            $driver = current($config);
            next($config);
            $filePath = current($config);
        } else {
            $driver = $config;
        }
        $driver = mb_strtolower($driver, 'UTF-8');

        if ($this->_checkDriver($driver)) {
            $this->_selectedDriver = $driver;
            include_once __DIR__ . DIRECTORY_SEPARATOR . 'ImportDrivers' . DIRECTORY_SEPARATOR . $driver . '.php';
            $this->_driver = new $driver($filePath);
        } else {
            throw new \Exception('Selected driver not available: ' . $driver);
        }
    }

    public function disableDrop()
    {
        return $this->_driver->disableDrop();
    }

    public function enableDrop()
    {
        return $this->_driver->enableDrop();
    }

    public function parse($page = false)
    {
        return $this->_driver->parse($page);
    }

    public function getResult($rows = 0, $offset = 0)
    {
        return $this->_driver->getResult($rows, $offset);
    }

    public function free()
    {
        $this->_driver->freeResult();
    }

    private function _checkDriver($driver)
    {
        return in_array($driver, $this->_availableDrivers) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'ImportDrivers' . DIRECTORY_SEPARATOR . $driver . '.php');
    }
}