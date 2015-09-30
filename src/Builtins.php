<?php

namespace Handlebars;

/**
 * Builtin helpers
 */
class Builtins
{
    /*
     * @var \Handlebars\Handlebars
     */
    private $handlebars;

    /**
     * Constructor
     *
     * @param \Handlebars\Handlebars $handlebars
     */
    public function __construct(Handlebars $handlebars)
    {
        $this->handlebars = $handlebars;
    }

    /**
     * Get all helpers
     *
     * @return array
     */
    public function getAllHelpers()
    {
        return array(
            'blockHelperMissing' => array($this, 'blockHelperMissing'),
            'each' => array($this, 'each'),
            'helperMissing' => array($this, 'helperMissing'),
            'if' => array($this, 'builtinIf'),
            'lookup' => array($this, 'lookup'),
            'unless' => array($this, 'unless'),
            'with' => array($this, 'with'),
        );
    }
    
    public function getAllDecorators()
    {
        return array(
            'inline' => array($this, 'inline'),
        );
    }

    /**
     * blockHelperMissing builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function blockHelperMissing($context, $options)
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

    /**
     * if builtin
     *
     * @param mixed $conditional
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function builtinIf($conditional, $options)
    {
        if( Utils::isCallable($conditional) ) {
            $conditional = call_user_func($conditional, $options->scope);
        }
        if( !empty($conditional) || (!empty($options->hash['includeZero']) && $conditional === 0) ) {
            return $options->fn($options->scope);
        } else {
            return $options->inverse($options->scope);
        }
    }

    /**
     * each builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @throws \Handlebars\RuntimeException
     * @return string
     */
    public function each($context, $options = null)
    {
        if( func_num_args() < 2 ) {
            throw new RuntimeException('Must pass iterator to #each');
        }

        $contextPath = null;
        if( $options->data !== null && $options->ids !== null ) {
            $contextPath = Utils::appendContextPath($options['data'], $options->ids[0]) . '.';
        }
        
        if( Utils::isCallable($context) ) {
            $context = call_user_func($context, $options->scope);
        }

        $data = null;
        if( $options->data !== null ) {
            $data = Utils::createFrame($options->data);
        }

        $ret = '';
        $i = 0;
        if( !empty($context) ) {
            $len = count($context) - 1;
            foreach( $context as $field => $value ) {
                if( $value === null ) {
                    $i++;
                    continue;
                }
                
                if( $data ) {
                    $data['index'] = $i;
                    $data['key'] = $field;
                    $data['first'] = ($i === 0);
                    $data['last'] = ($i === $len);

                    if( null !== $contextPath ) {
                        $data['contextPath'] = $contextPath . $field;
                    }
                }
                
                $ret .= $options->fn($value, array(
                    'data' => $data,
                    'blockParams' => array(
                        0 => $value,
                        1 => $field,
                        'path' => array($contextPath . $field, null),
                    ),
                ));
                $i++;
            }
        }
        if( $i === 0 ) {
            $ret = $options->inverse($options->scope);
        }
        return $ret;
    }

    /**
     * helperMissing builtin
     *
     * @throws \Handlebars\RuntimeException
     * @return NULL
     */
    public function helperMissing()
    {
        if( func_num_args() === 1 ) {
            return;
        } else {
            $options = func_get_arg(func_num_args() - 1);
            throw new RuntimeException('Helper missing: ' . $options->name);
        }
    }

    /**
     * lookup builtin
     *
     * @param mixed $obj
     * @param string $field
     * @return mixed
     */
    public function lookup($obj, $field)
    {
        return Utils::lookup($obj, $field);
    }

    /**
     * unless builtin
     *
     * @param mixed $conditional
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function unless($conditional, $options)
    {
        $ifHelper = $this->handlebars->getHelper('if');
        $newOptions = clone $options;
        $newOptions->fn = $options->inverse;
        $newOptions->inverse = $options->fn;
        return call_user_func($ifHelper, $conditional, $newOptions);
    }

    /**
     * with builtin
     *
     * @param mixed $context
     * @param \Handlebars\Options $options
     * @return mixed
     */
    public function with($context, $options)
    {
        if( Utils::isCallable($context) ) {
            $context = call_user_func($context, $options->scope);
        }
        if( $context !== null ) { // An empty object is true in javascript...
            $fn = $options->fn;
            $data = $options['data'];
            if( $options->data !== null && $options->ids !== null ) {
                $data = Utils::createFrame($options['data']);
                $data['contextPath'] = Utils::appendContextPath($options['data'], $options['ids'][0]);
            }
            return call_user_func($fn, $context, array(
                'data' => $data,
                'blockParams' => array(
                    0 => $context,
                    'path' => array(isset($data['contextPath']) ? $data['contextPath'] : null),
                ),
            ));
        } else {
            return $options->inverse();
        }
    }
    
    public function inline($fn, $props, $runtime, $options)
    {
        $ret = $fn;
        if( empty($props->partials) ) {
            $props->partials = array();
            $ret = new ClosureWrapper(function($context, $options) use ($runtime, $fn, $props) {
                $original = $runtime->getPartials();
                $runtime->registerPartials($props->partials);
                $ret = $fn($context, $options);
                $runtime->registerPartials($original);
                return $ret;
            });
        }
        
        $props->partials[$options->args[0]] = $options->fn;
        
        return $ret;
        //throw new Exception('Not yet implemented');
    }
}
