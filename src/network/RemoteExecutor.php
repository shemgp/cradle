<?php
/*==============================================================================
 *  Title      : Remote Executor (Parent basic class)
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 01.01.2016
 *==============================================================================
 */
namespace digger\cradle\network;

use digger\cradle\common\Basic;
use digger\cradle\common\Logger;
use digger\cradle\common\Debug;
use Exception;

/**
 * @brief Remote Executor
 * 
 * A parent class with basic functionality to to inheritance and create classes 
 * of interaction with remote hosts using remote access protocols (such as TELNET, SSH ...)
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * @warning This class is for inheritance only. Not for directly use.
 * 
 */
class RemoteExecutor {

    //---------------------------------
    // Error codes
    //---------------------------------
    
    const ERR_HOST_IS_EMPTY       = 1;
    const ERR_UNABLE_TO_CONNECT   = 2;
    const ERR_AUTH_FAIL           = 3;

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
    public $port;
    /**
     * @var_ <i>string</i> The username for authentication. 
     */
    public $user; 
    /**
     * @var_ <i>string</i> The password for authentication. 
     */
    public $password;    
    
    /**
     * @var_ <i>int</i> The socket default timeout in seconds. 
     * Defines how long should wait the response from remote side.
     */
    public $timeout;    
    
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
     * Open new connection to remote host
     * 
     * @param  array|string    $config  An array of properties to initialize the class. 
     *                                  If `$config` is a string it will be interpreted as a `host` 
     *                                  (same as $config = [ 'host' => $config ]).
     * @return <i>resource|false</i>    Resource ID of the open socket or FALSE on fail.
     */
    public function open( $config = null ) {
        
        //--- If it's a new config to create a new conection:
        if ($config) {
            if ($this->connection)  { $this->close(); }
            if (is_string($config)) { $config = ['host' => $config]; }
            $this->init($config);
        }
        
        //--- Nothing to do if the connection is opened:
        if ($this->connection) {
            return $this->connection;
        }
        
        //--- Check the host:
        if (!$this->host) { 
            $this->error(self::ERR_HOST_IS_EMPTY, __FUNCTION__);
            return false; 
        }
        
        $this->debug(date("Y.m.d H:i:s"), __FUNCTION__);
        
        //--- Connect to host:

        if (!($this->connection = $this->connect())) {
            $this->error(self::ERR_UNABLE_TO_CONNECT, __FUNCTION__, $this->host);
            return false;
        }
        
        $this->debug("Connected to [" . $this->host . ":" . $this->port . "]", __FUNCTION__);
        
        //--- Authenticate:
        $this->authenticate();
        
    return $this->connection;
    }
    
    /**
     * Close the connection
     */
    public function close() {
        if ($this->connection) { 
            $this->connection      = null;
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
            $commands = $this->prepareCommand($command);
            
            //--- Execute a commands:
            foreach ($commands as $singleCommand) {
                if ( ($result[] = $this->executeCommand($singleCommand, $timeout)) === false ) {
                    //--- Do not continue if problems occure:
                    break;
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
     * Clear the debug or(and) the error buffer
     * 
     * @param string $type Posible values: all|error|debug ; all - by default
     */
    public function clearBuffers($type = 'all') {
        if (($type == 'all' || $type == 'error') && is_object($this->errorLogger)) {
            $this->errorLogger->init($this->error);
        } 
        if (($type == 'all' || $type == 'debug') && is_object($this->debugLogger)) {
            $this->debugLogger->init($this->debug);
        }
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

    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------
    
    protected $connection;
    protected $isAuthenticated = false;
    
    //--- Loggers:
    
    protected $debugLogger;
    protected $errorLogger;


    //--------------------------------------------------------------------------
    // Methods
    //--------------------------------------------------------------------------
    
    /**
     * Init the class
     */
    protected function init($config) {
        //--- Init the class:
        Basic::initClass($this, $config);
        //--- Create loggers:
        if (!is_object($this->debugLogger) || isset($config['debug'])) {
            $this->debugLogger = new Debug($this->debug);
        }
        if (!is_object($this->errorLogger) || isset($config['error'])) {
            $this->errorLogger = new Logger($this->error);
        }
    }
    
    //--------------------------------------------------------------------------
  
    /**
     * Create an error
     * 
     * @throws Exception
     */
    protected function error($code, $source = "", $message = null, $detail = null) {
        //--- Get a message:
        if (!$message) {
            switch ($code) {
                case self::ERR_HOST_IS_EMPTY:
                    $message = "Host is empty";
                    break;
                case self::ERR_UNABLE_TO_CONNECT:
                    $message = "Unable to connect to [$detail]";
                    break;
                case self::ERR_AUTH_FAIL:
                    $message = "Authentication failed";
                    break;
                default:
                    $message = 'unknown';
            }
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
    protected function debug($message, $source = "") {
        if (!$this->debug) { return; }
        $this->debugLogger->save($message, $source);
    }    
    
    //--------------------------------------------------------------------------
    
    /**
     * Connect to remote host. 
     * @warninging Method to override
     * 
     * @return <b>boolean</b>  TRUE  - Successfuly connected. <br>
     *                         FALSE - Connection is fail.
     */
    protected function connect() {
        //--- Create the connection:
        // ...
        //--------------------------
        return false;
    }
    
    /**
     * Authenticate the access to remote side.
     * @warning Method to override
     * 
     * @return <b>boolean</b>  TRUE  - Authentication is success. <br>
     *                         FALSE - Authentication is fail.
     */
    protected function authenticate() {
        //--- Nothing to do if authentication is already pass:
        if ($this->isAuthenticated) {
            return true;
        }
        //--- Authenticate:
        // ...
        //-----------------
    return $this->isAuthenticated;    
    }
    
    /**
     * Extract a single commands from a string
     * 
     * @param  string       $command A string with commands separated by "\n"
     * @return <b>array</b>          An array of commands
     */
    protected function prepareCommand($command) {
        //--- Prepare an array of commands:
        if (!is_array($command)) { 
            $commands = explode("\n", $command); 
        } else {
            $commands = $command;
        }
    return $commands;    
    }


    /**
     * Execute a single command on remote side
     * @warning Method to override
     * 
     * @return string   The text data of response.
     */
    protected function executeCommand($command, $timeout = null) {
        $command = $command . ""; //--- convert to string
        //--- Execute:
        // ...
        //------------
    return $responseData;        
    }
    
}
    