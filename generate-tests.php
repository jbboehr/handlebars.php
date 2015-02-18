#!/usr/bin/env php
<?php

require __DIR__ . '/tests/generate-utils.php';
require __DIR__ . '/tests/generate-compiler.php';
require __DIR__ . '/tests/generate-vm.php';

$exportDir = __DIR__ . '/spec/handlebars/export/';
$specialSuites = array('parser', 'tokenizer');



// List files
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
    $vmTestFile = hbs_generate_test_file('VM', $suiteName);
    $vmOutput = hbs_generate_vm_class($suiteName, $tests);
    hbs_generate_write_file($vmTestFile, $vmOutput);
}
