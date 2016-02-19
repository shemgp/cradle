<?php
/*==============================================================================
 *  Title      : Telnet interaction
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 04.02.2016
 *==============================================================================
 */
namespace digger\cradle\network;

use digger\cradle\common\Basic;
use digger\cradle\common\Logger;
use digger\cradle\common\Debug;
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
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 *
 *   //--- 1. Short usage:
 *
 *   $r = (new Telnet([ 'host' => "host", 'user' => "user", 'password' => "password" ]))->exec("show clock");
 *   print_r( $r );
 *    
 *   //--- 2. Normal usage:
 *
 *   $t = new Telnet([
 *       'host'     => "host", 
 *       'user'     => "username",
 *       'password' => "password",
 *       'debug'    => "debug.dat", // to file (option)
 *   ]);
 *
 *   $commands = "show clock";                           // Single  command as a string
 *   $commands = "show clock\nterminal length 0";        // Several commands separated by "\n"
 *   $commands = ["show clock"];                         // Single  command as an array
 *   $commands = ["terminal length 0", "show clock"];    // Several commands as an array
 *
 *   $r = $t->exec($commands);
 *   print_r( $r );
 *   if ($r === false) {
 *       echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
 *       print_r($t->getErrors());
 *   }
 *
 *   //--- 3. Advanced usage:
 *
 *   $t = new Telnet([
 *       'host'     => "host", 
 *       'user'     => "username",
 *       'password' => "password",
 *       'timeout'  => 30,         // default timeout is 30 sec
 *       'debug'    => 2,          // to STDIN (echo)
 *       'errorSilent' => false,   // enable exceptions
 *   ]);
 *
 *   $commands = ["terminal length 0", "show clock"];
 *
 *   if (!is_array($commands)) { $commands = [$cmd]; }
 *   try {
 *       foreach ($commands as $command) {
 *           echo "command : $command\n";
 *           $r = $t->exec($command, 20); // every command with own timeout 20 sec 
 *           print_r($r);
 *       }
 *   } catch (Exception $e) {
 *       echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
 *       echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
 *       print_r($t->getErrors());
 *   }
 *
 *   $t->close();
 * 
 *   //--- 4. Several targets:
 *
 *   $config   = ['user' => "user", 'password' => "password", 'errorSilent' => false, 'debug' => 2];
 *   $hosts    = ['host1', 'host2', 'host3'];
 *   $commands = ['terminal length 0', 'show clock'];
 *
 *   $t = new Telnet($config);
 *
 *   foreach ($hosts as $host) {
 *       try {
 *           echo "Host: $host\n";
 *           $t->open($host);
 *           $r = $t->exec($commands); 
 *           print_r($r);
 *       } catch (Exception $e) {
 *           echo "Exception: " . $e->getMessage() . " Code: " . $e->getCode() . "\n";
 *           echo "Error: " . implode(" : ", $t->getLastError()) . "\n";
 *           print_r($t->getErrors());
 *       }
 *   }
 *   
 *   $t->close();
 * 
 * ~~~
 */
class Telnet {

    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------
    
    /**
     * @var_ <i>string</i> The target host (name|ip-address) 
     */
    public $host;
    /**
     * @var_ <i>int</i> The target TCP port.
     */
    public $port = 23;
    /**
     * @var_ <i>string</i> The username for authentication by 'user & password' method. 
     */
    public $user; 
    /**
     * @var_ <i>string</i> The password for authentication by 'user & password' method or 'just password' method. 
     */
    public $password;
    
    /**
     * @var_ <i>int</i> The socket default timeout in seconds. 
     * Defines how long should wait the response from remote side.
     */
    public $timeout = 10;
    
    /**
     * @var_ <i>boolean</i> If set true, the data returned by `exec` method will be trimmed.
     * The echo of the command will be deleted from the beginning and the marker 'Ready for input'
     * of the remote side will be deleted from the end.
     */
    public $trimResponse = true;
    
    /**
     * @var_ <i>boolean</i>  Defines the method of error throwing. <br>
     *  Possible values:                                           <br>
     *              true  - log error to own buffer (no exception),
     *                      the returned value of fail `exec` will be === false;     <br>
     *              false - throw exeption on error;
     */
    public $errorSilent = true; // no exception
    
    /**
     * @var_ <i>mixed</i> Defines a debug messages destination.  <br>
     *  Possible values:                    <br>
     *      false    - no debug;            <br>
     *      1        - debug to buffer;     <br>
     *      2        - debug to STDIN;      <br>
     *      filename - debug to file;
     */
    public $debug           = 1; // debug to buffer
       
    /**
     * @var_ <i>mixed</i> Defines a error mesages destination.  <br>
     *  Possible values:                    <br>
     *      false    - no debug;            <br>
     *      1        - debug to buffer;     <br>
     *      2        - debug to STDIN;      <br>
     *      filename - debug to file;
     */
    public $error           = 1; // errors to buffer


    //---------------------------------
    // Terminal parameters
    //---------------------------------
    
    /**
     * @var_ <i>boolean</i> Enable or disable echo.               <br>
     * TELNET option (1) "ECHO-ON" <http://tools.ietf.org/html/rfc857>
     */
    public $terminalEchoOn  = false;
    /**
     * @var_ <i>boolean</i> Enable or disable suppressing transmission of the 'TELNET GO AHEAD' character.<br>
     * TELNET option (3) "SUPPRESS-GO-AHEAD" <http://tools.ietf.org/html/rfc858>
     */
    public $terminalGoAhead = true;
    /**
     * @var_ <i>string</i> Terminal type name.                                 <br>
     * TELNET option (24) "TERMINAL-TYPE" <http://tools.ietf.org/html/rfc1091> <br>
     * TELNET terminal names <http://www.iana.org/assignments/terminal-type-names/terminal-type-names.xhtml#terminal-type-names-1>
     */
    public $ternimalType    = "DEC-VT100";
    /**
     * @var_ <i>int</i> Value of terminal characters in line.           <br>
     * TELNET option (31) "WINDOW-SIZE" <http://tools.ietf.org/html/rfc1073>
     */
    public $terminalWidth   = 0;
    /**
     * @var_ <i>int</i> Value of terminal lines count.                  <br>
     * TELNET option (31) "WINDOW-SIZE" <http://tools.ietf.org/html/rfc1073>
     */
    public $terminatHeight  = 0;
    
    //---------------------------------
    // Spesial
    //---------------------------------
    
    /**
     * @var_ <i>string</i> Regular expression template to find a input prompt marker ('Ready for input')
     * of remote side. By default it set as: '/^[^#>\$\%]+[#>\$\%]\s*$/'
     */
    public $inputPromptTemplate = '/^[^#>\$\%]+[#>\$\%]\s*$/'; 
    
    //---------------------------------
    // Error codes
    //---------------------------------
    
    const ERR_HOST_IS_EMPTY       = 1;
    const ERR_HOST_IS_NOT_ALIVE = 2;
    const ERR_SOCKET              = 3;
    const ERR_AUTH_FAIL           = 4;
    const ERR_CLOSED_BY_REMOTE    = 5;
    const ERR_TIMEOUT             = 6;
    const ERR_TIMEOUT_EXEC        = 7;

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
     * @return <i>resource|false</i>    Resource ID of the open socket or FALSE on fail.
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
            $this->error(self::ERR_HOST_IS_EMPTY, __FUNCTION__);
            return false; 
        }
        $this->debug(date("Y.m.d H:i:s"), __FUNCTION__);
        
        //--- Is host alive?
        if (!$this->isAlive()) {
            $this->error(self::ERR_HOST_IS_NOT_ALIVE, __FUNCTION__, $this->host);
            return false;
        }
        $this->debug("Host [" . $this->host . "] is alive", __FUNCTION__);
        
        //--- Craete Socket (TCP):
        if (!$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
            $this->error(self::ERR_SOCKET, __FUNCTION__, $this->socket);
            return false; 
        }
        
        //--- Connect to host:
        if (! socket_connect($this->socket, $this->host, $this->port)) {
            $this->error(self::ERR_SOCKET, __FUNCTION__, socket_last_error());
            return false; 
        }
        $this->debug("Connected to [" . $this->host . ":" . $this->port . "], socket [" . $this->socket . "]", __FUNCTION__);
        
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
     * @return <i>string|array|false</i> Text data of the response from the remote side.
     *                                   If the `$command` was an array (or string contained "\n")
     *                                   the response data would be an array: [ 0 => 'response to command-1', 1 => 'response to command-2', ... ]
     *                                   Returns FALSE on error.
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
                    if ( ($result[] = $this->executeCommand($singleCommand, $timeout)) === false ) {
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
     * @return <i>array</i> An array of error data (text message, code, target, ...)
     */
    public function getLastError() {
        return $this->errorLogger->getLastMessage();
    }

    /**
     * Returns an array of all errors stored in error buffer.
     * @return <i>array</i> An array of error buffer.
     */
    public function getErrors() {
        return $this->errorLogger->getMessages();
    }
    
    /**
     * Returns an array of debug buffer if `debug` property is not FALSE.
     * @return <i>array</i> An array of debug buffer.
     */
    public function getDebug() {
        return $this->debugLogger->getMessages();
    }

    /**
     * Returns a input prompt marker of remote side
     * @return <i>string</i> The marker
     */
    public function getInputPrompt() {
        return $this->inputPrompt;
    }    
    
    /**
     * Returns the last received data from remote side
     * 
     * @param boolean $trimmed (option) Default value = $trimResponse (@see $trimResponse)
     * @return <i>string</i> Last received data
     */
    public function getInputBuffer($trimmed = null) {
        
        $trimmed     = $trimmed !== null ? $trimmed : $this->trimResponse ;
        $inputBuffer = $this->inputBuffer;
        
        //--- Strip a garbage:
        if ($trimmed) {
            $inputBuffer = $this->trimResponse($inputBuffer);
        }
        return $inputBuffer;
    }
    
    /**
     * Returns seconds of the last timeout
     * 
     * @return float Seconds of last timeout
     */
    public function getLastTimeout() {
        return $this->lastTimeout;
    }
    
    /**
     * Checks if a connection is available
     *  
     * @param  int   $timeout (Option) Timeout in seconds to wait the response from remote side.
     * @return <i>boolean</i>  TRUE  - connection is available. <br>
     *                         FALSE - connection is not available.
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
   
    private $inputBuffer;
    private $lastRequest;
    private $inputPrompt;
    private $lastTimeout;
    
    //--- Loggers:
    
    protected $debugLogger;
    protected $errorLogger;


    //--------------------------------------------------------------------------
    
    /**
     * Init the class
     */
    private function init($config) {
        //--- Init the class:
        Basic::initClass($this, $config);
        //--- Create loggers:
        if (!is_object($this->debugLogger) || isset($config['debug'])) {
            $this->debugLogger = new Debug($this->debug);
        }
        if (!is_object($this->errorLogger) || isset($config['debug'])) {
            $this->errorLogger = new Logger($this->error);
        }
    }
    
    //--------------------------------------------------------------------------
  
    /**
     * Create an error
     * 
     * @throws Exception
     */
    private function error($code, $source = "", $detail = null) {
        //--- Get a message:
        switch ($code) {
            case self::ERR_HOST_IS_EMPTY:
                $message = "host [$detail] is empty";
                break;
            case self::ERR_HOST_IS_NOT_ALIVE:
                $message = "host [$detail] is not alive";
                break;
            case self::ERR_SOCKET:          
                $message = "socket error: " . socket_strerror($detail);
                break;
            case self::ERR_AUTH_FAIL:
                $message = "authentication failed";
                break;
            case self::ERR_CLOSED_BY_REMOTE:
                $message = "connection is closed by the remote side";
                break;
            case self::ERR_TIMEOUT:
                $message = "timeout";
                break;
            case self::ERR_TIMEOUT_EXEC:
                $message = "the command execution timeout";
                break;
            default:
                $message = 'unknown';
        }
        //--- Error structure:
        $error = [ 
            "source" => $source, 
            "message"=> $message,
            "code"   => $code,
            "detail" => $detail, 
        ];
        //--- Save error:
        $this->errorLogger->save($error, $source);
        $this->debugLogger->save("ERROR: " . $message, $source);
        //--- Throw exception if need:
        if (!$this->errorSilent) {
            throw new Exception($message, $code);
        }
    }
    
    /**
     * Create a debug message
     */
    private function debug($message, $source = "") {
        
        if (!$this->debug) { return; }
        
        if (is_array($message) && $message['0'] === 1) {
            list($code, $bytes, $commands, $text) = $message;
            $message = "bytes [$bytes],\n\tdata comm [" . $this->toString($commands, "comm") . "],\n\tdata text [" . $text . "]";
        }
        
        $this->debugLogger->save($message, $source);
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
    /*
    private function isError($e, $source) {
        //if ($e->getCode() === self::ERR_TIMEOUT) {
            //--- It's not an error
            //... Continue
            //return false;
        //} 
        //--- Create an error:
        $this->error($e->getCode(), $source);
        return true;
    }
    */
    
    /**
     * First request to remote side
     */
    private function handshake() {
        $this->debug("start", __FUNCTION__);
        try {
            $this->inputBuffer = $this->read($this->timeout);
        } catch (Exception $e) {
            $this->error($e->getCode(), __FUNCTION__); // isError
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
                    $this->inputPromptTemplate, //--- OK
                    '/(fail|invalid|error)/ims' //--- FAIL 
                ], 
                0, 
                $this->timeout,
                [
                    function ($answer, $m) { $this->isAuthenticated = true; },
                    function ($answer, $m) { $this->error(self::ERR_AUTH_FAIL, "device", preg_replace('/\s+/', " ", $answer)); }        
                ]
            );
                        
        } catch (Exception $e) {
            $this->error($e->getCode(), __FUNCTION__); // isError
        }

        if (!$this->isAuthenticated) {
            $this->close();
            $this->error(self::ERR_AUTH_FAIL, __FUNCTION__);
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

        $this->lastRequest = $command;
        
        //--- Send the command:
        $this->send($this->lastRequest . "\n"); //--- Add control enter
        
        try {
            
            //--- Receive the answer:
            $responseData = $this->getAnswer($this->inputPromptTemplate, 1, $timeout, function($ansewr, $m){ $this->inputPrompt = $m[0]; });
            //--- Strip a garbage:
            if ($this->trimResponse) {
                $responseData = $this->trimResponse($responseData);
            }
            
        } catch (Exception $e) {
            
            if ($e->getCode() == self::ERR_TIMEOUT) {
                $code = self::ERR_TIMEOUT_EXEC;
            } else {
                $code = $e->getCode();
            }
            $this->error($code, __FUNCTION__);
            $responseData = false;
            
        }    
        
    return $responseData;        
    }     
    
    /**
     * Strip a garbage from response data
     * @param  string $responseData Response data
     * @return string               Trimmed data
     */
    private function trimResponse($responseData) {
        
            //--- Delete command echo from begining:
            $strings = [$this->lastRequest, "\r", "\n"];
            foreach ($strings as $deleteSring) {
                if ( ($p = strpos($responseData, $deleteSring)) === 0 ) { 
                    $responseData = substr($responseData, strlen($deleteSring));
                }
            }
            
            //--- Delete input prompt at the end:
            if ( ($p = strrpos($responseData, $this->inputPrompt)) !== false ) { 
                $responseData = substr($responseData, 0, $p);
            }
          
    return $responseData;
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
            $this->error(self::ERR_SOCKET, __FUNCTION__, socket_last_error());
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
            $timer++;
            if ($bytes === 0) {
                //--- Close current session:
                $this->close();
                $this->error(self::ERR_CLOSED_BY_REMOTE, __FUNCTION__);
                throw new Exception("socket is closed by the remote side", self::ERR_CLOSED_BY_REMOTE);
            }
            if ($bytes === false) {
                if ($timer >= $timerMax) {
                    //--- This not an error just timeout:
                    $this->lastTimeout = $timerMax / 100; //<-- in seconds
                    throw new Exception("timeout", self::ERR_TIMEOUT);
                } else {
                    usleep(10000);
                }
            }
        }
        
        list($commands, $text) = $this->parseInput($buffer);
        
        $this->debug([1, $bytes, $commands, $text], __FUNCTION__);
        
        if ($bytes === false) {
            $this->error(self::ERR_SOCKET, __FUNCTION__, socket_last_error());
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
    private function getAnswer($inputPromptTemplates, $analyzeMode = 0, $timeout = null, $callbackFunctions = null, $failFunction = null) {
        if (!is_array($inputPromptTemplates)) $inputPromptTemplates = [$inputPromptTemplates];
        if (!is_array($callbackFunctions)) $callbackFunctions = [$callbackFunctions];
        if (!$timeout)                     $timeout           = $this->timeout;
        $this->inputBuffer = ""; //<-- Clear the buffer
        $answer = false;
        $finish = false;
        try {
            
                do {
                    //--- Read from socket:
                    $this->inputBuffer .= $this->read($timeout);
                    if ($analyzeMode) { //--- Fast analyze: (only the last string)
                        $analyze = array_pop(explode("\n", $this->inputBuffer));
                    } else {            //--- Tolal analyze: (all the answer) 
                        $analyze = $this->inputBuffer;
                    }
                    //--- Parse received data:
                    foreach ($inputPromptTemplates as $i => $inputPromptTemplate) {
                        if (preg_match($inputPromptTemplate, $analyze, $m)) {
                            if (is_callable($callbackFunctions[$i])) {
                                //--- Execute callback function on complete:
                                $callbackFunctions[$i]($this->inputBuffer, $m);
                            }
                            $finish = true;
                            break;
                        }
                    }
                } while (!$finish);
                //--- Copy received data:
                $answer = $this->inputBuffer;
                
            } catch (Exception $e) {
                
                if (is_callable($failFunction)) {
                    $this->debug($e->getMessage(), __FUNCTION__);
                     //--- Execute function on fail
                    $failFunction($e); 
                } else {
                    $this->error($e->getCode(), __FUNCTION__); // if isError
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
     * @var_ array TELNET commands 
     */
    private $tmCmd = [
        "SE"   => 0xf0, // (240)
        "SB"   => 0xfa, // (250)
        "WILL" => 0xfb, // (251)
        "WONT" => 0xfc, // (252)
        "DO"   => 0xfd, // (253)
        "DONT" => 0xfe, // (254)
        "IAC"  => 0xff, // (255) «Interpret as Command»
    ];
    private $tmCmdCodes = null;

    /**
     * @var_ array TELNET options 
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
