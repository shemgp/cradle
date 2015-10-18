<?php
/*==============================================================================
 *  Title      : Digger Simple PHP Code Parser
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 06.10.2015
 *==============================================================================
 */
namespace digger\cradle\text;

use digger\cradle\common\Basic;

/**
 * @brief Digger Simple PHP Code Parser
 * 
 * Parse source code of php-file and returns a hash array of code structure.
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * @todo How to use:
 * @code
 * 
 * require_once 'autoload.php';
 * 
 * use digger\cradle\common\SimpleCodeParser;
 * 
 * print_r( SimpleCodeParser::parseCode( file_get_contents('my_php_file.php') ) );
 * 
 * @endcode
 */
class SimpleCodeParser {
    
    /**
     * To strip input text data (code) of comments
     * 
     * @param  string $data Text data contains a source code. 
     * @return string text  Data without code comments (such as^ /* ...  and //... ).
     */
    public static function stripComments($data) {
        $data = preg_replace('/\/\*.*?\*\//ms', "", $data);
        $data = preg_replace('/\/\/[^\n]*\n/ms', "", $data);
    return $data;
    }
    
    /**
     * To split a source code into parts of namespases 
     * 
     * @param string $data  Text data contains a source code.
     * @return hash         Array structure: 
     * @code
     * array( 
     *      "" => "...code inside the GLOBAL namespace...",
     *      "namespace1" => "...code inside the namespace1...",
     *      "namespace2" => "...code inside the namespace2...", 
     *      ...
     *  )
     * @endcode
     */
    public static function getNamespaces ($data) {
        if (preg_match_all('/^\s*namespace\s+([\w\\\\]+)./ms', $data, $m)) {
            $bodies = preg_split('/^\s*namespace\s+([\w\\\\]+)./ms', $data);
            foreach ($m[1] as $i => $value) {
                $namespaces[$m[1][$i]] = $bodies[1+$i]; 
            }
        }
        else {
            $namespaces = array("" => $data);
        }
    return $namespaces;
    }
    
    /**
     * To find all functions into a source code
     * 
     * @param  string $data Text data contains a source code.
     * @return hash         Array structure: 
     * @code
     * array( 
     *      "function1" => "parameters of function",
     *      "function2" => "parameters of function", 
     *      ...
     *  )
     * @endcode
     */
    public static function getFunctions ( $data ) {
        if (preg_match_all('/^\s*function\s+([\w_]+)\s*\(([^\)]*)\)[^\{]*\{/ms', $data, $m)) {
            foreach ($m[1] as $i => $value) {
                $functionName = $m[1][$i];
                $functionPara = $m[2][$i];
                $functions[$functionName] = $functionPara;
            }
        }
        else {
        }
    return $functions;    
    }
    
    /**
     * To find all methods of class into a source code of a given class
     * 
     * @param  string $data Text data contains a class source code.
     * @return hash         Array structure: 
     * @code
     * array( 
     *      "method1" => array(
     *                      "type"   => static | abstract | "", 
     *                      "scope"  => private | protected | private , 
     *                      "params" => "parameters of methods"
     *                         )
     *      "method2" => array( ... ), 
     *      ...
     *  )
     * @endcode
     */
    public static function getClassMethods ( $data ) {
        if (preg_match_all('/^\s*([\w\s]*)\s*function\s+([\w_]+)\s*\(([^\{]*)\)[^\{\)]*\{/ms', $data, $m)) {
            foreach ($m[1] as $i => $value) {
                $methodProp = $m[1][$i];
                $methodScop = Basic::inPatterns($methodProp, array("public", "protected", "private"), true);
                if (!$methodScop) {
                    $methodScop = "public";    
                }
                $methodType = Basic::inPatterns($methodProp, array("abstract"), true);
                if (!$methodType) { 
                    $methodType = Basic::inPatterns($methodProp, array("static"), true);
                }
                $methodName = $m[2][$i];
                $methodPara = $m[3][$i];
                $methods[$methodName] = array("scope" => $methodScop, "type" => $methodType, "params" => $methodPara);
            }
        }
    return $methods;    
    }    
    
    /**
     * Get the first brace block positions contains { ...some data... }
     * 
     * @param  string $data Text data contains a class source code.
     * @return hash         Array structure:
     * 
     * array ( startPosition, endPosition )
     * 
     */
    public static function getFirstBraceBlockPos( $data ) {
        $startPos = strpos($data,"{");
        if ($startPos === false) { 
            return false;
        }
        $x    = 1; // count of "{"
        for ($p=$startPos+1; $p<strlen($data); $p++) {
            if ($data[$p] == "{") $x++;
            if ($data[$p] == "}") $x--;
            if ($x==0) break;
        }
    return array($startPos, $p);
    }
    
    /**
     * Get the first brace block contains "{ ...some data... }"
     * 
     * @param  string  $data        Text data contains a class source code.
     * @param  boolean $stripped    If TRUE: the first and the last braces wil be stripped from result.
     * @return string               Data placed inside two braces ( { ...some data... } ).
     */
    public static function getFirstBraceBlock( $data, $stripped=true ) {
        if (($p=self::getFirstBraceBlockPos($data)) === false) {
            return false;
        }
        if ($stripped) { 
            return substr($data,$p[0]+1, $p[1]-$p[0]-1);
        }
        else { 
            return substr($data,$p[0],   $p[1]-$p[0]+1);
        }
    }
    
    /**
     * To find all objects (classes and functions) into a source code
     * 
     * @param  string $data Text data contains a source code.
     * @return hash         Array structure: 
     * @code
     * array(
     *      "classes" => array(
     *          "class1" => array( 
     *              "method1" => array(
     *                      "type"  => static | ..., 
     *                      "scope" => private | protected | ... , 
     *                      "params" => "parameters of methods"
     *              ),
     *           ...
     *          )
     *      ),
     *      "functions" => array (
     *          "function1" => "parameters of function",
     *          ...
     *      )
     * )
     * @endcode
     */    
    public static function getObjects( $data ) {
        if (preg_match_all('/^\s*class\s+([\w_]+)\s*([^\{]*)\{/ms', $data, $m)) {
            foreach ($m[1] as $i => $value) {
                $className   = $m[1][$i];   
                $classPrefix = $m[0][$i]; 
                $classBegin  = strpos($data,$classPrefix);
                $classOffset = $classBegin + strlen($classPrefix)  -1;// step back to first "{" of class
                $treshData  .= substr($data,0,$classBegin);
                $body        = substr($data, $classOffset); 
                $p = self::getFirstBraceBlockPos($body);
                $body = substr($body,$p[0]+1, $p[1]-$p[0]-1); // $body without first "{" andlast "}" (end of class)
                $classes[$className] = $body;
                $data = substr($data, $classOffset + $p[1] + 1);
            }
            $treshData  .= $data;
        }
        else {
            $treshData = $data;
        }
    
        $functions = self::getFunctions($treshData);
    
    return array("classes" => $classes, "functions" => $functions);
    }
    
    /**
     * To parse a source code.
     * It is a main method to use.
     * 
     * @param  string $data  Text data contains a source code.
     * @return hash          Array structure: 
     * @code
     * array( 
     *  "namespace1" => array(
     *      "classes" => array(
     *          "class1" => array( 
     *              "method1" => array(
     *                      "type"  => static | ..., 
     *                      "scope" => private | protected | ... , 
     *                      "params" => "parameters of methods"
     *              ),
     *           ...
     *          )
     *      ),
     *      "functions" => array (
     *          "function1" => "parameters of function",
     *          ...
     *      )
     *  ),
     *  ...
     * )
     * @endcode
     */
    public static function parseCode($data) {
        $data       = self::stripComments( $data ); // strip of comments
        $nameSpaces = self::getNamespaces( $data ); // split to namespaces
        foreach ($nameSpaces as $nameSpace => $body) {
            $objects = self::getObjects( $body );
            if (is_array($objects["classes"]))
            foreach ($objects["classes"] as $className => $classBody) {
                $objects["classes"][$className] = self::getClassMethods($classBody);
            }
            $nameSpaces[$nameSpace] = $objects;
        }
    return $nameSpaces;    
    }
    
}
