<?php
/*==============================================================================
 *  Title      : Multi language messages
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 14.11.2015
 *==============================================================================
 */
namespace digger\cradle\application;

/**
 * @brief Multi language messages
 * 
 * Class to support of multi language messages.
 * Wrap of GNU "gettext" utility and its simplest replacement (just in case).
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Messages {

    /** 
     * Array of text domains for translations 
     * 
     * Structure:
     * @code
     * 
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
     * 
     * @endcode
     */
    public static $textDomains = null;

    /** Current language */
    public static $language = "";
    
    //--------------------------------------------------------------------------
    // Private
    //--------------------------------------------------------------------------
    
    /** Current text domain */
    private static $textDomain = "";   
    
//==============================================================================    
// Methods
//==============================================================================    
    
    /**
     * To set params of current text domain for translations.
     * 
     * (wrap of `gettext`)
     * 
     * @param string $domain    The name of the text domain
     * @param string $rootDir   Root directory to find translations
     * @code
     * 
     * The root directory structure:
     * 
     * (`gettext` native)
     * 
     *   /$rootDir
     *         $language/LC_MESSAGES/domainName.mo
     * 
     *  For example:
     * 
     *  messages/ 
     *         en/LC_MESSAGES/domainName.mo
     *         ru/LC_MESSAGES/domainName.mo
     *         ...
     * 
     * (`gettext` replacment by self::_ )
     * 
     *   /$rootDir
     *         $language/domainName.php
     * 
     *  For example:
     * 
     *  messages/
     *         en/domainName.php
     *         ru/domainName.php
     *         ...
     * 
     * @endcode
     * @param string $codeset UTF-8 by default.
     */
    public static function setTextDomain($domain, $rootDir, $codeset='UTF-8') {
        //--- Set root directory to find translations:
        //--- Clear the PHP "Gettext" cache (workaround for develop mode only):
        if (is_link($rootDir . "/nocache")) {
            bindtextdomain ($domain, $rootDir . "/nocache");
        }
        //--- Set root directory:
        bindtextdomain ($domain, $rootDir);
        //--- Set codeset:
        bind_textdomain_codeset($domain, $codeset ? $codeset : 'UTF-8');
        //--- Use text domain:
        textdomain ($domain);      
        //--- Store current text domain:
        self::$textDomain = $domain;
    }
    
    /**
     * Change current text domain for translation.
     * 
     * (wrap of `gettext`)
     * 
     * Property `self::$textDomains` mast be defined before @see $textDomains
     * 
     * @param string $domain The name of the current text domain
     */
    public static function useTextDomain($domain) {
        if (isset(self::$textDomains[$domain])) {
            extract(self::$textDomains[$domain]);
            self::setTextDomain($domain, $root, $codeset);
        }
    }
    
    /**
     * GNU `gettext` utility simplest replacement.
     * 
     * If you can't use the `gettext` for any reason, the simplest way to replace it 
     * is to use this method.
     * 
     * But in this case you should keep your translation messages in php file (directory structure @see setTextDomain).
     * The php file mast returns an array of structure:
     * 
     * @code
     * 
     * return [
     *  'message_id' => 'message text',
     *  ...
     * ]
     * 
     * @endcode
     * 
     * @todo How to use:
     * @code 
     * <?php 
     * 
     * echo Messages::_('Hello world!');
     * 
     * @endcode
     * 
     * @param  string $text text message
     * @return string text message
     */
    public static function _($text){  
        if (!self::$textDomains[self::$textDomain]["data"]) {
            $file = self::$textDomains[self::$textDomain]["root"] . "/" . substr(self::$language, 0, 2) . "/". self::$textDomain . ".php";
            if (is_file($file)) {
                self::$textDomains[self::$textDomain]["data"] = include($file); 
            }
            //--- To include only once:
            self::$textDomains[self::$textDomain]["data"][""] = "";        
        }
        if (isset(self::$textDomains[self::$textDomain]["data"][$text])) { 
            return self::$textDomains[self::$textDomain]["data"][$text];
        }
    return $text;
    }    
    
}
