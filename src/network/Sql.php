<?php
/*==============================================================================
 *  Title      : SQL (PDO) Wrapper 
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 10.03.2016
 *==============================================================================
 */
namespace digger\cradle\network;

/**
 * @brief SQL request Wrapper
 * 
 * Simple SQL (PDO) request wrapper class.
 * Designed to make simple SQL requests more easy and short.
 * 
 * Supported databse types: mysql, pgsql, oci, sqlite, dblib(mssql,sybase) ...and all of PDO db types.
 * Use: 
 * ~~~
 * print_r(PDO::getAvailableDrivers()); 
 * ~~~
 * to see all avalible PDO db types.
 * 
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * 
 * @warning Use of type=[dblib] need to change in file "/usr/local/etc/freetds.conf": <br>
 * [global]
 * port = 1433
 * tds version = 8.0
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 * 
 * use digger\cradle\network\Sql;
 * 
 * //--- Set the connection params:
 * $conn = [
 *          "type" => "mysql", //<-- mysql by default
 *          "host" => "localhost", 
 *          "base" => "test", 
 *          "user" => "test", 
 *          "pass" => "test", 
 *          "table"=> "users", //<-- Option
 *         ];
 * 
 * //--- Open connection (option): 
 * if ( $dbh = Sql::connect($conn) ) { 
 *     echo "Connected. ";
 * } else {
 *     die (print_r(Sql::getLastError(), true));
 * }
 * 
 * //=== SELECT request:
 * 
 * //--- Full use:
 * $result = Sql::select("select * from table1 where name=:name order by name", array("name"=>"my neme"))
 * 
 * //--- Short use:
 * $result = Sql::select();                // -> "select * from [TABLE]"
 * $result = Sql::select( "", "", $conn ); // -> "select * from [TABLE]" using params from $conn
 * 
 * //--- Spesial use:
 * $result = Sql::select( "from [TABLE]", array("name"=>"my name") );          // -> "select * from [TABLE] where name='my name'"
 * $result = Sql::select( "a,b,c from [TABLE]", array("name"=>"my neme") );    // -> "select a,b,c from [TABLE] where name='my name'"
 * $result = Sql::select( "table2", array("name"=>"my name")) ;                // -> "select * from table2 where name='my name'"
 * $result = Sql::select( "where name like :name", array("name"=>"my neme") ); // -> "select * from [TABLE] where name like 'my name'"
 * $result = Sql::select( "[WHERE] order by name", array("name"=>"new") );     // -> "select * from [TABLE] where name='new' order by name"
 * $result = Sql::select( "from [TABLE] order by name" );                      // -> "select * from [TABLE] order by name"
 * 
 * print_r($result);
 * 
 * // keyword `[TABLE]` will be autamatic replaced by value from $conn['table'];
 * 
 * //=== INSERT request:
 * 
 * //--- Full use:
 * echo Sql::insert( "insert into table1 (a,b,c) values (:a,:b,:c)", array("a"=>1, "b"=>2, "c"=>3), $conn )
 * 
 * //--- Short use:
 * echo Sql::insert("", ["a"=>1, "b"=>2, "c"=>3]);
 * 
 * //--- Spesial use:
 * echo Sql::insert("into [TABLE]", ["id_user"=>"1003", "name"=>"test"]);
 * echo Sql::insert("table2", ["id_user"=>"1003", "name"=>"test"]);
 *  
 * //=== UPDATE request:
 * 
 * //--- Full use:
 * echo Sql::update( "update table1 set name=?, id_user=? where name=?", array("my name", "1002", "old name"), $conn );
 * 
 * //--- Spesial use:
 * echo Sql::update( "set name=?, id_user=? where name=?", ["my name", "1002", "old name"]);
 * echo Sql::update( "[TABLE] set name=?, id_user=? where name=?", ["my name", "1002", "old name"] );
 * echo Sql::update( "table1 set name=?, id_user=? where name=?",  ["my name", "1002", "old name"] );
 * //                                                               --------- params ------------
 * 
 * echo Sql::update( "", array( ["name"=>"new name", "id_user"=>1002], ["name" => "old name"] ) );
 * //                            ---------- data (set) -------------    --- keys (where) ---
 * 
 * //=== DELETE request:
 * 
 * //--- Full use:
 * echo Sql::delete("delete from table1 where name=:a", array("a"=>"my neme"), $conn);
 * 
 * //--- Spesial use:
 * echo Sql::delete("", ["id_user"=>"1003", "name"=>"test"]);
 * echo Sql::delete("from [TABLE]", ["name"=>"my neme"]);
 * echo Sql::delete("table1", ["name"=>"my neme"]);
 * 
 * ~~~
 */
class  Sql {

    public static $default = array (
        "type"  => "mysql",
        "host"  => "localhost",
        "base"  => "",
        "user"  => "",
        "pass"  => "",
        "table" => "",
        "autoquery" => true,
        );    

    /**
     * SQL SELECT Request
     * 
     * @param string $query 
     * ~~~
     * if $default["autoquery"]==true then:
     * 
     *      1. select ...
     *      2. ... from ...
     *      3. from ...
     *      4. where ...
     *      5. < empty >
     * 
     *      tag [TABLE] - will be replaced by $connection['table']
     *      tag [WHERE] - will be replaced by auto created WHERE condition (if $data is array)
     * 
     * else $query mast be standard sql-query: "select ... from ... where ..."
     * ~~~
     * 
     * @param array  $data
     * ~~~
     * 1. $query="select ... where f1=? and f2=? and f3=?"        =>  $data = array(1, 2, 3 ...)
     * 2. $query="select ... where f1=:f1 and f2=:f2 and f3=:f3"  =>  $data = array("f1"=>1, "f2"=>2, "f3"=>3 ...)
     * ~~~
     * 
     * @param array   $connection     (option) An array of connection params (see a method @link connect @endlink)
     * @param boolean $returnStatment [ true | false ]<br> false - return array( 0=>array(fname=>value, ...), 1=... ); <br>
     *                                                     true  - return { PDOStatement }
     * @return <b> array | false </b>
     */        
    public static function select ( $query="", $data=null, $connection=null, $returnStatment=false ) {
        return self::request($query, $data, $connection, "select", $returnStatment );     
    }   

    /**
     * SQL INSERT Request
     * 
     * @param String $query 
     * ~~~
     * if $default["autoquery"]==true then:
     * 
     *      1. insert into ...
     *      2. into ...
     *      3. ...
     *      4. < empty >
     * 
     *      tag [TABLE] - will be replaced by $connection['table']
     * 
     * else $query mast be standard sql-query: "insert into ... (...) values (...)"
     * ~~~
     * 
     * @param array  $data
     * ~~~
     * 1. $query="insert into ... (f1,f2,f3...) values (?,?,?,..)"         =>  $data = array(1, 2, 3 ...)
     * 2. $query="insert into ... (f1,f2,f3...) values (:f1,:f2,:f3,...)"  =>  $data = array("f1"=>1, "f2"=>2, "f3"=>3 ...)
     * ~~~
     * 
     * @param array   $connection     (option) An array of connection params (see a method @link connect @endlink)
     * @param boolean $returnStatment [ true | false ]<br> false - return rowCount <br>
     *                                                     true  - return { PDOStatement }
     * @return <b>rowCount | false</b>
     */ 
    public static function insert ( $query, $data=null, $connection=null, $returnStatment=false ) {
        return self::request($query, $data, $connection, "insert", $returnStatment ); 
    }

    /**
     * SQL UPDATE request
     * 
     * @param string $query 
     * ~~~
     * if $default["autoquery"]==true then:
     * 
     *      1. update ...
     *      2. ...
     *      4. < empty >
     * 
     *      tag [TABLE] - will be replaced by $connection['table']
     * 
     * else $query mast be standard sql-query: "update ... set ... where ..."
     * ~~~
     * 
     * @param array  $data
     * ~~~
     * 1. $query="update ... set f1=?, f2=?, ... where f3=? and f4=?, ..."  =>  $data = array(1, 2, 3, 4 ...)
     * 2. $query="update ... "                                              =>  $data = array( array("f1"=>1, "f2"=>2, ...), array("f3"=>3, "f4"=>4, ...) )
     *                                                                                         --- "set" data -------------  ----- "where" keys ---------
     * ~~~
     * 
     * @param array   $connection     (option) An array of connection params (see a method @link connect @endlink)
     * @param boolean $returnStatment [ true | false ]<br> false - return rowCount <br>
     *                                                     true  - return { PDOStatement }
     * @return <b>rowCount | false</b>
     */
    public static function update ( $query, $data=null, $connection=null, $returnStatment=false ) {
        return self::request($query, $data, $connection, "update", $returnStatment ); 
    }

    /**
     * SQL DELETE request
     * 
     * @param string $query 
     * ~~~
     * if $default["autoquery"]==true then:
     * 
     *      1. delete from ...
     *      2. from ...
     *      3. ...
     *      4. < empty >
     * 
     *      tag [TABLE] - will be replaced by $connection['table']
     * 
     * else $query mast be standard sql-query: "delete from ... where ..."
     * ~~~
     * 
     * @param array  $data 
     * ~~~
     * 1. $query="delete from ... where f1=?, f2=?, f3-?, ..."              =>  $data = array(1, 2, 3 ...)
     * 2. $query="delete from ... where f1=:f1 and f2=:f2 and f3=:f3, ..."  =>  $data = array("f1"=>1, "f2"=>2, "f3"=>3 ...)
     * ~~~
     * 
     * @param array   $connection     (option) An array of connection params (see a method @link connect @endlink)
     * @param boolean $returnStatment [ true | false ] <br> false - return rowCount <br>
     *                                                      true  - return { PDOStatement }
     * @return <b>rowCount | false</b>
     */
    public static function delete ( $query, $data=null, $connection=null, $returnStatment=false ) {
        return self::request($query, $data, $connection, "delete", $returnStatment ); 
    }

    /**
     * Returns the last error of request
     * 
     * @return <b>array</b>  An array contains: [code, message, query]
     */
    public static function getLastError () { 
        return self::$_error; 
    }

    //--------------------------------------------------------------------------
    // SUB FUNCTION
    //--------------------------------------------------------------------------

    /**
     * Initiates an SQL connection
     * 
     * @param array $connection An array of connection params:
     * ~~~
     * [
     *      "type" => "mysql",
     *      "host" => "hostname", 
     *      "base" => "database name", 
     *      "user" => "user login", 
     *      "pass" => "password", 
     *      "table"=> "default table name", //<-- option
     * ];
     * ~~~
     * @return <b>connector | false</b>
     */
    public static function connect ( $connection=null ) {

        if (!$connection && self::$_c_current) { 
            //--- Return current connector:
            return self::$_connector[self::$_c_current]; 
        }
        if (!is_array($connection)) { 
            $connection=array(""); 
        }

        foreach ($connection as $k => $v) { 
            $c[strtolower($k)] = $v; 
        }
        extract(self::$default);     //<-- extract default
        extract($c, EXTR_OVERWRITE); //<-- extract $connection 

        $cc_id = strtolower("$type:$host:$base:$user"); //<-- connection id
        if(array_key_exists($cc_id, self::$_connector)) {
            self::$_c_current = $cc_id; 
            return self::$_connector[$cc_id]; 
        }

        try { 
            self::$_error = "";
            self::$_connector[$cc_id] = new \PDO("$type:host=$host;dbname=$base", $user, $pass);
            self::$_connector[$cc_id]->_connection_ = array_merge(self::$default,$c);
            self::$_c_current = $cc_id;
            return self::$_connector[$cc_id];
        }  
        catch(\PDOException $e) {  
            self::$_error =  [$e->getCode(), $e->getMessage()];   
        }    

    return false;
    }

    //==========================================================================
    // Private
    //==========================================================================
    
    private static $_connector = array();
    private static $_c_current = null;    // current connector id
    private static $_error     = "";
    
    //------------------------------------------------------------------------------
    
    private static function _addConnParams( &$dbh, $connection=null ) {

        if (is_object($dbh) && $connection) {
            if ($connection["table"]) { 
                $dbh->_connection_["table"] = $connection["table"];
            }
        }

    return $dbh;  
    }

    //------------------------------------------------------------------------------

    private static function request ( $query, $data=null, $connection=null, $type=null, $returnStatment=false, $autoQuery=true ) {

        if ( !($dbh = self::connect($connection)) ){
            return false;
        }
        
        if ($dbh->_connection_["autoquery"] && $autoQuery) { 
            $query = self::createQuery($query, $data, self::_addConnParams($dbh,$connection), $type); // echo "$type [ $query ]\n";
        }
        
        self::$_error = "";   
        $STH = $dbh->prepare($query);
        
        if (!is_array($data)) { 
            $data = [$data];
        }
        
        if ($STH->execute($data)) {    
            if ($returnStatment)   {
                return $STH;
            }
            if ($type == "select") { 
                return $STH->fetchAll(\PDO::FETCH_ASSOC); //echo "rowCount:".$STH->rowCount()."\n";echo "columnCount:".$STH->columnCount()."\n columns: "; print_r($STH->getColumnMeta(0));   
            } else {
                return $STH->rowCount();
            }
        } else { 
            self::$_error = [$STH->errorCode(), implode(":", $STH->errorInfo()), $STH->queryString]; //echo $STH->debugDumpParams();
        }

    return false;    
    }

    //------------------------------------------------------------------------------

    private static function createQuery( $query=null, &$data=null, $dbh=null, $requestType="select" ) {

        switch ($requestType){
            
            case "insert":
                if(!$query) { 
                    $query = "insert into [TABLE] ".self::_getQ($data); 
                    break; 
                }
                if(is_array($data) && !preg_match("/(\(|\))/i", $query)) {
                    $query.= self::_getQ($data); 
                }
                if(preg_match("/^\s*into /i", $query)) { 
                    $query = "insert ".$query;   
                }  
                if(preg_match("/^\s*(?!insert\s*into)/i", $query)) { 
                    $query = "insert into ".$query; 
                }  
                break;

            case "update":
                if(!$query) { 
                    $query = "update [TABLE] ".self::_getQ($data,3); 
                    break; 
                }
                if(is_array($data) && !preg_match("/\s*set\s*/i", $query)) { 
                    $query.= self::_getQ($data,3); 
                } 
                if(preg_match("/^\s*set /i", $query)) { 
                    $query = "[TABLE] ".$query; 
                }  
                if(preg_match("/^\s*(?!update)/i", $query)) { 
                    $query = "update ".$query;
                }  
                break;

         case "delete": 
            if(!$query) { 
                $query = "delete from [TABLE] ".self::_getQ($data,2); 
                break; 
            }  
            if(is_array($data) && !preg_match("/\s*where\s*/i", $query)) { 
                $query.= self::_getQ($data,2);   
            } 
            if(preg_match("/^\s*where /i", $query)) { 
                $query = "from [TABLE] ".$query; 
            }  
            if(preg_match("/^\s*from /i", $query)) { 
                $query = "delete ".$query; 
            }  
            break;

         case "select":
            if(!$query) { 
                $query = "select * from [TABLE] ".self::_getQ($data,1); 
                break;
            }  
            if(preg_match("/\[WHERE\]/", $query)) { 
                $query = str_replace("[WHERE]",self::_getQ($data,1),$query); 
            } else 
                if(is_array($data) && !preg_match("/\s*where\s*/i", $query)) { 
                    $query.= self::_getQ($data,1);   
                }  
            if(preg_match("/^\s*where /i", $query)) { 
                $query = "from [TABLE] ".$query; 
            }  
            if(preg_match("/^\s*from /i", $query)) { 
                $query = "* ".$query;
            }  
            if(preg_match("/^\s*(?!select)/i", $query)) { 
                $query = "select ".$query; 
            }
            break;  
        }

        if ($dbh && $dbh->_connection_["table"]) { 
            $query = str_replace("[TABLE]",$dbh->_connection_["table"],$query);
        }

    return $query;    
    }

    //--------------------------------------------------------------------------

    private static function _getQ ( &$data, $mode=null ) {

        if (!is_array($data)) { 
            return false;
        }   
        
        if ($mode === 3) { 
            //--- Update:
            list ($sets,$keys) = $data;
            if (is_array($sets)) { foreach ($sets as $k => $v) { $S[]="$k=?"; $A[]=$v; } }
            if (is_array($keys)) { foreach ($keys as $k => $v) { $K[]="$k=?"; $A[]=$v; } }
            if ($S) { $R = " set " . implode(",",$S); }
            if ($K) { $R.= " where " . implode(" and ",$K); }
            if ($A) { $data = $A; }
            return $R;
        } else {
            $F = null; 
            $V = null;
            $S = null;
            foreach ($data as $k => $v) { 
                if (is_string($k)) { 
                    $F[] = $k; 
                    $V[] = ":" . $k; 
                    $S[] = "$k=:$k"; 
                }
            }

            if (!$mode && is_array($F))     {
                //--- Insert:
                return "( ".implode(",",$F)." ) values ( ".implode(",",$V)." )";
            }
            if ( $mode===1 && is_array($S)) {
                //--- Select:
                return "where ".implode(",",$S);
            }
            if ( $mode===2 && is_array($S)) {
                //--- Delete:
                return "where ".implode(" and ",$S);
            }
        }
        
    return false;    
    }

}