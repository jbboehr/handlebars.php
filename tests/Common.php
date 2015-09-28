<?php

namespace Handlebars\Tests;

use Handlebars\Exception;
use Handlebars\Handlebars;
use Handlebars\PhpCompiler;
use Handlebars\VM;
use PHPUnit_Framework_TestCase;

class Common extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        if( !extension_loaded('handlebars') ) {
            throw new Exception('Handlebars extension not loaded');
        }
    }
}
