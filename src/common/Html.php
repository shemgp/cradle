<?php
/*==============================================================================
 *  Title      : Digger HTML Class
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 05.10.2015
 *==============================================================================
 */

namespace digger\cradle\common;

/**
 * @brief Digger HTML Class
 * 
 * Class for HTML represents of data.
 * 
 * @version 3.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 */
class Html {

    /**
     * To creates a simple HTML list from an input array.
     * 
     * @param  array    $array           Input array.
     * @param  string   $listType        (Option) Type of main tag (UL | OL)
     * @param  array    $arrayAttributes Array of attributes of main tag: ['class' => '...', 'id' => ... ]
     * @param  function $callback        Callback function (on befor create item) @see getElement
     * @return <i>string</i>             HTML code.
     * 
     * <h3>Example:</h3>
     * ~~~
     * echo Html::arrayToList(['a'=>['b'=>['c'=>'value']]], 'ul', ['id'=>'myID1', 'class'=>'myclass']);
     * 
     * //--- Output:
     * <ul id="myID1" class="myclass">
     *  <li>a
     *      <ul>
     *          <li>b
     *              <ul>
     *                  <li>value</li>
     *              </ul>
     *          </li>
     *      </ul>
     *  </li>
     * </ul>
     * ~~~
     */
    public static function arrayToList($array, $listType='ul', $arrayAttributes = null, $callback = null) {
        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $sub = "";
                if (is_array($value)) {
                    $sub = self::arrayToList($value ,$listType, null, $callback);
                } else {
                    if ($value) $key = $value;
                }
                $html .= self::getElement('li', $key . $sub, null, $callback);
            }
        } else {
            $html .= self::getElement('li', $array, null, $callback);
        }
        $html = self::getElement($listType, $html, $arrayAttributes, $callback);
    return $html;    
    }

    /**
     * To create an html element
     * 
     * @param string   $tag             Html tag name (a, ul, li ...)
     * @param string   $body            It is a text will be placed inside the tag.
     * @param array    $arrayAttributes Array of tag attributes: ['class' => '...', 'id' => ... ]
     * @param function $callback        This function will be called with given parameters:
     *                                  $tag, $body, $arrayAttributes that can be changed before creating the element.
     * @return <i>string</i>            HTML code.
     * 
     * <h3>Example:</h3>
     * ~~~
     * echo Html::getElement('a', 'my link', ['href' => 'http://somewere'], function(&$tag, &$body, &$attr){ $body .= '... some body ...'; $attr['class']='new class'; });
     * 
     * //--- Output:
     * <a href="http://somewere" class="new class">my link... some body ...</a>
     * ~~~
     */
    public static function getElement($tag, $body='', $arrayAttributes=null, $callback = null) {
        //--- Call user function to change content:
        if (is_callable($callback)) {
            call_user_func_array($callback, [&$tag, &$body, &$arrayAttributes]);
        }
        //--- Create html attributes:
        if (is_array($arrayAttributes)) {
            foreach ($arrayAttributes as $key => $value) {
                $attributes .= " $key=" . '"' . htmlspecialchars($value,  ENT_QUOTES) . '"';
            }
        }
        //--- Return html element:
    return "<$tag$attributes>" . $body . "</$tag>";
    }
    
}
