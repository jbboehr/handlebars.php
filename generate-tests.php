#!/usr/bin/env php
<?php

require __DIR__ . '/tests/generate-utils.php';
require __DIR__ . '/tests/generate-integration.php';
require __DIR__ . '/tests/generate-vm.php';

$specialSuites = array('parser', 'tokenizer', 'delimiters', '~lambdas');



// List files
$exportDir = __DIR__ . '/spec/handlebars/export/';
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
    
    // VM
    $vmTestFile = hbs_generate_test_file('VM', 'handlebars', $suiteName);
    $vmOutput = hbs_generate_vm_class('handlebars', $suiteName, $tests);
    hbs_generate_write_file($vmTestFile, $vmOutput);
    
    // Integration
    $integrationTestFile = hbs_generate_test_file('Integration', 'handlebars', $suiteName);
    $integrationOutput = hbs_generate_integration_class('handlebars', $suiteName, $tests);
    hbs_generate_write_file($integrationTestFile, $integrationOutput);
}



// Mustache tests
$mustacheDir = __DIR__ . '/spec/mustache/specs/';
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
    
    // VM
    //$vmTestFile = hbs_generate_test_file('VM', 'mustache', $suiteName);
    //$vmOutput = hbs_generate_vm_class('mustache', $suiteName, $tests['tests']);
    //hbs_generate_write_file($vmTestFile, $vmOutput);
    
    // Integration
    $integrationTestFile = hbs_generate_test_file('Integration', 'mustache', $suiteName);
    $integrationOutput = hbs_generate_integration_class('mustache', $suiteName, $tests['tests']);
    hbs_generate_write_file($integrationTestFile, $integrationOutput);
}