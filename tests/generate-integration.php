<?php

$integrationSkipSuites = array();
$integrationSkipTests = array(
    'testSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
    'testStandaloneIndentationEachLineOfThePartialShouldBeIndentedBeforeRendering1',
    'testNoInterpolationMustacheFreeTemplatesShouldRenderAsIs1',
);

function hbs_generate_integration_class_header($specName, $suiteName) {
    $testNamespace = hbs_generate_namespace($specName, 'Integration');
    $className = hbs_generate_class_name($specName, $suiteName);
    return <<<EOF
<?php
            
namespace $testNamespace;

use \PHPUnit_Framework_TestCase;
use \Handlebars\SafeString;

class $className extends PHPUnit_Framework_TestCase {
    private \$handlebars;
    public function setUp() { 
		if( !extension_loaded('handlebars') ) {
			throw new \Exception('Handlebars extension not loaded');
			//\$this->markTestIncomplete();
		}
        \$this->handlebars = new \Handlebars\Handlebars();
    }
    private function execute() {
        \$args = func_get_args();
        \$expected = array_shift(\$args);
        \$actual = call_user_func_array(array(\$this->handlebars, 'render'), \$args);
        \$this->assertEquals(\$expected, \$actual);
        //\$this->assertEquals(preg_replace('/\s+/', '', \$expected), preg_replace('/\s+/', '', \$actual));
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
    if( in_array($functionName, $integrationSkipTests) || 
            in_array($suiteName, $integrationSkipSuites) ) {
        $parts[] = hbs_generate_function_incomplete();
    }
    
    // Register global helpers/partial
    if( !empty($test['globalHelpers']) ) {
        $globalHelpers = $test['globalHelpers'];
        convertLambdas($globalHelpers);
        $parts[] = i(2) . '$this->handlebars->registerHelpers(' . i_var_export(2, $globalHelpers) . ');';
        unset($test['globalHelpers']); // maybe bad idea
    } 
    if( !empty($test['globalPartials']) ) {
        $parts[] = i(2) . '$this->handlebars->registerPartials(' . i_var_export(2, $test['globalPartials']) . ');';
        unset($test['globalPartial']); // maybe bad idea
    }
    
    // Main parts
    $parts[] = hbs_generate_test_vars($test);
    
    // Generate throws
    if( !empty($test['exception']) ) {
        $parts[] = i(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
    }
    
    $parts[] = hbs_generate_integration_function_body($test);
    $parts[] = hbs_generate_function_footer();
    
    return "\n" . join("\n", $parts) . "\n";
}

function hbs_generate_integration_class($specName, $suiteName, $tests) {
    $usedNames = array();
    
    $output = hbs_generate_integration_class_header($specName, $suiteName);
    foreach( $tests as $test ) {
        $test['specName'] = $specName;
        $test['suiteName'] = $suiteName;
        $ret = hbs_generate_integration_test($suiteName, $test, $usedNames);
        if( $ret ) {
            $output .= $ret;
        }
    }
    $output .= hbs_generate_class_footer();
    
    return $output;
}