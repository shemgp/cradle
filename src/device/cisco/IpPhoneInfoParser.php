<?php
/*==============================================================================
 *  Title      : Cisco IP Phone Info Parser
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 29.01.2015
 *  Version    : 2.0
 *==============================================================================
 */
namespace digger\cradle\device\cisco;

use digger\cradle\device\cisco\IpPhone;

/**
 * @brief Cisco IP Phone Info Parser
 * 
 * Class to parse a data of Cisco IP-phone parameters.
 * 
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * @warning This class uses `digger\cradle\device\cisco\IpPhone` class;
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 *
 * use digger\cradle\device\cisco\IpPhoneInfoParser;
 * 
 *   $info = new IpPhoneInfoParser("172.16.0.20");
 *   echo 
 *       "Model:       " . $info->getModel()        . "\n"
 *     . "DN:          " . $info->getDn()           . "\n"
 *     . "Device Name: " . $info->getDeviceName()   . "\n"
 *     . "SN:          " . $info->getSerialNumber() . "\n"
 *     . "MAC:         " . $info->getMac()          . "\n"
 *   ;
 * 
 * ~~~
 */
class IpPhoneInfoParser {

    /**
     * Constructor
     * 
     * @param string|array $data IP address of IP phone or an array of retrieved data by @ref IpPhone::getInfo.
     */
    public function __construct( $data ) { 
        $this->init($data); 
        return $this;
    }

    /**
     * Returns IP Phone model name
     * 
     * @return <b>string</b> IP Phone model name
     */
    public function getModel() {
        if ($this->model) {
            return $this->model; 
        }
        if (is_array($this->rawData)) {
            foreach ($this->rawData as $value) {  
                if (preg_match('/(^CP-.*)/', $value, $m)) {
                    $this->model = str_replace(" ","",$m[1]); 
                    break; 
                }
            }    
        }
    return $this->model;
    }

    /**
     * Returns IP Phone Directory number
     * 
     * @return <b>string</b> IP Phone Directory number
     */
    public function getDn() {
        if ($this->dn) {
            return $this->dn; 
        }
        if (is_array($this->rawData)) { 
                $this->dn = $this->rawData["phone dn"]; 
            if (!$this->dn) { 
                $this->dn = $this->rawData["номер телефона"];
            }
        }
    return $this->dn;    
    }

    /**
     * Returns IP Phone Device Name
     * 
     * @return <b>string</b> IP Phone Device Name
     */
    public function getDeviceName() { 
        if ($this->name) {
            return $this->name; 
        }
        if (is_array($this->rawData)) {
                $this->name = $this->rawData["host name"];
            if (!$this->name) { 
                $this->name = $this->rawData["имя хоста"];
            }
        }
    return $this->name;
    }

    /**
     * Returns IP Phone Serial Number
     * 
     * @return <b>string</b> IP Phone Serial Number
     */
    public function getSerialNumber() {
        if ($this->serial) {
            return $this->serial;
        }
        if (is_array($this->rawData)) {
                $this->serial = $this->rawData["serial number"];
            if (!$this->serial) { 
                $this->serial = $this->rawData["серийный номер"];
            }
            if (!$this->serial) { 
                $this->serial = $this->rawData[12]; // CP-3911
            }
        }
    return $this->serial;
    }

    /**
     * Return IP Phone MAC address
     * 
     * @return <b>string</b> IP Phone MAC address
     */
    public function getMac() {
        if ($this->mac) { 
            return $this->mac;
        }
        if (is_array($this->rawData)) {
                $this->mac = $this->rawData["mac address"];
            if (!$this->mac) {
                $this->mac = $this->rawData["mac-address"];
            }
            if (!$this->mac) {
                $this->mac = $this->rawData["mac адрес"];
            }
            if (!$this->mac) {
                $this->mac = $this->rawData["mac-адрес"];
            }
            if (!$this->mac) { // CP-3911
                foreach ($this->rawData as $value) { 
                    if (preg_match('/mac[ -]+address[\s:]+(.*)/i', $value, $m)) { 
                        $this->mac = preg_replace('/\W/',"",$m[1]); 
                        break; 
                    }
                }
            }    
        }
    return $this->mac;
    }
    
//------------------------------------------------------------------------------    
//  PRIVATE
//------------------------------------------------------------------------------ 
    
    protected $rawData;
    protected $name;
    protected $model;
    protected $serial;
    protected $mac;
    protected $dn;

//------------------------------------------------------------------------------ 
    
    /**
     * Initialization
     * 
     * @param string|array $data  IP address of IP phone or an array of retrieved data by @ref IpPhone::getInfo.
     */
    protected function init( $data ) {
        $this->name   = null;
        $this->model  = null;
        $this->serial = null;
        $this->mac    = null;
        $this->dn     = null;
        //--- If $data is IP address:
        if (is_string($data)) {
            //--- Retrive IP phone info:
            $data = IpPhone::getInfo($data);
        }
        if (is_array($data)) {
            $this->rawData = [];
            foreach ($data as $key => $value) { 
                $this->rawData[ mb_strtolower($key, "UTF-8") ] = $value; 
            }
        }
    }    

}
