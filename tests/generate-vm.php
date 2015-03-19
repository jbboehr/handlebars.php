<?php

$vmSkipSuites = array();
$vmSkipTests = array(
    'testSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
);

function hbs_generate_vm_class_header($specName, $suiteName) {
    $testNamespace = hbs_generate_namespace('VM', $specName);
    $className = hbs_generate_class_name($specName, $suiteName);
    return <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;
use \Handlebars\Utils;

class $className extends PHPUnit_Framework_TestCase {
    private \$vm;
    public function setUp() { 
        \$this->vm = new \Handlebars\VM();
    }
    private function execute() {
        \$args = func_get_args();
        \$expected = array_shift(\$args);
        \$actual = call_user_func_array(array(\$this->vm, 'execute'), \$args);
        \$this->assertEquals(\$expected, \$actual);
    }

EOF;
}

function hbs_generate_vm_function_body($test) {
    // Generate opcodes
    $parts[] = i(2) . '$opcodes = ' . i_var_export(2, $test['opcodes']) . ";";
    
    // Generate partial opcodes
    $partialOpcodes = (isset($test['partialOpcodes']) ? $test['partialOpcodes'] : array());
    $globalPartialOpcodes = (isset($test['globalPartialOpcodes']) ? $test['globalPartialOpcodes'] : array());
    $partialOpcodes += $globalPartialOpcodes;
    $parts[] = i(2) . '$partialOpcodes = ' . i_var_export(2, $partialOpcodes) . ";";
    
    // Generate executor
    $parts[] = i(2) . "\$this->execute(\$expected, \$opcodes, \$data, \$helpers, \$partialOpcodes, \$allOptions);";
    
    return join("\n", $parts);
}

function hbs_generate_vm_test($suiteName, $test, &$usedNames) {
    global $vmSkipSuites, $vmSkipTests;
    
    $parts = array();
    
    hbs_generate_patch_test_object($test);
    
    $functionName = hbs_generate_function_name($test, $usedNames);
    $parts[] = hbs_generate_function_header($test, $functionName);
    
    // Mark skipped
    if( in_array($functionName, $vmSkipTests) || in_array($suiteName, $vmSkipSuites) ) {
        $parts[] = hbs_generate_function_incomplete();
    }
    
    $parts[] = hbs_generate_test_vars($test);
    
    // Generate throws
    if( !empty($test['exception']) ) {
        $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    $parts[] = hbs_generate_vm_function_body($test);
    $parts[] = hbs_generate_function_footer();
    
    return "\n" . join("\n", $parts) . "\n";
}

function hbs_generate_vm_class($specName, $suiteName, $tests) {
    $usedNames = array();
    
    $output = hbs_generate_vm_class_header($specName, $suiteName);
    foreach( $tests as $test ) {
        $test['specName'] = $specName;
        $test['suiteName'] = $suiteName;
        $output .= hbs_generate_vm_test($suiteName, $test, $usedNames);
    }
    $output .= hbs_generate_class_footer();
    
    return $output;
}