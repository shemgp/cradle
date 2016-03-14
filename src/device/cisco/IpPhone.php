<?php
/*==============================================================================
 *  Title      : Cisco IP phone interaction
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 29.01.2015
 *  Version    : 2.0
 *==============================================================================
 */
namespace digger\cradle\device\cisco;

use Exception;

/**
 * @brief Cisco IP phone interaction
 * 
 * Class to remote interact with  Cisco IP-phone 79xx series
 * 
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 *
 * use digger\cradle\device\cisco\IpPhone;
 *
 * //--- Execute some action on a phone:
 * print_r( IpPhone::execute("172.16.0.1", '<ExecuteItem Priority="0" URL="Dial:7000"/>', "user", "password") );
 *
 * //--- Dial a number:
 * print_r( IpPhone::dial("7000", "172.16.0.1", "user", "password") );
 *
 * //--- Get IP phone info:
 * print_r( IpPhone::getInfo("172.16.0.1") );
 *
 * ~~~
 */
class IpPhone{
 
    /**
     * Execute an action on Cisco IP Phone
     * 
     * @param string $ipAddr    Cisco IP Phone ip-address.
     * @param string $message   Cisco IP Phone Execute-Item (format: "<ExecuteItem Priority='...' URL='...'/>").
     * @param string $user      User name     (phone associated).
     * @param string $pass      User password (phone associated).
     * @return <b>array</b>     An array of structure: 
     * ~~~
     * 
     * array( 
     *      'code'     => ... , 
     *      'response' => ... , 
     *      'error'    => ... , 
     *      'request'  => ... , 
     *      'debug'    => ...
     * )
     * 
     * ~~~
     * @throws Exception
     */    
    static function execute( $ipAddr, $message, $user, $pass ) {
        $return    = [
            'code'     => 0, 
            'response' => '', 
            'error'    => '', 
            'request'  => '', 
            'debug'    => '',
        ];

        $message   = "<CiscoIPPhoneExecute>".$message."</CiscoIPPhoneExecute>\r\n";
        $message   = urlencode($message);
        $length    = strlen($message);
        $auth      = base64_encode($user.':'.$pass);

        $ipAddress = $ipAddr;
        $port      = 80;

        $httpIpMsg = "POST /CGI/Execute HTTP/1.0\n"
                   . "Content-Type: text/xml\n"
                   . "Content-Length: $length\n"
                   . "Authorization: Basic $auth\n\n"
                   . "XML=$message\n";

        try {
            //--- TEST PING -------------------------------------------
            exec("ping -W 1 -c 1 $ipAddress", $a, $r); // Linux PING
            if ($r!=0) { 
                throw new Exception("$ipAddress is unreacheble.");
            }
            //---------------------------------------------------------

            //--- Create a Socket
            $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
            if (!$socket) {
              throw new Exception("Socket creation failed.");
            }
            $return['debug'] .= "Socket created OK.\n";

            //--- Open the socket but don`t wait longer then timeout
            socket_set_nonblock($socket);
            $timeout    = 0;
            $timeoutMax = 3000; // time out for error connections
            while (!@socket_connect($socket, $ipAddress, $port)) {
                $err = socket_last_error($socket);
                if ($err == 115 || $err == 114) { 
                    $timeout++;
                    if ($timeout>$timeoutMax) { 
                       throw new Exception("Socket connection timeout.");
                    }
                    // sleep(1); // timeout 1 second
                } else { 
                    throw new Exception("Socket connection error.");
                }
            }
            socket_set_block($socket);
            //-----------------------------------------------------------------
            $return['debug']  .= "Soceket connected to: $ipAddress:$port OK.\n";
            $return['request'] = $httpIpMsg;
            //--- Send a data
            socket_write($socket, $httpIpMsg , strlen($httpIpMsg));
            //--- Receive a data
            while ($out = socket_read($socket, 2048)) { 
                $return['response'] .= $out;
            }
            $return['code'] = 1;
        }
        catch (Exception $e)
        {
            $return['error'] = $e->getMessage();
            $return['code']  = -1;
        }
        //--- Close the Socket
        if ($socket>0) 
        {
            socket_close($socket);
            $return['debug'] .="Soceket closed.";
        } 

    return $return;
    }

    /**
     * Dail a phone number on Cisco IP Phone
     * 
     * @param string $number   Number to dial.
     * @param string $ipAddr   Cisco IP Phone ip-address.
     * @param string $user     User name (phone associated).
     * @param string $pass     User password (phone associated).
     * @return <b>array</b>    An array same as @ref execute.
     * 
     * @see execute
     */
    static function dial( $number, $ipAddr, $user, $pass )
    {
        return self::Execute($ipAddr,"<ExecuteItem Priority='0' URL='Dial:$number'/>",$user,$pass);
    }

    /**
     * Retrieves Cisco IP Phone information by HTTP request
     * 
     * @param  string $ipAddr IP address of Cisco phone.
     * @return <b>array</b>   An array of the IP phone parameters.
     */
    static function getInfo( $ipAddr ) {
        //--- Get Data 
        $d = file_get_contents("http://$ipAddr");
        if ( $d === false ) { 
            return false;
        }
        //--- Convert non-UTF8 format
        if (!preg_match("/\W(utf-*8)\W/i", $d, $m)) { 
            $d = iconv("WINDOWS-1251","UTF-8",$d);
        }
        //--- Parse data:
        if (preg_match("/^<\?xml /i", $d)) {
        //--- Parse XML file   

            $xml   = simplexml_load_string($d);
            $json  = json_encode($xml);
            $a     = json_decode($json,TRUE); 
            foreach ($a['MenuItem'] as $i => $v) $b[$i] = $v['Name'];
            foreach ($b as $i => $v) { 
                list($key,$value) = split (":", $v, 2);
                $key   = trim($key);
                $value = trim($value);
                if ($key==="" && $value==="") continue; // throw out empty values 
                if (!$value) $value = $key;  
                if (!$key)   $key   = $i;
                $arrayInfo[$key] = $value;
            }

        } else {
        //--- Parce HTML file 
            $d = preg_replace('/<table[^>]*>/i', " ||| ", $d);          // замена тега на разделитель
            $d = preg_replace('/<tr>/i', " || ", $d);                   // замена тега на разделитель
            $d = preg_replace('/<td>/i', " | ", $d);                    // замена тега на разделитель
            //---
            $d = preg_replace('/(<(\/|\!)?[\w-]+[^>]*>)+/i', "\t", $d); // удаление всех HTML-тегов
            $d = preg_replace('/&nbsp;/i', " ", $d);                    // замена пробелов
            $d = preg_replace('/\s+/is', " ", $d);                      // сжатие пробелов
            //---
            $d = preg_replace('/ \|\| \| /', " || ", $d);               // сжатие разделителей
            //---
            $a = explode("|||",$d);
            $b = explode("||",$a[3]); 
            $arrayInfo['device_info'] = trim($a[1],"| ");
            foreach ($b as $i => $v) { 
                list($key,$value) = split ('\|', $v, 2);
                $key   = trim($key);
                $value = trim($value);
                if ($key==="" && $value==="") continue; // throw out empty values 
                if (!$value) $value = $key;  
                if (!$key)   $key   = $i;
                $arrayInfo[$key] = $value;
            }
        }
        
    return $arrayInfo;
    }

}