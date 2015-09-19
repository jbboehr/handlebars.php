#!/usr/bin/env php
<?php

foreach( array(__DIR__ . '/../../autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/vendor/autoload.php') as $file ) {
    if( file_exists($file) ) {
        require $file;
        break;
    }
}

$opts = getopt('t:jp', array(
  'template:',
  'compile',
  'lex',
  'parse',
  'known-helpers:',
  'compat::',
  'string-params::',
  'track-ids::',
  'use-depths::',
  'known-helpers-only::',
  'disable-js-compat::',
  'disable-native-runtime::',
  'ignore-standalone::',
));

if( isset($opts['t']) ) {
    $templateFile = $opts['t'];
} else if( isset($opts['template']) ) {
    $templateFile = $opts['template'];
} else {
    echo "No input template\n";
    exit(1);
}
if( $templateFile === '-' ) {
    $template = '';
    while( false !== ($line = fgets(STDIN)) ) {
        $template .= $line;
    }
} else {
    $template = file_get_contents($templateFile);
}

$compileOptions = array(
    'compat' => isset($opts['compat']),
    'stringParams' => isset($opts['string-params']),
    'trackIds' => isset($opts['track-ids']),
    'useDepths' => isset($opts['use-depths']),
    'knownHelpers' => isset($opts['known-helpers']) ? explode(',', $opts['known-helpers']) : null,
    'knownHelpersOnly' => isset($opts['known-helpers-only']),
    'disableJsCompat' => isset($opts['disable-js-compat']),
    'disableNativeRuntime' => isset($opts['disable-native-runtime']),
    'ignoreStandalone' => isset($opts['ignore-standalone']),
);
$handlebars = new \Handlebars\Handlebars();
$compiler = new \Handlebars\Compiler();

if( isset($opts['compile']) ) {
    $flags = $compiler->makeCompilerFlags($compileOptions);
    
    // compile
    if( isset($opts['j']) ) {
        echo json_encode(\Handlebars\Native::compile($template, $flags), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(\Handlebars\Native::compile($template, $flags));
    } else {
        echo \Handlebars\Native::compilePrint($template, $flags);
    }
} else if( isset($opts['parse']) ) {
    // parse
    if( isset($opts['j']) ) {
        echo json_encode(\Handlebars\Native::parse($template), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(\Handlebars\Native::parse($template));
    } else {
        echo \Handlebars\Native::parsePrint($template);
    }
} else if( isset($opts['lex']) ) {
    // lex
    if( isset($opts['j']) ) {
        echo json_encode(\Handlebars\Native::lex($template), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(\Handlebars\Native::lex($template));
    } else {
        echo \Handlebars\Native::lexPrint($template);
    }
} else {
    // precompile
    echo $handlebars->precompile($template, $compileOptions);
}
