<?php

namespace Handlebars\Tests;

use Handlebars\Handlebars;
use Handlebars\DefaultRegistry;
use Handlebars\Compiler\PhpCompiler;
use Handlebars\Compiler\Runtime as CompilerRuntime;
use Handlebars\VM\Runtime as VMRuntime;

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
        $compiler = new PhpCompiler();
        $templateSpecStr = $compiler->compile($test->getOpcodes(), $test->compileOptions);

        if( false ) {
            $file = tempnam(sys_get_temp_dir(), 'HandlebarsTestsCache');
            file_put_contents($file, '<?php return ' . $templateSpecStr . ';');
            $templateSpec = include $file;
        } else {
            $templateSpec = eval('return ' . $templateSpecStr . ';');
            if( !$templateSpec ) {
                echo $templateSpecStr;
            };
        }

        $fn = new CompilerRuntime($handlebars, $templateSpec);
        $actual = $fn($test->getData(), $test->getOptions());
        $this->assertEquals($test->expected, $actual);
    }

    /**
     * @param SpecTestModel $test
     * @dataProvider specProvider
     */
    public function testLegacyVM(SpecTestModel $test)
    {
        if( in_array($test->name, self::$skipTests) || in_array($test->name, self::$skipLegacyVMTests) ) {
            $this->markTestIncomplete();
        }
        if( $test->description === 'decorators' ||
                !empty($test->decorators) ||
                $test->description === 'inline partials' ) {
            $this->markTestIncomplete("The VM does not support decorators in export mode - requires custom compiler option");
        }

        if( $test->exception ) {
            $this->setExpectedException('\\Handlebars\\Exception');
        }

        $handlebars = $this->handlebarsFactory($test, 'vm');

        $vm = new VMRuntime($handlebars, $test->getOpcodes());
        $actual = $vm($test->getData(), $test->getAllOptions());
        $this->assertEquals($test->expected, $actual);
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
                if( $k === 'name' || $k === 'opcodes' || $k === 'partialOpcodes' || $k === 'ast' || $k === 'partialAsts' ) continue;
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
        $partialRegistry = new DefaultRegistry();
        $handlebars = Handlebars::factory(array(
            'mode' => $mode,
            'helpers' => new DefaultRegistry($test->getAllHelpers()),
            'partials' => $partialRegistry,
            'decorators' => new DefaultRegistry($test->getAllDecorators()),
        ));
        if( $mode === 'compiler' ) {
            $compiler = new PhpCompiler();
            foreach( $test->getAllPartialOpcodes() as $name => $partialOpcodes ) {
                $partialRegistry[$name] = new CompilerRuntime(
                    $handlebars,
                    eval('return ' . $compiler->compile($partialOpcodes, $test->compileOptions) . ';')
                );
            }
        } else if( $mode === 'vm' ) {
            foreach( $test->getAllPartialOpcodes() as $name => $partialOpcodes ) {
                $partialRegistry[$name] = new VMRuntime($handlebars, $partialOpcodes);
            }
        } else {
            throw new \Exception('Unknown mode: ' . $mode);
        }
        $handlebars->setPartials($partialRegistry);
        return $handlebars;
    }
}
