<?php
/*==============================================================================
 *  Title      : Data Class
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\common;
/**
 * @brief Data Class
 * 
 * Class to work with different data formats
 *
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Data {
    
    /**
     * Loads a data from file and returns data as a hash array. 
     * 
     * The file can be in the following formats:
     * - *.php
     * - *.json
     * - *.xml
     * 
     * @param string|Array $fileName The name of config file. May be specified as a string 
     * or an array of strings (list of possible alternative file names).<br>
     * <b>For example:</b> array("config.php", "config.json", "config.xml")
     * 
     * @param boolean $stripXmlAttributes 
     * - =TRUE, XML-tag's attributes will be ignored (except "value" attribute, 
     *   its value will be saved as tag's value).<br>
     * - =FALSE, XML-tag's attributes will be stored as \@attributes.
     * 
     * @return <i>hash</i>   Array or FALSE, if file $fileName not found.
     */
    public static function load( $fileName, $stripXmlAttributes=true )
    {
        if (!is_array($fileName)) { 
            $fileArray = array($fileName);  
        } else {
            $fileArray = $fileName;
        }
        foreach ($fileArray as $fileName) {       
            if (file_exists($fileName)) { 
                $fileExtention = strtolower(substr(strrchr($fileName, '.'), 1));
                switch ($fileExtention) {
                    case  "php": $hash = include $fileName;
                        break;
                    case "json": $hash = json_decode(file_get_contents($fileName),TRUE); 
                        break;
                    case  "xml": $xml  = simplexml_load_string(file_get_contents($fileName));
                                 $json = json_encode($xml);
                                 $hash = json_decode($json,TRUE);
                                 if ($stripXmlAttributes) $hash = self::stripXmlAttributes ($hash);
                        break;
                }
            return $hash;    
            }
        }
    return false;    
    } 
    
    /**
     * Strip Attributes of array 
     * 
     * @param array $hash
     * @return <i>array</i>
     */
    private static function stripXmlAttributes($hash) {
        if (is_array($hash)) { 
            foreach ($hash as $key => $value) 
                if (is_array($value) && is_array($value['@attributes']) && array_key_exists('value',$value['@attributes']))
                    $hash[$key] = $value['@attributes']['value'];  
                else    
                    $hash[$key] = self::stripXmlAttributes($value);
        }
    return $hash;      
    }
    
    /**
     * Set data from hash array (or config file) to a destination object or an array
     * 
     * @param  object        $destination Link to destination class or an array to be set.
     * @param  string|hash   $source      "Config file name" or hash array of property values to set 
     *                                    to destination.
     * @param  boolean       $strict      =TRUE  - non-existent properties will be ignored; <br>
     *                                    =FALSE - non-existent properties will be created and set.
     * @param  boolean       $merge       =TRUE  - if class property is an array new and old values 
     *                                    will be merged (recursively).
     * @return <i>boolean</i>             TRUE if properties of class was successfully set.
     */
    public static function set ( &$destination, $source, $strict=false, $merge=false ) {
        //--- Check:
        if (!$source || !$destination) {
            return false;
        }
        //--- Load data from file to an array:
        if (is_string($source)) {
            $source = self::load( realpath($source) ); 
        }
        //--- Data mast be an array:
        if (!is_array($source)) {
            return false;
        }
        //--- Destination is a class object:
        if (is_object($destination)) {
            self::setObject($destination, $source, $strict, $merge);
            return true;
        }
        //--- Destination is Array:
        if (is_array($destination)) {
            self::setObject($destination, $source, $strict, $merge);
            return true;
        }
    return false;    
    }
    
    /**
     * Copy data from source array to destination Object
     * 
     * @param array   $destination  Destination array
     * @param array   $source       Source array
     * @param boolean $strict      =TRUE  - non-existent properties will be ignored; <br>
     *                             =FALSE - non-existent properties will be created and set.
     * @param boolean $merge       =TRUE  - if class property is an array new and old values 
     *                                    will be merged (recursively)
     */
    public static function setObject (  &$destination, $source, $strict=true, $merge=false ) {

        foreach ($source as $key => $value) {
            if ($strict && !isset($destination->$key) ) { 
                continue; 
            }
            if ( !is_array($value) || !$merge ) { 
                $destination->$key = $value;
            } else {
                if (!is_array($destination->$key)) {
                    if ($strict) {
                        continue; 
                    }
                    $destination->$key = [];
                } 
                self::setArray($destination->$key, $value); //$destination->$key = array_merge_recursive ($destination->$key, $value);
            }    
        }        
    }

    /**
     * Copy data from source array to destination array
     * 
     * @param array   $destination  Destination array
     * @param array   $source       Source array
     * @param boolean $strict       If =TRUE the source key mast exists in desstination array or be a number
     */
    public static function setArray( &$destination, $source, $strict=false ) {

        foreach ($source as $key => $value) {
            
            //--- In strict mode key mast exists in desstination array or be a number
            if ($strict && !array_key_exists($key, $destination) && !is_numeric($key)) { continue; }
            
            //--- 1) Merge destination (String) value and source (String | Array) value:
            if (!is_array($destination[$key])) {
                if (!is_array($value)) { 
                //--- 1.1) Merge destination (String) and source (String):
                    if ($value === null) { continue; }  //<--- do not merge with NULL 
                    if (is_numeric($key) && !$strict) { //<--- do not renumber in strict mode!!!
                        $destination[] = $value; //<--- add value with new number (renumbered)
                    } else {
                        $destination[$key] = $value;
                    }
                } else {
                //--- 1.2) Merge destination (String) and source (Array):
                    if ($destination[$key] === null) {
                        $destination[$key] = $value; //<--- do not merge with NULL
                    } else { 
                        $destination[$key] = [ $destination[$key] ];
                        self::setArray($destination[$key], $value, $strict); //$destination[$key] = array_merge($destination[$key], $value );
                    }
                }
            } else {
            //--- 2) Merge destination (Array) value and source (String | Array) value:
                if (!is_array($value)) { 
                //--- 2.1) Merge destination (Array) and source (String):
                    if ($value === null) {
                        //--- nothing to do (do not merge with NULL)
                    } else { 
                        self::setArray($destination[$key], [ $value ], $strict); //$destination[$key] = array_merge($destination[$key], [ $value ]);
                    }    
                } else {
                //--- 2.2) Merge destination (Array) and source (Array):
                    self::setArray($destination[$key], $value, $strict); //<--- Recursive
                }
            }
        }        
    }
    
}