<?php
/*==============================================================================
 *  Title      : SNMP protocol wrapper
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 04.02.2016
 *==============================================================================
 */
namespace digger\cradle\network;

use digger\cradle\common\Basic;

/**
 * @brief SNMP protocol wrapper
 *
 * A simple class to send SNMP protocol requests.
 *
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
 * use digger\cradle\network\Snmp;
 *
 * //--- 1. Short usage:
 *
 * print_r( (new Snmp(['host'=>'localhost', 'readCommunity'=>'public']))->get('.1.3.6.1.2.1.1.5.0') );
 * print_r( (new Snmp(['host'=>'localhost', 'readCommunity'=>'public']))->getParsed('.1.3.6.1.2.1.1.5.0') );
 * print_r( (new Snmp(['host'=>'localhost', 'readCommunity'=>'public']))->getValue('.1.3.6.1.2.1.1.5.0') );
 *
 * //--- 2. Normal usage:
 *
 * $snmp = new Snmp(['host'=>'localhost', 'readCommunity'=>'public']);
 *
 * print_r( $snmp->get('.1.3.6.1.2.1.1.5.0') );
 * print_r( $snmp->getParsed('.1.3.6.1.2.1.1.5.0') );
 * print_r( $snmp->getValue('.1.3.6.1.2.1.1.5.0') );
 *
 * //--- 3. Advanced usage (with several targets):
 *
 * $snmp = new Snmp([
 *                   'readCommunity' => ['public', 'other', '...'],
 *                   'version'       => [2,1],
 *                   'writeCommunity'=> 'forsave',
 *                  ]);
 *
 * //--- Get current hostname:
 * $snmp->open('first_host');
 * print_r( $snmp->get('.1.3.6.1.2.1.1.5.0') );
 *
 * //--- Set new hostname:
 * print_r( $snmp->set('.1.3.6.1.2.1.1.5.0', 'New_hostname') );
 * print_r( $snmp->getLastError() );
 *
 * //--- Connect to a new host:
 * print_r( $snmp->open('other_host')->get('.1.3.6.1.2.1.1.5.0') );
 *
 * //--- Connect to an other host:
 * print_r( $snmp->open('other_host2')->get('.1.3.6.1.2.1.1.5.0') );
 *
 * // ...
 *
 * ~~~
 */
class Snmp {

    //---------------------------------
    // Error codes
    //---------------------------------

    const ERROR_UNKNOWN_VERSION = 1;
    const ERROR_UNKNOWN_OID     = 2;
    const ERROR_UNKNOWN_TYPE    = 3;
    const ERROR_CATNT_SET_OID   = 4;

    //--------------------------------------------------------------------------
    // Properties
    //--------------------------------------------------------------------------

    /**
     * @var_ <b>string</b> Target hostname or IP address
     */
    public $host;
    /**
     * @var_ <b>string</b> SNMP read community.
     * You may to set an array of possible values, for auto detect a correct one.
     */
    public $readCommunity;
    /**
     * @var_ <b>string</b> SNMP write community.
     */
    public $writeCommunity;
     /**
     * @var_ <b>array</b> An array of possible SNMP agent versions to use. =0 - auto detect the correct SNMP agent version.
     */
    public $version   = 0;
    /**
     * @var_ <b>int</b> The number of seconds until the first timeout.
     */
    public $timeout   = 2;
    /**
     * @var_ <b>int</b> The number of times to retry if timeouts occur.
     */
    public $retries   = 2;
    /**
     * @var_ <b>int</b> the OID output format
     * Acceptable values: SNMP_OID_OUTPUT_NUMERIC | SNMP_OID_OUTPUT_FULL
     * @see [http://php.net/manual/ru/function.snmp-set-oid-output-format.php]
     */
    public $oidOutputFormat = SNMP_OID_OUTPUT_NUMERIC;
    /**
     * SNMP v3 params
     *
     * @var_ <b>array</b>
     */
    public $version3 = [
        'secName'        => '',
        'secLevel'       => '',
        'authProtocol'   => '',
        'authPassphrase' => '',
        'privProtocol'   => '',
        'privPassphrase' => '',
    ];

    /**
     * Constructor
     *
     * @param array $config An array of properties to initialize the class.
     */
    public function __construct($config) {
        $this->open($config);
        return $this;
    }

    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Open new SNMP session
     *
     * @param  array|string    $config  An array of properties to initialize the class.
     *                                  If `$config` is a string it will be interpreted as a `host`
     *                                  (same as $config = [ 'host' => $config ]).
     * @return <i>this</i>     A link to an instance of this class.
     */
    public function open( $config = null ) {
        //--- If it's a new config to create a new conection:
        if ($config) {
            $this->close();
            if (is_string($config)) { $config = ['host' => $config]; }
            $this->init($config);
        }
        return $this;
    }

    /**
     * Close current SNMP session
     */
    public function close() {
    }

    /**
     * Tries to define a real version of SNMP agent.
     *
     * List of possible version values should be set in property $version.
     * By default (if property $version = 0) list of possible version values
     * wille be set to [2,1] and this method will try to auto detect what
     * version is correct for current SNMP agent.
     *
     * @return <b>int</b> A real version of SNMP agent
     */
    public function getAgentVersion() {
        if ($this->agentVersion) {
            return $this->agentVersion;
        }

        $testRequest = '.1.3.6.1.2.1.1.1.0'; //--- get SystemName

        if (!is_array($this->readCommunity)) {
            $this->readCommunity = [$this->readCommunity];
        }

        if (!$this->version)            { $this->version = [2,1]; }
        if (!is_array($this->version))  { $this->version = [$this->version]; }

        foreach ($this->readCommunity as $readCommunity) {
            foreach ($this->version as $version) {
                //--- Test for version:
                if($this->snmpget($testRequest, $version, $readCommunity)) {
                    $this->agentStatus         = true;
                    $this->agentReadCommunity  = $readCommunity;
                    return $this->agentVersion = $version;
                }
            }
        }

        $this->setError(self::ERROR_UNKNOWN_VERSION);
        $this->agentStatus = false;
    return false;
    }

    /**
     * Tries to define a real "read community" of SNMP agent.
     *
     * List of possible "read community" values to detect should be set in property $readCommunity.
     *
     * @return <b>string</b> A "read community" of SNMP agent
     */
    public function getAgentReadCommunity() {
        if (!$this->agentReadCommunity) {
            $this->getAgentVersion();
        }
        return $this->agentReadCommunity;
    }

    /**
     * Returns a current SNMP agent status - "alive" or "unreacheble".
     *
     * @return <b>boolean</b>   TRUE  - agent is "alive" and ready to communicate.<br>
     *                          FALSE - agent is "unreacheble".                   <br>
     *                          NULL  - is not defined yet.
     */
    public function getAgentStatus() {
        if ($this->agentStatus === null) {
            $this->getAgentVersion();
        }
        return $this->agentStatus;
    }

    /**
     * Returns a last error
     *
     * @return <b>array</b> An array of error details.
     */
    public function getLastError() {
        return $this->errors;
    }


    //--------------------------------------------------------------------------

    /**
     * Set a value for SNMP object
     *
     * @param string $oid     SNMP object id (OID).
     * @param mixed  $value   Value to set. (Type of value will get from SNMP agent).
     * @return <b>boolean</b> TRUE on success. FALSE if error has occurred.
     */
    public function set($oid, $value) {
        if (!$this->getAgentStatus()) { return false; }
        $this->setError(); //<-- Clear errors
        if( ($response = $this->snmpset($oid, $value)) === false ) {
            if (!$this->getLastError()) {
                $this->setError(self::ERROR_CATNT_SET_OID);
            }
        }
        return $response;
    }

    /**
     * Get a raw value of SNMP object.
     * This method is equal of snmpwalk.
     *
     * @param string $oid     SNMP object id (OID).
     * @return <b>array</b>   An array of values returned by snmpwall request.
     *                        Array format:<br>
     *                        [
     *                          oid-index => "TYPE: Some value",
     *                        ]
     */
    public function get($oid) {
        if (!$this->getAgentStatus()) { return false; }
        $this->setError(); //<-- Clear errors
        if( ($response = $this->snmpget($oid)) === false ) {
            $this->setError(self::ERROR_UNKNOWN_OID);
        }
        return $response;
    }

    /**
     * Get a value of SNMP object and parse it.
     * This method is equal of snmpwalk.
     *
     * @param string $oid     SNMP object id (OID).
     * @return <b>array</b>   An array of values returned by snmpwall request.
     *                        Array format:<br>
     *                        [
     *                          0 => [ "some value", oid-index, "TYPE"],
     *                          ...
     *                        ]
     */
    public function getParsed($oid) {
        if (($response = $this->get($oid)) === false) { return false; }
        foreach ($response as $dataOid => $value) {
            list($value, $type) = self::parseValue($value);
            $offset = ltrim(preg_replace('/^' . preg_quote($oid) . '/', '', $dataOid), '.');
            $res[$offset] = [$value, $dataOid, $type];
        }
        return $res;
    }

    /**
     * Get a single value of SNMP object.
     * This method is equal of snmpget.
     *
     * @param string $oid     SNMP object id (OID).
     * @return <b>string</b>  A single clean value (without data type prefix)
     */
    public function getValue($oid) {
        if (($response = $this->get($oid)) === false) { return false; }
        list($value, ) = self::parseValue(reset($response));
        return $value;
    }

    //--------------------------------------------------------------------------
    // Parse methods
    //--------------------------------------------------------------------------

    /**
     * Parse a raw value of SNMP object
     *
     * @param string $value value of SNMP object of format: "TYPE: Some value"
     * @return <b>array</b> An array: [$value, $type]
     */
    public static function parseValue($value) {
        if (preg_match('/^([\w ]+):\s*(.*)/ms', $value, $m)) {
            $type  = strtoupper($m[1]);
            $value = $m[2];
        } else {
            $type  = "";
            $value = $value;
        }
        return [$value, $type];
    }

    /**
     * Parse a raw value of SNMP object contains a TimeTicks
     *
     * @param string  $stringValue value of SNMP object of format: "(3454566544) ..."
     * @param boolean $inSeconds   =TRUE if you need to convert the result in seconds.
     * @return <b>string</b>       TimeTicks value in milliseconds, or in seconds
     */
    public static function parseTimeTicks($stringValue, $inSeconds = false) {
        if (preg_match('/\((\d+)\)/', $stringValue, $m)) {
            $stringValue = $m[1];
        }
        if ($inSeconds) { $stringValue /= 100; } //<-- convert to seconds
        return $stringValue;
    }

    /**
     * Converts seconds to output format.
     *
     * @param int    $seconds         Input seconds.
     * @param string $format          A string contains a keys for replacement:<br>
     *                                [D] - days     <br>
     *                                [H] - hours    <br>
     *                                [M] - minutes  <br>
     *                                [S] - seconds
     * @return <b>array | string</b>  If $format is empty or NULL, returned value is an array:
     *                                [$days, $hours, $minutes, $seconds]
     */
    public static function parseTime($seconds, $format = null) {
        $days    = floor( $seconds / 86400 ); //60*60*24
        $hours   = floor(($seconds - $days*86400) / 3600);
        $minutes = floor(($seconds - $days*86400 - $hours*3600) / 60);
        $seconds = floor ($seconds - $days*86400 - $hours*3600 - $minutes*60);
        if ($minutes<10) { $minutes = "0" . $minutes; }
        if ($seconds<10) { $seconds = "0" . $seconds; }
        $result = [$days, $hours, $minutes, $seconds];
        if (!$format)    { return $result; }
    return str_replace(['[D]', '[H]', '[M]', '[S]'], $result, $format);
    }

    /**
     * Extract an IP address from SNMP OID.
     *
     * @param  string $oid   SNMP Object ID (OID) ends with IP address value.
     * @return <b>string</b> Extracted IP Address
     */
    public static function parseIpAddr($oid) {
        if (preg_match('/.*\.(\d+\.\d+\.\d+\.\d+)$/', $oid, $m)) {
            return $m[1];
        }
        //if (preg_match('/.*\.(\d+\.\d+\.\d+\.\d+)\.\d+$/', $oid, $m)) {
        //    return $m[1];
        //}
    return false;
    }

    //==========================================================================
    // Private
    //==========================================================================

    protected $agentStatus;
    protected $agentVersion;
    protected $agentReadCommunity;
    protected $errors;

    /**
     * Init the class
     */
    protected function init($config) {
        //--- Init the class:
        Basic::initClass($this, $config);
        //--- Reset:
        $this->agentStatus        = null;
        $this->agentVersion       = null;
        $this->agentReadCommunity = null;
        $this->setError();
        snmp_set_oid_output_format($this->oidOutputFormat);
    }

    /**
     * Set an error by error code.
     *
     * @param int $code Error code
     */
    protected function setError($code = null) {
        if ($code === null) {
            //--- Clear:
            $this->errors = null;
        } else {
            switch ($code) {
                case self::ERROR_UNKNOWN_VERSION:
                    $message = "Unknown agent version";
                    break;
                case self::ERROR_UNKNOWN_OID:
                    $message = "Unknown OID";
                    break;
                case self::ERROR_UNKNOWN_TYPE:
                    $message = "Unknown value type";
                    break;
                case self::ERROR_CATNT_SET_OID:
                    $message = "Can't set the value";
                    break;
                default:
            }
            //--- Set error:
            $this->errors = [
                'message' => $message,
                'code'    => $code,
            ];
        }
    }

    /**
     * @var_ array SNMP object value types
     */
    protected $typeCodes =[
        'INTEGER'           => 'i',
	'UNSIGNED INTEGER'  => 'u',
	'STRING'            => 's',
	'HEX STRING'        => 'x',
	'DECIMAL STRING'    => 'd',
	'NULL'              => 'n',
	'OID'               => 'o',
	'TIMETICKS'         => 't',
	'IPADDRESS'         => 'a',
	'BITS'              => 'b',
    ];

    protected function getTypeCode($type) {
        return $this->typeCodes[strtoupper($type)];
    }

    //--------------------------------------------------------------------------

    protected function snmpget($oid, $version = null, $community = null) {
        if (!$version)    { $version = $this->getAgentVersion();         }
        if (!$community)  { $community = $this->getAgentReadCommunity(); }
        switch ($version) {
            case 1:
                return @snmprealwalk($this->host, $community, $oid, $this->timeout*1000000, $this->retries);
            case 2:
                return @snmp2_real_walk($this->host, $community, $oid, $this->timeout*1000000, $this->retries);
            case 3:
                extract($this->version3);
                return @snmp3_real_walk($this->host , $secName, $secLevel, $authProtocol, $authPassphrase, $privProtocol, $privPassphrase, $oid, $this->timeout*1000000, $this->retries );
            default:
                return false;
        }
    }

    protected function snmpset($oid, $value, $type = null, $version = null, $community = null) {
        if (!$version)    { $version   = $this->getAgentVersion(); }
        if (!$community)  { $community = $this->writeCommunity;    }
        if (!$type)       { list($oldValue, $oid, $type) = reset($this->getParsed($oid)); }
        if ( !($typeCode = $this->getTypeCode($type)) ) {
            $this->setError(self::ERROR_UNKNOWN_TYPE);
            return false;
        }
        switch ($version) {
            case 1:
                return @snmpset($this->host, $community, $oid, $typeCode, $value, $this->timeout*1000000, $this->retries);
            case 2:
                return @snmp2_set($this->host, $community, $oid, $typeCode, $value, $this->timeout*1000000, $this->retries);
            case 3:
                extract($this->version3);
                return @snmp3_set($this->host , $secName, $secLevel, $authProtocol, $authPassphrase, $privProtocol, $privPassphrase, $oid, $typeCode, $value, $this->timeout*1000000, $this->retries );
            default:
                return false;
        }
    }


}
