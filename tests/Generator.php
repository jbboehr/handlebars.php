<?php

namespace Handlebars\Tests;

use Handlebars\Utils;

abstract class Generator
{
    protected $className;
    protected $namespace;
    protected $outputFile;
    protected $skipTests = array();
    protected $suiteName;
    protected $specName;
    protected $usedNames;
    protected $mode;
    
    public function __construct(array $options)
    {
        $this->ns = $options['ns'];
        $this->suiteName = $options['suiteName'];
        $this->specName = $options['specName'];
        
        // Make namespace
        $this->namespace = 'Handlebars\\Tests\\Spec\\'
                . $options['ns'];
        
        // Make class name
        $this->className = ucfirst($this->specName)
            . str_replace(' ', '', ucwords(str_replace('-', ' ', $this->suiteName)))
            . 'Test';
        
        // Make output file
        if( !empty($options['outputFile']) ) {
            $this->outputFile = $options['outputFile'];
        } else {
            $this->outputFile = 'tests/Spec/' . $this->ns . '/' . $this->className . '.php';
        }
    }
    
    public function generate(array $tests)
    {
        $output = $this->generateHeader();
        
        foreach( $tests as $test ) {
            $this->patchTestObject($test);
            
            $ret = $this->generateTest($test);
            if( $ret ) {
                $output .= $ret;
            }
        }
        
        $output .= $this->generateFooter();
        return $output;
    }
    
    abstract protected function generateTest(array $test);
    
    public function write($contents)
    {
        // Make parent directory
        if( !is_dir(dirname($this->outputFile)) ) {
            mkdir(dirname($this->outputFile), 0755, true);
        }
        // Write file
        $ret = file_put_contents($this->outputFile, $contents);
        if( !$ret ) {
            throw new \Exception('Failed to write output file: ' . $this->outputFile);
        }
    }
    
    
    
    
    protected function generateHeader()
    {
        return <<<EOF
<?php
            
namespace {$this->namespace};

use \Handlebars\Handlebars;
use \Handlebars\PhpCompiler;
use \Handlebars\Runtime;
use \Handlebars\SafeString;
use \Handlebars\Utils;
use \Handlebars\Tests\Common;

class {$this->className} extends Common {

EOF;
    }
    
    protected function generateFooter()
    {
        return <<<EOF
}
EOF;
    }
    
    protected function generateFunctionHeader(array &$test)
    {
        $title = $this->generateTestTitle($test);
        $functionName = $this->generateFunctionName($test);
        return <<<EOF
    /**
     * {$title}
     */
    public function $functionName() {

EOF;
    }
    
    protected function generateFunctionFooter($test)
    {
        $parts = array();
        if( !empty($test['exception']) ) {
            //$parts[] = '$this->assertFalse(true);';
        } else {
            $parts[] = '$this->assertEquals($expected, $actual);';
        }
        return $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n"
            . $this->indent(1) . "}\n\n";
        /* return <<<EOF
    }

EOF; */
    }
    
    protected function generateFunctionName(array &$test)
    {
        if( isset($test['__functionName']) ) {
            return $test['__functionName'];
        }
        
        $title = $this->generateTestTitle($test);
        $functionName = 'test'
                . (isset($test['testMode']) ? ucfirst($test['testMode']) : '')
                .  str_replace(' ', '', ucwords(preg_replace('/[^a-zA-Z0-9]+/', ' ', $title)));
        if( isset($this->usedNames[$functionName]) ) {
            $id = ++$this->usedNames[$functionName];
        } else {
            $id = $this->usedNames[$functionName] = 1;
        }
        $functionName .= $id;
        return $test['__functionName'] = $functionName;
    }
    
    protected function generateTestTitle(array $test)
    {
        if( isset($test['desc']) && isset($test['name']) ) {
            return $test['name'] . ' - ' . $test['desc'];
        } else {
            return $test['description'] . ' - ' . $test['it'];
        }
    }
    
    protected function generateTestVars(array $test)
    {
        $parts = array();

        $data = isset($test['data']) ? $test['data'] : null;
        $this->convertLambdas($data);

        // Generate general test data
        $parts[] = '$data = ' . $this->indentVarExport(2, $data) . ";";
        $parts[] = '$tmpl = ' . $this->indentVarExport(2, $test['template']) . ";";
        $parts[] = '$expected = ' . $this->indentVarExport(2, $test['expected']) . ";";

        // Generate partials
        $partials = $test['partials'];
        if( !empty($test['globalPartials']) ) {
            foreach( $test['globalPartials'] as $k => $v ) {
                if( !isset($partials[$k]) ) {
                    $partials[$k] = $v;
                }
            }
            //$partials = array_merge($test['globalPartials'], $partials);
        }
        $parts[] = '$partials = ' . $this->indentVarExport(2, $partials) . ";";

        // Generate helpers
        $helpers = $test['helpers'];
        if( !empty($test['globalHelpers']) ) {
            $helpers += $test['globalHelpers'];
        }
        $this->convertLambdas($helpers);
        $parts[] = '$helpers = ' . $this->indentVarExport(2, $helpers) . ";";

        // Generate decorators
        $decorators = $test['decorators'];
        if( !empty($test['globalDecorators']) ) {
            $decorators += $test['globalDecorators'];
        }
        $this->convertLambdas($decorators);
        if( isset($decorators['inline']) ) { // @todo fixme - shouldn't be saved
            unset($decorators['inline']);
        }
        
        $parts[] = '$decorators = ' . $this->indentVarExport(2, $decorators) . ";";

        // Generate options - @todo merge compile and runtime options for now
        $parts[] = '$compileOptions = ' . $this->indentVarExport(2, isset($test['compileOptions']) ? $test['compileOptions'] : array()) . ";";

        $this->convertLambdas($test['options']);
        $parts[] = '$options = ' . $this->indentVarExport(2, isset($test['options']) ? $test['options'] : array()) . ";";

        $parts[] = '$allOptions = array_merge($compileOptions, $options);';


        $parts[] = '$handlebars = new Handlebars(array("mode" => ' . var_export($this->mode, true) . '));';

        // Register global helpers/partial
        if( !empty($test['testMode']) && $test['testMode'] == 'integration' ) {
            if( !empty($test['globalHelpers']) ) {
                $globalHelpers = $test['globalHelpers'];
                $this->convertLambdas($globalHelpers);
                $parts[] = '$handlebars->registerHelpers(' . $this->indentVarExport(2, $globalHelpers) . ');';
                unset($test['globalHelpers']); // maybe bad idea
            }
            if( !empty($test['globalPartials']) ) {
                $parts[] = '$handlebars->registerPartials(' . $this->indentVarExport(2, $test['globalPartials']) . ');';
                unset($test['globalPartial']); // maybe bad idea
            }
            if( !empty($test['globalDecorators']) ) {
                $globalDecorators = $test['globalDecorators'];
                $this->convertLambdas($globalDecorators);
                $parts[] = '$handlebars->registerDecorators(' . $this->indentVarExport(2, $globalDecorators) . ');';
                unset($test['globalDecorators']); // maybe bad idea
            }
        }
        
        // Generate opcodes
        if( !empty($test['testMode']) && $test['testMode'] == 'export' &&
                $this->specName !== 'Mustache' ) {
            // Generate opcodes
            $parts[] = '$opcodes = json_decode(' . $this->indentVarExport(2, json_encode($test['opcodes'])) . ", true);";

            // Generate partial opcodes
            $partialOpcodes = (isset($test['partialOpcodes']) ? $test['partialOpcodes'] : array());
            $globalPartialOpcodes = (isset($test['globalPartialOpcodes']) ? $test['globalPartialOpcodes'] : array());
            $partialOpcodes += $globalPartialOpcodes;
            $parts[] = '$partialOpcodes = json_decode(' . $this->indentVarExport(2, json_encode($partialOpcodes)) . ", true);";
        }
        
        // Add incomplete
        if( in_array($this->generateFunctionName($test), $this->skipTests) ) {
            $parts[] = '$this->markTestIncomplete();';
        }
        
        // Generate throws
        if( !empty($test['exception']) ) {
            $parts[] = $this->indent(2) . "\$this->setExpectedException('\\Handlebars\\Exception');";
        }

        return $this->indent(2) . join("\n" . $this->indent(2), $parts) . "\n";
    }
    
    
    
    
    
    
    protected function convertLambdas(&$data)
    {
        if( !is_array($data) ) {
            return;
        }
        foreach( $data as $k => $v ) {
            if( !is_array($v) ) {
                continue;
            }
            if( !empty($v['!code']) ) {
                $data[$k] = new ClosureHolder($v['php'] . '/*' . $v['javascript'] . '*/');
            } else {
                $this->convertLambdas($data[$k]);
            }
        }
    }
    
    protected function indent($n, $str = '')
    {
        return str_pad($str, $n * 4, ' ', STR_PAD_LEFT);
    }
    
    protected function indentVarExport($n, $var)
    {
        return str_replace("\n", "\n" . $this->indent($n), $this->varExport($var));
    }
    
    protected function patchTestObject(array &$test)
    {
        if( empty($test['helpers']) ) {
            $test['helpers'] = array();
        }
        if( empty($test['partials']) ) {
            $test['partials'] = array();
        }
        if( empty($test['decorators']) ) {
            $test['decorators'] = array();
        }
        if( !array_key_exists('expected', $test) ) {
            $test['expected'] = null;
        }
        if( $this->specName === 'Mustache' ) {
            $test['compileOptions']['compat'] = true;
        }
    }
    
    protected function varExport($var, $indent = 0)
    {
        if( $var instanceof ClosureHolder ) {
            return (string) $var;
        } else if( is_array($var) ) {
            if( empty($var) ) {
                return 'array()';
            } else {
                $output = "array(\n";
                $isNormalArray = Utils::isIntArray($var);
                foreach( $var as $k => $v ) {
                    $output .= $this->indent($indent + 1)
                            . (!$isNormalArray ? var_export($k, true)
                            . ' => ' : '' )
                            . $this->varExport($v, $indent + 1) . ",\n";
                }
                $output .= $this->indent($indent) . ')';
                return $output;
            }
        } else {
            $v = var_export($var, true);
            if( is_string($var) ) {
                $v = str_replace("\n", $v[0] . ' . "\n" . ' . $v[0], $v);
            }
            return $v;
        }
    }
}
