<?php

namespace Handlebars\Tests;

use Handlebars\Compiler\PhpCompiler;

class HandlebarsExportTest extends Common
{
    private $data;
    private $dataName;
    private $specialSuites = array('parser', 'tokenizer');
    static private $skipTests = array(
        'helpers - block params - should take presednece over parent block params',
        'blocks - standalone sections - block standalone else sections can be disabled',
        'blocks - decorators - should fail when accessing variables from root',
        'subexpressions - subexpressions - subexpressions can\'t just be property lookups',
    );
    static private $skipLegacyVMTests = array(
        'regressions - Regressions - should support multiple levels of inline partials',
        'regressions - Regressions - GH-1089: should support failover content in multiple levels of inline partials',
    );

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->data = $data;
        $this->dataName = $dataName;
        parent::__construct($name, $data, $dataName);
    }

    /**
     * @dataProvider specProvider
     */
    public function testCompiler($test)
    {
        if( in_array($test['name'], self::$skipTests) ) {
            $this->markTestIncomplete();
        }
        $test = $this->prepareTestData($test);

        if( !empty($test['exception']) ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'compiler');
        $compiler = new PhpCompiler();
        $templateSpecStr = $compiler->compile($this->convertContext($test['opcodes']), $test['compileOptions']);
        $templateSpec = eval('return ' . $templateSpecStr . ';');
        if( !$templateSpec ) {
            echo $templateSpecStr;
        };

        $globalPartials = array();
        foreach( $test['globalPartialOpcodes'] as $name => $partialOpcode ) {
            $globalPartials[$name] = new \Handlebars\Compiler\Runtime(
                $handlebars,
                eval('return ' . $compiler->compile($this->convertContext($partialOpcode), $test['compileOptions']) . ';')
            );
        }
        $handlebars->registerPartials($globalPartials);

        $partials = array();
        foreach( $test['partialOpcodes'] as $name => $partialOpcode ) {
            $partials[$name] = new \Handlebars\Compiler\Runtime(
                $handlebars,
                eval('return ' . $compiler->compile($this->convertContext($partialOpcode), $test['compileOptions']) . ';')
            );
        }
        $test['options']['partials'] = $partials;
        $handlebars->registerPartials($partials);

        $fn = new \Handlebars\Compiler\Runtime($handlebars, $templateSpec);
        $actual = $fn($test['data'], $test['options']);
        $this->assertEquals($test['expected'], $actual);
    }

    /**
     * @dataProvider specProvider
     */
    public function testLegacyVM($test)
    {
        if( in_array($test['name'], self::$skipTests) || in_array($test['name'], self::$skipLegacyVMTests) ) {
            $this->markTestIncomplete();
        }
        if( $test['description'] === 'decorators' ||
                !empty($test['decorators']) ||
                $test['description'] === 'inline partials' ) {
            $this->markTestIncomplete("The VM does not support decorators in export mode - requires custom compiler option");
        }
        $test = $this->prepareTestData($test);

        if( !empty($test['exception']) ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'vm');

        $globalPartials = array();
        foreach( $test['globalPartialOpcodes'] as $name => $partialOpcode ) {
            $globalPartials[$name] = new \Handlebars\VM\Runtime($handlebars, $this->convertContext($partialOpcode));
        }
        $handlebars->registerPartials($globalPartials);

        $partials = array();
        foreach( $test['partialOpcodes'] as $name => $partialOpcode ) {
            $partials[$name] = new \Handlebars\VM\Runtime($handlebars, $this->convertContext($partialOpcode));
        }
        $test['options']['partials'] = $partials;

        $allOptions = array_merge($test['compileOptions'], $test['options']);

        $vm = new \Handlebars\VM\Runtime($handlebars, $this->convertContext($test['opcodes']));
        $actual = $vm($test['data'], $allOptions);
        $this->assertEquals($test['expected'], $actual);
    }

    // Note: New VM doesn't have a way of specifying opcodes (yet)

    public function specProvider($testName)
    {
        $dir = getenv('HANDLEBARS_EXPORT_DIR');
        if( !$dir ) {
            $dir = __DIR__ . '/../vendor/jbboehr/handlebars-spec/export';
        }

        $tests = array();
        foreach( scandir($dir) as $file ) {
            if( $file[0] === '.' || substr($file, -5) !== '.json' ) continue;
            $suiteName = substr($file, 0, -strlen('.json'));
            $filePath = $dir . '/' . $file;
            if( in_array($suiteName, $this->specialSuites)/* !== in_array($testName, array('testParser', 'testTokenizer'))*/ ) {
                continue;
            }
            $i = 0;
            foreach( json_decode(file_get_contents($filePath), true) as $test ) {
                $i++;
                $test['suiteName'] = $suiteName;
                $test['number'] = $i;
                $test['name'] = $name = sprintf('%s - %s - %s', $test['suiteName'], $test['description'], $test['it']);
                $tests[$name] = array($test);
            }
        }
        return $tests;
    }

    protected function getDataSetAsString($includeData = true)
    {
        $out =  ' | ' . $this->dataName;
        if( $includeData ) {
            $test = $this->data[0];
            $out .= "\n";
            foreach( $test as $k => $v ) {
                if( $k === 'name' || $k === 'opcodes' || $k === 'partialOpcodes' || $k === 'ast' || $k === 'partialAsts' ) continue;
                if( !is_scalar($v) ) {
                    $v = json_encode($v);
                }
                $out .= $k . ': ' .  $v . "\n";
            }
        }
        return $out;
    }

    protected function handlebarsFactory($test, $mode = null)
    {
        $handlebars = new \Handlebars\Handlebars(array('mode' => $mode));
        $globalHelpers = (array) $this->convertCode($test['globalHelpers']);
        $globalPartials = (array) $this->convertCode($test['globalPartials']);
        $globalDecorators = (array) $this->convertCode($test['globalDecorators']);
        $handlebars->registerHelpers($globalHelpers);
        $handlebars->registerPartials($globalPartials);
        $handlebars->registerDecorators($globalDecorators);
        return $handlebars;
    }

    protected function prepareTestData($test)
    {
        $test = array_merge(array(
            'data' => null,
            'helpers' => array(),
            'partials' => array(),
            'decorators' => array(),
            'globalHelpers' => array(),
            'globalPartials' => array(),
            'globalDecorators' => array(),
            'exception' => false,
            'message' => null,
            'compileOptions' => array(),
            'options' => array(),
            'opcodes' => array(),
            'partialOpcodes' => array(),
            'globalPartialOpcodes' => array(),
        ), $test);
        $test['compileOptions']['data'] = true; // @todo fix
        $test['data'] = $this->convertCode($test['data']);
        $test['helpers'] = (array) $this->convertCode($test['helpers']);
        $test['partials'] = (array) $this->convertCode($test['partials']);
        $test['decorators'] = (array) $this->convertCode($test['decorators']);
        if( isset($test['options']['data']) ) {
            $test['options']['data'] = $this->convertCode($test['options']['data']);
        }
        $test['options']['helpers'] = $test['helpers'];
        $test['options']['partials'] = $test['partials'];
        $test['options']['decorators'] = $test['decorators'];
        return $test;
    }
}
