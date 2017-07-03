<?php

include_once __DIR__.DIRECTORY_SEPARATOR.'ImportDriver.php';
class csv extends ImportDriver
{
    private $enableConvertEncoding    = true;
    private $convertFrom              = false;
    private $convertTo                = 'UTF-8';
    private $encodingsList            = [
        'UTF-8',
        'UTF-32', 'UTF-32BE', 'UTF-32LE',
        'UTF-16', 'UTF-16BE', 'UTF-16LE',
        'UTF-7', 'UTF7-IMAP', 'ASCII',
        'EUC-JP', 'SJIS', 'eucJP-win', 'SJIS-win',
        'ISO-2022-JP', 'ISO-2022-JP-MS', 'CP932',
        'CP51932', 'SJIS-mac', 'SJIS-Mobile#DOCOMO',
        'SJIS-Mobile#KDDI', 'SJIS-Mobile#SOFTBANK',
        'UTF-8-Mobile#DOCOMO', 'UTF-8-Mobile#KDDI-A',
        'UTF-8-Mobile#KDDI-B', 'UTF-8-Mobile#SOFTBANK',
        'ISO-2022-JP-MOBILE#KDDI', 'JIS', 'JIS-ms',
        'CP50220', 'CP50220raw', 'CP50221',
        'CP50222', 'ISO-8859-1', 'ISO-8859-2',
        'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5',
        'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8',
        'ISO-8859-9', 'ISO-8859-10', 'ISO-8859-13',
        'ISO-8859-14', 'ISO-8859-15', '7bit', '8bit',
        'EUC-CN', 'CP936',
        'GB18030', 'HZ', 'EUC-TW',
        'CP950', 'BIG-5', 'EUC-KR',
        'UHC', 'ISO-2022-KR', 'Windows-1251',
        'Windows-1252', 'CP866', 'KOI8-R',
    ];

    private $lineSeparator      = PHP_EOL;
    private $columnSeparator    = ',';
    private $fileContent;

    public  $result = [];

    public function __construct($filePath)
    {
        parent::__construct($filePath);


        $this->fileContent = file_get_contents($this->_filePath);
        if (empty($this->fileContent)) {
            throw new \Exception('Empty file!');
        }

        $this->convertFrom = $this->checkNeedConvert();
        if ($this->convertFrom !== false) {
            $this->enableConvertEncoding($this->convertFrom);
        }
    }

    /**
     * Enable file encode converting
     * @param $from
     * @return $this
     */
    public function enableConvertEncoding($from) {
        $this->enableConvertEncoding = true;
        $this->convertFrom = $from;
        return $this;
    }

    /**
     * Set encoding for convert
     * @param $to
     * @return $this
     */
    public function setConvertTo($to) {
        $this->convertTo = $to;

        return $this;
    }

    /**
     * Set line separator
     * @param $end
     * @return $this
     */
    public function setLineEnd($end) {
        $this->lineSeparator = $end;

        return $this;
    }

    /**
     * Set column delimiter
     * @param $delimiter
     * @return $this
     */
    public function setColumnDelimiter($delimiter) {
        $this->columnSeparator = $delimiter;

        return $this;
    }

    /**
     * Parse file
     * @return $this
     * @throws \Exception
     */
    public function parse($page = false) {
        if ($this->enableConvertEncoding) {
            $this->convertEncode();
        }

        $delimiter = $this->columnSeparator;
        $this->_result = array_map(
            function($param) use ($delimiter)
            {
                return str_getcsv($param, $delimiter);
            },
            explode($this->lineSeparator, $this->fileContent)
        );

        if (empty($this->_result)) {
            throw new \Exception('File parse fail.');
        }

        return $this;
    }

    /**
     * Check for need convert encoding
     * @return bool|string
     * @throws \Exception
     */
    private function checkNeedConvert() {
        $currentEncoding = $this->checkEncode($this->fileContent);
        if (!$currentEncoding) {
            throw new \Exception('File encoding not matched.');
        }
        if ($this->convertTo != $currentEncoding) {
            return $currentEncoding;
        }
        return false;
    }

    /**
     * @param $string
     * @return bool
     */
    private function checkEncode($string) {
        foreach ($this->encodingsList as $encoding) {
            if (
                mb_check_encoding($string, $encoding) &&
                (md5($string) === md5(iconv($encoding, $encoding, $string)))
            ) {
                return $encoding;
            }
        }
        return false;
    }

    /**
     * Convert file encoding
     * @throws \Exception
     */
    private function convertEncode() {
        $this->fileContent = iconv($this->convertFrom, $this->convertTo, $this->fileContent);
        if (!$this->fileContent) {
            throw new \Exception('Can not convert encoding.');
        }
        return true;
    }

}