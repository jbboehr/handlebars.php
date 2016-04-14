<?php

namespace Handlebars\Tests;

use Psr\Log\AbstractLogger;

class MockLogger extends AbstractLogger
{
    public $logs = array();

    public function log($level, $message, array $context = array())
    {
        $this->logs[] = array($level, $message, $context);
    }
}