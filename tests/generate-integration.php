<?php

$integrationSkipSuites = array();
$integrationSkipTests = array(
    'testDataPassedToHelpersEach1',
    'testTheHelpersHashIsAvailableIsNestedContextsHelpersHash1',
    'testFailsWithMultipleAndArgsRegistration1',
    'testGH731ZeroContextRenderingRegressions1',
    'testSubexpressionsCanTJustBePropertyLookupsSubexpressions2',
    'testThrowOnMissingPartialPartials1',
    'testShouldTrackContextPathForArraysBlockHelperMissing1',
    'testShouldTrackContextPathForKeysBlockHelperMissing1',
    'testShouldHandleNestingBlockHelperMissing1',
);

function hbs_generate_integration_class_header($suiteName) {
    $testNamespace = hbs_generate_namespace('Integration');
    $className = hbs_generate_class_name($suiteName);
    return <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;

class $className extends PHPUnit_Framework_TestCase {
    private \$handlebars;
    public function setUp() { 
        \$this->handlebars = new \Handlebars\Handlebars();
    }
    private function execute() {
        \$args = func_get_args();
        \$expected = array_shift(\$args);
        \$actual = call_user_func_array(array(\$this->handlebars, 'render'), \$args);
        \$this->assertEquals(\$expected, \$actual);
    }

EOF;
}

function hbs_generate_integration_function_body($test) {
    $parts = array();
    
    // Generate executor
    $parts[] = i(2) . "\$this->execute(\$expected, \$tmpl, \$data, \$helpers, \$partials, \$options);";
    
    return join("\n", $parts);
}

function hbs_generate_integration_test($suiteName, $test, &$usedNames) {
    global $integrationSkipSuites, $integrationSkipTests;
    
    $parts = array();
    
    hbs_generate_patch_test_object($test);
    
    $functionName = hbs_generate_function_name($test, $usedNames);
    $parts[] = hbs_generate_function_header($test, $functionName);
    
    // Mark skipped
    if( in_array($functionName, $integrationSkipTests) || in_array($suiteName, $integrationSkipSuites) ) {
        $parts[] = hbs_generate_function_incomplete();
    }
    
    $parts[] = hbs_generate_test_vars($test);
    
    // Generate throws
    if( !empty($test['exception']) ) {
        $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    $parts[] = hbs_generate_integration_function_body($test);
    $parts[] = hbs_generate_function_footer();
    
    return "\n" . join("\n", $parts) . "\n";
}

function hbs_generate_integration_class($suiteName, $tests) {
    $usedNames = array();
    
    $output = hbs_generate_integration_class_header($suiteName);
    foreach( $tests as $test ) {
        $output .= hbs_generate_integration_test($suiteName, $test, $usedNames);
    }
    $output .= hbs_generate_class_footer();
    
    return $output;
}