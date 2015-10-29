<?php

namespace Handlebars\Helper;

use Handlebars\Utils;

class BlockHelperMissing
{
    /**
     * blockHelperMissing builtin
     *
     * @param mixed $context
     * @param \Handlebars\Runtime $runtime
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function __invoke($context, $runtime, $options)
    {
        if( $context === true ) {
            $fn = $options->fn;
            return $fn($options->scope);
        } else if( empty($context) && $context !== 0 ) {
            $fn = $options->inverse;
            return $fn($options->scope);
        } else if( Utils::isIntArray($context) ) {
            if( $options->ids !== null ) {
                $options->ids[] = $options->name;
            }
            $eachHelper = $runtime->nameLookup($runtime->getHelpers(), 'each');
            /** @var callable $eachHelper */
            return $eachHelper($context, $options);
        } else {
            $tmpOptions = $options;
            if( $options->data !== null && $options->ids !== null ) {
                $data = Utils::createFrame($options['data']);
                $data['contextPath'] = Utils::appendContextPath($options['data'], $options['name']);
                $options = array('data' => $data);
            }
            $fn = $tmpOptions->fn;
            return $fn($context, $options);
        }
    }
}
