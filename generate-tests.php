<?php

$opcodesDir = __DIR__ . '/spec/handlebars/opcodes/';
$opcodesOutputDir = __DIR__ . '/tests/Spec';
$specialSuites = array('parser', 'tokenizer');



// Load opcode files
$opcodesFiles = array();
foreach( scandir($opcodesDir) as $file ) {
    if( $file[0] === '.' || substr($file, -5) !== '.json' ) {
        continue;
    }
    $opcodesFiles[] = $opcodesDir . $file;
}

if( !is_dir($opcodesOutputDir) ) {
    mkdir($opcodesOutputDir);
}



// Generate opcode tests
foreach( $opcodesFiles as $filePath ) {
    $fileName = basename($filePath);
    $suiteName = substr($fileName, 0, -strlen('.json'));
    if( in_array($suiteName, $specialSuites) ) {
        continue;
    }
    
    $tests = json_decode(file_get_contents($filePath), true);
    
    if( !$tests  ) {
        //trigger_error("No tests in file: " . $file, E_USER_WARNING);
        continue;
    }
    
    $className = 'Handlebars'. str_replace(' ', '', ucwords(str_replace('-', ' ', $suiteName))) . 'Test';
    $testNamespace = 'Handlebars\\Tests\\Spec';
    $testFile = 'tests/Spec/' . $className . '.php';
    
    $output = <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;

class $className extends PHPUnit_Framework_TestCase {
    private \$vm;
    public function setUp() { 
        \$this->vm = new \Handlebars\VM();
    }

EOF;
    $usedNames = array();
    
    foreach( $tests as $test ) {
        $parts = array();
        
        // Fix helpers/partials
        if( empty($test['helpers']) ) {
            $test['helpers'] = array();
        }
        if( empty($test['partials']) ) {
            $test['partials'] = array();
        }
        
        // Generate header
        $functionName = 'test' .  str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $test['it'] . '-' . $test['description'])));
        if( isset($usedNames[$functionName]) ) {
            $id = ++$usedNames[$functionName];
        } else {
            $id = $usedNames[$functionName] = 1;
        }
        $functionName .= $id;
        $parts[] = i(1) . "public function $functionName() {";
        
        $data = $test['data'];
        convertLambdas($data);
        
        // Generate general test data
        $parts[] = i(2) . '$it = ' . i_var_export(2, $test['it']) . ";";
        $parts[] = i(2) . '$desc = ' . i_var_export(2, $test['description']) . ";";
        $parts[] = i(2) . '$data = ' . i_var_export(2, $data) . ";";
        $parts[] = i(2) . '$tmpl = ' . i_var_export(2, $test['template']) . ";";
        $parts[] = i(2) . '$expected = ' . i_var_export(2, (isset($test['expected']) ? $test['expected'] : null)) . ";";
        $parts[] = i(2) . '$partials = ' . i_var_export(2, (isset($test['partials']) ? $test['partials'] : null)) . ";";
        
        // Generate helpers
        $helpers = $test['helpers'];
        convertLambdas($helpers);
        $parts[] = i(2) . '$helpers = ' . i_var_export(2, $helpers) . ";";
        /*
        if( !empty($test['helpers']['php']) ) {
            $parts[] = i(2) . '$helpers = array(';
            foreach( $test['helpers']['php'] as $name => $fn ) {
                $parts[] = i(3) . "'" . $name . "' => " . $fn . ',';
            }
            $parts[] = i(2) . ');';
        } else {
            $parts[] = i(2) . '$helpers = array();';
        }*/
        
        // Generate throws
        $throwsStr = '';
        if( !empty($test['exception']) ) {
            $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
        }
        
        // Generate opcodes
        $parts[] = i(2) . '$opcodes = ' . i_var_export(2, (isset($test['opcodes']) ? $test['opcodes'] : null)) . ";";
        
        $parts[] = i(2) . "\$actual = \$this->vm->execute(\$opcodes, \$data, \$helpers, \$partials);";
        $parts[] = i(2) . "\$this->assertEquals(\$expected, \$actual);";
        
        // Footer
        $parts[] = '    }';
        
        $output .= "\n" . join("\n", $parts) . "\n";
    }
    
    $output .= "\n}\n";
    
    // Write
    if( !is_dir(dirname($testFile)) ) {
        mkdir(dirname($testFile));
    }
    file_put_contents($testFile, $output);
}



// Utils

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
        return var_export($var, true);
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