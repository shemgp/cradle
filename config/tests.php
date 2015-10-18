<?php
/*==============================================================================
 *  Title      : Application automatic tests generator's config
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 06.10.2015
 *==============================================================================
 */
return [
    "sourcePaths"     => ['src'],
    "sourcePatterns"  => ['\.php$'],   
    "sourceExclude"   => ['^_', 'Test\.php$'],
    "sourceRecursive" => true,    
    
    "destinationRoot" => $basePath . '/tests',
    "destinationTree" => true,
        "runFileName" => 'run.sh',
        "phpUnitFile" => $basePath . '/vendor/bin/phpunit',  
];

