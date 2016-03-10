<?php

namespace Handlebars;

abstract class BaseImpl implements Impl
{
    /**
     * @var Registry
     */
    protected $helpers;

    /**
     * @var Registry
     */
    protected $partials;

    /**
     * @var Registry
     */
    protected $decorators;

    /**
     * @return Registry
     */
    public function getDecorators()
    {
        return $this->decorators;
    }

    /**
     * @return Registry
     */
    public function getHelpers()
    {
        return $this->helpers;
    }

    /**
     * @return Registry
     */
    public function getPartials()
    {
        return $this->partials;
    }

    /**
     * @param Registry $helpers
     */
    public function setHelpers(Registry $helpers)
    {
        $this->helpers = $helpers;
    }

    /**
     * @param Registry $partials
     */
    public function setPartials(Registry $partials)
    {
        $this->partials = $partials;
    }

    /**
     * @param Registry $decorators
     */
    public function setDecorators(Registry $decorators)
    {
        $this->decorators = $decorators;
    }
}
