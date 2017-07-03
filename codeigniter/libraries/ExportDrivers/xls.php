<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

include_once __DIR__.DIRECTORY_SEPARATOR.'ExportDriver.php';
include_once __DIR__.DIRECTORY_SEPARATOR. '..' . DIRECTORY_SEPARATOR . 'PHPExcel' . DIRECTORY_SEPARATOR . 'PHPExcel.php';

class xls extends ExportDriver {

    public function __construct()
    {
        parent::__construct();
        $this->_extension = 'xls';
    }

    public function generate($data)
    {
        $this->_result = new PHPExcel();
        $this->_result->getProperties()
            ->setCreator('Lead-R');
        $this->_result->setActiveSheetIndex(0);
        foreach ($data as $rowNum => $row) {
            $column = 'a';
            foreach ($row as $val) {
                $this->_result->setActiveSheetIndex(0)->setCellValue($column.($rowNum+1), $val);
                $column++;
            }
        }
        $writer = PHPExcel_IOFactory::createWriter($this->_result, 'Excel5');
        $writer->save($this->_tmpFileName);
    }

    public function saveAsFile($filename)
    {
        return rename($this->_tmpFileName, $filename . '.' . $this->_extension);
    }
}