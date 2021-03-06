<?php

namespace Handlebars\Compiler;

use Handlebars\Impl;
use Handlebars\Runtime as BaseRuntime;
use Handlebars\RuntimeException;
use Handlebars\DepthList;

class Runtime extends BaseRuntime
{
    public function __construct(Impl $handlebars, $templateSpec)
    {
        parent::__construct($handlebars);

        if( !is_array($templateSpec) ) {
            throw new RuntimeException('Not an array: ' . var_export($templateSpec, true));
        }

        foreach( $templateSpec as $k => $v ) {
            if( is_int($k) ) {
                $this->programs[$k] = $v;
            } else if( $k === 'main' ) {
                $this->main = $v;
            } else if( $k === 'main_d' ) {
                $this->programDecorators['main'] = $v;
            } else if( substr($k, -2) === '_d' ) {
                $k = substr($k, 0, -2);
                $this->programDecorators[$k] = $v;
            } else {
                $this->options[$k] = $v;
            }
        }

        if( isset($this->programDecorators['main']) ) {
            $this->decoratorMap->attach($this->main, $this->programDecorators['main']);
        }
    }

    /**
     * Magic invoke method. Executes the template.
     *
     * @param mixed $context
     * @param array $options
     * @return string
     */
    public function __invoke($context = null, array $options = null)
    {
        parent::__invoke($context, $options);

        $data = $this->processDataOption($options, $context);
        $depths = $this->processDepthsOption($options, $context);
        $blockParams = array(); // @todo

        $runtime = $this;
        $fn = $this->main;
        $main = function($context) use ($runtime, $fn, $data, $depths, $blockParams) {
            return $fn(
                $context,
                $runtime->getHelpers(),
                $runtime->getPartials(),
                $data,
                $runtime,
                $blockParams,
                $depths
            );
        };
        $main = $this->executeDecorators($this->main, $main, $runtime, $depths, $data, $blockParams);
        return $main($context, $options);
    }

    /**
     * @param array $options
     * @param mixed $context
     * @return \Handlebars\DepthList
     */
    private function processDepthsOption($options, $context)
    {
        if( empty($this->options['useDepths']) ) {
            return null;
        }

        if( isset($options['depths']) ) {
            $depths = $options['depths'];
        } else {
            $depths = new DepthList();
        }

        if( !isset($options['depths'][0]) || $options['depths'][0] !== $context ) {
            $depths->unshift($context);
        }

        return $depths;
    }
}
