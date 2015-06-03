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
  'compat::',
  'string-params::',
  'track-ids::',
  'use-depths::',
  'known-helpers-only::',
  'disable-js-compat::',
  'disable-native-runtime::'
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
    'knownHelpersOnly' => isset($opts['known-helpers-only']),
    'disableJsCompat' => isset($opts['disable-js-compat']),
    'disableNativeRuntime' => isset($opts['disable-native-runtime']),
);

if( isset($opts['compile']) ) {
    // compile
    if( isset($opts['j']) ) {
        echo json_encode(handlebars_compile($template, $compileOptions), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(handlebars_compile($template, $compileOptions));
    } else {
        echo handlebars_compile_print($template, $compileOptions);
    }
} else if( isset($opts['parse']) ) {
    // parse
    if( isset($opts['j']) ) {
        echo json_encode(handlebars_parse($template), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(handlebars_parse($template));
    } else {
        echo handlebars_parse_print($template);
    }
} else if( isset($opts['lex']) ) {
    // lex
    if( isset($opts['j']) ) {
        echo json_encode(handlebars_lex($template), constant('JSON_PRETTY_PRINT'));
    } else if( isset($opts['p']) ) {
        var_export(handlebars_lex($template));
    } else {
        echo handlebars_lex_print($template);
    }
} else {
    // precompile
    $handlebars = new \Handlebars\Handlebars();
    echo $handlebars->precompile($template, $compileOptions);
}
