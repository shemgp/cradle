<?php
/*==============================================================================
 *  Title      : LDAP wrapper 
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 10.03.2016
 *==============================================================================
 */
namespace digger\cradle\network;

/**
 * @brief LDAP request wrapper 
 * 
 * A simple LDAP request wrapper class.
 * Designed to make simple LDAP requests more easy and short.
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems 
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 * 
 * use digger\cradle\network\Ldap;
 * 
 * //--- Set the connection params:
 * $conn = [
 *      "type" => "ldal", 
 *      "host" => "ldapserver", 
 *      "base" => "dc=...,dc=...,dc=...", 
 *      "user" => "username@domain", 
 *      "pass" => "password"
 * ];
 * 
 * //--- Open connection (option): 
 * if ( $dbh = Ldap::connect($conn) ) { 
 *     echo "Connected. ";
 * } else {
 *     die (print_r(Ldap::getLastError(), true));
 * }
 *
 * //--- Get some data from LDAP server:
 * print_r( Ldap::request( "(|(sn=$person*)(givenname=$person*))", array("cn","title","memberOf"), $conn) );
 * print_r( Ldap::request( "sn=some body*", "cn,title,memberOf" ) );
 * print_r( Ldap::request( "cn=John S*", "cn,title,department,l,co,memberOf,mail,mailNickname,telephoneNumber", $conn) );
 * print_r( Ldap::request( "cn=John S*" ) );
 * 
 * ~~~
 */
class Ldap {

    public static $default = array(
        "type" => "ldap",
        "host" => "localhost",
        "base" => "",
        "user" => "",
        "pass" => "",
        "attributes" => "cn",
        );    

    /**
     * Connect to LDAP server (option)
     * 
     * @param array $connection An array of connection params:
     * ~~~
     * [
     *      "host" => "...",
     *      "base" => "...",
     *      "user" => "...",
     *      "pass" => "...",
     *      "attributes" => "...",
     * ]
     * ~~~
     * @return <b>false | LDAP_resource_id</b>
     */
    static function connect ( $connection=null ) {

        if (!$connection && self::$_c_current) {
            //--- Return the current connector:
            return self::$_connector[self::$_c_current];
        }
        if (!is_array($connection)) { 
            $connection=array("");
        }

        foreach ($connection as $k => $v) { $c[strtolower($k)] = $v; }
        extract(self::$default);    // extract default
        extract($c,EXTR_OVERWRITE); // extract $connection 

        $cc_id = strtolower("$type:$host:$base:$user"); // connection id
        if(array_key_exists($cc_id, self::$_connector)) { 
            self::$_c_current = $cc_id; 
            return self::$_connector[$cc_id]; 
        }

        $error_reporting = error_reporting(E_ERROR);
        try { 
            self::$_error = "";
            //---
            $cc = ldap_connect($host);
            ldap_set_option($cc, LDAP_OPT_PROTOCOL_VERSION, 3); // <-- Important!
            ldap_set_option($cc, LDAP_OPT_REFERRALS, 0);        // <-- Important!
            if ($cc) { 
                self::$_connector[$cc_id] = $cc; 
            } else { 
                throw new \Exception (_("Unable to connect to:")." [ $host ]", -1);
            }
            if (!ldap_bind($cc,$user,$pass)) {
                throw new \Exception (_(ldap_error($cc))." [ $host ]", ldap_errno($cc));
            }
            
            self::$_conn_data[$cc] = array_merge(self::$default,$c);
            self::$_c_current = $cc_id;
            error_reporting($error_reporting);
            return self::$_connector[$cc_id];
        }  
        catch(\Exception $e) {  
            self::$_error =  [$e->getCode(),$e->getMessage()];   
        }    
        error_reporting($error_reporting);

    return false;
    }

    /**
     * Execute LDAP request (LDAP search)
     * 
     * @param string  $query            LDAP filter
     * @param array   $data             LDAP attributes ()
     * @param array   $connection       (option) An array of connection params (see a method @link connect @endlink)
     * @param string  $type             type of request [ not used ] default = "select"
     * @param boolean $returnStatment   TRUE = return raw data (LDAP response entries)
     * 
     * @return <b>false | array | rowCount</b>
     */
    public static function request ( $query, $data=null, $connection=null, $type="select", $returnStatment=false ) {

        if ( !($cc = self::connect($connection)) ) {
            return false;
        }
        
        $cc_data = self::$_conn_data[$cc];  
        self::$_error = "";   

        if (!$data)           { $data = $cc_data["attributes"]; }
        if (is_string($data)) { $data = explode(",",preg_replace('/\W{1,}/',',',$data)); }

        $query = self::createQuery($query,$data,$cc_data); //echo "[ $query ]\n";

        $error_reporting = error_reporting(E_ERROR);
        $filter = $query;
        $attrib = $data;
        $STH = ldap_search($cc, $cc_data["base"], $filter, $attrib);
        error_reporting($error_reporting);

        if ($STH) {
            $entries = ldap_get_entries($cc, $STH);
            if ($returnStatment)   {
                return $entries; 
            }
            if ($type == "select") { 
                return self::fetchAll($entries);   
            }
            else { 
                return ldap_count_entries($cc, $STH); //$entries["count"]
            }
        } else { 
            self::$_error = [ ldap_errno($cc), ldap_error($cc), "base_dn: ".$cc_data["base"]." filter: ".$query ];
        }

    return false;    
    }    

    /**
     * Returns the last error of request
     * 
     * @return <b>array</b>  An array contains: [code, message, query]
     */
    public static function getLastError () { 
        return self::$_error; 
    }
    
    /**
     * Destructor
     */
    public static function destroy() { 
        if (is_array(self::$_connector)) { 
            foreach (self::$_connector as $cc) { 
                ldap_unbind($cc); 
            }
        }
    }

    //==========================================================================
    // PRIVATE
    //==========================================================================

    private static $_connector = array();
    private static $_conn_data = array();
    private static $_c_current = null;    // current connector id
    private static $_error     = "";


    //------------------------------------------------------------------------------

    private static function createQuery( $query=null, &$data=null, $connection=null ) {
        return $query;
    }

    //------------------------------------------------------------------------------

    private static function fetchAll ( $entries ) {

        if (!is_array($entries)) { 
            return $entries; 
        }
        
        $result = [];
        $n      = 0;
        foreach ($entries as $index => $entry) {
            if (!is_numeric($index)) { continue; } 
            if (is_array($entry)) {
                foreach ($entry as $i => $data) {
                    if (!is_numeric($i) && $i!=="count") {
                        if (isset($data["count"])) { 
                            unset($data["count"]); 
                        }
                        if (is_array($data) && count($data)<2) { 
                            $data = $data[0];
                        }
                        $result[$n][$i] = $data;     
                    }
                }
            }
            $n++;      
        }

    return $result;    
    }

}

