<?php

namespace Handlebars;

use RuntimeException as BaseException;

/**
 * Runtime exception
 *
 * Note: this class is only used when the extension isn't loaded
 */
class RuntimeException extends BaseException implements Exception
{
}
