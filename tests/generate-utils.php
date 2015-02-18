<?php

function convertLambdas(&$data) {
    if( !is_array($data) ) {
        return;
    }
    foreach( $data as $k => $v ) {
        if( is_array($v) ) {
            if( !empty($v['!code']) ) {
                $data[$k] = new ClosureHolder($v['php'] . '/*' . $v['javascript'] . '*/');
            } else {
                convertLambdas($data[$k]);
            }
        }
    }
}

function i($n) {
    return str_pad('', $n * 4, ' ', STR_PAD_LEFT);
}

function i_var_export($n, $var) {
    return str_replace("\n", "\n" . i($n), my_var_export($var));
}

function is_integer_array(array $arr) {
    $isSeq = true;
    $currentIndex = 0;
    foreach( $arr as $k => $v ) {
        $isSeq &= ($k === $currentIndex++);
    }
    return $isSeq;
}

function my_var_export($var, $indent = 0) {
    if( $var instanceof ClosureHolder ) {
        return (string) $var;
    } else if( is_array($var) ) {
        if( empty($var) ) {
            return 'array()';
        } else {
            $output = "array(\n";
            $isNormalArray = is_integer_array($var);
            foreach( $var as $k => $v ) {
                $output .= i($indent + 1) 
                        . (!$isNormalArray ? var_export($k, true) 
                        . ' => ' : '' )
                        . my_var_export($v, $indent + 1) . ",\n";
            }
            $output .= i($indent) . ')';
            return $output;
        }
    } else {
        $v = var_export($var, true);
        if( is_string($var) ) {
            $v = str_replace("\n", $v[0] . ' . "\n" . ' . $v[0], $v);
        }
        return $v;
    }
}

class ClosureHolder {
    private $closureText;
    public function __construct($closureText) {
        $this->closureText = $closureText;
    }
    public function __toString() {
        return $this->closureText;
    }
}



function hbs_generate_class_name($suiteName) {
    return 'Handlebars'. str_replace(' ', '', ucwords(str_replace('-', ' ', $suiteName))) . 'Test';
}

function hbs_generate_namespace($ns) {
    return 'Handlebars\\Tests\\Spec\\' . $ns;
}

function hbs_generate_test_file($ns, $suiteName) {
    $className = hbs_generate_class_name($suiteName);
    return 'tests/Spec/' . $ns . '/' . $className . '.php';
}

function hbs_generate_patch_test_object(&$test) {
    // Fix helpers/partials/expected
    if( empty($test['helpers']) ) {
        $test['helpers'] = array();
    }
    if( empty($test['partials']) ) {
        $test['partials'] = array();
    }
    if( !array_key_exists('expected', $test) ) {
        $test['expected'] = null;
    }
}

function hbs_generate_test_vars($test) {
    $parts = array();
    
    $data = $test['data'];
    convertLambdas($data);
    
    // Generate general test data
    //$parts[] = i(2) . '$it = ' . i_var_export(2, $test['it']) . ";";
    //$parts[] = i(2) . '$desc = ' . i_var_export(2, $test['description']) . ";";
    $parts[] = i(2) . '$data = ' . i_var_export(2, $data) . ";";
    $parts[] = i(2) . '$tmpl = ' . i_var_export(2, $test['template']) . ";";
    $parts[] = i(2) . '$expected = ' . i_var_export(2, $test['expected']) . ";";
    $parts[] = i(2) . '$partials = ' . i_var_export(2, $test['partials']) . ";";
    
    // Generate helpers
    $helpers = $test['helpers'];
    if( !empty($test['globalHelpers']) ) {
        $helpers += $test['globalHelpers'];
    }
    convertLambdas($helpers);
    $parts[] = i(2) . '$helpers = ' . i_var_export(2, $helpers) . ";";
    
    // Generate options - @todo merge compile and runtime options for now
    $options = array();
    if( isset($test['compileOptions']) ) {
        $options = array_merge($options, $test['compileOptions']);
    }
    if( isset($test['options']) ) {
        $options = array_merge($options, $test['options']);
    }
    $parts[] = i(2) . '$options = ' . i_var_export(2, $options) . ";";
    
    // Generate throws
    if( !empty($test['exception']) ) {
        $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    return join("\n", $parts);
}

function hbs_generate_function_incomplete() {
    return i(2) . '$this->markTestIncomplete();' . "\n";;
}

function hbs_generate_write_file($fileName, $contents) {
    if( !is_dir(dirname($fileName)) ) {
        mkdir(dirname($fileName), 0755, true);
    }
    file_put_contents($fileName, $contents);
}
