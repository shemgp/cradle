<?php
/*==============================================================================
 *  Title      : Digger Basic Class
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */

namespace digger\cradle\common;

/**
 * @brief Digger Basic Class
 * 
 * Digger class of common simple basic functions
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Basic {
    
    /**
     * To replace keys in the text template by values.
     * 
     * Example:
     * @code
     * 
     * $text         = "some text with some [KEY1] and [KEY2] ...";
     * $replacements = array("[KEY1]" => "Value1", "[KEY2]" => "Value2");
     * echo Basic::replace($text, $replacements);
     * 
     * //-------------
     * // Will output: some text with some Value1 and Value2 ..
     * 
     * @endcode
     * 
     * @param string $templateText      Template text.  
     * @param hash   $hashReplacements  Hash array of replacements.
     * @param string $leftQualifier     Left  qualifier (by default = '[').
     * @param string $rightQualifier    Right qualifier (by default = ']').
     * @return string                   Template text with replacements.
     */
    public static function replace( $templateText, $hashReplacements, $leftQualifier="[", $rightQualifier="]" ) {
        if (is_array($hashReplacements))
        foreach ($hashReplacements as $key => $value) 
            $templateText = str_replace($leftQualifier . $key . $rightQualifier, $value ,$templateText); 
    return $templateText;
    }
    
    /**
     * Checks if a value matches any of patterns
     * 
     * Example:
     * @code
     * 
     * Basic::inPatterns( "someString", array("^some", "ing$", "\.php$") );
     * 
     * //-------------
     * // Will return: TRUE
     * 
     * Basic::inPatterns( "some data in text", array("data", "need", "to find"), true );
     * 
     * //-------------
     * // Will return: "data"
     * 
     * @endcode
     * 
     * @param  string           $value          Subject for matching.
     * @param  array            $patternList    Array of regular expressions (patterns).
     * @return boolean | string TRUE (or pattern) if specified value matches one of patterns from list.
     *                          FALSE if no matches.
     */
    public static function inPatterns ( $value, $patternList, $returnPattern=false ) {
        if (is_array($patternList)) {
            foreach ($patternList as $pattern) {
                if (preg_match("/$pattern/", $value)) { 
                    if (!$returnPattern) 
                        return true;
                    else                 
                        return $pattern;
                }
            }    
        }
    return false;    
    }
    
    /**
     * To create a two-dimensional array from multidimensional array 
     * by replacing a sequence of keys of the original array 
     * to the single path contains this sequence.
     * 
     * @param  array  $array        Input multidimensional array
     * @param  string $basePath     (Optional) 
     * @param  string $keyPrefix    (Optional) Prefix for key  (for example: '[' )
     * @param  string $keyPostfix   (Optional) Postfix for key (for example: ']' )
     * @return array  two-dimensional array
     * 
     * Example:
     * ~~~
     * $inputArray = [
     *      'a' => [
     *          'b' => [
     *              'c' => 'value'
     *          ]
     *      ]
     * ];
     * 
     * print_r(self::pathValue($inputArray));
     * print_r(self::pathValue($inputArray, 'base'));
     * print_r(self::pathValue($inputArray, 'base', '[', ']'));
     * 
     * //--- Output:
     * 
     * Array {
     *      'a/b/c' => 'value'
     * }
     * 
     * Array {
     *      'base/a/b/c' => 'value'
     * }
     * 
     * Array {
     *      '[base/a/b/c]' => 'value'
     * }
     * ~~~
     */
    public static function pathValue($array, $basePath=null, $keyPrefix=null, $keyPostfix=null) {
        if ($basePath) { $basePath .= '/'; }
        $new_array = [];
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $path = $basePath . $key;
                if (is_array($value)) {
                    $new_array = array_merge($new_array, self::pathValue($value, $path, $keyPrefix, $keyPostfix));
                } else {
                    $new_array[$keyPrefix . $path . $keyPostfix] = $value;
                }
            }
        } else {
           $new_array = $array; 
        }
    return $new_array;
    }    
}
