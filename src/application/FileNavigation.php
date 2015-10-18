<?php
/*==============================================================================
 *  Title      : File Navigation
 *  Author     : Digger (c) SAD-Systems <http://sad-systems.ru>
 *  Created on : 03.10.2015
 *==============================================================================
 */
namespace digger\cradle\application;

use digger\cradle\common\Files;
use digger\cradle\common\Html;

/**
 * @brief File Navigation
 * 
 * Simple file navigation.
 * Creates HTML tree list from file system tree.
 *
 * @version 1.0
 * @author Digger <mrdigger@sad-systems.ru>
 * @copyright (c) 2015, SAD-Systems
 * 
 * @todo How to use:
 * @code
 * <?php
 * //--- file: index.php
 * 
 * $navigation     = new FileNavigation(['filePatterns' => '.*\.php$', 'excludePatterns' => '^' . basename(__FILE__) . '$']);
 * $navigationMenu = $navigation->getNavigation();
 * $contentFile    = $navigation->getCurrentItem();
 * 
 * ?>
 * <html>
 *   <body>
 *     <nav> <?= $navigationMenu => </nav>
 *     <div class="content"> <?php include $contentFile; ?> </div>
 *   </body>
 * </html>
 * @endcode
 */
class FileNavigation {
    
    public $routerPrefix    = 'r';
    public $cssActive       = 'active';
    public $listType        = 'ul';
    public $path            = "";
    public $filePatterns    = null; //['.*\.php$'];
    public $excludePatterns = null;
    public $recursive       = true;
    public $stripExtention  = '.php';
    
    private $currentItem;

    /**
     * Constructor
     * 
     * @param array $arrayParams Initialization parameters of the class.
     * 
     * Example:
     * ~~~
     * $navigation = new FileNavigation(['filePatterns' => '.*\.php$', 'excludePatterns' => '^' . basename(__FILE__) . '$']);
     * ~~~
     */
    public function __construct($arrayParams = null) {
        if (is_array($arrayParams)) {
            foreach ($arrayParams as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                }
            }
        }
    }

    /**
     * Returns current active item
     * 
     * @return string
     */
    public function getCurrentItem() {
        return $this->currentItem;
    }

    /**
     * Returns HTML code list contains tree of files spesified by class properties. 
     * 
     * @return string HTML code
     */
    public function getNavigation() {
        
        //--- Create real path:
        $this->path = realpath($this->path);
        //--- Create filePatterns from given stripExtention:
        if ($this->stripExtention && !$this->filePatterns) { $this->filePatterns = '.*' . preg_quote($this->stripExtention) . '$' ; }
        
        //--- Select all files by patterns:
        $files = Files::globFiles($this->path, $this->filePatterns, $this->excludePatterns, $this->recursive);
        
        //--- Get requested file:
        $activeFile = $_REQUEST[$this->routerPrefix];
        
        //--- Add file extention:
        if ($activeFile && $this->stripExtention) { $activeFile .= $this->stripExtention; }
        
        //--- Check: Is it my file?:
        if (!isset($files[rtrim($this->path, '/') . '/' .$activeFile])) { $activeFile = ''; }
        
        //--- Get default file (first):
        if (!$activeFile) {
            $name = array_keys($files)[0];
            $base = array_values($files)[0];
            $activeFile = preg_replace('/^' . preg_quote($base . '/', '/') . '/', '', $name);
        }
        
        //--- Set current item:
        $this->currentItem = $activeFile;
        
        //--- Create HTML code of tree:
        return Html::arrayToList(Files::getFilesTree($files), $this->listType, null, function(&$tag, &$body, &$attr){ 
            if ($tag == 'li' && preg_match('/(.*)\.\w+$/', $body, $m)) {
                if ($body == $this->currentItem) { $attr['class'] = $this->cssActive; }
                if ($this->stripExtention) { $body = preg_replace('/' . preg_quote($this->stripExtention). '$/', '', $body); }
                $body = Html::getElement('a', basename($m[1]), ['href'=>'?' . $this->routerPrefix . '=' . $body]);
            }
        });
        
    }
    
}
