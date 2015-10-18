<?php
/*==============================================================================
 *  Title      : Tests layout
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 06.10.2015
 *==============================================================================
 */

$baseDir    = dirname(__DIR__);
$configFile = $baseDir . '/config/main.php';
$topic      = _('Tests');

require $baseDir . '/src' . '/application/Starter.php';

$config = \digger\cradle\application\Starter::getConfig($configFile, true);
extract($config, EXTR_OVERWRITE);

$appTests = require $config['appTests'];
$results  = new digger\cradle\tests\PutResults($appTests['destinationRoot']);

?><!DOCTYPE html>
<html lang="<?= $language ?>">
    <head>
        <title><?= $appInfo['title'] . ' : ' . $topic ?></title>

        <meta http-equiv="X-UA-Compatible" content="IE=Edge">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon"          type="image/vnd.microsoft.icon" href="favicon.ico" />
        <link rel="shortcut icon" type="image/vnd.microsoft.icon" href="favicon.ico" />

	<link  href='css/tests.css' rel='stylesheet' type='text/css'>
        <script src='css/jquery.js'></script>
        
    </head>
    <body>
        <div class="container-fluid">
            <h1><?= $appInfo['title'] . ' : ' . $topic ?></h1>
            <div>
                <?= $results->toHtml() ?>
                <?= $results->getJsContent() ?>
            </div>
        </div>
    </body>
</html>

