<?php

namespace Handlebars\Tests;

class CompilerGenerator extends Generator
{
    protected $skipTests = array(
        'testExportSubexpressionsSubexpressionsCanTJustBePropertyLookups1',
        'testIntegrationSubexpressionsSubexpressionsCanTJustBePropertyLookups1',
        'testExportSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
        'testIntegrationSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
        // // Note: https://github.com/wycats/handlebars.js/blob/v2.0.0/spec/spec.js#L27
        // 'testExportStandaloneIndentationEachLineOfThePartialShouldBeIndentedBeforeRendering1',
        'testIntegrationStandaloneIndentationEachLineOfThePartialShouldBeIndentedBeforeRendering1',
    );
    
    public function __construct(array $options)
    {
        $options['ns'] = 'Compiler';
        parent::__construct($options);
    }
    
    protected function generateTest(array $test)
    {
        return $this->generateTestExport($test) . $this->generateTestIntegration($test);
    }
    
    protected function generateTestExport(array $test)
    {
        if( $this->specName === 'Mustache' ) {
            return;
        }
        
        $test['testMode'] = 'export';
        $header = $this->generateFunctionHeader($test);
        $header .= $this->generateTestVars($test);
        $footer = $this->generateExecutor();
        $footer .= $this->generateFunctionFooter($test);
        
        $parts = array();
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
        
    }
    
    protected function generateTestIntegration(array $test)
    {
        $test['testMode'] = 'integration';
        $header = $this->generateFunctionHeader($test);
        $header .= $this->generateTestVars($test);
        $footer = $this->generateExecutor();
        $footer .= $this->generateFunctionFooter($test);
        
        $parts = array();
        $parts[] = '$partialOpcodes = array();';
        $parts[] = '$opcodes = $this->handlebars->compile($tmpl, $compileOptions);';
        $parts[] = '$partialOpcodes = $this->handlebars->compilePartials($partials, $compileOptions);';
        //$parts[] = 'foreach( $partials as $name => $partial ) {';
        //$parts[] = '    $partialOpcodes[$name] = $this->handlebars->compile($partial, $compileOptions);';
        //$parts[] = '}';
        
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
    }
    
    protected function generateExecutor()
    {
        return <<<EOF
        \$templateSpecStr = \$this->compiler->compile(\$opcodes, \$compileOptions);
        \$templateSpec = eval('return ' . \$templateSpecStr . ';');
        \$partialFns = array();
        foreach( \$partialOpcodes as \$name => \$partialOpcode ) {
            \$partialFns[\$name] = new \Handlebars\Runtime(eval('return ' . \$this->compiler->compile(\$partialOpcode, \$compileOptions) . ';'));
        }
        if( !\$templateSpec ) {
            echo \$templateSpecStr; exit(1);
        };
        \$fn = new \Handlebars\Runtime(\$templateSpec, \$helpers, \$partialFns);
        if( isset(\$compileOptions['data']) || true ) { \$options['data'] = \$data; }
        \$actual = \$fn(\$data, \$options);
        \$this->assertEquals(\$expected, \$actual);

EOF;
    }
}
