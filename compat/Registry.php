<?php

namespace Handlebars;

use ArrayAccess;
use IteratorAggregate;

/**
 * Note: this class is only used when the extension isn't loaded
 */
interface Registry extends IteratorAggregate, ArrayAccess
{

}
