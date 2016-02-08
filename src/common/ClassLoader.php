<?php
/*==============================================================================
 *  Title      : Simple Class Loader
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\common;
/**
 * @brief Digger Class Loader
 * 
 * Spesial auto loader for all Digger components
 *
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * // Your application structure:
 * //
 * // /application_root/
 * //                ├─ src/ (PSR-4 class style)
 * //                │    │
 * //                │    ├── Class1.php
 * //                │    ├── Class2.php
 * //                │    ├── ...
 * //                │    └── ClassN.php 
 * //                │
 * //                └─ lib/ (PSR-0 class style)
 * //                     │
 * //                     └── vendor/
 * //                             └── package/
 * //                                     ├── Class1.php
 * //                                     ├── Class1.php
 * //                                     ├── ...
 * //                                     └── ClassN.php 
 * 
 * require_once 'ClassLoader.php';
 * 
 * // PSR-0:
 * digger\cradle\common\ClassLoader::register('/application_root', [ 'lib' ]);
 * 
 * // PSR-4:
 * digger\cradle\common\ClassLoader::register('/application_root', ["vendor\\package\\" => 'src/']);
 * 
 * // Both (PSR-0 and PSR-4):
 * digger\cradle\common\ClassLoader::register('/application_root', ['lib', "vendor\\package\\" => 'src/']);
 * 
 * ~~~
 */
class ClassLoader {

    /**
     * @var_ <i>string</i> 
     * Root path to search classes
     */
    public static $rootPath = null;

    /**
     * @var_ <i>array</i>
     * Search list to find classes in specific directories
     */
    public  static $searchList = ['lib', 'controllers', 'models'];
    
    private static $_loaderAlreadyRegistred = false;
    
    /**
     * To register autoloader
     * 
     * @param string $rootPath Root path to start search the classes
     */
    public static function register($rootPath, $searchList = null)
    {
        self::$rootPath = $rootPath;
        if ($searchList!==null) { self::$searchList = $searchList; }
        if (!self::$_loaderAlreadyRegistred) {
            spl_autoload_register(array(__CLASS__, 'load'));
            self::$_loaderAlreadyRegistred = true;
        }
    }
    
    /**
     * Function to load a class
     * 
     * @param  string  $class   Class name for class to load.
     * @return <i>boolean</i>   TRUE on success.
     */
    public static function load( $class )
    {
        $class = str_replace("\\", '/', $class);
        
        if (!self::$searchList)           { self::$searchList = ['']; }
        if (!is_array(self::$searchList)) { self::$searchList = [self::$searchList]; }
        
        foreach (self::$searchList as $key => $path) {
            
            $fileName = "";
            
            if (is_numeric($key)) {
            //--- PSR-0:
                if ($path) { $path .= '/'; }
                $fileName = self::$rootPath . '/' . $path . $class . ".php";
            } else if (is_string($key)) {
            //--- PSR-4:
                $key = str_replace("\\", '/', $key);
                $fileName = self::$rootPath . '/' . preg_replace('/^' . preg_quote($key, '/') . '/i', $path, $class) . ".php";
            }

            //--- Include the class file:
            if ($fileName && is_file($fileName)) {
              require_once $fileName;
              return true;
            }
            
        }
        
    return false;
    }
}