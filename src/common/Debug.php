<?php
/*==============================================================================
 *  Title      : Debug
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 08.02.2016
 *==============================================================================
 */
namespace digger\cradle\common;

use digger\cradle\common\Logger;

/**
 * Class to save debug information
 *   
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * 
 * $debug = new Debug("file.log");
 * $debug->save("New message 1", __FUNCTION__);
 * $debug->save("New message 2", __FUNCTION__);
 * print_r($debug->getMessages()); 
 * 
 * ~~~
 */
class Debug extends Logger {
    
    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------
    
    //--------------------------------------------------------------------------
    // Public methods
    //--------------------------------------------------------------------------

    /**
     * Save a debug message
     * 
     * @param mixed  $message The debug message of any type.
     * @param string $source  The source of this message (for example: __FUNCTION__)
     */
    public function save($message, $source = "") {
        if (!$this->destination) { return; }
        
        $message = $source . " : " . (is_string($message) ? $message : print_r($message, true));
        
        parent::save($message);
    }
    
}
