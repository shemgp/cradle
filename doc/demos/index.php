<?php
/*==============================================================================
 *  Title      : Demo layout
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 05.10.2015
 *==============================================================================
 */

$baseDir    = dirname(dirname(__DIR__));
$configFile = $baseDir . '/config/main.php';

require $baseDir . '/src' . '/application/Starter.php';

extract(\digger\cradle\application\Starter::getConfig($configFile, true), EXTR_OVERWRITE);

$navigation = new \digger\cradle\application\FileNavigation(['excludePatterns' => '^' . basename(__FILE__) . '$']);
$navigationMenu = $navigation->getNavigation();
$contentFile    = $navigation->getCurrentItem();
$subTitle       = preg_replace('/\.\w+$/', '', basename($contentFile));
$topic          = _('Demo');

?><!DOCTYPE html>
<html lang="<?= $language ?>">
    <head>
        <title><?= $appInfo['title'] . ' : ' . $topic . ' : ' . $subTitle ?></title>

        <meta http-equiv="X-UA-Compatible" content="IE=Edge">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <link rel="icon"          type="image/vnd.microsoft.icon" href="favicon.ico" />
        <link rel="shortcut icon" type="image/vnd.microsoft.icon" href="favicon.ico" />

	<link href='../css/demo.css' rel='stylesheet' type='text/css'>
    </head>
    <body>
        <div class="container-fluid">
            <h1><?= $appInfo['title'] . ' : ' . $topic . ' : ' . $subTitle ?></h1>
            <div class="demo-nav">
                <?= $navigationMenu ?>
            </div>
            <div class='demo-page'>
                <?php include $contentFile; ?>
            </div>
        </div>
    </body>
</html>

