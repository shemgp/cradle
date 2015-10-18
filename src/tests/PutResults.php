<?php
/*==============================================================================
 *  Title      : PhpUnit Tests Results visualisation
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 06.10.2015
 *==============================================================================
 */
namespace digger\cradle\tests;

use digger\cradle\common\Data;
use digger\cradle\common\Files;
use digger\cradle\common\Html;

/**
 * @brief PhpUnit Tests Results visualisation
 * 
 * Finds all xml-files with PHPUnit tests results,
 * parse and represents it in HTML format.
 * 
 * @version 2.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * 
 * @todo How to use:
 * @code
 * require_once 'autoload.php';
 * 
 * use digger\cradle\tests\PutResults;
 * 
 * $results = new PutResults("/path/to/phpunit-test-results");
 * 
 * echo $results->toHtml();        //<-- out HTML code
 * echo $results->getJsContent();  //<-- out some Javascript code (require JQuery)
 * 
 * @endcode
 * 
 * @note
 * This class requires Javascript libs:
 *  - JQuery
 * 
 * To colorize HTML output use css-file with next styles:
 * ~~~
        //--- statuses:
 
        .testcases [data-testcase-ok]         { color: #00aa00; }
        .testcases [data-testcase-ok] > span:first-child { text-decoration: line-through; }
        .testcases [data-testcase-fail]       { color: #aa0000; }
        .testcases [data-testcase-error]      { color: #eb8f00; }

        //--- main items
 
        .testcases > ul > li { cursor: pointer; }

        //--- comments

        .testcases > ul > li > span {
                 color: #888888;
             font-size: 0.9em;
            font-style: italic;
        }
 
        //--- fail description:
 
        .testcases > ul > li div {
                  color: #555555;
              font-size: 0.9em;
                padding: 2px 10px 10px 10px;
            border-left: 1px solid #000000;
        }
 * ~~~ 
 * css class ".testcases" can be changed by @ref $cssContainerDivClass property.
 */
class PutResults {
    
    /**
     * CSS class name for main container (div)
     * @var string
     */
    public $cssContainerDivClass = "testcases";
    
    /**
     * Name of attribute of HTML li tag if test is ok
     * @var string
     */
    public $cssStatusOk      = "status-ok";

    /**
     * Name of attribute of HTML li tag if test is failed
     * @var string
     */
    public $cssStatusFail    = "status-fail";

    /**
     * Name of attribute of HTML li tag if test have error
     * @var string
     */
    public $cssStatusError   = "status-error";
    
    public $cssTestSuite     = "testsuite";
    public $cssTestName      = "testname";
    public $cssDescription   = "description";

    


    /**
     * Path to PHPUnit results xml-files
     * @var string 
     */
    private $targetPath = NULL;
    
    /**
     * Test suites structure
     * @var array 
     */
    private $testSuites = NULL;
    
    /**
     * Constructor
     * It is a main method
     * 
     * @param string $targetPath Path to PHPUnit results xml-files.
     */
    public function __construct( $targetPath="" ) {
        $this->setTargetPath($targetPath);
        $this->findAll();
    }

    /**
     * Set real path to PHPUnit results xml-files.
     * 
     * @param  string $targetPath   Path to PHPUnit results xml-files.
     * @return string               Real target path.
     */
    public function setTargetPath( $targetPath="" ) {
        $this->targetPath = realpath($targetPath);
        return $this->targetPath;
    }
    
    /**
     * Get path to PHPUnit results xml-files. 
     * (Or set new target path)
     * 
     * @param  string $targetPath   (Option) new path to PHPUnit results xml-files to set instead.
     * @return string               Real target path.
     */
    public function getTargetPath( $targetPath=NULL ) {
        if ($targetPath === NULL) {
            if ($this->targetPath === NULL) {
                $this->setTargetPath();
            }
        } else {
            $this->setTargetPath($targetPath);
        }
    return $this->targetPath;
    }
    
    /**
     * To find all existing PHPUnit results xml-files.
     * 
     * @param  string $targetPath   (Option) new path to PHPUnit results xml-files. 
     * @return hash                 Array of testSuites structure:
     * @code
     * Array (
            [someFile.xml] => Array
                (
                    [@attributes] => Array
                        (
                            [name] => someTest
                            [file] => someFile.xml
                            [tests] => 
                            [assertions] => 
                            [failures] => 1
                            [errors] => 
                            [time] => 
                            [base] => basePath
                        )
                    [testcase] => Array
                        (
                            [0] => Array
                                (
                                    [@attributes] => Array
                                        (
                                            [name] => 
                                            [class] => 
                                            [file] => 
                                            [line] =>
                                            [assertions] =>
                                            [time] =>
                                        )
                                    [failure] => some failure
                                )
                )
            [someFile2.xml] => Array( ... )
            ...
     
     * @endcode
     */
    public function findAll( $targetPath=NULL ) {
        //--- Find all xml files of results:
        $targetPath = $this->getTargetPath($targetPath);
        $files      = Files::globFiles($targetPath, "\.xml$", NULL, true);
        $this->testsStructure = Files::getFilesTree($files);
        $testSuites = array();
        //--- Load results to common array:
        if (is_array($files)) {
            foreach ($files as $fileName => $basePath) {
                $data         = Data::load($fileName, false);
                if (!empty($data["testsuite"]["@attributes"])) {
                    $data["testsuite"]["@attributes"]["base"] = $basePath;
                    $testSuites[$fileName] = $data["testsuite"];
                }
            }
        } //echo "<listing>"; print_r($testSuites); exit;
        $this->testSuites = $testSuites;
    return $this->testSuites;    
    }

    /**
     * Represents testSuites structure in HTML format.
     * 
     * @return string HTML code:
     * @code
     * 
     * <div class='$cssContainerDivClass'>
     *  ...
     *  <ul>
     *      <li class='$testsuite'><span>TestClassName1</span> <em>--> FileName1</em> </span>
     *           <ul>
     *               <li><div class='$testname'> test_methodName1 </div> </li>
     *               <li><div class='$testname'> test_methodName2 </div> <div class='$description'> ... fail description ... </div></li>
     *                 ...
     *           </ul>
     *      </li>
     *       ...
     *  </ul>
     * </div>
     * 
     * @endcode
     */
    public function toHtml() {
        if (!is_array($this->testSuites)) { 
            return false;
        }
        //--- Generate HTML:
        $out = Html::arrayToList(
                $this->testsStructure, 
                'ul', 
                null, 
                function(&$tag, &$body, &$attr) { 
            
                    if ($tag == 'li' && preg_match('/(.*)\.\w+$/', $body)) {
                        $suiteFile = $this->targetPath . '/' . $body;
                        $suite     = $this->testSuites[$suiteFile];
                        //--------------------------------
                        if (is_array($suite["@attributes"])) {
                            $sa        = $suite["@attributes"];
                            $fileName  = $body;
                            $testsList = [];
                            if (is_array($suite["testcase"])) {
                                if (isset($suite["testcase"][0])) {
                                    foreach ($suite["testcase"] as $testcase) {
                                        $testsList []= $this->toHtmlTestcase($testcase);
                                    }
                                } else {
                                        $testsList []= $this->toHtmlTestcase($suite["testcase"]);
                                }
                            }
                            $testsList = Html::arrayToList($testsList);
                            $body      = "<span>" . $sa["name"] . "</span> <em> --> $fileName</em>" . $testsList;
                            $attr['class'] = $this->cssTestSuite;
                        } else {
                            $body .= ' --' . _('file error') . '!';
                            $attr['class'] = $this->cssStatusError;
                        }
                        //--------------------------------
                    }
                    
                });
    return "<div class='" . $this->cssContainerDivClass . "'>$out</div>";
    }
    
    /**
     * @param  hash      $testcase  Testcase structure.
     * @return string               HTML code.
     */
    private function toHtmlTestcase ($testcase) {
        $attr    = $testcase["@attributes"];
        $name    = $attr["name"];
        $cssClass = "";
        if (!$attr["assertions"]) { 
            $cssClass = $this->cssStatusFail;
        }
        if ($testcase["error"]) {
            $cssClass = $this->cssStatusError;
            $fail_description = $testcase["error"];
        }
        if ($testcase["failure"]) {
            $cssClass = $this->cssStatusFail;
            $fail_description = $testcase["failure"];
        }
        if (!$cssClass) {
            $cssClass = $this->cssStatusOk;
        }
        if ($fail_description) $fail_description = "<div class='" . $this->cssDescription . "'>" . htmlspecialchars($fail_description) . "</div>";
        $out = "<div class='" . $this->cssTestName . " " . $cssClass . "'>$name</div> $fail_description";
    return $out;                
    }
    
    /**
     * Get Javascript content additions (Option)
     * 
     * @return string Javascript code. 
     */
    function getJsContent() {
        return '<script> '
             . '$(function() { '
             . '    var section = $(".' . $this->cssContainerDivClass . ' .' . $this->cssTestName . '");'
             . '        section.on("click", function() { $(this).next(".' . $this->cssDescription . '").toggle(); } );'
             . '        $(".' . $this->cssTestSuite . ' > span").on("click", function() { $(this).parent().find(".' . $this->cssDescription . '").toggle(); } );'
             . '        $(".' . $this->cssDescription . '").toggle();'
             . '}); '
             . '</script>';
    }
    
}
