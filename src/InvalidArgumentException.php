<?php

namespace Handlebars;

use InvalidArgumentException as BaseException;

/**
 * Invalid argument exception
 *
 * Note: this class is only used when the extension isn't loaded
 */
class InvalidArgumentException extends BaseException implements Exception
{
}
