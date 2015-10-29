<?php

use Handlebars\Utils;

require __DIR__ . '/../vendor/autoload.php';

$count = 5000;

function execute($withExtension) {
    // Find the json and handlebars modules
    $command = 'php -n -d display_errors=On -d error_reporting=E_ALL ';
    $extensionDir = ini_get('extension_dir');
    if( file_exists($extensionDir . '/json.so') ) {
        $command .= "-d 'extension=" . $extensionDir . "/json.so' ";
    }
    if( extension_loaded('xhprof') && file_exists($extensionDir . '/xhprof.so') ) {
        $command .= "-d 'extension=" . $extensionDir . "/xhprof.so' ";
    }
    if( $withExtension ) {
        if (file_exists($extensionDir . '/handlebars.so')) {
            $command .= "-d 'extension=" . $extensionDir . "/handlebars.so' ";
        } else {
            echo "Extension not found\n";
            exit(1);
        }
    }
    $command .= ' ' . __FILE__;
    $command .= ' run';
    echo $command, "\n";
    ob_start();
    passthru($command);
    $res = ob_get_clean();
    return json_decode($res, true);
}

function runNameLookup($arr, $key) {
    global $count;

    $start = microtime(true);
    for( $i = 0; $i < $count; $i++ ) {
        $v = Utils::nameLookup($arr, $key);
    }
    $end = microtime(true);

    return $end - $start;
}

$results = array();

function addResult($name, $delta)
{
    global $count, $results;
    $name .=  (extension_loaded('handlebars') ? ' (ext)' : '');
    $result = array(
        'title' => $name,
        'count' => $count,
        'total' => sprintf("%g", $delta),
        'average' => sprintf("%g", ($delta) / $count * 1000),
        'opsPerMillis' => sprintf("%g", $count / ($delta) / 1000),
    );
    $results[$name] = $result;
}

function bench()
{
    global $results;

    addResult('nameLookup - Array - Hit', runNameLookup(array('foo' => 'bar'), 'foo'));
    addResult('nameLookup - Array - Miss', runNameLookup(array('foo' => 'bar'), 'baz'));
    addResult('nameLookup - Object - Hit', runNameLookup((object)array('foo' => 'bar'), 'foo'));
    addResult('nameLookup - Object - Miss', runNameLookup((object)array('foo' => 'bar'), 'baz'));
    addResult('nameLookup - ArrayAccess - Hit', runNameLookup(new ArrayObject(array('foo' => 'bar')), 'foo'));
    addResult('nameLookup - ArrayAccess - Miss', runNameLookup(new ArrayObject(array('foo' => 'bar')), 'baz'));
    addResult('nameLookup - Invalid', runNameLookup(null, 'baz'));

    echo json_encode($results);
    exit(0);
}

if( empty($argv[1]) || $argv[1] !== 'run' ) {
    $results = array_merge(execute(false), execute(true));
    ksort($results);
    $table = new Console_Table;
    echo $table->fromArray(array(
        'Test', 'Runs', 'Total (s)', 'Average (ms)', 'Ops/msec'
    ), $results);
    exit(0);
} else {
    bench();
}
