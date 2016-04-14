<?php

namespace Handlebars;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerAwareInterface;

/**
 * Note: this class is only used when the extension isn't loaded
 */
interface Impl extends LoggerAwareInterface
{
    public function getHelpers();
    public function getPartials();
    public function getDecorators();
    public function getLogger();
    public function setHelpers(Registry $helpers);
    public function setPartials(Registry $partials);
    public function setDecorators(Registry $decorators);
    public function setLogger(LoggerInterface $logger);
    public function render($tmpl, $context = null, array $options = null);
    public function renderFile($filename, $context = null, array $options = null);
}
