<?php
/*==============================================================================
 *  Title      : SSH interaction
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 09.03.2016
 *==============================================================================
 */
namespace digger\cradle\network;

use digger\cradle\network\RemoteExecutor;

/**
 * @brief SSH interaction
 * 
 * A simple class to send commands to remote host through the SSH protocol.
 *
 * @version 4.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 *
 * require_once __DIR__ . '/../../../../autoload.php';
 * 
 * use digger\cradle\network\Ssh;
 * 
 *   //--- 1. Short usage:
 *
 *   $r = (new Ssh([ 'host' => "host", 'user' => "user", 'password' => "password" ]))->exec("hostname");
 *   print_r( $r );
 *    
 *   //--- 2. Normal usage:
 *
 *   $t = new Ssh([
 *       'host'     => "host", 
 *       'user'     => "username",
 *       'password' => "password",
 *       'debug'    => "debug.dat", // to file (option)
 *   ]);
 *
 *   $commands = "hostname";              // Single  command as a string
 *   $commands = "hostname\ndate";        // Several commands separated by "\n"
 *   $commands = ["hostname"];            // Single  command as an array
 *   $commands = ["hostname", "date"];    // Several commands as an array
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
 *   $t = new Ssh([
 *       'host'     => "host", 
 *       'user'     => "username",
 *       'password' => "password",
 *       'debug'    => 2,          // to STDIN (echo)
 *       'errorSilent' => false,   // enable exceptions
 *   ]);
 *
 *   $commands = ["hostname", "date"];
 *
 *   if (!is_array($commands)) { $commands = [$cmd]; }
 *   try {
 *       foreach ($commands as $command) {
 *           echo "command : $command\n";
 *           $r = $t->exec($command); 
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
 *   $commands = ['hostname', 'date'];
 *
 *   $t = new Ssh($config);
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
class Ssh extends RemoteExecutor {

    //---------------------------------
    // Error codes
    //---------------------------------

    const ERR_STREAM_CREATE = 5;    
    
    //---------------------------------
    // Execute types
    //---------------------------------
    
    /**
     * Execute a command on a remote server
     */
    const EXECTYPE_EXEC  = 1;
    /**
     * Request an interactive shell
     */
    const EXECTYPE_SHELL = 2;
    
    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------
    
  //public $host;        <-- is inherited
    /**
     * @var_ <i>int</i> The target TCP port.
     */
    public $port = 22; //<-- is overridden
  //public $user;        <-- is inherited 
  //public $password;    <-- is inherited    
  //public $timeout;     <-- is inherited
  
    /**
     * @var_ <i>int</i> The type of remote interaction.
     *                  Acceptable values: self::EXECTYPE_EXEC | self::EXECTYPE_SHELL
     * @see EXECTYPE_EXEC
     * @see EXECTYPE_SHELL
     */    
    public $execType        = self::EXECTYPE_EXEC;
    /**
     * @var_ <i>string</i> Type of virtual terminal (option). 
     */    
    public $terminalType    = 'vt102';
    /**
     * @var_ <i>int</i> Width of the virtual terminal. 
     */    
    public $width           = 80;
    /**
     * @var_ <i>int</i> Height of the virtual terminal. 
     */    
    public $height          = 25;
    /**
     * @var_ <i>int</i> should be one of SSH2_TERM_UNIT_CHARS or SSH2_TERM_UNIT_PIXELS 
     */    
    public $widthHeightType = SSH2_TERM_UNIT_CHARS;
    /**
     * @var_ <i>string</i> An associative array of name/value pairs to set in the target environment (option). 
     */    
    public $environment;
    
    //--------------------------------------------------------------------------
    // Public functions
    //--------------------------------------------------------------------------

    //... All methods are inherited from parent class ...
  
    //==========================================================================
    // Private
    //==========================================================================
  
    /**
     * Create an error
     * 
     * @throws Exception
     */
    protected function error($code, $source = "", $detail = null) {
        //--- Get a message:
        switch ($code) {
            case self::ERR_STREAM_CREATE:          
                $message = "Unable to create a stream";
                break;
            default:
        }
        return parent::error($code, $source, $message, $detail);
    }
    
    /**
     * Connect to remote host 
     * @warn Method to override
     * 
     * @return <b>boolean</b>  TRUE  - Successfuly connected. <br>
     *                         FALSE - Connection is fail.
     */
    protected function connect() {
        return @ssh2_connect($this->host, $this->port);
    }    
    
    /**
     * Authenticate the access to remote side
     * 
     * @return boolean  TRUE  - Authentication is success. <br>
     *                  FALSE - Authentication is fail.
     */
    protected function authenticate() {
        //--- Nothing to do if authentication is already pass:
        if ($this->isAuthenticated) {
            return true;
        }
        
        //--- Authenticate:
        $this->debug("start", __FUNCTION__);
        
        if(!@ssh2_auth_password($this->connection, $this->user, $this->password)) {
            $this->close();
            $this->error(self::ERR_AUTH_FAIL, __FUNCTION__);
        } else {
            $this->isAuthenticated = true;
            $this->debug("Success", __FUNCTION__);
        }
        
    return $this->isAuthenticated;    
    }
    
    
    /**
     * Prepare commands to execute 
     * 
     * @param  string       $command A string with commands separated by "\n"
     * @return <b>array</b>          An array of commands
     */
    protected function prepareCommand($command) {
        if ($this->execType === self::EXECTYPE_SHELL) {
            //--- Implode an array to single command:
            if (is_array($command)) { 
                $command = implode("\n", $command); 
            } 
            $commands = [$command . "\n"];
        } else {
            //--- Explode a string to an array of commands:
            if (!is_array($command)) { 
                $commands = explode("\n", $command); 
            } else {
                $commands = $command;
            }
        }
    return $commands;    
    }
    
    /**
     * Execute a single command on remote side
     * 
     * @return string   The text data of response.
     */
    protected function executeCommand($command, $timeout = null) {
        
        $command = $command . ""; //--- convert to string
        
        $this->debug("Command: " . $command, __FUNCTION__);
        
        switch ($this->execType) {
            case self::EXECTYPE_EXEC:
                $this->debug("Exec type: EXEC ", __FUNCTION__);
                $stream = ssh2_exec($this->connection, $command, $this->terminalType, $this->environment, $this->width, $this->height, $this->widthHeightType);
                $this->debug("Command is sent", __FUNCTION__);
                break;
            case self::EXECTYPE_SHELL:
                $this->debug("Exec type: SHELL ", __FUNCTION__);
                $stream = ssh2_shell($this->connection,          $this->terminalType, $this->environment, $this->width, $this->height, $this->widthHeightType);
                break;
            default:
        }
        
        if (!$stream) {
            
            $this->error(self::ERR_STREAM_CREATE, __FUNCTION__);
            $responseData = false;
            
        } else {
            
            $this->debug("Stream created", __FUNCTION__);   
            $responseData = "";     
            stream_set_blocking($stream, true);
            if ($this->execType == self::EXECTYPE_SHELL) {
                fwrite($stream, $command); //.PHP_EOL
                $this->debug("Command is sent", __FUNCTION__);
            }
            while( $o = fgets($stream) ) {
                $responseData .= $o;
                $this->debug("Response: " . $o, __FUNCTION__); 
            }
            fclose($stream);
            $this->debug("Stream closed", __FUNCTION__); 
            
        }
        
    return $responseData;        
    }
    
}
    