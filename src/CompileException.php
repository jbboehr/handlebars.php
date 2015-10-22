<?php

namespace Handlebars;

use Exception as BaseException;

/**
 * Compiler exception
 *
 * Note: this class is only used when the extension isn't loaded
 */
class CompileException extends BaseException implements Exception
{
}
