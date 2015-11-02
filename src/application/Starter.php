<?php
/*==============================================================================
 *  Title      : Starter
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\application;

use digger\cradle\common\Data;

/**
 * @brief Web application basic functions
 * 
 * Very small and simple web application core.
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Starter {

    /** Router request key */
    public static $routerKey = 'r';
    
    /** Sub title separator */
    public static $subTitleSeparator = ' | ';

    /** Array of text domains for translations */
    public static $textDomains = null;

    /** Flag =true if autoloader is included */
    private static $autoloader = false;
    
    /** Relative reverse path to web root */
    private static $uriRootBack = null;

    
    /**
     * Returns a file name of currently registred autoloader
     * 
     * @return string|false Full filename or false
     */
    public static function getAutoloaderFile() {
        return self::$autoloader;
    }

    /**
     * Returns array of parameters loaded from $configFile and include autoloader (if it's set)
     * 
     * @param  string        $config      full name of main configuration file
     * @param  boolean       $appInfo     Include (or not) application information (option). False by default.
     * @param  string|array  $assetsFiles [basename|list of basenames] of assets configuration file to include (option). Not included by default. 
     * @return array
     */
    public static function getConfig($config, $appInfo = false, $assetsFiles = null) {
        
        //--- Include config file:
        if (is_string($config)) { $config = require $config; }
        
        //--- Include autoloader:
        if (!self::$autoloader) {
            if ($config['autoLoader']) { 
                //--- Register autoloader defined by config:
                $autoLoader = $config['autoLoader'];
            } else { 
                //--- Register default own autoloader:
                $autoLoader = __DIR__ . '/../autoload.php';
            }
            $autoLoader = realpath($autoLoader);
            require_once $autoLoader;
            self::$autoloader = $autoLoader;
        }
        
        //--- Include application information:
        if ($appInfo && $config['appInfo'] && is_string($config['appInfo'])) { 
            $config['appInfo'] = require $config['appInfo']; 
        }
        if (!is_array($config['appInfo'])) { 
            $config['appInfo'] = []; 
        }
        
        //--- Include layout page assets: (silent)
        if ($assetsFiles) {
            $config['assets'] = self::getAssets($assetsFiles, $config['assetsPath']);
        }
        
        //--- Set page title:
        if (!$config['title']) {
            $config['title'] = $config['appInfo']['title'];
        }
        
        //-- Set page subtitle:
        if ($config['assets']['title']) {
            if ($config['title']) { $config['title'] .= self::$subTitleSeparator; }
            $config['title'] .= $config['assets']['title'];
        }
        
    return $config;    
    }
    
    /**
     * Gets the URI root path
     * 
     * @return string
     */
    public static function getUriRootBack() {
        if (self::$uriRootBack !== null) {
        //--- Get an existing:
            return self::$uriRootBack;
        }
        //--- Create current uriRootBack:
        self::$uriRootBack = "";
        $webDir  = dirname($_SERVER['SCRIPT_NAME']);
        $uriPath = $_SERVER['REDIRECT_URL'];
        $path    = ltrim(preg_replace('/^' . preg_quote($webDir, '/') . '/', '', $uriPath), "/\\");
        $path    = str_replace("\\", '/', $path);
        if (preg_match('/\/$/', $path)) {
            $path = rtrim($path, '/');
        } else {
            $path = trim(dirname($path), '.');
        }
        if ($path) {
            foreach (explode('/', $path) as $nothing) {
                self::$uriRootBack .= '../'; 
            }
        }
    return self::$uriRootBack;    
    }

    /**
     * Create relative to web root link
     * 
     * @param  string $link
     * @return string
     */
    public static function getRelativeLink($link) {
        if (preg_match('/^\w+:\/\//', $link)) {
            return $link;
        }
    return self::getUriRootBack() . $link;    
    }

    /**
     * Returns array of assets obtained from assets-files.
     * 
     * The method loads and combines multiple arrays into one.
     * 
     * @param  array $assetFiles List of files returns array of data.
     * @param string $assetPath (option) Path to find files with data.
     * @return array Merged arrays data.
     */
    public static function getAssets($assetFiles, $assetPath=null) {
        if (!is_array($assetFiles)) { $assetFiles = [$assetFiles]; }
        $allAssets = null;
        foreach ($assetFiles as $assetFile) {
            //--- Include asset files: (silent)
            if ($assetFile) {
                if ($assetPath) { $assetPath .= DIRECTORY_SEPARATOR; }
                $assetFile = $assetPath . $assetFile; 
                if (!file_exists($assetFile)) { continue; }
                $assets = require $assetFile;
                if (!is_array($assets))       { continue; }
                if (!is_array($allAssets))    { $allAssets = []; }
                
                Data::setArray($allAssets, $assets);
            }            
        }
        //--- Change base link to relative link for assets files (css, javascript)
        if (is_array($allAssets)) {
            $checkList = ['css', 'js'];
            foreach ($checkList as $target) {
                if (is_array($allAssets[$target])) {
                    foreach ($allAssets[$target] as $index => $link) {
                        $allAssets[$target][$index] = self::getRelativeLink($allAssets[$target][$index]);  
                    }
                }
            }
        }
        
    return $allAssets;   
    }
    
    /**
     * To set params of current text domain for translations
     * 
     * @param string $domain    The name of the text domain
     * @param string $rootDir   Root directory to find translations
     * @code
     * ~~~
     * folder structure:
     *   /$rootDir
     *         en/LC_MESSAGES/domainName.mo
     *         ru/LC_MESSAGES/domainName.mo
     * ~~~
     * @endcode
     * @param string $codeset UTF-8 by default.
     */
    public static function setTextDomain($domain, $rootDir, $codeset='UTF-8') {
        // Set root directory to find translations:
        bindtextdomain ($domain, $rootDir);
        // Set codeset:
        bind_textdomain_codeset($domain, $codeset ? $codeset : 'UTF-8');
        // Use text domain:
        textdomain ($domain);        
    }
    
    /**
     * Change current text domain for translation.
     * 
     * Property `self::$textDomains` mast be defined before:
     * 
     * @code
     * ~~~
     * self::$textDomains = [
     *   'text_domain1' => [
     *       'root'    => '/path/to/some_folder1',
     *       'codeset' => 'UTF-8',
     *   ],
     *   'text_domain2' => [
     *       'root'    => '/path/to/some_folder2',
     *       'codeset' => 'UTF-8',
     *   ],
     *   ...
     * ]
     * ~~~
     * @endcode
     * 
     * @param string $domain The name of the current text domain
     * 
     */
    public static function useTextDomain($domain) {
        if (isset(self::$textDomains[$domain])) {
            extract(self::$textDomains[$domain]);
            self::setTextDomain($domain, $root, $codeset);
        }
    }

        /**
     * Set application current language
     * 
     * @param  string $lang Value of HTML tag "lang" (en|ru|en-US|ru-RU|...)
     * @return string 
     */
    public static function setLanguage($lang) { 
        if (strlen($lang) < 3) {
            // Two letters lang (en|ru|...):
            $lang    = strtolower($lang);
            $country = ($lang == 'en') ? 'US' : strtoupper($lang);
            $lang   .= '_' . $country;
        } else {
            //--- Language pair (en-US|ru-RU):
            $lang = str_replace('-', '_', $lang);
        }
         //putenv("LANG=" . $lang);
    return setlocale(LC_ALL, $lang); //<-- "ru_RU"
    }


    /**
     * Start web application
     * 
     * Very simple application router.
     * It makes the following:
     * 
     *  - loads application config (and starts autoloader)
     *  - parse input request and define requested Controller and Action
     *  - include the Controller
     *  - define Layout file (from Controller->layout or config['layout'])
     *  - define View file (execute the Controller method which should return the View name, or gets the Action name if the method return nothing )
     *  - include Layout file. The Layout file may include the View file by call: ~~~include $contentFile;~~~
     * 
     * Inside the Layout and View files all configuration options are available as variables
     * 
     * @param string|array $config File name or array of params of application configuration.
     * 
     * @todo
     * Example:
     * @code
     * 
     * file: index.php
     * 
     * //--- Application base (root) directory:
     *   $basedir    = dirname(__DIR__);
     * //--- Configuration file:
     *   $configFile = $basedir . '/config/main.php';
     * //--- Include Starter class:
     *   require $basedir . '/lib/digger' . '/application/Starter.php';
     * //--- Start application:
     *   digger\cradle\application\Starter::startApp($configFile);
     * 
     * @endcode
     */
    public static function startApp($config) {
        
        //--- Load config:
        $config = self::getConfig($config, true);
        
        //--- Very simple router:
        $route = $_REQUEST[self::$routerKey];
        if (!$route) { $route = $config['defaultRoute']; }
        //--- get controller name and action:
        if (preg_match('/(.*)\/([^\/]+)$/', $route, $m)) {
            $controller = $m[1];
            $action     = $m[2];
        } else {
            $controller = trim($route, "\/");
        }
        
        //--- Set application language:
        if ($config['language']) { 
            self::setLanguage($config['language']);
        }
        //--- Set current text domain:
        self::$textDomains = $config['textDomains'];
        if (is_array(self::$textDomains)) {
            //--- use the first text domain by default:
            self::useTextDomain(array_shift(array_keys(self::$textDomains)));
        }
        //-----------------------
        
        try {
            
            //--- Very simple execute the Controller (mapped only):
            $controllerFile = $config['controllerMap'][$controller];
            if (!$controllerFile) {
                throw new \Exception (('Not found') . ": $controller");
            }
            
            //--- Include the Controller class:
            require_once $config['basePath'] . '/' . $controllerFile . '.php';
            $controllerClassName = basename($controllerFile);
            $controllerClass     = new $controllerClassName();
            
            //--- Set a layout:
            $layout        = $controllerClass->layout;
            if (!$layout)        { $layout = $config['layout']; }
            
            //--- Set an action:
            $defaultAction = $controllerClass->defaultAction;
            if (!$defaultAction) { $defaultAction = 'index'; }
            if (!$action)        { $action = $defaultAction; }
            $actionMethod = 'action' . ucwords($action);
            
            //--- Get View:
            if (method_exists($controllerClass, $actionMethod)) {
                $view = $controllerClass->$actionMethod();
            } else {
                throw new \Exception(_('Action not found'));
            }
            if (!$view) { $view = $action; }
            
            //--- Layout file:
            $layoutFile = $config['layoutPath'] . '/' . $layout . '.php';
            
            //--- View file:
            $controllerName = strtolower(preg_replace('/Controller$/i','', $controllerClassName));
            $viewFile   = $config['viewPath'] . '/' . $controllerName . '/' . $view . '.php';
            //echo "$layout : $action : $view || $layoutFile : $viewFile";
            
            //--- Include Layout:
            // extract config varibles:
            extract(self::getConfig($config, true, [basename($layoutFile), basename($viewFile)]), EXTR_OVERWRITE);
            // URI root back (may add a relative web root to any links in the view):
            $uriRootBack = self::getUriRootBack();
            // $contentFile may be included in Layout:
            $contentFile = $viewFile;
            // include:            
            require $layoutFile;
            //-------------------------- 
            
        } catch (\Exception $e) {
            echo $e->getMessage();
        }

    }
    
}
