<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class ExportDriver {
    protected $_extension;
    protected $_tmpFileName;
    protected $_result;

    public function __construct()
    {
        $this->_tmpFileName = FCPATH .'files/exportTemp_' . md5(microtime(true));
    }

    public function generate($data) {}
    public function saveAsFile($filename) {
        return true;
    }
    public function getFileContent($removeFile = false) {
        if (!file_exists($this->_tmpFileName)) {
            throw new \Exception('No result file');
        }
        $content = file_get_contents($this->_tmpFileName);
        if ($removeFile) {
            unlink($this->_tmpFileName);
        }
        return $content;
    }

    public function getExtension()
    {
        return $this->_extension;
    }

    public function __destruct()
    {
        if (file_exists($this->_tmpFileName)) {
            unlink($this->_tmpFileName);
        }
    }
}