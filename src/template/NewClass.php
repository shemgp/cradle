<?php
/*==============================================================================
 *  Title      : Template of the new class
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 08.02.2016
 *==============================================================================
 */
namespace digger\cradle\template;

use digger\cradle\common\Basic;
use Exception;

/**
 * @brief Template of the new class
 *
 * This file contains code style and a basic structure of the new class.<br>
 * Doc style is adapted to [«Doxygen»](http://www.stack.nl/~dimitri/doxygen/) (documentation generator).
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 *
 * <h3>Example of usage:</h3>
 * ~~~
 *
 * Here is the main example code
 *
 * ~~~
 */
class NewClass {

    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------

    /**
     * @var_ <i>string</i> The public property
     */
    public $property;

    //---------------------------------
    // Constatnts
    //---------------------------------

    /**
     * The constatnt of the class
     */
    const SOME_CLASS_CONST = 1;

    //--------------------------------------------------------------------------
    // Public methods
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
    }

    /**
     * The method
     *
     * @param  type         $param   Description
     *
     * @return <i>type</i>           Description
     *
     * @see $property
     *
     * <h3>Example:</h3>
     * ~~~
     *
     * Here is a place for example code...
     *
     * ~~~
     */
    public function method( $param = null ) {

    }

    //==========================================================================
    // Private
    //==========================================================================

    private $private;

    //--------------------------------------------------------------------------
    // Private methods
    //--------------------------------------------------------------------------

    /**
     * Init the class
     */
    private function init($config) {
        //--- Init the class:
        Basic::initClass($this, $config);
    }

}
