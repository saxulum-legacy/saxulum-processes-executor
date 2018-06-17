<?php

return [
    "target_php_version" => null,
    'directory_list' => [
        'src',
        'vendor/psr/log',
        'vendor/symfony/process'
    ],
    "exclude_analysis_directory_list" => [
        'vendor/'
    ],
    'plugins' => [
        'AlwaysReturnPlugin',
        'UnreachableCodePlugin',
        'DollarDollarPlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
    ],
];
