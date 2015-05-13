<?php

if( file_exists($file = __DIR__ . '/../vendor/autoload.php') ) {
    require $file;
} else {
    throw new \Exception('Unable to find composer autoloader');
}
