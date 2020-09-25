<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Behavior;
use Think\Storage;
/**
 *
 */
class ReadHtmlCacheBehavior {
    //
    public function run(&$params){
        //
        if(IS_GET && C('HTML_CACHE_ON'))  {
            $cacheTime = $this->requireHtmlCache();
            if( false !== $cacheTime && $this->checkHTMLCache(HTML_FILE_NAME,$cacheTime)) { //
                //
                echo Storage::read(HTML_FILE_NAME,'html');
                exit();
            }
        }
    }

    //
    static private function requireHtmlCache() {
        //
         $htmls = C('HTML_CACHE_RULES'); //
         if(!empty($htmls)) {
            $htmls = array_change_key_case($htmls);
            //
            //
            //
            $controllerName = strtolower(CONTROLLER_NAME);
            $actionName     = strtolower(ACTION_NAME);
            if(isset($htmls[$controllerName.':'.$actionName])) {
                $html   =   $htmls[$controllerName.':'.$actionName];   //
            }elseif(isset($htmls[$controllerName.':'])){//
                $html   =   $htmls[$controllerName.':'];
            }elseif(isset($htmls[$actionName])){
                $html   =   $htmls[$actionName]; //
            }elseif(isset($htmls['*'])){
                $html   =   $htmls['*']; //
            }
            if(!empty($html)) {
                //
                $rule   = is_array($html)?$html[0]:$html;
                //
                $callback = function($match){ 
                    switch($match[1]){
                        case '_GET':        $var = $_GET[$match[2]]; break;
                        case '_POST':       $var = $_POST[$match[2]]; break;
                        case '_REQUEST':    $var = $_REQUEST[$match[2]]; break;
                        case '_SERVER':     $var = $_SERVER[$match[2]]; break;
                        case '_SESSION':    $var = $_SESSION[$match[2]]; break;
                        case '_COOKIE':     $var = $_COOKIE[$match[2]]; break;
                    }
                    return (count($match) == 4) ? $match[3]($var) : $var;
                };
                $rule     = preg_replace_callback('/{\$(_\w+)\.(\w+)(?:\|(\w+))?}/', $callback, $rule);
                // {ID|FUN}
                $rule     = preg_replace_callback('/{(\w+)\|(\w+)}/', function($match){return $match[2]($_GET[$match[1]]);}, $rule);
                $rule     = preg_replace_callback('/{(\w+)}/', function($match){return $_GET[$match[1]];}, $rule);
                //
                $rule   = str_ireplace(
                    array('{:controller}','{:action}','{:module}'),
                    array(CONTROLLER_NAME,ACTION_NAME,MODULE_NAME),
                    $rule);
                // {|FUN}
                $rule  = preg_replace_callback('/{|(\w+)}/', function($match){return $match[1]();},$rule);
                $cacheTime  =   C('HTML_CACHE_TIME',null,60);
                if(is_array($html)){
                    if(!empty($html[2])) $rule    =   $html[2]($rule); //
                    $cacheTime  =   isset($html[1])?$html[1]:$cacheTime; //
                }else{
                    $cacheTime  =   $cacheTime;
                }
                
                //
                define('HTML_FILE_NAME',HTML_PATH . $rule.C('HTML_FILE_SUFFIX',null,'.html'));
                return $cacheTime;
            }
        }
        //
        return false;
    }

    /**
     *
     *
     * @access public
     * @param string $cacheFile  静态文件名
     * @param integer $cacheTime  缓存有效期
     * @return boolean
     */
    static public function checkHTMLCache($cacheFile='',$cacheTime='') {
        if(!is_file($cacheFile) && 'sae' != APP_MODE ){
            return false;
        }elseif (filemtime(\Think\Think::instance('Think\View')->parseTemplate()) > Storage::get($cacheFile,'mtime','html')) {
            //
            return false;
        }elseif(!is_numeric($cacheTime) && function_exists($cacheTime)){
            return $cacheTime($cacheFile);
        }elseif ($cacheTime != 0 && NOW_TIME > Storage::get($cacheFile,'mtime','html')+$cacheTime) {
            //
            return false;
        }
        //
        return true;
    }

}