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
        
        // Added in v3
        'testExportBlockParamsShouldTakePresedneceOverParentBlockParams1',
        'testIntegrationBlockParamsShouldTakePresedneceOverParentBlockParams1',
        
        // Added in v4
        'testExportDecoratorsShouldFailWhenAccessingVariablesFromRoot1',
        'testIntegrationDecoratorsShouldFailWhenAccessingVariablesFromRoot1',
        'testExportStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled1',
        'testIntegrationStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled1',
        'testExportStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled2',
        'testIntegrationStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled2',
    );
    
    public function __construct(array $options)
    {
        $this->mode = \Handlebars\Handlebars::MODE_COMPILER;
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
        //$footer = $this->generateExecutor();
        $footer = $this->generateFunctionFooter($test);
        
        $parts = array();
        $parts[] = '// @todo make runtime partials work';
        $parts[] = '$fn = $handlebars->compile($tmpl, $compileOptions);';
        $parts[] = '$options["data"] = $data;';
        $parts[] = '$options["helpers"] = $helpers;';
        $parts[] = '$options["partials"] = $partials;';
        $parts[] = '$options["decorators"] = $decorators;';
        $parts[] = '$actual = $fn($data, $options);';
        
        /*
        $parts[] = '$opcodes = $this->handlebars->compile($tmpl, $compileOptions);';
        $parts[] = '$partialOpcodes = $this->handlebars->compilePartials($partials, $compileOptions);';
        //$parts[] = 'foreach( $partials as $name => $partial ) {';
        //$parts[] = '    $partialOpcodes[$name] = $this->handlebars->compile($partial, $compileOptions);';
        //$parts[] = '}';
        */
        
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
    }
    
    protected function generateExecutor()
    {
        return <<<EOF
        \$compiler = new PhpCompiler();
        \$templateSpecStr = \$compiler->compile(\$opcodes, \$compileOptions);
        \$templateSpec = eval('return ' . \$templateSpecStr . ';');
        foreach( \$partialOpcodes as \$name => \$partialOpcode ) {
            \$partials[\$name] = new \Handlebars\Compiler\Runtime(\$handlebars, eval('return ' . \$compiler->compile(\$partialOpcode, \$compileOptions) . ';'));
        }
        if( !\$templateSpec ) {
            echo \$templateSpecStr; exit(1);
        };
        \$fn = new \Handlebars\Compiler\Runtime(\$handlebars, \$templateSpec);
        if( isset(\$compileOptions['data']) || true ) { \$options['data'] = \$data; }
        \$options["helpers"] = \$helpers;
        \$options["partials"] = \$partials;
        \$options["decorators"] = \$decorators;
        \$actual = \$fn(\$data, \$options);

EOF;
    }
}
