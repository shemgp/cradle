<?php
/*==============================================================================
 *  Title      : Digger File Class
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\common;

use digger\cradle\common\Basic;

/**
 * @brief Digger File Class
 * 
 * Class for file operations
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Files {

    /**
     * Extends PHP glob with recursion
     * 
     * @param  string  $path        Path to search files.
     * @param  string  $mask        File mask. <i>Note: ::glob('/my/path' ,'{,.}*', GLOB_BRACE, true) - will output system hidden files.</i>
     * @param  int     $flags       PHP glob flags.
     * @param  boolean $recursive   Search files recursively.
     * @return array                An array containing the matched files/directories, an empty array if no file matched or FALSE on error. (Such as php glob).
     * 
     * Example:
     * @code
     * 
     * print_r( Files::glob("/some/path") );
     * 
     * //-------- 
     * // Return:
     * 
     * Array (
     *      [0] => "/some/path/file1",
     *      [1] => "/some/path/file2",
     *      ...
     * )
     * @endcode
     */
    public static function glob( $path, $mask="*", $flags=NULL, $recursive=false ) {
        if (($files = glob($path . "/" . $mask, $flags)) === false) { 
            return false;
        }    
        $out = array();
        foreach ($files as $file) {
            $base_name = basename($file);
            if ($base_name == '.' || $base_name == '..') { continue; } // skip . and .. 
            $out[] = $file;
            if ($recursive && is_dir($file)) {
                $glob  = self::glob($file, $mask, $flags, $recursive);
                if (!empty($glob)) { 
                    $out = array_merge($out, $glob);
                }
            }
        }
    return $out;    
    }

    /**
     * Extends PHP glob with recursion and base path (files only)
     * 
     * @param  string|array $paths           Paths to find files (base paths). 
     *                                       Default = system current directory.
     * @param  string|array $filePatterns    RegExp patterns to filter files (to find files). 
     *                                       Default = "" - all files.
     * @param  string|array $excludePatterns RegExp patterns to filter files (to ignore files). 
     *                                       Default = NULL - no files to exclusion.
     * @param  boolean      $recursive       =TRUE - find files in sub directories. 
     *                                       Default = TRUE.
     * @return array                         A hash array of found files. Hash structure:
     * @code
     *  array( 
     *      "/absolute/file/name1" => "/base/path" 
     *      "/absolute/file/name2" => "/base/path" 
     *      ...
     * )
     * @endcode
     * 
     * Exapmle:
     * @code
      
       print_r( Files::globFiles('/base/path/', '\.php$') );

       //-------- 
       // Return:
      
       Array (
            '/base/path/file1.php'                                  => '/base/path',
            '/base/path/file2.php'                                  => '/base/path',
            '/base/path/folder1/file1_in_folder1.php'               => '/base/path',
            '/base/path/folder1/file2_in_folder1.php'               => '/base/path',
            '/base/path/folder1/subfolder1/file1_in_subfolder1.php' => '/base/path',
       )

     * @endcode
     */
    public static function globFiles( $paths=array(''), $filePatterns=array(''), $excludePatterns=NULL, $recursive=true ) {
        //--- Convert $paths to array:
        if (!is_array($paths)) {
            $paths        = array($paths);
        }
        //--- Convert $filePatterns to array:
        if (!is_array($filePatterns)) {
            $filePatterns = array($filePatterns); 
        }
        //--- Convert $excludePatterns to array:
        if ($excludePatterns && !is_array($excludePatterns)) {
            $excludePatterns = array($excludePatterns);
        }
        //--- Get all source paths: 
        $sourcePaths = array();
        foreach ($paths as $sourcePath) {
            $sourcePath               = realpath($sourcePath);
            $sourcePaths[$sourcePath] = $sourcePath;
            if ($sourcePath && is_array($out = self::glob($sourcePath, "*", GLOB_ONLYDIR, $recursive))) {
                foreach ($out as $path) {
                    $sourcePaths[$path] = $sourcePath;
                }
            }
        }
        //--- Get all source files and filter it:
        foreach ($sourcePaths as $path => $base) {
            if ( is_array($files = self::glob($path)) ) {
                foreach ($files as $file) {
                    if (!is_file($file)) { 
                        continue;
                    }
                    $name = basename($file);
                    if ( Basic::inPatterns($name, $filePatterns) &&
                        !Basic::inPatterns($name, $excludePatterns) ) {
                        $outSources[$file] = $base;
                    }
                }
            }
        }
    return $outSources;        
    }

    /**
     * Returns file path of $targetPath relatively $currentPath
     * 
     * @param string $targetPath    Source directory (absolute path).
     * @param string $currentPath   Current (or another) directory (absolute path).
     * @return hash                 A hash array structure: 
     * @code
     * array
     * (
     *  "common"   => "common base part of $targetPath and $currentPath", 
     *  "current"  => "current path (absolute)",
     *  "relative" => "part of $targetPath relatively $currentPath"
     * );  
     * 
     * Full path to target = "current" + "relative"
     * @endcode
     * 
     * Example:
     * @code
     * 
     * getRelativePath ("/var/www/doc_root/path/to/site1", "/var/www/doc_root/otherpath/to/site2")
     * 
     * return: Array
     * (
     *      [common] => /var/www/doc_root
     *      [current] => /var/www/doc_root/otherpath/to/site2
     *      [relative] => /../../../path/to/site1
     * )
     * 
     * Full relative path from current to target will be:  /var/www/doc_root/otherpath/to/site2/../../../path/to/site1
     * 
     * @endcode
     */
    public static function getRelativePath ($targetPath, $currentPath=NULL) {
        if ($currentPath === NULL) {
            $currentPath = realpath(""); // system current directory
        }
        $targetPath  = rtrim($targetPath,'/');
        $currentPath = rtrim($currentPath,  '/');
        $source = explode("/", $targetPath);
        $dest   = explode("/", $currentPath);
        $commonPart = "";
        for($i=0; $i<count($source); $i++) {
            if ($source[$i] != $dest[$i]) {
                break;
            }
            if ($source[$i]!="") {
                $commonPart .= "/".$source[$i];
            }
        }
        $sourceSubDir = preg_replace('/^'.preg_quote($commonPart, "/").'/', "", $targetPath);
        $destSubDir   = preg_replace('/^'.preg_quote($commonPart, "/").'/', "", $currentPath);
        $destSubDir   = preg_replace('/\/[^\/]+/', '/..', $destSubDir);
        $relativePart = $destSubDir . $sourceSubDir;
    
    return array(
        "common"   => $commonPart, 
        "current"  => $currentPath,
        "relative" => $relativePart
    );    
    }    
    
    /**
     * Create a hash array represents a file tree structure
     * 
     * @param  hash    $arraySources    Hash array of sources returned by @ref globFiles method.
     * @param  boolean $stripBase       (Option) =TRUE - creates relative file path structure.
     *                                           =FALSE - creates absolute file path structure.
     * @return boolean | array          A hash array represents a file tree structure.
     * 
     * Exapmle:
     * @code
    // $files = Files::globFiles('/base/path/', '\.php$');
       $files = array(
       '/base/path/file1.php' => '/base/path',
       '/base/path/file2.php' => '/base/path',
       '/base/path/folder1/file1_in_folder1.php' => '/base/path',
       '/base/path/folder1/file2_in_folder1.php' => '/base/path',
       '/base/path/folder1/subfolder1/file1_in_subfolder1.php' => '/base/path',
       )
      
       Files::getFilesTree($files, true);
     
       //-------- 
       // Return:
      
        Array
        (
            [file1.php] => file1.php
            [file2.php] => file2.php
            [folder1] => Array
                (
                    [file1_in_folder1.php] => folder1/file1_in_folder1.php
                    [file2_in_folder1.php] => folder1/file2_in_folder1.php
                    [subfolder1] => Array
                        (
                            [file1_in_subfolder1.php] => folder1/subfolder1/file1_in_subfolder1.php
                        )

                )

        )
      
     * @endcode
     * @see    globFiles
     */
    public static function getFilesTree ( $arraySources, $stripBase=true ) {
        //--- $arraySources mast be an array:
        if (!is_array($arraySources)) {
            return false;
        }
        //--- Create tree of files:
        $filesTree = array();
        foreach ($arraySources as $file => $base) {
            //--- Strip file base path: (for relevant paths)
            if ($stripBase) {
                $fileName = substr($file, strlen($base)+1);
            } else {
                $fileName = $file;
            }
            //--- Create tree structure from $fileName:
            $filePaths = explode('/', $fileName);
            $p = &$filesTree;
            foreach ($filePaths as $part) {
                if (!is_array($p[$part])) {
                    $p[$part] = array(); 
                }
                $p = &$p[$part];
            }
            //--- Unset array if it is file and set the filename of a file:
            if (is_file($file)) { 
                $p = $fileName;
            }
        }
    return $filesTree;    
    }
    
}
