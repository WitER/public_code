<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

include_once __DIR__.DIRECTORY_SEPARATOR.'ExportDriver.php';

class xml extends ExportDriver {

    public function __construct()
    {
        parent::__construct();
        $this->_extension = 'xml';
    }

    public function generate($data)
    {
        $this->_result = new SimpleXMLElement('<data/>');

        reset($data);
        $firstRow = current($data);

        foreach ($data as $row) {
            if ($firstRow == $row) continue;
            $xmlRow = $this->_result->addChild('запись');
            foreach ($row as $field => $value) {
                $xmlRow->addChild(str_replace(['%', ',', ' ', ':', '/', '\\', '__'], '_', $firstRow[$field]), $value);
            }
        }

        file_put_contents($this->_tmpFileName, $this->_result->asXML());
    }

    public function saveAsFile($filename)
    {
        return rename($this->_tmpFileName, $filename . '.' . $this->_extension);
    }
}