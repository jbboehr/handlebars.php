<?php

namespace Handlebars\Helper;

use Handlebars\Utils;

class BlockHelperMissing
{
    public function __construct($handlebars)
    {
        $this->handlebars = $handlebars;
    }


    /**
     * blockHelperMissing builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function __invoke($context, $options)
    {
        if( $context === true ) {
            return $options->fn($options->scope);
        } else if( empty($context) && $context !== 0 ) {
            return $options->inverse($options->scope);
        } else if( Utils::isIntArray($context) ) {
            if( $options->ids !== null ) {
                $options->ids[] = $options->name;
            }
            $eachHelper = $this->handlebars->getHelper('each');
            return call_user_func($eachHelper, $context, $options);
        } else {
            $tmpOptions = $options;
            if( $options->data !== null && $options->ids !== null ) {
                $data = Utils::createFrame($options['data']);
                $data['contextPath'] = Utils::appendContextPath($options['data'], $options['name']);
                $options = array('data' => $data);
            }
            return $tmpOptions->fn($context, $options);
        }
    }
}
