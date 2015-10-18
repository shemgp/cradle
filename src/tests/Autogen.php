<?php
/*==============================================================================
 *  Title      : Auto generator of PHPUnit tests
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 23.04.2015
 *==============================================================================
 */
namespace digger\cradle\tests;

use Exception;
use digger\cradle\common\Data;
use digger\cradle\common\Files;
use digger\cradle\common\Basic;
use digger\cradle\text\SimpleCodeParser;

/**
 * @brief Digger simple automatic generator of PHPUnit tests
 * 
 * Generates PHPUnit test skeletons for PHP files with classes and functions.
 * 
 * @version 2.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * @throws Exception
 * 
 * @todo How to generate tests:
 * @code
 * require_once 'autoload.php';
 * 
 * use digger\cradle\common\Autogen;
 * 
 * try {
 * 
 *   $config  = array( ... hash of properties values ... );
 *   $autogen = new Autogen($config, true);
 *   print_r($autogen->getResult()); 
 * 
 * } catch (Exception $e) {
 *    echo "ERROR: " . $e->getMessage() . "\n";    
 * }
 * @endcode
 * 
 */
class Autogen {

//------------------------------------------------------------------------------
// MAIN PROPERTIES
//------------------------------------------------------------------------------
    
    /**
     * Class name template for test
     * @var string 
     */
    public $testClassName = "[NAME]Test";
    
    /**
     * Method name template for test
     * @var string
     */
    public $testName = "test_[NAME]";
    
    /**
     * Array of regular expressions to exclude some methods (such as: __construct, __destruct, and so on ...)
     * @var string
     */
    public $excludeNames = [ "^__" ];

    /**
     * Namespace for test
     * @warning Do not recomended to change this default value!
     * @var string
     */
    public $testNameSpace = ""; 
    
    /**
     * Header template for output PHP file
     * @var string 
     */
    public $templateHeader = "<?php
/*==============================================================================
 *  Title      : PHPUnit test. This file was created by [GENERATOR_NAME]
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : [DATE]
 *  Updated on : [DATE]
 *==============================================================================
 */\n";
    
    /**
     * Template for some system comments
     * @var string
     */
    public $templateComments =" *
 * PHPUnit tests for:
 * 
 *      [CLASS_LIST]
 *
 * contains in file:
 * 
 *      [SOURCE_FILE]
 *
 *------------------------------------------------------------------------------
";
    
    /**
     * Class body template for output PHP file
     * @var string 
     */
    public $templateClass = "\n
/**
 * @covers [CLASS_NAME]
 */        
class [TEST_CLASS_NAME] extends PHPUnit_Framework_TestCase
{
}\n";
    
    /**
     * Class method/function body template for output PHP file
     * @var string 
     */
    public $templateTest ='
   /**
     * @covers [NAME]
     */    
    public function [TEST_NAME]()
    {
        [CREATE]
        $this->assertEquals( [INSTANCE], true );
    }
';    
    
//------------------------------------------------------------------------------
//  MULTI FILES PROPERTY   
//------------------------------------------------------------------------------
    
    /**
     * Source paths to find all source files
     * @var array 
     */
    public $sourcePaths    = [ "./" ];
    
    /**
     * Array of regexp patterns to find only necessary file names
     * @var array 
     */
    public $sourcePatterns = [ '\.php$' ];   
    
    /**
     * Array of regexp patterns to exclude some file names
     * @var array 
     */
    public $sourceExclude  = [ '^_', 'Test\.php$' ];
    
    /**
     * Searching for source files recursively
     * @var boolean 
     */
    public $sourceRecursive = true;
    
    /**
     * Root of destination path
     * @var string 
     */
    public $destinationRoot = "./tests";
    
    /**
     * Use relative path of source file in "include_once" directive
     * @var boolean 
     */
    public $includeRelativePath = true;
    
    /**
     * Create output sub directories
     * @var boolean 
     */
    public $destinationTree = true;
    
    /**
     * Template for file name of test
     * @var string 
     */
    public $testFileName    = "[NAME]Test.php";
    
    /**
     * Run file name
     * @var string 
     */
    public $runFileName   = "run.sh";
    
    /**
     * PHPUnit exec file
     * @var string
     */
    public $phpUnitFile     = "phpunit"; ///usr/local/bin/phpunit
    
    /**
     * Template for output run file
     * @var string 
     */
    public $runFileHeaderTemplate = "#!/bin/sh
echo
echo '*=============================================================================='
echo '*  Title      : Run tests. This file was created by [GENERATOR_NAME]'
echo '*  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>'
echo '*  Created on : [DATE]'
echo '*=============================================================================='
echo

cd [RUN_DIR]
";
    
    /**
     * Template for output run file line
     * @var string
     */
    public $runFileLineTemplate = '
echo ______________________________________________________________________________
echo 
echo Run test: [[TEST_TITLE]] from [[TEST_FILE]]
echo 
[PHPUNIT] --log-junit [TEST_LOG].xml [TEST_NAME] [TEST_FILE]';    
    
    /**
     * Include 'requrie_once' directive in out test file
     * @var string
     */
    public $includeAutoloaderFileName = "";
    
//------------------------------------------------------------------------------
// METHODS    
//------------------------------------------------------------------------------
    
    /**
     * Class constructor
     * 
     * @param hash|string  $config       Hash array with values of class properties, 
     *                                   or config file name contains it.
     * @param boolean      $generateNow  Generate tests on create.
     */
    public function __construct( $config=NULL, $generateNow=false ) {
        //--- Set default autoloader:
        //$this->includeAutoloaderFileName = __DIR__ . "/../../../autoload.php";
        if ($generateNow) {
        //--- Generate tests right now:
            $this->generate($config);
        } else {
        //--- Set only class properties:
            Data::set($this, $config); // $this->setConfig($config);
        }
    }
    
    /**
     * Strip function parameters. 
     * To clear any symbols befor "$paramName" (such as '&' or 'typeOfParameter')
     * 
     * @param  string $stringParams String of function parameters.
     * @return string               Stripped string.
     */
    public function stripFunctionParams($stringParams) {
        $params = explode(',', $stringParams);
        foreach ($params as $i => $param) {
            $params[$i] = preg_replace('/^[^\$]*/', '', trim($param) );
        }
        $strippedString = implode(', ', $params);
    return " $strippedString "; 
    }
    
    /**
     * Generate code with PHPUnit tests for classes and functions of source code
     * 
     * @param  string $sourceCode     PHP-file source code.
     * @param  string $sourceFileName (Option) needed to create "require" directive.
     * @param  string $resultFileName (Option) needed to create class name for stanalong functions and to create "require" directive with relative path.
     * @param  string $currentContent (Option) needed to insert result code inside an existing content.
     * @return string                 Result code of PHP-file contains tests.
     */
    public function generateCode( $sourceCode, $sourceFileName="", $resultFileName="NonameClass", $currentContent=NULL ) {
        
        //--- Prepare excludeNames Array:
        if ($this->excludeNames && !is_array($this->excludeNames)) $this->excludeNames = array($this->excludeNames);
        
        //--- Create class name for functions:
        $baseClassName = preg_replace("/\..*$/","",basename($resultFileName));
                
        //--- Create source code structure:
        $codeStructure = SimpleCodeParser::parseCode( $sourceCode );

        //--- Create out structure for tests:
        
        foreach ($codeStructure as $nameSpace => $body) {

        //--- Create Namespaces:    
            if ($nameSpace) {
                $useNameSpaces[] = $nameSpace; 
                $nameSpacePrefix = basename(str_replace("\\", "/", $nameSpace)) . "\\";
                $nameSpaceFull   = $nameSpace . "\\";
            } 
            else { 
                $nameSpacePrefix = "";
                $nameSpaceFull   = "";
            }

        //--- Create Tests for Classes:
            if (is_array($body["classes"])) 
            foreach ($body["classes"] as $className => $methods) {
                $sourceClassList[] = "   class: " . $nameSpaceFull . $className;
                $testClassName     = Basic::replace($this->testClassName, array("NAME" => $className));
                $testClassMap[$testClassName] = $nameSpaceFull . $className;
                if (is_array($methods)) foreach ($methods as $methodName => $params) {
                    if (($params["scope"]=="" || $params["scope"]=="public") && !Basic::inPatterns($methodName, $this->excludeNames)) {
                        $add = false;
                        if ($params["type"]=="static") {
                            $name     = "static method: " . $nameSpaceFull . "$className::$methodName";
                            $create   = "";
                            $instance = $nameSpacePrefix . "$className::$methodName(" . $this->stripFunctionParams($params["params"]) . ")";
                            $add      = true;
                        } 
                        elseif ( $params["type"]=="" ) {
                            $name     = "method: " . $nameSpaceFull . "$className->$methodName";
                            $create   = "\$c = new " . $nameSpacePrefix . "$className();";
                            $instance = "\$c->$methodName(" . $this->stripFunctionParams($params["params"]) . ")";
                            $add      = true;
                        }
                        if (!$add) continue;
                //--- Add Tests to outStructure:
                        $testName = Basic::replace($this->testName, array("NAME" => $methodName));
                        $outStructure[$testClassName][$testName] = Basic::replace($this->templateTest, array(
                            "NAME"      => $name,
                            "TEST_NAME" => $testName,
                            "CREATE"    => $create,
                            "INSTANCE"  => $instance
                        ));
                    }
                }
            }    
        //--- Create Tests for functions:
            if (is_array($body["functions"])) foreach ($body["functions"] as $functionName => $params) {
                if ( Basic::inPatterns($functionName, $this->excludeNames) ) continue;
                $name              = "function: " . $nameSpaceFull . $functionName;
                $sourceClassList[] = $name;
                //--- Add Tests to outStructure:
                $testClassName = $baseClassName;
                $testName      = Basic::replace($this->testName, array("NAME" => $functionName));
                $outStructure[$testClassName][$testName] = Basic::replace($this->templateTest, array(
                    "NAME"      => $name,
                    "TEST_NAME" => $testName,
                    "CREATE"    => "",
                    "INSTANCE"  => $nameSpacePrefix . "$functionName(" . $this->stripFunctionParams($params) . ")"
                ));
            }    
        }        

        //--- If outStructure is empty - break:
        if (!$outStructure) { 
            return false;
        }
        
        //--- Get existing result code structure (if it was saved earlier):
        $existingStructure = SimpleCodeParser::parseCode($currentContent);

        //--- Generate new result code:

        $resultCode        = $currentContent;
        
        //--- Add Header:
        if ($resultCode == "") {
            $resultCode = Basic::replace($this->templateHeader, array(
                "DATE"           => date("Y.m.d H:i"), 
                "GENERATOR_NAME" => get_class()
            ));
        }
        //--- Add some comments:
        if (is_array($sourceClassList)) {
            sort($sourceClassList);
            $sourceClassList = implode("\n *      ",$sourceClassList);
        }
        $comments = Basic::replace($this->templateComments, array(
            "SOURCE_FILE" => basename($sourceFileName),
            "CLASS_LIST"  => $sourceClassList  
        ));
        $resultCode = preg_replace('/( \*=+\n)([^=]*?)( \*\/)/ms', '\1'.$comments.'\3', $resultCode);

        //--- Add testNameSpace:
        if ($this->testNameSpace) {
            $testNameSpace = "\n\n" . "namespace ". $this->testNameSpace .";\n";
            $regExpPattern = '^\s*namespace\s+'.preg_quote($this->testNameSpace, "/").'\s*;';
            $resultCode    = self::placeAfter( $resultCode, $testNameSpace, $regExpPattern );
        }
        //--- Add "USE" directive:
        if (is_array($useNameSpaces)) 
            foreach ($useNameSpaces as $nameSpace) {
                $useNameSpaceString = "\n\n" . "use $nameSpace;";
                $regExpPattern      = '^\s*use\s+'.preg_quote($nameSpace, "/").'\s*;';
                $resultCode         = self::placeAfter( $resultCode, $useNameSpaceString, $regExpPattern, array('^\s*(require|require_once)\s+[^;]+;', '^\s*namespace\s+[^;]+;', '^\s*<\?php') );
        }
        //--- Add "REQUIRE" directive:
        if ($sourceFileName) {
            $includeSourceFileName = $sourceFileName;
            if ($this->includeRelativePath) {
                $relativeSourcePath    = Files::getRelativePath(dirname($includeSourceFileName), dirname($resultFileName));
                $includeSourceFileName = $relativeSourcePath["relative"] . "/" . basename($includeSourceFileName);
                $require   = "\n\n" . "require_once __DIR__ . '$includeSourceFileName';";
            } else {
                $require   = "\n\n" . "require_once '$includeSourceFileName';";
            }
            $regExpPattern = "^\\s*(require|require_once)\\s+[^;]*\\(?\\s*['\"]+".preg_quote($includeSourceFileName, "/")."['\"]+\\s*\\)?\\s*;";
            $resultCode    = self::placeAfter( $resultCode, $require, $regExpPattern, array('^\s*namespace\s+[^;]+;', '^\s*<\?php'));
        }
        //--- Add "REQUIRE AUTOLOADER" directive:
        if ($this->includeAutoloaderFileName) {
            $includeAutoloaderFileName = realpath($this->includeAutoloaderFileName);
            if (!$includeAutoloaderFileName) throw new Exception('Autoloader not found : [' . $this->includeAutoloaderFileName . ']');
            $relativeSourcePath        = Files::getRelativePath(dirname($includeAutoloaderFileName), dirname($resultFileName)); 
            $includeAutoloaderFileName = $relativeSourcePath["relative"] . "/" . basename($includeAutoloaderFileName); 
            $require       = "\n\n" . "require_once __DIR__ . '$includeAutoloaderFileName';";
            $regExpPattern = "^\\s*(require|require_once)\\s+[^;]*\\(?\\s*['\"]+".preg_quote($includeAutoloaderFileName, "/")."['\"]+\\s*\\)?\\s*;";
            $resultCode    = self::placeAfter( $resultCode, $require, $regExpPattern, array('^\s*namespace\s+[^;]+;', '^\s*<\?php'));
        }
        //--- Add file body:
        if (is_array($outStructure)) { 
            //--- Add classes
            foreach ($outStructure as $className => $methods) {
                if (is_array($existingStructure[$this->testNameSpace]["classes"]) && array_key_exists($className, $existingStructure[$this->testNameSpace]["classes"])) continue;
                $resultCode .= Basic::replace($this->templateClass, array(
                    "CLASS_NAME"      => $testClassMap[$className],
                    "TEST_CLASS_NAME" => $className
                ));
            }
            //--- Add methods
            foreach ($outStructure as $className => $methods) {
                $classBody = "";
                foreach ($methods as $methodName => $methodBody) {
                    if (is_array($existingStructure[$this->testNameSpace]["classes"][$className]) && array_key_exists($methodName, $existingStructure[$this->testNameSpace]["classes"][$className])) continue;
                    $classBody .= $methodBody;
                }
                if (preg_match('/^\s*class\s+'.preg_quote($className, "/").'\s+([^\{]*)/ms', $resultCode, $m, PREG_OFFSET_CAPTURE)) {
                    list($startPosition, $endPosition) = SimpleCodeParser::getFirstBraceBlockPos(substr($resultCode,$m[0][1]));
                    $endPosition += $m[0][1];
                    $resultCode = substr($resultCode, 0, $endPosition) . $classBody . substr($resultCode, $endPosition);
                }
            }
        }
        //--- Change Update time in header ---
        $resultCode = preg_replace("/Updated on : \d\d\d\d\.\d\d\.\d\d \d\d:\d\d/", 'Updated on : '.date("Y.m.d H:i"), $resultCode);
        //------------------------------------
        //print_r($resultCode); exit;
    return $resultCode;    
    }

    /**
     * To place a some new text into the source text after a first found "key pattern"
     * 
     * @param  string $sourceText     A source text.
     * @param  string $newText        A new text to insert. 
     * @param  string $findPattern    Regular expression to find duplicates of $newText and to cancel insertion.
     *                                If $findPattern=NULL $newText will inserted without check and duplicate may occur. 
     * @param  array  $afterKeys      The $newText will inserted after first found "key pattern" from $afterKeys patterns.
     * @return string                 Result text.
     */    
    public static function placeAfter( $sourceText, $newText, $findPattern=NULL, $afterKeys=array('<\?php') ) {
        //--- if $newText is exists - nothing to do!
        if ($findPattern && preg_match("/$findPattern/ms", $sourceText)) 
            return $sourceText;
        //--- AfterKeys need to be an array:
        if (!is_array($afterKeys)) $afterKeys = array( $afterKeys );
        //--- Find first occurance of AfterKey:
        foreach ($afterKeys as $afterKey) {
            unset($m);
            if (preg_match('/'.$afterKey.'/ms', $sourceText, $m, PREG_OFFSET_CAPTURE)) {
                $p = $m[0][1] + strlen($m[0][0]);
                unset($m);
                //--- Ignore first symple comment:
                if (preg_match('/\s*\/\*[^\*]+.*?\*\//ms', substr($sourceText, $p), $m, PREG_OFFSET_CAPTURE)) {
                    $p += $m[0][1] + strlen($m[0][0]);
                }
                //--- Insert in place:
                $sourceText = substr($sourceText, 0, $p) . $newText . substr($sourceText, $p);
                break;
            }
        }    
    return $sourceText;    
    }

    /**
     * Generate file with PHP-unit tests
     * 
     * @param string $sourceFile Input file name.
     * @param string $outFile    Output file name.
     * @throws Exception
     */
    public function generateFile ($sourceFile, $outFile) {
        //--- Load source code:
        if (!file_exists($sourceFile)) { 
            throw new Exception("Source file not found");
        }
        $sourceCode = file_get_contents($sourceFile);
        if (!$sourceCode) { 
            throw new Exception("Empty source");
        }
        //--- Load current content of $outFile:
        if (file_exists($outFile)) { 
            $currentContent = file_get_contents($outFile);
        }
        //--- Generate tests and insert to current content of $outFile:
        $currentContent = $this->generateCode($sourceCode, $sourceFile, $outFile, $currentContent);
        //--- Save out file:
        if ($currentContent) {
            //--- Create out file path:
            $path = dirname($outFile);
            if (!is_dir($path)) { 
                mkdir ($path, 0777, true);
            }    
            //--- Save:
            if (file_put_contents($outFile, $currentContent) === FALSE) { 
                throw new Exception("Can't save a file");
            }
        } else { 
            throw new Exception("Empty result code");
        }
    }
    
    /**
     * Hash array of result
     * @var hash 
     */
    private $resultData;
    
    /**
     * Generate all tastes by input config or by class default values
     * 
     * @param  hash         $config Config params (class properties values)
     * @throws Exception
     * @return hash                 An array of generated files. (Array structure see in @ref getResult)
     * @see    getResult
     */
    public function generate ( $config=NULL ) {
        //--- Set configuration:
        Data::set($this, $config); // $this->setConfig($config);
        //--- Get input files:
        $inputFiles = Files::globFiles($this->sourcePaths, $this->sourcePatterns, $this->sourceExclude, $this->sourceRecursive); //$this->getSources();
        if (empty($inputFiles)) { 
            throw new Exception("no input files");
        }
        //--- Create and set destination root path:
        if ($this->destinationRoot && !is_dir($this->destinationRoot)) { 
            mkdir ($this->destinationRoot, 0777, true);
        }    
        $destinationRoot = realpath($this->destinationRoot);
        if (!$destinationRoot) {
            throw new Exception("[destinationRoot] does not exists");
        }
        //--- Create files:
        foreach ($inputFiles as $inputFile => $base) {
            //--- Create files with sub directories: 
            if ($this->destinationTree) {
                $path = substr(dirname($inputFile), strlen($base)) . "/";
                $path = $destinationRoot . $path;
                //if (!is_dir($path)) { 
                //    mkdir ($path, 0777, true);
                //}    
            //--- Create files in same directory:    
            } else {
                $path = $destinationRoot . "/";
            }
            //--- Create output file name:
            $outputFile = $path . Basic::replace($this->testFileName, array(
                "NAME" => preg_replace('/\.[^\.]*$/', "", basename($inputFile))
            ));
            //--- Generate file:
            try { 
                $this->generateFile ($inputFile, $outputFile);
                $destinations[$outputFile] = "test";
            } catch (Exception $e) {
                $errors[$outputFile] = $e->getMessage();
            }
        }
        //--- Create common run file:
        if ($this->runFileName) {
            $runFile = $destinationRoot . "/" . basename($this->runFileName); 
            $runBody = $this->generateRunFile($destinations, $destinationRoot);
            if ($runBody) { 
                if (file_put_contents($runFile, $runBody) === FALSE) { 
                    $errors[$runFile] = "Can't create run file";
                } else {
                    chmod($runFile, 0755); //--- set execute rights
                    $destinations[$runFile] = "run";
                }
            }
        }
        //--- Create result data:
        $this->resultData['destinations'] = $destinations;
        $this->resultData['errors']       = $errors;
        //---
    return $this->getResult();    
    }
    
    /**
     * Returns a result of generation
     * 
     * @return hash Array of structure:
     * @code
     * array (
     *      'destinations' => array( 'filename' => 'test|run', ... )
     *      'errors'       => array( 'filename' => 'error message', ... )
     * )
     * @endcode
     */
    public function getResult() {
        return $this->resultData;
    }
    
    /**
     * Generate common file body to execute all tests
     * 
     * @param  array  $destinations     List of destination file names.
     * @param  string $destinationRoot  Root path for destinations.
     * @return string                   A source code (text) of run-file.
     */
    public function generateRunFile($destinations, $destinationRoot) {
        if (!is_array($destinations)) {
            return false;
        }
        $destinationRoot = realpath($destinationRoot);
        $body = Basic::replace($this->runFileHeaderTemplate, array(
            "DATE"           => date("Y.m.d H:i"), 
            "GENERATOR_NAME" => get_class(),
            "RUN_DIR"        => $destinationRoot,
        ));
        foreach ($destinations as $file => $_) {
            $testStruct = SimpleCodeParser::parseCode(file_get_contents($file));
            if (is_array($testStruct)) { 
                foreach ($testStruct as $nameSpace) {
                    if (is_array($nameSpace["classes"])) {
                        foreach ($nameSpace["classes"] as $className => $value) {
                            $testName = $className;
                            $path     = Files::getRelativePath(dirname($file), $destinationRoot);
                            $fileName = "." . $path["relative"] . "/" . basename($file);
                            $logName  = "." . $path["relative"] . "/" . $testName;
                            $body .= Basic::replace($this->runFileLineTemplate, array(
                                "TEST_TITLE" => $testName,
                                "TEST_NAME"  => $testName,
                                "TEST_FILE"  => $fileName,
                                "TEST_LOG"   => $logName,
                                "PHPUNIT"    => $this->phpUnitFile
                            ));
                        }
                    }
                }
            }
        }
    return $body;    
    }
    
}


//=== FOR DEBUG:
/* 1
$autogen    = new Autogen();
$sourceFile = "Simple.php";
$outFile    = "SimpleTest.php";
$autogen->generateFile($sourceFile, $outFile);
*/

/* 2
$autogen = new Common\Autogen();
print_r( $autogen->generate() );
*/

/*
$fileName = "Phone.php";
print_r(Common\SimpleCodeParser::parseCode( file_get_contents($fileName) )); 
exit;

$data = "
    
 awee  {cdd
 l;{{1}{4}{5}{{2}{3}}}
 dfdf}1
 dfdfdf

    ";
$data = "ABC{123456789}DEFGH{123{4}5{{6}7}89}END";
$data = NULL;
$data = "1234567890";
$data = "12{34567890";
$data = "1234567890}";

//print_r( Common\SimpleCodeParser::getFirstBraceBlockPos($data) );
//print_r( Common\SimpleCodeParser::getFirstBraceBlock($data,false) );

$data = "   class Myclass extends Ej implen  {| class data |}\n"
        . "class MayClass {function 1();{2}3}...";
print_r( Common\SimpleCodeParser::getObjects($data) );
*/