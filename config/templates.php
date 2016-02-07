<?php
/*==============================================================================
 *  Title      : Templates
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 01.09.2015
 *==============================================================================
 */

$basePath = dirname(__DIR__);

return [
        'templatesPath'   => $basePath . '/templates/app',
        'params' => [
            'doxygen_template' => 'templates/doxygen',
            'jsdoc_template'   => 'templates/jsdoc',
            'phpunit'          => 'vendor/bin/phpunit', // full path
        ],
];

