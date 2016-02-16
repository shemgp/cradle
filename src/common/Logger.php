<?php
/*==============================================================================
 *  Title      : Logger
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 10.02.2016
 *==============================================================================
 */
namespace digger\cradle\common;

/**
 * 
 * This class is designed to save log messages.
 *  
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * 
 * $logger = new Logger("file.log");
 * $logger->save("New message 1");
 * $logger->save("New message 2");
 * print_r($logger->getMessages());
 * 
 * ~~~
 */
class Logger {
    
    /**
     * Constructor
     * 
     * @param int|string $destination 
     * @see init
     */
    public function __construct($destination = null) {
        $this->init($destination);
    }
    
    //--------------------------------------------------------------------------
    // Public methods
    //--------------------------------------------------------------------------
    
    /**
     * Init the class
     * 
     * @param int|string $destination Defines a log destination <br>
     * Possible values:                   <br>
     *      false    - no log;            <br>
     *      1        - log to buffer;     <br>
     *      2        - log to STDIN;      <br>
     *      filename - log to file;
     */
    public function init($destination = 1) {
        //--- Init the class:
        $this->destination = $destination;
        //--- Create & clear the log destination:
        if ($this->destination === 1) {
            $this->buffer = [];
        } else 
        if (is_string($this->destination)) { 
            file_put_contents ($this->destination, ""); 
        }
    }     
    
    /**
     * Save a message
     * 
     * @param mixed $message The log message of any type.
     */
    public function save($message) {
        if (!$this->destination) { return; }
        
        if ($this->destination === 1) {
            //--- Log to buffer:
            $this->buffer[] = $message;
        } else if ($this->destination === 2) {
            //--- Log to STDIN:
            echo (is_string($message) ? $message : print_r($message, true)) . "\n";
        } else if (is_string($this->destination) && is_file($this->destination)) {
            //--- Log to file:
            if (!is_string($message)) { $message = chr(0xff) . serialize($message); }
            file_put_contents($this->destination, $message . "\n", FILE_APPEND);
        }
    }

    /**
     * Returns an array of log messages.
     * @return <i>array</i> An array of bufferd messages.
     */
    public function getMessages() {
        if ($this->destination === 1) {
            return $this->buffer;
        } else if (is_string($this->destination) && is_file($this->destination)) {
            $buffer = file($this->destination, FILE_IGNORE_NEW_LINES);
            for ($i=0; $i<count($buffer); $i++) {
                if ($buffer[$i][0] === chr(0xff)) {
                    $buffer[$i] = unserialize(substr($buffer[$i], 1));
                }
            }
            return $buffer;
        }
    }
    
    /**
     * Returns data of the last message
     * @return <i>mixed</i> The message of any type.
     */
    public function getLastMessage() {
        $messages = $this->getMessages();
        if (is_array($messages) && count($messages)>0) {
            return $messages[count($messages)-1];
        }
    return false;
    }
    
    //==========================================================================
    // Protected
    //==========================================================================
    
    /**
     * @var_ <i>mixed</i> Defines a log destination.  <br>
     * @see init
     */    
    protected $destination;
    
    /**
     * @var_ <i>array</i> The default buffer to save messages
     */
    protected $buffer = [];    

    //==========================================================================
    // Private
    //==========================================================================
    
}
