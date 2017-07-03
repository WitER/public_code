<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ImportDriver {
    protected $_filePath;
    protected $_result;
    protected $_dropFile = true;

    public function __construct($filePath)
    {
        $this->_filePath = $filePath;
        if (!file_exists($this->_filePath)) {
            throw new \Exception('File not exists!');
        }
    }

    public function disableDrop()
    {
        $this->_dropFile = false;
        return $this;
    }

    public function enableDrop()
    {
        $this->_dropFile = true;
        return $this;
    }

    public function parse($page = false) { return $this; }

    public function getResult($rows = 0, $offset = 0) {
        $result = [];

        $end = $offset + $rows;
        $end = $end <= 0 ? count($this->_result) : $end;

        for ($i=$offset; $i<$end; $i++) {
            $result[] = $this->_result[$i];
        }

        return $result;
    }

    public function freeResult()
    {
        $this->_result = array();
    }

    public function __destruct()
    {
        if (file_exists($this->_filePath) && $this->_dropFile) {
            unlink($this->_filePath);
        }
    }
}