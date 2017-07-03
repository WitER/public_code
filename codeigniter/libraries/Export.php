<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

/**
 * Class Export
 */
class Export {

    private $_availableDrivers = array('csv', 'xml', 'xls');
    private $_selectedDriver;
    /**
     * @var $_driver ExportDriver
     */
    private $_driver;

    public function __construct($driver)
    {
        if (is_array($driver)) {
            reset($driver);
            $driver = current($driver);
        }
        $driver = mb_strtolower($driver, 'UTF-8');
        if ($this->_checkDriver($driver)) {
            $this->_selectedDriver = $driver;
            include_once __DIR__ . DIRECTORY_SEPARATOR . 'ExportDrivers' . DIRECTORY_SEPARATOR . $driver . '.php';
            $this->_driver = new $driver;
        } else {
            throw new \Exception('Selected driver not available: ' . $driver);
        }
    }


    public function generate($data)
    {
        $this->_driver->generate($data);
        //var_dump($this->_driver);
    }

    public function saveFile($fileName)
    {
        return $this->_driver->saveAsFile($fileName);
    }

    public function download($fileName)
    {
        $CI =& get_instance();
        $CI->load->helper('download');
        if(ob_get_level() > 0) {
            ob_clean();
        }
        force_download($fileName . '.' . $this->_driver->getExtension(), $this->_driver->getFileContent(true));
    }

    private function _checkDriver($driver)
    {
        return in_array($driver, $this->_availableDrivers) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . 'ExportDrivers' . DIRECTORY_SEPARATOR . $driver . '.php');
    }
}