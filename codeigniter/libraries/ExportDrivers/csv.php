<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

include_once __DIR__.DIRECTORY_SEPARATOR.'ExportDriver.php';

class csv extends ExportDriver {
    private $_subResult;

    public function __construct()
    {
        parent::__construct();
        $this->_subResult = array();
        $this->_extension = 'csv';
    }

    public function generate($data)
    {
        $this->_subResult = fopen($this->_tmpFileName, 'w+');
        foreach ($data as $row) {
            fputcsv($this->_subResult, $row);
        }
        fclose($this->_subResult);
    }

    public function saveAsFile($filename)
    {
        return rename($this->_tmpFileName, $filename . '.' . $this->_extension);
    }
}