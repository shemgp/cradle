<?php
/*==============================================================================
 *  Title      : Common automation initialization
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 06.10.2015
 *==============================================================================
 */
//--- Main params:

$baseDir    = dirname(__DIR__);
$configFile = $baseDir . '/config/main.php';

//--- Load main configuration and start autoloader:

require_once $baseDir . '' . '/src/application/Starter.php';

//--- Return config array:

return \digger\cradle\application\Starter::getConfig($configFile, true);

