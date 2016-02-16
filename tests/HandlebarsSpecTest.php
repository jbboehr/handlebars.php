<?php

namespace Handlebars\Tests;

class HandlebarsSpecTest extends Common
{
    private $data;
    private $dataName;
    private $specialSuites = array('parser', 'tokenizer');
    static private $skipTests = array(
        'basic - basic context - compiling with a string context',
        'blocks - standalone sections - block standalone else sections can be disabled',
        'blocks - decorators - should fail when accessing variables from root',
        'helpers - block params - should take presednece over parent block params',
        'subexpressions - subexpressions - subexpressions can\'t just be property lookups',
    );
    static private $skipLegacyVMTests = array(
        'partials - inline partials - should override global partials'
    );
    static private $skipNewVMTests = array(
        'decorators',
        'partial blocks',
        'inline partials',
        'string params mode',
        'track ids',
        'track-ids',

        'regressions - Regressions - should support multiple levels of inline partials',
        'regressions - Regressions - GH-1089: should support failover content in multiple levels of inline partials',
        'regressions - Regressions - GH-1099: should support greater than 3 nested levels of inline partials',
        'subexpressions - subexpressions - in string params mode,',
        'subexpressions - subexpressions - as hashes in string params mode',
    );

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        $this->data = $data;
        $this->dataName = $dataName;
        parent::__construct($name, $data, $dataName);
    }

    public function setUp()
    {
        parent::setUp();
        if( !extension_loaded('handlebars') ) {
            return $this->markTestSkipped("Integration tests require the handlebars extension");
        }
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

        $handlebars = $this->handlebarsFactory($test);
        $fn = $handlebars->compile($test['template'], $test['compileOptions']);
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
        $test = $this->prepareTestData($test);

        if( !empty($test['exception']) ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'vm');
        $allOptions = array_merge($test['compileOptions'], $test['options']);
        $allOptions['alternateDecorators'] = true;
        $actual = $handlebars->render($test['template'], $test['data'], $allOptions);
        $this->assertEquals($test['expected'], $actual);
    }

    /**
     * @dataProvider specProvider
     */
    public function testNewVM($test)
    {
        if( in_array($test['name'], self::$skipTests) ||
                in_array($test['name'], self::$skipNewVMTests) ||
                in_array($test['description'], self::$skipNewVMTests) ||
                in_array($test['suiteName'], self::$skipNewVMTests) ) {
            $this->markTestIncomplete();
        }
        $test = $this->prepareTestData($test);

        if( !empty($test['exception']) ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'cvm');

        // @todo allow passing in
        $handlebars->registerHelpers($test['helpers']);
        $handlebars->registerPartials($test['partials']);
        unset($test['options']['helpers']);
        unset($test['options']['partials']);

        $allOptions = array_merge($test['compileOptions'], $test['options']);
        //$allOptions['alternateDecorators'] = true;
        $actual = $handlebars->render($test['template'], $test['data'], $allOptions);
        $this->assertEquals($test['expected'], $actual);
    }

    public function specProvider($testName)
    {
        $dir = getenv('HANDLEBARS_SPEC_DIR');
        if( !$dir ) {
            $dir = __DIR__ . '/../vendor/jbboehr/handlebars-spec/spec';
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
                if( $k === 'name' ) continue;
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
