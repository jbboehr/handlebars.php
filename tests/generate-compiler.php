<?php

$compilerSkipSuites = array();
$compilerSkipTests = array(
    'testSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
);

function hbs_generate_compiler_class_header($specName, $suiteName) {
    $testNamespace = hbs_generate_namespace('Compiler', $specName);
    $className = hbs_generate_class_name($specName, $suiteName);
    return <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;
use \Handlebars\Utils;

class $className extends PHPUnit_Framework_TestCase {
    private \$compiler;
    public function setUp() { 
        \$this->compiler = new \Handlebars\PhpCompiler();
    }

EOF;
}

function hbs_generate_compiler_function_body($test) {
    // Generate opcodes
    $parts[] = i(2) . '$opcodes = ' . i_var_export(2, $test['opcodes']) . ";";
    
    // Generate partial opcodes
    $partialOpcodes = (isset($test['partialOpcodes']) ? $test['partialOpcodes'] : array());
    $globalPartialOpcodes = (isset($test['globalPartialOpcodes']) ? $test['globalPartialOpcodes'] : array());
    $partialOpcodes += $globalPartialOpcodes;
    $parts[] = i(2) . '$partialOpcodes = ' . i_var_export(2, $partialOpcodes) . ";";
    
    // Generate executor
    $parts[] = i(2) . "\$templateSpecStr = \$this->compiler->compile(\$opcodes, \$compileOptions);";
    $parts[] = i(2) . "\$templateSpec = eval('return ' . \$templateSpecStr . ';');";
    
    $parts[] = i(2) . "\$partialFns = array();";
    $parts[] = i(2) . "foreach( \$partialOpcodes as \$name => \$partialOpcode ) {";
    $parts[] = i(3) . "\$partialFns[\$name] = new \Handlebars\Runtime(eval('return ' . \$this->compiler->compile(\$partialOpcode, \$compileOptions) . ';'));";
    $parts[] = i(2) . '}';
    
    $parts[] = i(2) . "if( !\$templateSpec ) { echo \$templateSpecStr; die(); };";
    $parts[] = i(2) . "\$fn = new \Handlebars\Runtime(\$templateSpec, \$helpers, \$partialFns);";
    $parts[] = i(2) . "if( isset(\$compileOptions['data']) || true ) { \$options['data'] = \$data; }";
    $parts[] = i(2) . "\$actual = \$fn(\$data, \$options);";
    $parts[] = i(2) . "\$this->assertEquals(\$expected, \$actual);";
    
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
        $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    $parts[] = hbs_generate_compiler_function_body($test);
    $parts[] = hbs_generate_function_footer();
    
    return "\n" . join("\n", $parts) . "\n";
}

function hbs_generate_compiler_class($specName, $suiteName, $tests) {
    $usedNames = array();
    
    $output = hbs_generate_compiler_class_header($specName, $suiteName);
    foreach( $tests as $test ) {
        $test['specName'] = $specName;
        $test['suiteName'] = $suiteName;
        $output .= hbs_generate_compiler_test($suiteName, $test, $usedNames);
    }
    $output .= hbs_generate_class_footer();
    
    return $output;
}