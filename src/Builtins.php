<?php

namespace Handlebars;

/**
 * Builtin helpers
 */
class Builtins
{
	/*
	 * @var \Handlebars\VM
	 */
    private $vm;
    
    /**
     * Constructor
     * 
     * @param \Handlebars\VM $vm
     */
    public function __construct(VM $vm)
    {
        $this->vm = $vm;
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
        if( is_callable($context) ) {
            $context = call_user_func($context, $options);
        }
        if( $context === true ) {
            return $options->fn($options->scope);
        } else if( $context === false || $context === null || (empty($context) && $context !== 0) ) {
            return $options->inverse($options->scope);
        } else if( Utils::isIntArray($context) ) {
            if( $options->ids !== null ) {
                $options->ids = array($options->name);
                //$options->ids[] = $options->name;
            }
            $eachHelper = $this->vm->getHelper('each');
            return call_user_func($eachHelper, $context, $options);
        } else {
            $tmpOptions = $options;
            if( $options->data !== null && $options->ids !== null ) {
                $data = $options['data'];
                $data['contextPath'] = (isset($options['data']['contextPath']) ? $options['data']['contextPath'] . '.' : '') . $options['name'];
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
        if( is_callable($conditional) ) {
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
            $contextPath = (isset($options['data']['contextPath']) ? 
                $options['data']['contextPath'] . '.' : 
                '') . $options->ids[0] . '.';
        }
        if( is_callable($context) ) {
            $context = call_user_func($context, $options->scope);
        }
        
        $data = $options->data ?: array();
        
        // @todo distinguish integer vs assoc array?
        $ret = '';
        $i = 0;
        if( !empty($context) ) {
            $len = count($context) - 1;
            foreach( $context as $k => $value ) {
                //$data = array();
                $data['index'] = $i;
                $data['key'] = $k;
                $data['first'] = ($i === 0);
                $data['last'] = ($i === $len);
                
                if( $contextPath ) {
                    $data['contextPath'] = $contextPath . $k;
                }
                
                $ret .= $options->fn($value, array('data' => $data));
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
            return null;
        } else {
            $options = func_get_arg(func_num_args() - 1);
            throw new RuntimeException("Helper missing: " . $options->name);
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
        return isset($obj[$field]) ? $obj[$field] : null;
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
        $ifHelper = $this->vm->getHelper('if');
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
        if( is_callable($context) ) {
            $context = call_user_func($context, $options->scope);
        }
        if( !empty($context) ) {
            $fn = $options->fn;
            if( $options->data && $options->ids ) {
                $data = $options['data'];
                $data['contextPath'] = (isset($options['data']['contextPath']) ? $options['data']['contextPath'] . '.' : '') . $options['ids'][0];
                $options = array('data' => $data);
            }
            return call_user_func($fn, $context, $options);
        } else {
            return $options->inverse();
        }
    }
}
