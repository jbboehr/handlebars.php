<?php

namespace Handlebars;

use Psr\Log\LoggerInterface;

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
     * @var LoggerInterface
     */
    protected $logger;

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
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * @param Registry $helpers
     * @return $this
     */
    public function setHelpers(Registry $helpers)
    {
        $this->helpers = $helpers;
        return $this;
    }

    /**
     * @param Registry $partials
     * @return $this
     */
    public function setPartials(Registry $partials)
    {
        $this->partials = $partials;
        return $this;
    }

    /**
     * @param Registry $decorators
     * @return $this
     */
    public function setDecorators(Registry $decorators)
    {
        $this->decorators = $decorators;
        return $this;
    }

    /**
     * @param LoggerInterface $logger
     * @return $this
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
        return $this;
    }
}
