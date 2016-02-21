#!/usr/bin/env php
<?php

// Try to disable xdebug >.>
if( extension_loaded('xdebug') ) {
    echo "Xdebug is loaded, trying to re-run with bare configuration\n";
    // Find the json and handlebars modules
    $command = 'php -n -d display_errors=On -d error_reporting=E_ALL ';
    $extensionDir = ini_get('extension_dir');
    if( file_exists($extensionDir . '/json.so') ) {
        $command .= "-d 'extension=" . $extensionDir . "/json.so' ";
    }
    if( file_exists($extensionDir . '/handlebars.so') ) {
        $command .= "-d 'extension=" . $extensionDir . "/handlebars.so' ";
    }
    if( extension_loaded('xhprof') && file_exists($extensionDir . '/xhprof.so') ) {
        $command .= "-d 'extension=" . $extensionDir . "/xhprof.so' ";
    }
    $command .= ' ' . __FILE__;
    $command .= ' ' . join(' ', array_map('escapeshellarg', $argv));
    //echo $command, "\n";
    passthru($command);
    exit(0);
}

if( extension_loaded('xhprof') ) {
    xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY);
}

require __DIR__ . '/../vendor/autoload.php';

$tests = json_decode(file_get_contents(__DIR__ . '/../vendor/jbboehr/handlebars-spec/spec/bench.json'), true);
$count = 500;
$results = array();
$table = new Console_Table;

function runCompiled($test) {
    global $count;
    
    $test['mode'] = 'compiler';
    
    $expected = $test['expected'];
    $tmpl = $test['template'];
    $data = isset($test['data']) ? evalLambdas($test['data']) : null;
    $helpers = isset($test['helpers']) ? evalLambdas($test['helpers']) : null;
    $partials = isset($test['partials']) ? $test['partials'] : null;
    $options = isset($test['compileOptions']) ? $test['compileOptions'] : null;

    // @todo fix this
    $options['data'] = true;
    
    $handlebars = new \Handlebars\Handlebars();
    $fn = $handlebars->compile($tmpl, $options);
    
    // Compile partials in advance
    if( !empty($partials) ) {
        foreach( $partials as $name => $partial ) {
            $partials[$name] = $handlebars->compile($partial, $options);
        }
    }
    
    $start = microtime(true);
    for( $i = 0; $i < $count; $i++ ) {
        $actual = $fn($data, array(
            'helpers' => $helpers,
            'partials' => $partials,
        ));
    }
    $end = microtime(true);
    
    if( $actual !== $expected ) {
        throw new \Exception('Test output mismatch');
    }
    
    return $end - $start;
}

function runVM($test) {
    global $count;
    
    $test['mode'] = 'vm';
    
    $expected = $test['expected'];
    $tmpl = $test['template'];
    $data = isset($test['data']) ? evalLambdas($test['data']) : null;
    $helpers = isset($test['helpers']) ? evalLambdas($test['helpers']) : null;
    $partials = isset($test['partials']) ? $test['partials'] : null;
    $options = isset($test['compileOptions']) ? $test['compileOptions'] : null;
    
    $handlebars = new \Handlebars\Handlebars(array('mode' => \Handlebars\Handlebars::MODE_VM));
    
    $start = microtime(true);
    for( $i = 0; $i < $count; $i++ ) {
        $actual = $handlebars->render($tmpl, $data, array(
            'helpers' => $helpers,
            'partials' => $partials,
        ));
    }
    $end = microtime(true);
    
    if( $actual !== $expected ) {
        throw new \Exception('Test output mismatch');
    }
    
    return $end - $start;
}

function runCVM($test) {
    global $count;

    $test['mode'] = 'cvm';

    $expected = $test['expected'];
    $tmpl = $test['template'];
    $data = isset($test['data']) ? evalLambdas($test['data']) : null;
    $helpers = isset($test['helpers']) ? evalLambdas($test['helpers']) : array();
    $partials = isset($test['partials']) ? $test['partials'] : array();
    $options = isset($test['compileOptions']) ? $test['compileOptions'] : array();

    $handlebars = new \Handlebars\Handlebars(array('mode' => \Handlebars\Handlebars::MODE_CVM));
    // @todo allow to be passed in
    $handlebars->registerHelpers($helpers);
    $handlebars->registerPartials($partials);

    $start = microtime(true);
    for( $i = 0; $i < $count; $i++ ) {
        $actual = $handlebars->render($tmpl, $data/*, array(
            'helpers' => $helpers,
            'partials' => $partials,
        )*/);
    }
    $end = microtime(true);

    if( $actual !== $expected ) {
        throw new \Exception('Test output mismatch');
    }

    return $end - $start;
}

function addResult($test, $delta, $mode) {
    global $count, $results;
    $result = array(
        'title' => $test['it'] . ' (' . $mode . ')',
        'count' => $count,
        'total' => sprintf("%g", $delta),
        'average' => sprintf("%g", ($delta) / $count * 1000),
        'opsPerMillis' => sprintf("%g", $count / ($delta) / 1000),
    );
    $results[] = $result;
}

$doCompiler = in_array('compiler', $argv);
$doVM = in_array('vm', $argv);
$doCVM = in_array('cvm', $argv);
$noFilter = !$doCompiler && !$doVM && !$doCVM;

foreach( $tests as $test ) {
    if( $noFilter || $doCompiler ) {
        addResult($test, runCompiled($test), 'compiler');
    }
    if( $noFilter || $doVM ) {
        addResult($test, runVM($test), 'vm');
    }
    if( $noFilter || $doCVM ) {
        addResult($test, runCVM($test), 'cvm');
    }
}

echo $table->fromArray(array(
    'Test', 'Runs', 'Total (s)', 'Average (ms)', 'Ops/msec'
), $results);

function evalLambdas(&$arr) {
    if( is_array($arr) ) {
        foreach( $arr as $k => $v ) {
            if( !is_array($v) ) {
                continue;
            }
            if( isset($v['!code']) && isset($v['php']) ) {
                $arr[$k] = eval('return ' . $v['php'] . ';');
            } else {
                evalLambdas($v);
            }
        }
    }
    return $arr;
}

if( extension_loaded('xhprof') ) {
    $xhprof_data = xhprof_disable();
    $xhprof_runs = new XHProfRuns_Default(sys_get_temp_dir());
    $run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_handlebars");
    echo "Run ID: ", $run_id, "\n";
}