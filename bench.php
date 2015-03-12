#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

$tests = json_decode(file_get_contents(__DIR__ . '/spec/handlebars/spec/bench.json'), true);
$handlebars = new \Handlebars\Handlebars();
$count = 2000;

foreach( $tests as $test ) {
    $tmpl = $test['template'];
    $data = isset($test['data']) ? evalLambdas($test['data']) : null;
    $helpers = isset($test['helpers']) ? evalLambdas($test['helpers']) : null;
    $partials = isset($test['partials']) ? $test['partials'] : null;
    $options = isset($test['compileOptions']) ? $test['compileOptions'] : null;
    
    $fn = $handlebars->compile($tmpl, $partials, $options);
    
    $start = microtime(true);
    for( $i = 0; $i < $count; $i++ ) {
        //$actual = $handlebars->render($tmpl, $data, $helpers, $partials, $options);
        $actual = $fn($data, $helpers);
    }
    $end = microtime(true);
    
    if( $actual !== $test['expected'] ) {
        throw new \Exception('Test output mismatch');
    }
    
    printf("Test: %s - %s\n", $test['description'], $test['it']);
    printf("Total: %f\n", $end - $start);
    printf("Avg: %f\n", ($end - $start) / $count);
    printf("Ops/msec: %d\n", round($count / ($end - $start) / 1000));
}

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
