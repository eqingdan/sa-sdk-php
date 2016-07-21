<?php

namespace EQingdan\SensorsAnalytics\Consumers;

class FileConsumer extends AbstractConsumer
{
    private $fileHandler;

    public function __construct($filename)
    {
        $this->fileHandler = fopen($filename, 'a+');
    }

    public function send($msg)
    {
        if ($this->fileHandler === null) {
            return false;
        }
        return fwrite($this->fileHandler, $msg . "\n") === false ? false : true;
    }

    public function close()
    {
        if ($this->fileHandler === null) {
            return false;
        }
        return fclose($this->fileHandler);
    }
}
