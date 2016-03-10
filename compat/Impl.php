<?php

namespace Handlebars;

/**
 * Note: this class is only used when the extension isn't loaded
 */
interface Impl
{
    const MODE_COMPILER = 'compiler';
    const MODE_VM = 'vm';
    const MODE_CVM = 'cvm';

    public function getHelpers();
    public function getPartials();
    public function getDecorators();
    public function setHelpers(Registry $helpers);
    public function setPartials(Registry $partials);
    public function setDecorators(Registry $decorators);
    public function render($tmpl, $context = null, array $options = null);
    public function renderFile($filename, $context = null, array $options = null);
}
