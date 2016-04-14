<?php

namespace Handlebars\Tests;

use Handlebars\DefaultRegistry;

class MustacheSpecTest extends Common
{
    private $data;
    private $dataName;
    static private $skipSuites = array('delimiters', '~lambdas');
    static private $skipTests = array(
        'partials - Standalone Indentation - Each line of the partial should be indented before rendering.',
    );

    public function __construct($name = null, array $data = array(), $dataName = '') {
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
     * @param SpecTestModel $test
     * @dataProvider specProvider
     */
    public function testCompiler(SpecTestModel $test)
    {
        if( in_array($test->name, self::$skipTests) ) {
            $this->markTestIncomplete();
        }

        if( $test->exception ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'compiler');
        $fn = $handlebars->compile($test->template, array('compat' => true));
        $actual = $fn($test->getData());
        $this->assertEquals($test->expected, $actual);
    }

    /**
     * @param SpecTestModel $test
     * @dataProvider specProvider
     */
    public function testLegacyVM(SpecTestModel $test)
    {
        if( in_array($test->name, self::$skipTests) ) {
            $this->markTestIncomplete();
        }

        $handlebars = $this->handlebarsFactory($test, 'vm');
        $actual = $handlebars->render($test->template, $test->getData(), array('compat' => true));
        $this->assertEquals($test->expected, $actual);
    }

    /**
     * @param SpecTestModel $test
     * @dataProvider specProvider
     */
    public function testNewVM(SpecTestModel $test)
    {
        if( in_array($test->name, self::$skipTests) ) {
            $this->markTestIncomplete();
        }

        $handlebars = $this->handlebarsFactory($test, 'cvm');
        $actual = $handlebars->render($test->template, $test->getData(), array('compat' => true));
        $this->assertEquals($test->expected, $actual);
    }

    public function specProvider()
    {
        $dir = getenv('MUSTACHE_SPEC_DIR');
        if( !$dir ) {
            $dir = __DIR__ . '/../vendor/jbboehr/mustache-spec/specs';
        }

        $tests = array();
        foreach( scandir($dir) as $file ) {
            if( $file[0] === '.' || substr($file, -5) !== '.json' ) continue;
            $suiteName = substr($file, 0, -strlen('.json'));
            if( in_array($suiteName, self::$skipSuites) ) continue;
            $filePath = $dir . '/' . $file;
            $i = 0;
            $testData = json_decode(file_get_contents($filePath), true);
            foreach( $testData['tests'] as $test ) {
                $i++;
                // Convert to handlebars spec format
                $test['it'] = $test['desc'];
                $test['description'] = $test['name'];
                // Patch
                $test['suiteName'] = $suiteName;
                $test['number'] = $i;
                $test['name'] = $name = sprintf('%s - %s - %s', $test['suiteName'], $test['description'], $test['it']);
                $tests[$name] = array(new SpecTestModel($test));
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

    protected function handlebarsFactory(SpecTestModel $test, $mode = null)
    {
        $handlebars = new \Handlebars\Handlebars(array('mode' => $mode));
        $handlebars->setPartials(new DefaultRegistry($test->getAllPartials()));
        return $handlebars;
    }
}
