<?php

namespace Handlebars\Helper;

class Unless extends IfHelper
{
    /**
     * unless builtin
     *
     * @param mixed $conditional
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function __invoke($conditional, $options)
    {
        return parent::__invoke(!$conditional, $options);
    }
}
