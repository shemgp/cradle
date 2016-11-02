<?php
/*==============================================================================
 *  Title      : ICMP protocol wrapper
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 04.02.2016
 *==============================================================================
 */
namespace digger\cradle\network;

/**
 * @brief ICMP protocol wrapper
 *
 * A simple class to send ICMP protocol requests to check is target host reachable.
 *
 * This class just use Linux ping command.
 *
 * @version 4.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 *
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 *
 *  print_r( Icmp::ping('localhost') );
 *  print_r( Icmp::ping('localhost', ['tryCount'=>4, 'timeout'=>2]) );
 *
 * ~~~
 */
class Icmp {

    /**
     * External ping command
     * @_var string
     */
    public static $command = 'ping -q -n -c 1 -i 1 -W [TIMEOUT] [HOST]';

    /**
     * ICMP ping command wrapper
     *
     * @param string $host      Hostname (IP address)
     * @param array  $params    An array of params: [ <br>
     *                          'fast'     => true,  // stop send the ping on first success result <br>
     *                          'tryCount' => 3,     // count of retries if no response <br>
     *                          'timeout'  => 1,     // first timeout in seconds <br>
     *                          'timeoutIncrease' => true // increase every next retry timeout on 1 second<br>
     *                          ]
     * @return <b>int</b><br>   0 - alive       <br>
                                1 - unreachable <br>
                                2 - error       <br>
     */
    public static function ping($host, $params = null) {

        $config = [
            'fast'            => true,
            'tryCount'        => 3,
            'timeout'         => 1, // first timeout (in seconds)
            'timeoutIncrease' => true,
        ];
        if (is_array($params)) {
            foreach ($params as $key => $value) { if (isset($config[$key])) { $config[$key] = $value;  }  }
        }
        extract($config);

        for ($i=0; $i<$tryCount; $i++) {
           unset($resultArray);
           $ping = str_replace( ['[HOST]', '[TIMEOUT]'] , [$host, $timeout] , self::$command); //echo "$ping\n";
           $stat = 1;
           exec($ping, $resultArray, $stat);
           if ($stat == 2)          { break;      }
           if ($stat == 0 && $fast) { break;      }
           if ($timeoutIncrease)    { $timeout++; }
        }
        return $stat;
    }

}

/*Test:

    print_r(Icmp::ping('localhost', ['tryCount'=>4]));
/**/
