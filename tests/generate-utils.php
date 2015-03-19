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



function hbs_generate_class_name($specName, $suiteName) {
    return ucfirst($specName) . str_replace(' ', '', ucwords(str_replace('-', ' ', $suiteName))) . 'Test';
}

function hbs_generate_namespace($specName, $ns) {
    return 'Handlebars\\Tests\\Spec\\' . $specName . '\\' . $ns;
}

function hbs_generate_test_file($ns, $specName, $suiteName) {
    $className = hbs_generate_class_name($specName, $suiteName);
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
    
    $data = isset($test['data']) ? $test['data'] : null;
    convertLambdas($data);
    
    // Generate general test data
    //$parts[] = i(2) . '$it = ' . i_var_export(2, $test['it']) . ";";
    //$parts[] = i(2) . '$desc = ' . i_var_export(2, $test['description']) . ";";
    $parts[] = i(2) . '$data = ' . i_var_export(2, $data) . ";";
    $parts[] = i(2) . '$tmpl = ' . i_var_export(2, $test['template']) . ";";
    $parts[] = i(2) . '$expected = ' . i_var_export(2, $test['expected']) . ";";
    
    // Generate partials
    $partials = $test['partials'];
    if( !empty($test['globalPartials']) ) {
        foreach( $test['globalPartials'] as $k => $v ) {
            if( !isset($partials[$k]) ) {
                $partials[$k] = $v;
            }
        }
        //$partials = array_merge($test['globalPartials'], $partials);
    }
    $parts[] = i(2) . '$partials = ' . i_var_export(2, $partials) . ";";
    
    // Generate helpers
    $helpers = $test['helpers'];
    if( !empty($test['globalHelpers']) ) {
        $helpers += $test['globalHelpers'];
    }
    convertLambdas($helpers);
    $parts[] = i(2) . '$helpers = ' . i_var_export(2, $helpers) . ";";
    
    // Generate options - @todo merge compile and runtime options for now
    $parts[] = i(2) . '$compileOptions = ' . i_var_export(2, isset($test['compileOptions']) ? $test['compileOptions'] : array()) . ";";
    $parts[] = i(2) . '$options = ' . i_var_export(2, isset($test['options']) ? $test['options'] : array()) . ";";
    $parts[] = i(2) . '$allOptions = array_merge($compileOptions, $options);';
    
    return join("\n", $parts);
}

function hbs_generate_function_incomplete() {
    return i(2) . '$this->markTestIncomplete();' . "\n";
}

function hbs_generate_write_file($fileName, $contents) {
    if( !is_dir(dirname($fileName)) ) {
        mkdir(dirname($fileName), 0755, true);
    }
    file_put_contents($fileName, $contents);
}

function hbs_generate_function_name($test, &$usedNames) {
    $title = hbs_generate_test_title($test);
    $functionName = 'test' .  str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $title)));
    if( isset($usedNames[$functionName]) ) {
        $id = ++$usedNames[$functionName];
    } else {
        $id = $usedNames[$functionName] = 1;
    }
    $functionName .= $id;
    return $functionName;
}

function hbs_generate_function_header($test, $functionName) {
    $title = hbs_generate_test_title($test);
    return <<<EOF
    /**
     * {$title}
     */
    public function $functionName() {
EOF;
}

function hbs_generate_function_footer() {
    return '    }';
}

function hbs_generate_class_footer() {
    return "\n}\n";
}

function hbs_generate_test_title($test) {
    if( isset($test['desc']) && isset($test['name']) ) {
        return $test['name'] . ' - ' . $test['desc'];
    } else {
        return $test['description'] . ' - ' . $test['it'];
    }
}
