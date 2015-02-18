<?php

$compilerSkipSuites = array();
$compilerSkipTests = array();
$compilerShouldntThrowTests = array(
    'testEachOnImplicitContextEach1',
    'testFailsWithMultipleAndArgsRegistration1',
    'testIfAContextIsNotFoundHelperMissingIsUsedHelperMissing1'
);

function hbs_generate_compiler_class_header($suiteName) {
    $testNamespace = hbs_generate_namespace('Compiler');
    $className = hbs_generate_class_name($suiteName);
    return <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;

class $className extends PHPUnit_Framework_TestCase {
    private \$compiler;
    public function setUp() { 
        \$this->compiler = new \Handlebars\Compiler();
    }
    private function execute() {
        \$args = func_get_args();
        \$expected = array_shift(\$args);
        \$actual = call_user_func_array(array(\$this->compiler, 'compile'), \$args);
        \$this->assertEquals(\$expected, \$actual);
    }

EOF;
}

function hbs_generate_compiler_function_body($test) {
    // Generate ast
    $parts[] = i(2) . '$ast = ' . i_var_export(2, $test['ast']) . ";";
    
    // Generate opcodes
    $parts[] = i(2) . '$opcodes = ' . i_var_export(2, $test['opcodes']) . ";";
    
    // Generate executor
    $parts[] = i(2) . "\$this->execute(\$opcodes, \$ast, \$options);";
    
    return join("\n", $parts);
}

function hbs_generate_compiler_test($suiteName, $test, &$usedNames) {
    global $compilerSkipSuites, $compilerSkipTests;
    
    $parts = array();
    
    hbs_generate_patch_test_object($test);
    
    $functionName = hbs_generate_function_name($test, $usedNames);
    $parts[] = hbs_generate_function_header($test, $functionName);
    
    // Mark skipped
    if( in_array($functionName, $compilerSkipTests) || in_array($suiteName, $compilerSkipSuites) ) {
        $parts[] = hbs_generate_function_incomplete();
    }
    
    $parts[] = hbs_generate_test_vars($test);
    
    // Generate throws
    if( !empty($test['exception']) ) {
        $parts[] = i(2) . "// Compiler probably shouldn't throw;";
        $parts[] = i(2) . "//\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    $parts[] = hbs_generate_compiler_function_body($test);
    $parts[] = hbs_generate_function_footer();
    
    return "\n" . join("\n", $parts) . "\n";
}

function hbs_generate_compiler_class($suiteName, $tests) {
    $usedNames = array();
    
    $output = hbs_generate_compiler_class_header($suiteName);
    foreach( $tests as $test ) {
        $output .= hbs_generate_compiler_test($suiteName, $test, $usedNames);
    }
    $output .= hbs_generate_class_footer();
    
    return $output;
}
