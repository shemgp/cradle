<?php
/*==============================================================================
 *  Title      : Diff wrapper
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 14.03.2016
 *==============================================================================
 */
namespace digger\cradle\text;

/**
 * @brief Diff wrapper
 * 
 * A simple wrapper of Linux diff utility to compare text data line by line.
 *
 * This class just use Linux `diff` command.
 * 
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2016, SAD-Systems
 * 
 * <h3>Example of usage:</h3>
 * ~~~
 * <?php
 * 
 *  //--- Display the text returned by `diff` utility:
 *  print_r( Diff::diff('some text1', 'some text2') );
 * 
 *  //--- Display parsed `diff` results as an array:
 *  print_r( Diff::diffParse('some text1', 'some text2') );
 * 
 *  //--- Compare two text:  
 * 
 *  list($data1, $data2) = Diff::compare('some text1', 'some text2');
 * 
 *  function map($data) {
 *      return array_map(function($value){ 
 *                          list($state, $string) = $value; 
 *                          return ($state ? $state : ' ') . ": " . $string; 
 *                       }, $data);
 *  }
 *
 *  echo "The first text data:  " . print_r( map($data1), true ) . "\n";
 *  echo "The second text data: " . print_r( map($data2), true ) . "\n";
 * 
 * ~~~
 */
class Diff {
  
    /**
     * Compare two text data line by line
     * 
     * @param  string  $text1
     * @param  string  $text2
     * @param  boolean $bin     TRUE - for binary files;<br> 
     *                          FALSE - for text files
     * @param  string  $options "-u" - Universal format
     * @return <b>string</b>    A text data with `diff` utility results.
     * @throws Exception
     */
    public static function diff( $text1, $text2, $bin=false, $options=NULL ) {
    //
    // Get the difference: diff -u file.old file.new > file.diff     
    //
        $textDiff = false;
        $path     = "/tmp/";
        $cmdDiff  = "diff";     // for text   files          
        if ( $bin ) {
            $cmdDiff  = "cmp";  // for binary files
        }

        do {

            $id         = uniqid("digger_diff_");
            $fileName1 = $path . $id . ".1";
            $fileName2 = $path . $id . ".2";

        } while ( file_exists($fileName1) );

        file_put_contents($fileName1, $text1);
        file_put_contents($fileName2, $text2);
        exec("$cmdDiff$options $fileName1 $fileName2", $a, $r);
        $textDiff = implode("\n", $a);

        unlink($fileName1);
        unlink($fileName2);

        if ($r!=1 and $r!=0) {
            throw new \Exception("$r : $textDiff"); 
        }

    return $textDiff;    
    }

    /*
     * Restore the data (Patch).
     * 
     * @param  string $textOld
     * @param  string $textDiff
     * @return string
     * @throws Exception
     *
    static function patch ( $textOld, $textDiff ) {
    //
    // Restore the data: patch -o - file.old file.diff // output to console
    //    
        $textNew = false;
        $path    = "/tmp/";

        do {

            $id        = uniqid("digger_patch_");
            $fileName1 = $path . $id . ".1";
            $fileName2 = $path . $id . ".2";

        } while ( file_exists($fileName1) );

        file_put_contents($fileName1, $textOld);
        file_put_contents($fileName2, $textDiff);
        exec("patch -o - $fileName1 $fileName2", $a, $r);
        if (!$r) $textNew = implode("\n",$a);

        unlink($fileName1);
        unlink($fileName2);

        if ($r!=1 and $r!=0) throw new \Exception("$r : $textNew"); 

    return $textNew;    
    }
    */    
    
    /**
     * Parses diff results into the array.
     * 
     * @param string $text1 Input text with an old data.
     * @param string $text2 Input text with a new data.
     * @return <b>array</b> An array of structure:
     * ~~~
     * array (
     *   [block_index] => [
     *       0 => state,                      // state is: a - added, d - deleted, c - changed
     *       1 => [start_indnex, end_indnex], // of text1 (old data)
     *       2 => [start_indnex, end_indnex], // of text2 (new data)
     *       3 => "raw diff string",          // (option for debug or other parse)         
     *   ]
     * )
     * ~~~
     */
    public static function diffParse($text1, $text2) {
        $diff   = self::diff($text1, $text2);
       
        $blocks = [];
        
        foreach (explode("\n", $diff) as $string) {
            if (preg_match('/^([\d,]+)([acd])([\d,]+)$/', $string, $m)) { //print_r($m);
                $indexes1 = array_pad( explode(',', $m[1]), 2, $m[1] );
                $indexes2 = array_pad( explode(',', $m[3]), 2, $m[3] );        
                $blocks[] = [
                    0 => $m[2],     // state
                    1 => $indexes1, // [begin, end] of text1 (old)
                    2 => $indexes2, // [begin, end] of text2 (new)
                    3 => $m[0],     // (diff string)
                ];
            }
        }
        
    return $blocks;
    }

    /**
     * Compares two texts and represents results as array.
     * 
     * @param string $text1 Input text with an old data.
     * @param string $text2 Input text with a new data.
     * @param boolean $fill TRUE by default. If this parameter is FALSE the 
     *                      difference between two text will be just marked. 
     *                      Otherwise the missing strings will be filled by emptiness.
     * 
     * @return <b>array</b> An array of structure:
     * ~~~
     * array (
     *   [0] => [ //--- The first text (from an old data):
     *       0 => [ state, "text of string 0"],    
     *       1 => [ state, "text of string 1"],    
     *       ...
     *   ],
     *   [1] => [ //--- The second text (from a new data):
     *       0 => [ state, "text of string 0"],    
     *       1 => [ state, "text of string 1"],    
     *       ...
     *   ]
     * )
     * 
     * where `state` is one of:
     * 
     * 'a' - added;
     * 'd' - deleted;
     * 'c' - changed;
     * ''  - not changed;
     * 'f' - filled (missing) string;
     * 
     * ~~~
     */    
    public static function compare( $text1, $text2, $fill=true ) {
        $blocks   = self::diffParse($text1, $text2);
        $strings1 = array_map(function($string){ return ['', $string]; }, explode("\n", $text1));
        $strings2 = array_map(function($string){ return ['', $string]; }, explode("\n", $text2));
        
        $offset1 = 0;
        $offset2 = 0;
        
        foreach ($blocks as $block) {
            list($state, $index1, $index2) = $block;
            switch ($state) {
                case 'a':
                    $insertRowCount = $index2[1] - $index2[0] + 1;
                    for ($i=0; $i<$insertRowCount; $i++) { 
                        $strings2[ $index2[0]-1 + $i + $offset2 ][0] = 'a'; //--- mark as `added`
                    }
                    if ($fill) {
                        //--- Insertion of fill: (to text1)
                        $offset1 += self::fill($strings1, $index1[0] + $offset1, $insertRowCount);
                    }
                    break;
                    
                case 'd':
                    $insertRowCount = $index1[1] - $index1[0] + 1;
                    for ($i=0; $i<$insertRowCount; $i++) { 
                        $strings1[ $index1[0]-1 + $i + $offset1 ][0] = 'd'; //--- mark as `deleted`
                    }
                    if ($fill) {
                        //--- Insertion of fill: (to text2)
                        $offset2 += self::fill($strings2, $index2[0] + $offset2, $insertRowCount);
                    }
                    break;
                    
                case 'c':
                    $insertRowCount1 = $index1[1] - $index1[0] + 1;
                    for ($i=0; $i<$insertRowCount1; $i++) { 
                        $strings1[ $index1[0]-1 + $i + $offset1 ][0] = 'c'; //--- mark as `changed`
                    }
                    $insertRowCount2 = $index2[1] - $index2[0] + 1;
                    for ($i=0; $i<$insertRowCount2; $i++) { 
                        $strings2[ $index2[0]-1 + $i + $offset2 ][0] = 'c'; //--- mark as `changed`
                    }
                    if ($fill && $insertRowCount1 != $insertRowCount2) {
                        //--- Insertion of fill: 
                        $insertRowCount = abs($insertRowCount1 - $insertRowCount2);
                        if ($insertRowCount1 > $insertRowCount2) {
                            //--- Fill text2:
                            $offset2 += self::fill($strings2, $index2[0] + $offset2, $insertRowCount);
                        } else {
                            //--- Fill text1:
                            $offset1 += self::fill($strings1, $index1[0] + $offset1, $insertRowCount);
                        }
                    }
                    break;
            }
        }
        
        $data = [
            0 => $strings1,
            1 => $strings2,
        ];
    return $data;
    }
    
    private static function fill(&$array, $position, $insertRowCount) {
        $insertRows = array_fill(0, $insertRowCount, ['f', '']); //--- array of fillings
        array_splice($array, $position, 0, $insertRows);
        return $insertRowCount;
    }
    

}
