<?php
namespace Pinelabs\PinePGGateway\Logger;

use Monolog\Logger;
use Magento\Framework\Logger\Handler\Base;

class Handler extends Base
{
    protected $loggerType = Logger::DEBUG;
    protected $fileName = '/var/log/pinelabs_gateway.log';
}
