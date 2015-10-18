<?php
/*==============================================================================
 *  Title      : Creater
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\application;

use digger\cradle\common\Basic;
use digger\cradle\common\Files;

/**
 * @brief Creates some automation actions
 * 
 * This class is designed to automate the console operations
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Creater {
    
    /**
     * Output a log message
     * 
     * @param string $message text to out
     */
    private static function log($message, $type = null) {
        switch ($type) {
            case 'error':
                $message = _('ERROR') . ': ' . $message; 
                break;
            case 'warn':
                $message = _('WARNING') . ': ' . $message; 
                break;
            default:
                break;
        }
        echo $message . "\n";
    }
    
    /**
     * To create an application structure from templates
     * 
     * @param  string $config Array of config params. 
     * ~~~
     * $config = [
     *   'basePath'     => '/path/to/copy/template/files',
     *   'appTemplates' => [
     *      'templatesPath' => '/path/to/templates/directory',
     *      'params'        => [ an optional array of any extra params ],
     *   ],
     *   'appInfo' => [ an optional array of any extra params ]     
     * ]
     * ~~~
     * @return null
     */
    public static function createApplication($config) {
        //--- Check config:
        if (!is_array($config)) {
            self::log(_('Config mast be an array!'), 'error');
            return -1;
        }
        //--- Include application templates:
        if ($config['appTemplates'] && is_string($config['appTemplates'])) { 
            $config['appTemplates'] = require $config['appTemplates']; 
        }
        if (!is_array($config['appTemplates'])) { 
            self::log(_('appTemplates mast be an array!'), 'error');
            return -1;
        }
        //--- Extract and prepare expected params:
        $params = array_merge(
                Basic::pathValue($config['appInfo'], null, '[', ']'),
                Basic::pathValue($config['appTemplates']['params'], null, '[', ']')
                );
        //print_r($params);
        
        //--- Get Application templates structure:
        $applicationBase = $config['basePath'];
        $templateBase    = $config['appTemplates']['templatesPath'];
        $templateFiles   = Files::glob($templateBase, '{,.}*', GLOB_BRACE, true);
        foreach ($templateFiles as $srcFile) {
            $file    = preg_replace('/^' . preg_quote($templateBase . '/', '/') . '/', '', $srcFile);
            $dstFile = $applicationBase . '/' . $file; 
            $result  = null;
             try {
                //--- Skip an exists file(dir):
                if (file_exists($dstFile)) {
                    throw new \Exception(_('SKIPPED') . ' ' . _('destination is exists'), -2);
                }
                //--- Copy file:
                if (is_file($srcFile)) {
                    $dstDir = dirname($dstFile);
                    if (!file_exists($dstDir) && !mkdir($dstDir, 0775, true)) { 
                        throw new \Exception(_('cant create a directory'));
                    }
                    //--- Load source content:
                    $content = file_get_contents($srcFile);
                    //--- Replace shortcodes [...key...] to values:
                    $content = strtr($content, $params);
                    //--- Save content to destination:
                    if (file_put_contents($dstFile, $content) === false) {
                        throw new \Exception(_('cant copy a file'));
                    }
                    $message = _('OK') . ' ' . _('file copied');
                }
                //--- Create a directory:
                if (is_dir($srcFile)) {
                    if (mkdir($dstFile, 0775, true)) {
                        $message = _('OK') . ' ' . _('directory created');
                    } else {
                        throw new \Exception(_('cant create a directory'));
                    }
                }
            } catch (\Exception $e) {
                $message = $e->getMessage();
                $result  = 'error';
                if ($e->getCode() == -2) { $result  = 'warn'; }
            }
                       
            self::log( _('source') . ': [' . $file . "]\t" . $message . "\t[" . $dstFile . ']', $result);           
        }
            
    }
}
