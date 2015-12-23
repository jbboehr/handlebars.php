#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

// Process arguments
$options = array_merge(array(
    'handlebars-spec' =>  __DIR__ . '/vendor/jbboehr/handlebars-spec/',
    'mustache-spec' =>  __DIR__ . '/vendor/jbboehr/mustache-spec/',
), getopt('', array(
    'handlebars-spec:',
    'mustache-spec',
    'help'
)));

if( isset($options['help']) ) {
    echo "Usage: generate-tests.php [--handlebars-spec <path>] [--mustache-spec <path>]\n";
    exit(0);
}
$handlebarsSpecDir = $options['handlebars-spec'];
$mustacheSpecDir = $options['mustache-spec'];
$specialSuites = array('parser', 'tokenizer', 'delimiters', '~lambdas');

// List files
$exportDir = $handlebarsSpecDir . '/export/';
$exportFiles = array();
foreach( scandir($exportDir) as $file ) {
    if( $file[0] === '.' || substr($file, -5) !== '.json' ) {
        continue;
    }
    $exportFiles[] = $exportDir . $file;
}

// Generate tests
foreach( $exportFiles as $filePath ) {
    $fileName = basename($filePath);
    $suiteName = substr($fileName, 0, -strlen('.json'));
    if( in_array($suiteName, $specialSuites) ) {
        continue;
    }
    
    $tests = json_decode(file_get_contents($filePath), true);
    
    if( !$tests ) {
        trigger_error("No tests in file: " . $file, E_USER_WARNING);
        continue;
    }
    
    // VM (generator)
    $vmGenerator = new \Handlebars\Tests\VMGenerator(array(
        'specName' => 'Handlebars',
        'suiteName' => $suiteName,
        'ns' => 'VM',
    ));
    $vmGenerator->write($vmGenerator->generate($tests));
    
    // Compiler (generator)
    $compilerGenerator = new \Handlebars\Tests\CompilerGenerator(array(
        'specName' => 'Handlebars',
        'suiteName' => $suiteName,
        'ns' => 'Compiler',
    ));
    $compilerGenerator->write($compilerGenerator->generate($tests));
}



// Mustache tests
$mustacheDir = $mustacheSpecDir . '/specs/';
$mustacheFiles = array();
foreach( scandir($mustacheDir) as $file ) {
    if( $file[0] === '.' || substr($file, -5) !== '.json' ) {
        continue;
    }
    $mustacheFiles[] = $mustacheDir . $file;
}

// Generate tests
foreach( $mustacheFiles as $filePath ) {
    $fileName = basename($filePath);
    $suiteName = substr($fileName, 0, -strlen('.json'));
    if( in_array($suiteName, $specialSuites) ) {
        continue;
    }
    
    $tests = json_decode(file_get_contents($filePath), true);
    
    if( !$tests ) {
        trigger_error("No tests in file: " . $file, E_USER_WARNING);
        continue;
    }
    
    // VM (generator)
    $vmGenerator = new \Handlebars\Tests\VMGenerator(array(
        'specName' => 'Mustache',
        'suiteName' => $suiteName,
        'ns' => 'VM',
    ));
    $vmGenerator->write($vmGenerator->generate($tests['tests']));
    
    // Compiler (generator)
    $compilerGenerator = new \Handlebars\Tests\CompilerGenerator(array(
        'specName' => 'Mustache',
        'suiteName' => $suiteName,
        'ns' => 'Compiler',
    ));
    $compilerGenerator->write($compilerGenerator->generate($tests['tests']));
}