<?php
/*==============================================================================
 *  Title      : Telnet interaction
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 04.02.2016
 *==============================================================================
 */
namespace digger\cradle\network;

use digger\cradle\common\Basic;
use Exception;

/**
 * @brief Telnet interaction
 * 
 * A simple class to send commands to remote host through the TELNET protocol.
 * 
 * TELNET protocol specification: <http://tools.ietf.org/html/rfc854><br>
 * TELNET option:    <http://www.iana.org/assignments/telnet-options/telnet-options.xhtml><br>
 * TELNET terminals: <http://www.iana.org/assignments/terminal-type-names/terminal-type-names.xhtml#terminal-type-names-1><br>
 *
 * @version 4.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * @todo How to use:
 * @code
   <?php
 
    //--- 1. Short usage:

    $r = (new Telnet([ 'host' => "host", 'user' => "user", 'password' => "password" ]))->exec("show clock");
    print_r( $r );
     
    //--- 2. Normal usage:
 
    $t = new Telnet([
        'host'     => "host", 
        'user'     => "username",
        'password' => "password",
        'debug'    => "debug.dat", // to file (option)
    ]);
 
    $commands = "show clock";                           // Single  command as a string
    $commands = "show clock\nterminal length 0";        // Several commands separated by "\n"
    $commands = ["show clock"];                         // Single  command as an array
    $commands = ["terminal length 0", "show clock"];    // Several commands as an array

    $r = $t->exec($commands);
    print_r( $r );
    if ($r === false) {
        echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
        print_r($t->getErrors());
    }

    //--- 3. Advanced usage:

    $t = new Telnet([
        'host'     => "host", 
        'user'     => "username",
        'password' => "password",
        'timeout'  => 30,         // default timeout is 30 sec
        'debug'    => 2,          // to STDIN (echo)
        'errorSilent' => false,   // enable exceptions
    ]);

    $commands = ["terminal length 0", "show clock"];

    if (!is_array($commands)) { $commands = [$cmd]; }
    try {
        foreach ($commands as $command) {
            echo "command : $command\n";
            $r = $t->exec($command, 20); // every command with own timeout 20 sec 
            print_r($r);
        }
    } catch (Exception $e) {
        echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
        echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
        print_r($t->getErrors());
    }
 
    $t->close();
  
    //--- 4. Several targets:

    $config   = ['user' => "user", 'password' => "password", 'errorSilent' => false, 'debug' => 2];
    $hosts    = ['host1', 'host2', 'host3'];
    $commands = ['terminal length 0', 'show clock'];

    $t = new Telnet($config);

    foreach ($hosts as $host) {
        try {
            echo "Host: $host\n";
            $t->open($host);
            $r = $t->exec($commands); 
            print_r($r);
        } catch (Exception $e) {
            echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
            echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
            print_r($t->getErrors());
        }
    }
    
    $t->close();

 * @endcode
 */
class Telnet {

    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------
    
    /**
     * @var string The target host (name|ip-address) 
     */
    public $host;
    /**
     * @var int The target TCP port.
     */
    public $port      = 23;
    /**
     * @var string The username for authentication by 'user & password' method. 
     */
    public $user; 
    /**
     * @var string The password for authentication by 'user & password' method or 'just password' method. 
     */
    public $password;
    
    /**
     * @var int The socket default timeout in seconds. 
     * Defines how long should wait the response from remote side.
     */
    public $timeout   = 10;
    
    /**
     * @var boolean If set true, the data returned by `exec` method will be trimmed.
     * The echo of the command will be deleted from the beginning and the marker 'Ready for input'
     * of the remote side will be deleted from the end.
     */
    public $trimResponse = true;
    
    /**
     * @var boolean  Defines the method of error throwing.  <br>
     *  Possible values:                                    <br>
     *              true  - log error to own buffer (no exception),
     *                      the returned value of fail `exec` will be === false;     <br>
     *              false - throw exeption on error;
     */
    public $errorSilent     = true; // no exception
    
    /**
     * @var mixed Defines a debug target.   <br>
     *  Possible values:                    <br>
     *      false    - no debug;            
     *      1        - debug to buffer;     
     *      2        - debug to STDIN;      <br>
     *      filename - debug to file;
     */
    public $debug           = 1; // debug to buffer
       
    //---------------------------------
    // Terminal parameters
    //---------------------------------
    
    /**
     * @var boolean Enable or disable echo.                       <br>
     * TELNET option (1) "ECHO-ON" <http://tools.ietf.org/html/rfc857>
     */
    public $terminalEchoOn  = false;
    /**
     * @var boolean Enable or disable suppressing transmission of the 'TELNET GO AHEAD' character.<br>
     * TELNET option (3) "SUPPRESS-GO-AHEAD" <http://tools.ietf.org/html/rfc858>
     */
    public $terminalGoAhead = true;
    /**
     * @var string Terminal type name.                                         <br>
     * TELNET option (24) "TERMINAL-TYPE" <http://tools.ietf.org/html/rfc1091> <br>
     * TELNET terminal names <http://www.iana.org/assignments/terminal-type-names/terminal-type-names.xhtml#terminal-type-names-1>
     */
    public $ternimalType    = "DEC-VT100";
    /**
     * @var int Value of terminal characters in line.                   <br>
     * TELNET option (31) "WINDOW-SIZE" <http://tools.ietf.org/html/rfc1073>
     */
    public $terminalWidth   = 0;
    /**
     * @var int Value of terminal lines count.                          <br>
     * TELNET option (31) "WINDOW-SIZE" <http://tools.ietf.org/html/rfc1073>
     */
    public $terminatHeight  = 0;

    //---------------------------------
    // Error codes
    //---------------------------------
    
    const ERR_HOST_IS_EMPTY       = 1;
    const ERR_HOST_IS_UNREACHABLE = 2;
    const ERR_SOCKET              = 3;
    const ERR_AUTH_FAIL           = 4;
    const ERR_CLOSED_BY_REMOTE    = 5;
    const ERR_TIMEOUT             = 6;

    //---------------------------------
    // Spesial
    //---------------------------------
    
    /**
     * @var string Regular expression template to find a marker 'Ready for input'
     * of remote side. By default it set as: '/^[^#>\$\%]+[#>\$\%]\s*$/'
     */
    public $readyKeyTemplate = '/^[^#>\$\%]+[#>\$\%]\s*$/'; 
    
    //--------------------------------------------------------------------------
    // Public functions
    //--------------------------------------------------------------------------
    
    /**
     * Constructor
     * 
     * @param array $config An array of properties to initialize the class.
     */
    public function __construct( $config = null ) {
        $this->init($config);
    }
    
    /**
     * Destructor
     */
    function __destruct() {
        $this->close();
    }

    /**
     * Open new telnet connection
     * 
     * @param  array|string    $config  An array of properties to initialize the class. 
     *                                  If `$config` is a string it will be interpreted as a `host` 
     *                                  (same as $config = [ 'host' => $config ]).
     * @return resource|false           Resource ID of the open socket or FALSE on fail.
     */
    public function open( $config = null ) {
        
        //--- If it's a new config to create a new conection:
        if ($config) {
            if ($this->socket) $this->close();
            if (is_string($config)) { $config = ['host' => $config]; }
            $this->init($config);
        }
        
        //--- Nothing to do if the socket is opened:
        if ($this->socket) {
            return $this->socket;
        }
        
        //--- Check the host:
        if (!$this->host) { 
            $this->error("Host property is empty", __FUNCTION__, self::ERR_HOST_IS_EMPTY);
            return false; 
        }
        
        //--- Is host alive?
        if (!$this->isAlive()) {
            $this->error("Host [" . $this->host. "] is unreachable", __FUNCTION__, self::ERR_HOST_IS_UNREACHABLE);
            return false;
        }
        
        //--- Craete Socket (TCP):
        if (!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
            $this->error("Socket:", __FUNCTION__, self::ERR_SOCKET, $this->socket);
            return false; 
        }
        
        //--- Connect to host:
        if (! socket_connect($this->socket, $this->host, $this->port)) {
            $this->error("Connect:", __FUNCTION__, self::ERR_SOCKET, socket_last_error());
            return false; 
        }
        $this->debug("Connected [" . $this->host . ":" . $this->port . "], socket [" . $this->socket . "]", __FUNCTION__);
        
        //--- First handshake:
        $this->handshake();
        
    return $this->socket;
    }
    
    /**
     * Close the telnet connection
     */
    public function close() {
        if ($this->socket) { 
            socket_close($this->socket); 
            $this->socket          = null;
            $this->isAuthenticated = false;
            $this->debug("OK", __FUNCTION__);
        }
    }
    
    /**
     * Execute a single command or an array of commands on the remote side.
     * 
     * @param  string|array  $command   A command or an array of commands to execute.
     * @param  int           $timeout   (Option) Timeout in seconds to wait the response. 
     *                                  If it doesn't set the default timeout will be used.
     * 
     * @return string|array|false       Text data of the response from the remote side.
     *                                  If the `$command` was an array (or string contained "\n")
     *                                  the response data would be an array of format: 'command' => 'response text'.
     *                                  Returns FALSE on error.
     */
    public function exec($command, $timeout = null) {
        
        //--- Open and authenticate:
        if ($this->open() && $this->authenticate()) { 
            
            //--- Prepare an array of commands:
            if (!is_array($command)) { 
                $commands = explode("\n", $command); 
            } else {
                $commands = $command;
            }
            
            //--- Execute an array of commands:
            for ($i=0; $i<count($commands); $i++) {
                //--- the command may include some other commands separate by "\n":
                foreach (explode("\n", $commands[$i]) as $singleCommand) {
                    if ( ($result[$singleCommand] = $this->executeCommand($singleCommand, $timeout)) === false ) {
                        $i = count($commands);
                        break;
                    }
                }
            }
            
            //--- Return result: (a single string or an array)
            if (count($result) === 1 && is_string($command)) {
                return array_pop($result); 
            }
            return $result;
        }
        
    return false;
    }
    
    //--------------------------------------------------------------------------

    /**
     * Returns data of the last error
     * @return array An array of error data (text message, code, target, ...)
     */
    public function getLastError() {
        return count($this->errorBuffer)>0 ? $this->errorBuffer[count($this->errorBuffer)-1] : null;
    }

    /**
     * Returns an array of all errors stored in error buffer.
     * @return array An array of error buffer.
     */
    public function getErrors() {
        return $this->errorBuffer;
    }
    
    /**
     * Returns an array of debug buffer if `debug` property is not FALSE.
     * @return array An array of debug buffer.
     */
    public function getDebug() {
        return $this->debugBuffer;
    }

    /**
     * Returns a marker 'Ready for input' of remote side
     * @return string The marker
     */
    public function getReadyKey() {
        return $this->readyKey;
    }    
    
    /**
     * Checks if a host is reachable
     *  
     * @param  int      $timeout (Option) Timeout in seconds to wait the response from remote side.
     * @return boolean  TRUE  - host is reachable. <br>
     *                  FALSE - host is unreachable.
     */
    public function isAlive($timeout = null) {
        
        $timeout = $timeout ? $timeout : $this->timeout;
        $timeout = $timeout ? $timeout : 5;
        
        if($fp = @fsockopen($this->host, $this->port, $errCode, $errStr, $timeout)){   
           $alive = true;  // Host is alive
           fclose($fp);
        } else {
           $alive = false; // Host is unreachable 
        } 
        
    return $alive;    
    }
    
    //==========================================================================
    // Private
    //==========================================================================
    
    private $socket;
    
    private $isAuthenticated = false;
    
    private $errorBuffer = [];
    private $debugBuffer = [];
    private $inputBuffer;
    
    private $readyKey;

    //--------------------------------------------------------------------------
    
    /**
     * Init the class
     */
    private function init($config) {
        //--- Init the class:
        Basic::initClass($this, $config);
        //--- Create file for debug:
    if (is_string($this->debug)) { file_put_contents ($this->debug, ""); }
    }
    
    //--------------------------------------------------------------------------
  
    /**
     * Create an error
     * 
     * @throws Exception
     */
    private function error($message, $process = "", $code = null, $extCode = null) {
        //--- Extend the message:
        if ($code === self::ERR_SOCKET) { $message .= ($message ? " " : "") . socket_strerror($extCode); }
        //--- Error structure:
        $this->errorBuffer[] = [ 
            "process" => $process, 
            "message" => $message, 
            "code"    => $code, 
            "extcode" => $extCode 
        ];
        //--- Throw exception if need:
        if (!$this->errorSilent) {
            throw new Exception($message, $code);
        }
    }
    
    /**
     * Create a debug message
     */
    private function debug($message, $process = "") {
        if (!$this->debug) { return; }
        
        $message = $process . " : " . $message;
        
        if ($this->debug === 1) {
            //--- Log to buffer:
            $this->debugBuffer[] = $message;
        } else if ($this->debug === 2) {
            //--- Log to STDIN:
            echo $message . "\n";
        } else if (is_string($this->debug) && is_file($this->debug)) {
            //--- Log to file:
            file_put_contents($this->debug, $message . "\n", FILE_APPEND);
        }
    }
    
    //--------------------------------------------------------------------------
    // Communication
    //--------------------------------------------------------------------------
    
    /**
     * Check the exception
     * 
     * @return boolean TRUE  if it is an error;     <br>
     *                 FALSE if it's not en error
     */
    private function isError($e, $process) {
        $this->debug($e->getMessage(), $process);
        //if ($e->getCode() === self::ERR_TIMEOUT) {
            //--- It's not an error
            //... Continue
        //    return false;
        //} 
        //--- Create an error:
        $this->error($e->getMessage(), $process, $e->getCode());
        return true;
    }
    
    /**
     * First request to remote side
     */
    private function handshake() {
        $this->debug("start", __FUNCTION__);
        try {
            $this->inputBuffer = $this->read($this->timeout);
        } catch (Exception $e) {
            $this->isError($e, __FUNCTION__);
        }
    }    
    
    /**
     * Authenticate the access to remote side
     * 
     * @return boolean  TRUE  - Authentication is success. <br>
     *                  FALSE - Authentication is fail.
     */
    private function authenticate() {
        //--- Nothing to do if authentication is already pass:
        if ($this->isAuthenticated) {
            return true;
        }
        //--- Nothing to do if User & Password is empty:
        if (!$this->user && !$this->password) {
            $this->debug("User & Password is empty - authentication is disabled", __FUNCTION__);
            return true;
        }
        
        //--- Authenticate:
        $this->debug("start", __FUNCTION__);
        
        try {
            
            $authType = false;
            do {
                if (!preg_match("/(login|user|password)/i", array_pop(explode("\n", $this->inputBuffer)), $m)) {
                        $this->inputBuffer .= $this->read($this->timeout);
                } else {
                    $authType = strtolower($m[1]);
                }
            } while (!$authType);
            $this->debug("method: " . $authType, __FUNCTION__);
            
            switch ($authType) {
                case  "user":   //--- Auth by user & password
                case "login":
                    $this->send($this->user . "\n");
                    $this->getAnswer('/password/i');
                    $this->send($this->password . "\n");
                    break;
                default:        //--- Auth by password only
                    $this->send($this->password . "\n");
                    break;
            }
            $this->getAnswer(
                [
                    $this->readyKeyTemplate,    //--- OK
                    '/(fail|invalid|error)/ims' //--- FAIL 
                ], 
                0, 
                $this->timeout,
                [
                    function ($answer, $m) { $this->isAuthenticated = true; },
                    function ($answer, $m) { $this->error(preg_replace('/\s+/', " ", $answer), "device", self::ERR_AUTH_FAIL); }        
                ]
            );
                        
        } catch (Exception $e) {
            $this->isError($e, __FUNCTION__);
        }

        if (!$this->isAuthenticated) {
            $this->debug("Authentication is failed. (Connection will be closed)", __FUNCTION__);
            $this->close();
            $this->error("Authentication is failed", __FUNCTION__, self::ERR_AUTH_FAIL);
        } else {
            $this->debug("Success", __FUNCTION__);
        }
    
    return $this->isAuthenticated;    
    }

    /**
     * Execute a single command on remote side
     * 
     * @return string   The text data of response.
     */
    private function executeCommand($command, $timeout = null) {
        
        $command = $command . ""; //--- convert to string

        //--- Do not send an empty command:
        if ($command == "") return "";

        //--- Send the command:
        $this->send($command . "\n"); //--- Add control enter
        //--- Receive the answer:
        $answerData = $this->getAnswer($this->readyKeyTemplate, 1, $timeout, function($ansewr, $m){ $this->readyKey = $m[0]; });
        //--- Strip a garbage:
        if ($this->trimResponse) {
            //--- Delete command echo from begining:
            $strings = [$command, "\r", "\n"];
            foreach ($strings as $deleteSring) {
                if ( ($p = strpos($answerData, $deleteSring)) === 0 ) { 
                    $answerData = substr($answerData, strlen($deleteSring));
                }
            }
            //--- Delete `readyKey` at the end:
            if ( ($p = strrpos($answerData, $this->readyKey)) !== false ) { 
                $answerData = substr($answerData, 0, $p);
            }
        }
            
    return $answerData;        
    }     
    
    //--------------------------------------------------------------------------
    // Read & Write operations
    //--------------------------------------------------------------------------
    
    /**
     * Sends a data through the socket
     * 
     * @param  string    $data  Data to send
     * @return int|false        Count of bytes sent or FALSE on fail
     */
    private function send($data) {
        $bytesSent = socket_send($this->socket, $data, strlen($data), 0);
        $this->debug("bytes [$bytesSent], data [" . $data . "]", __FUNCTION__);
        if ($bytesSent === false) {
            $this->error("", __FUNCTION__, self::ERR_SOCKET, socket_last_error());
        }
    return $bytesSent;
    }
    
    /**
     * Reads the response data from the socket.
     * Parses response data and separates into commands and text data.
     * Sends telnet commands to remote side if it will found in response.
     * 
     * @param  int  $timeout Maximum seconds to wait the response
     * 
     * @return string|false  Text data of response or FALSE on fail.
     * 
     * @throws Exception 
     *      1. If timeout; 
     *      2. If socket is closed by the remote side
     *      3. If socket error
     */
    private function read($timeout) {
        $buffer   = "";
        $size     = 8192;
        $timer    = 0;
        $timerMax = 100 * ($timeout ? $timeout : 1); // 1 sec by default
        
        $bytes = false;
        while($bytes === false) {
            $b       = "";
            $bytes   = @socket_recv($this->socket, $b, $size, MSG_DONTWAIT); // 0=disconnected, false=no data
            $buffer .= $b;
            $timer++; //echo $timer . ") [$bytes] [" . strlen($buffer) . "]\n";
            if ($bytes === 0) {
                //--- Close current session:
                $this->close();
                $error = "socket is closed by the remote side";
                $this->error($error, __FUNCTION__, self::ERR_CLOSED_BY_REMOTE);
                throw new Exception($error, self::ERR_CLOSED_BY_REMOTE);
            }
            if ($bytes === false) {
                if ($timer >= $timerMax) {
                    //--- This not an error just timeout:
                    throw new Exception("timeout", self::ERR_TIMEOUT);
                } else {
                    usleep(10000);
                }
            }
        }
        
        list($commands, $text) = $this->parseInput($buffer);
        
        $this->debug("bytes [$bytes],\n\tdata comm [" . $this->toString($commands, "comm") . "],\n\tdata text [" . $text . "]", __FUNCTION__);
        
        if ($bytes === false) {
            $this->error("", __FUNCTION__, self::ERR_SOCKET, socket_last_error());
            throw new Exception(socket_last_error(), self::ERR_SOCKET);
        }
        
        //--- Response to input commands:
        if ( $responseComm=$this->getCommResponse($commands) ) {
            $this->send($responseComm);
        }
        //------------------------------
        
    return $text;    
    }

    /**
     * Advanced `read` (`read` wrapper)
     */
    private function getAnswer($readyKeyTemplates, $analyzeMode = 0, $timeout = null, $callbackFunctions = null, $failFunction = null) {
        if (!is_array($readyKeyTemplates)) $readyKeyTemplates = [$readyKeyTemplates];
        if (!is_array($callbackFunctions)) $callbackFunctions = [$callbackFunctions];
        if (!$timeout)                     $timeout           = $this->timeout;
        $answer = "";
        $finish = false;
        try {
                do {
                    //--- Read from socket:
                    $answer .= $this->read($timeout);
                    if ($analyzeMode) { //--- Fast analyze: (only the last string)
                        $analyze = array_pop(explode("\n", $answer));
                    } else {            //--- Tolal analyze: (all the answer) 
                        $analyze = $answer;
                    }
                    //--- Parse received data:
                    foreach ($readyKeyTemplates as $i => $readyKeyTemplate) {
                        if (preg_match($readyKeyTemplate, $analyze, $m)) {
                            if (is_callable($callbackFunctions[$i])) {
                                //--- Execute callback function on complete:
                                $callbackFunctions[$i]($answer, $m);
                            }
                            $finish = true;
                            break;
                        }
                    }
                } while (!$finish);
            } catch (Exception $e) {
                
                if (is_callable($failFunction)) {
                    
                    $this->debug($e->getMessage(), __FUNCTION__);
                     //--- Execute function on fail
                    $failFunction($e); 
                    
                } else {
                    
                    if ($this->isError($e, __FUNCTION__)) {
                        //--- Clear the received data:
                        $answer = false;
                    } 
                    
                }
                
            }
    return $answer;        
    }
    
    //--------------------------------------------------------------------------
    // Parse terminal commands & options
    //--------------------------------------------------------------------------

    /**
     * Parses data and separate into TELNET-commands and text
     */
    private function parseInput($string) {
        
        $text     = ''; 
        $commands = null;
        $len      = strlen($string);
        
        for ($i=0; $i<$len; $i++) {
            if (ord($string[$i]) == 0xff) { // Commands block: IAC (255)
                
                $cmd = ord($string[++$i]);  // Telnet command
                if ($cmd == 0xfb | // WILL (251)
                    $cmd == 0xfc | // WONT (252)
                    $cmd == 0xfd | // DO   (253)
                    $cmd == 0xfe   // DONT (254)
                   ) {
                    
                        $r = [ $cmd => ord($string[++$i]) ];
                        
                } else if ( $cmd == 0xfa ) { // SB (250)
                    
                    $opt    = ord($string[++$i]);
                    $params = "";
                    while($i<$len) {
                        $i++;
                        if (ord($string[$i]) == 0xff && ord($string[$i+1]) == 0xf0) { // IAC SE (240)
                            $i++;
                            break; 
                        }
                        $params [] = ord($string[$i]); //if (ord($string[$i]) >= 32 && ord($string[$i])<=126) { $params .= $string[$i]; }
                        
                    }
                    $r = [ $cmd => [ $opt => implode(' ', $params) ]];
                    
                } else {
                    
                    $r = $cmd;
                    
                }
                
                $commands[] = $r;
                
            } else { // Text block:
                $text .= $string[$i];
            }
        }
        
    return [ $commands, $text ];    
    }
    
    /**
     * Create a string with TELNET commands for response to remote side
     */
    private function getCommResponse($commBuffer) {
        if (!$commBuffer) return null;
        
        $IAC = chr(0xff);
        
        foreach ($commBuffer as $command) {
            if (!is_array($command)) continue;
            foreach ($command as $cmd => $opt) {
                $apply = false;
                switch ($cmd) {
                    case 0xfb: // WILL (251)
                        $rpcmd = chr(0xfd); // DO
                        $rncmd = chr(0xfe); // DONT 
                        $apply = true;
                        break;
                    case 0xfd: // DO   (253) 
                        $rpcmd = chr(0xfb); // WILL
                        $rncmd = chr(0xfc); // WONT 
                        $apply = true;
                        braek;
                    default:
                }
                if ($apply) {
                    $rcmd = $rncmd . chr($opt); //--- Negative option by default
                    switch ($opt) {
                        case 0x1: // ECHO-ON (1)
                            if ($this->terminalEchoOn) $rcmd = $rpcmd . chr($opt); //--- Positive option
                            break;
                        case 0x3: // GO-AHEAD (3)
                            if ($this->terminalGoAhead) $rcmd = $rpcmd . chr($opt); //--- Positive option
                            break;
                        case 0x18: // TERMINAL-TYPE (24)
                                $rcmd   =        chr(0xfb) . chr($opt)
                                        . $IAC . chr(0xfa) . chr($opt) . chr(0)
                                        . $this->ternimalType
                                        . $IAC . chr(0xf0);// . chr($opt);
                            break;
                        case 0x1f: // WINDOW-SIZE (31)
                                $rcmd   =        chr(0xfb) . chr($opt)
                                        . $IAC . chr(0xfa) . chr($opt)
                                        . chr($this->terminalWidth  >> 8) . chr($this->terminalWidth  & 0x00ff) 
                                        . chr($this->terminatHeight >> 8) . chr($this->terminatHeight & 0x00ff)
                                        . $IAC . chr(0xf0) . chr($opt);
                            break;
                    }
                    $out .= $IAC . $rcmd;
                }
            }
        }
    return $out;
    }
    
    /**
     * Converts mixed data to string
     */
    private function toString($input, $mode = null) {
        switch ($mode) {
            case "comm": // terminal commands array:
                if (is_array($input)) {
                    foreach ($input as $cmd) {
                        if (is_array($cmd)) {
                            foreach ($cmd as $key => $value) {
                                if (is_array($value)) {
                                    $value = preg_replace('/\s+/', ' ', print_r($value, true));
                                } else {
                                    $value = $this->getTerminalOptionName($value);
                                }
                                $out[] = $this->getTerminalCommandName($key) . ":" . $value;
                            }
                        } else {
                            $out[] = $cmd;
                        }
                    }
                    $out = implode(", ", $out);
                }
                break;
            default: 
                $out = print_r($input, true);
        }
    return $out;    
    }
    
    /**
     * @var array TELNET commands 
     */
    private $tmCmd = [
        "SE"   => 0xf0, // (240)
        "SB"   => 0xfa, // (250)
        "WILL" => 0xfb, // (251)
        "WONT" => 0xfc, // (252)
        "DO"   => 0xfd, // (253)
        "DONT" => 0xfe, // (254)
        "IAC"  => 0xff  // (255) «Interpret as Command»
    ];
    private $tmCmdCodes = null;

    /**
     * @var array TELNET options 
     */
    private $tmOption = [ // http://www.iana.org/assignments/telnet-options/telnet-options.xhtml
        "ECHO"   => 0x1,  // (1)  ECHO              http://tools.ietf.org/html/rfc857
        "SUPG"   => 0x3,  // (3)  SUPPRESS-GO-AHEAD http://tools.ietf.org/html/rfc858
        "TTYPE"  => 0x18, // (24) TERMINAL-TYPE     http://tools.ietf.org/html/rfc1091
        "WSIZE"  => 0x1f, // (31) WINDOW-SIZE       http://tools.ietf.org/html/rfc1073
    ];
    private $tmOptionCodes = null;
   
    /**
     * TELNET command code to command name
     */
    private function getTerminalCommandName($code) {
        if (!is_array($this->tmCmdCodes))   $this->tmCmdCodes = array_flip($this->tmCmd);
        if (array_key_exists($code, $this->tmCmdCodes)) $code = $this->tmCmdCodes[$code];
    return $code;    
    }
    
    /**
     * TELNET option code to option name
     */
    private function getTerminalOptionName($code) {
        if (!is_array($this->tmOptionCodes))   $this->tmOptionCodes = array_flip($this->tmOption);
        if (array_key_exists($code, $this->tmOptionCodes)) $code = $this->tmOptionCodes[$code];
    return $code;    
    }
    
}
