<?php

namespace Handlebars\Tests;

class VMGenerator extends Generator
{
    protected $skipTests = array(
        // 'testExportSubexpressionsSubexpressionsCanTJustBePropertyLookups1',
        // 'testIntegrationSubexpressionsSubexpressionsCanTJustBePropertyLookups1',
        'testExportSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
        'testIntegrationSubexpressionsSubexpressionsCanTJustBePropertyLookups2',
        // // Note: https://github.com/wycats/handlebars.js/blob/v2.0.0/spec/spec.js#L27
        // 'testExportStandaloneIndentationEachLineOfThePartialShouldBeIndentedBeforeRendering1',
        'testIntegrationStandaloneIndentationEachLineOfThePartialShouldBeIndentedBeforeRendering1',

        // Added in v3
        'testExportBlockParamsShouldTakePresedneceOverParentBlockParams1',
        'testIntegrationBlockParamsShouldTakePresedneceOverParentBlockParams1',
    );
    
    public function __construct(array $options)
    {
        $options['ns'] = 'VM';
        parent::__construct($options);
    }
    
    protected function generateTest(array $test)
    {
        return $this->generateTestExport($test) . $this->generateTestIntegration($test);
    }
    
    
    protected function generateTestIntegration(array $test)
    {
        $test['testMode'] = 'integration';
        $header = $this->generateFunctionHeader($test);
        $header .= $this->generateTestVars($test);
        $footer = $this->generateFunctionFooter($test);
        
        $parts[] = '$allOptions["helpers"] = $helpers;';
        $parts[] = '$allOptions["partials"] = $partials;';
        $parts[] = '$actual = $this->handlebars->render($tmpl, $data, $allOptions);';
        $parts[] = '$this->assertEquals($expected, $actual);';
        
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
    }
    
    protected function generateTestExport(array $test)
    {
        if( $this->specName === 'Mustache' ) {
            return;
        }
        
        $test['testMode'] = 'export';
        $header = $this->generateFunctionHeader($test);
        $header .= $this->generateTestVars($test);
        $footer = $this->generateFunctionFooter($test);
        
        $parts[] = '$helpers += $this->handlebars->getHelpers();';
        $parts[] = '$actual = $this->vm->execute($opcodes, $data, $helpers, $partialOpcodes, $allOptions);';
        $parts[] = '$this->assertEquals($expected, $actual);';
        
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
        
    }
}
