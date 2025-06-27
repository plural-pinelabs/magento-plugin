<?php
namespace Pinelabs\PinePGGateway\Helper;

use Zend_Log;
use Zend_Log_Writer_Stream;

class Logger
{
    public static function getLogger()
    {
        $logPath = BP . '/var/log/PinePG/' . date("Y-m-d") . '.log';
        if (!is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0777, true);
        }

        $writer = new Zend_Log_Writer_Stream($logPath);
        $logger = new Zend_Log();
        $logger->addWriter($writer);
        return $logger;
    }
}
