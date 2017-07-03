<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

include_once __DIR__.DIRECTORY_SEPARATOR.'ImportDriver.php';
include_once __DIR__.DIRECTORY_SEPARATOR. '..' . DIRECTORY_SEPARATOR . 'PHPExcel' . DIRECTORY_SEPARATOR . 'PHPExcel.php';

class xls extends ImportDriver {

    public function __construct($filePath)
    {
        parent::__construct($filePath);
    }

    public function parse($page = false)
    {
        try {
            $inputFileType = PHPExcel_IOFactory::identify($this->_filePath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($this->_filePath);
        } catch(Exception $e) {
            die('Error loading file "'.pathinfo($this->_filePath,PATHINFO_BASENAME).'": '.$e->getMessage());
        }

        $sheet = $objPHPExcel->getSheet($page !== false ? $page : 0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        for ($row = 1; $row <= $highestRow; $row++){
            //  Read a row of data into an array
            $rowData = $sheet->rangeToArray(
                'A' . $row . ':' . $highestColumn . $row,
                NULL,
                TRUE,
                true,
                true
            );
            reset($rowData);
            $this->_result[] = current($rowData);
        }

        return $this;
    }
}