# handlebars.php

[![Build Status](https://travis-ci.org/jbboehr/handlebars.php.svg?branch=master)](https://travis-ci.org/jbboehr/handlebars.php)
[![Code Coverage](https://scrutinizer-ci.com/g/jbboehr/handlebars.php/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/jbboehr/handlebars.php/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/jbboehr/handlebars/v/stable.svg)](https://packagist.org/packages/jbboehr/handlebars)
[![License](https://poser.pugx.org/jbboehr/handlebars/license.svg)](https://packagist.org/packages/jbboehr/handlebars)

PHP handlebars.js Compiler and VM. Use with [handlebars.c](https://github.com/jbboehr/handlebars.c) and [php-handlebars](https://github.com/jbboehr/php-handlebars).


## Requirements

[php-handlebars](https://github.com/jbboehr/php-handlebars)


## Install

Via Composer

``` bash
composer require jbboehr/handlebars
```


## Usage

``` php
$handlebars = new Handlebars\Handlebars();

$fn = $handlebars->compile('{{foo}}');
echo $fn(array(
    'foo' => 'bar',
));

echo $handlebars->render('{{foo}}', array(
    'foo' => 'bar',
));
```


## Testing

``` bash
make test
```


## License

This project is licensed under the [LGPLv3](http://www.gnu.org/licenses/lgpl-3.0.txt).
handlebars.js is licensed under the [MIT license](http://opensource.org/licenses/MIT).
