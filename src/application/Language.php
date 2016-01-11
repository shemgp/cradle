<?php
/*==============================================================================
 *  Title      : Language
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 14.11.2015
 *==============================================================================
 */
namespace digger\cradle\application;

/**
 * @brief Languge functions
 * 
 * Class with methods to operate with user language.
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Language {
    
    /** 
     * Cookie language key (to determine user's selection) 
     * @var string
     */
    public static $cookieLanguageKey = 'language';

    /** 
     * List of acceptable languages. 
     * The first in list is the default.
     * @var array
     */
    public static $languages = [
        "en", // en-US
        "ru", // ru-RU
    ];    

    /**
     * Short form of language code. 
     * @see getLanguage
     * 
     * TRUE is 2-symbols form (en|ru|...).
     * FALSE is 5-symbols form (en-US|ru-RU|...).
     * @var boolean 
     */
    public static $shortForm = false;

    /**
     * Language code delimiter
     * @var string 
     */
    public static $delimiter = '-';
    
    //--------------------------------------------------------------------------
    // Private
    //--------------------------------------------------------------------------
    
    /** Current standard language */
    private static $language = null;
    
    /** Current locale set by language */
    private static $locale = null;
    
//==============================================================================    
// Methods
//==============================================================================    
    
    /**
     * Converts the input language code to the standard form (language-COUNTRY)
     * 
     * @param  string $lang Input language code - two letters or languge pair ( en | en-us | en_US | ru | ru-RU | ... )
     * @return string Language output code: language-COUNTRY ( en-US | ru-RU | ... )
     */
    public static function getLanguageCode($lang) {
        if (strlen($lang) < 3) {
            // Two letters lang (en|ru|...):
            $lang    = strtolower($lang);
            $country = ($lang == 'en') ? 'US' : strtoupper($lang);
            $lang   .= self::$delimiter . $country;
        } else {
            //--- Language pair (en-US|ru-RU|en-us|ru-ru|...):
            $lang = strtolower($lang[0] . $lang[1]) . self::$delimiter . strtoupper($lang[3] . $lang[4]);
        }
    return $lang;    
    }
    
    /**
     * Checks is the input language present in the acceptable list (in array $languages).
     * 
     * @param  string         $inputLang Input language code - two letters or languge pair ( en | en-us | en_US | ru | ru-RU | ... )
     * @return boolean|string            FALSE | "standard language code"
     */
    public static function isLanguageAcceptable($inputLang) {
        //--- Convert language to standard form:
        $lang = self::getLanguageCode($inputLang);  
        //--- Empty self::$languages means "acceptable any"
        if (!is_array(self::$languages) || empty(self::$languages)) { 
            return $lang; 
        }
        //--- Check:
        foreach (self::$languages as $_lang) {
            $_lang = self::getLanguageCode($_lang);
            if ($lang == $_lang) { 
                return $lang;
            }
        }
    return false;    
    }
    
    /**
     * Set current language and locale
     * 
     * @param  string $inputLang Value of HTML tag "lang" (en|ru|en-US|ru-RU|...)
     * @return string            Locale code on success
     */
    public static function setLanguage($inputLang) { 
        //--- Convert lang to standard form:
        $lang = self::isLanguageAcceptable($inputLang);
        //--- If lang is not acceptable set default language (the first of $languages):
        if ($lang === false) { $lang = self::getLanguageCode(self::$languages[0]); }
        //--- Set locale:
        $lang_locale    = str_replace("-", "_", $lang); //<-- "ru_RU"
        $locales        = array_merge([$lang_locale], self::getLocales("$lang_locale.utf"));
        self::$locale   = setlocale(LC_ALL, $locales);  //<-- ["ru_RU", "ru_RU.UTF-8"]
        //--- Store current language:
        self::$language = $lang;
        //self::$inputLanguage = $inputLang;
        //putenv("LANG=" . self::$language);
    return self::$language;
    }
    
    /**
     * Returns language list accepted by user
     * 
     * @return array  language list accepted by user
     */
    public static function getUserAcceptLanguages() {
        $lang_list = explode(",", strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']));
        $languages = [];
        foreach ($lang_list as $lang_string) {
            list($lang, $q) = explode(";q=", $lang_string);
            if ($q === null) { $q = 1; }
            $languages[$lang] = $q;
        }
        arsort($languages, SORT_NUMERIC);
    return $languages;    
    }
    
    /**
     * Returns user's most accepted language
     * 
     * @return string 2-symbols code of user's most accepted language
     */
    public static function getUserAcceptLanguage() {
        $lang = array_shift(array_keys(self::getUserAcceptLanguages()));
    return $lang; //substr($lang, 0, 2);
    }
    
    /**
     * Returns the code of current language
     * 
     * @return string The code of current language.
     *                Can be 2-symbols form (en|ru|...) or 5-symbols form (en-US|ru-RU|...) depending on the $shortForm value.
     * @see shortForm 
     */
    public static function getLanguage() {
        //--- Return the stored language:
        if (!self::$language) {
            //--- If is set $_COOKIE's languge: 
            if ($_COOKIE[self::$cookieLanguageKey]) {
                self::setLanguage($_COOKIE[self::$cookieLanguageKey]);
            } else {
            //--- Define language accepted by user's browser:
                self::setLanguage(self::getUserAcceptLanguage());
            }
        }
        //--- Short form:
        if (self::$shortForm) { return substr(self::$language, 0, 2); }
        //--- Standard form:
    return self::$language;
    }
    
    /**
     * Returns current system locale.
     * 
     * @return string
     */
    public static function getLocale() {
        return setlocale(LC_ALL, 0);
    }
    
    /**
     * Returns locales list of current server.
     * 
     * @return array Locales list of current server
     */
    public static function getLocales($filter = null) {
        if ($filter) { 
            $filter = " | grep -i '$filter'"; 
        } else { 
            $filter = ""; 
        }
        exec("locale -a" . $filter, $list);
    return $list;
    }
    
}