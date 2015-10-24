<?php

namespace Handlebars\Decorator;

use Handlebars\ClosureWrapper;
use Handlebars\Utils;

class Inline
{
    public function __invoke($fn, $props, $runtime, $options)
    {
        $ret = $fn;
        if( empty($props->partials) ) {
            $props->partials = array();
            $ret = ClosureWrapper::wrap(function($context, $options) use ($runtime, $fn, $props) {
                $original = $runtime->getPartials();
                $partials = clone $original;
                foreach( $props->partials as $k => $v ) {
                    $partials[$k] = $v;
                }
                $runtime->setPartials($partials);
                $ret = $fn($context, $options);
                $runtime->setPartials($original);
                return $ret;
            });
        }

        $props->partials[$options->args[0]] = $options->fn;

        return $ret;
    }
}
