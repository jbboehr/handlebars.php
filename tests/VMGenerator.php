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

        // Added in v4
        'testExportDecoratorsShouldFailWhenAccessingVariablesFromRoot1',
        'testIntegrationDecoratorsShouldFailWhenAccessingVariablesFromRoot1',
        'testExportStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled1',
        'testIntegrationStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled1',
        'testExportStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled2',
        'testIntegrationStandaloneSectionsBlockStandaloneElseSectionsCanBeDisabled2',

        // These are skipped for export mode because of the alternate decorators compile option
        'testExportInlinePartialsShouldDefineInlinePartialsForTemplate1',
        'testExportInlinePartialsShouldOverwriteMultiplePartialsInTheSameTemplate1',
        'testExportInlinePartialsShouldDefineInlinePartialsForBlock1',
        'testExportInlinePartialsShouldOverrideTemplatePartials1',
        'testExportInlinePartialsShouldOverridePartialsDownTheEntireStack1',
        'testExportInlinePartialsShouldDefineInlinePartialsForPartialCall1',
        'testExportInlinePartialsShouldDefineInlinePartialsForBlock3',
    );
    
    public function __construct(array $options)
    {
        $this->mode = \Handlebars\Handlebars::MODE_VM;
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
        
        $parts[] = '$allOptions["alternateDecorators"] = true;';
        
        $parts[] = '$allOptions["helpers"] = $helpers;';
        $parts[] = '$allOptions["partials"] = $partials;';
        $parts[] = '$allOptions["decorators"] = $decorators;';


        $parts[] = '$actual = $handlebars->render($tmpl, $data, $allOptions);';
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
        
        $parts[] = 'if( !empty($decorators) || !empty($globalDecorators) ) {';
        $parts[] = '    $this->markTestIncomplete("The VM does not support decorators in export mode - requires custom compiler option");';
        $parts[] = '}';

        $parts[] = 'foreach( $partialOpcodes as $name => $partialOpcode ) {';
        $parts[] = '  $partials[$name] = new \\Handlebars\\VM\\Runtime($handlebars, $partialOpcode);';
        $parts[] = '}';

        $parts[] = '$allOptions["helpers"] = $helpers;';
        $parts[] = '$allOptions["partials"] = $partials;';
        $parts[] = '$allOptions["decorators"] = $decorators;';

        $parts[] = '$vm = new \\Handlebars\\VM\\Runtime($handlebars, $opcodes);';
        $parts[] = '$actual = $vm($data, $allOptions);';
        $parts[] = '$this->assertEquals($expected, $actual);';
        
        return $header
            . $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $footer;
        
    }
}

