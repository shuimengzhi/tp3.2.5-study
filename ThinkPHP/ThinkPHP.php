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

//----------------------------------
// ThinkPHP 入口文件
//----------------------------------

// 记录启动时间
$GLOBALS['_beginTime'] = microtime(TRUE);
//检查内存分配大小的方法是否存在
define('MEMORY_LIMIT_ON',function_exists('memory_get_usage'));
// 如果存在那个方法，设置开始分配的内存大小
if(MEMORY_LIMIT_ON) $GLOBALS['_startUseMems'] = memory_get_usage();

//TP版本
const THINK_VERSION     =   '3.2.3';

//URL模式
const URL_COMMON        =   0;  //
const URL_PATHINFO      =   1;  //
const URL_REWRITE       =   2;  //
const URL_COMPAT        =   3;  //

// 后缀
const EXT               =   '.class.php';

//判断是否定义一些常量
defined('THINK_PATH')   or define('THINK_PATH',     __DIR__.'/');
defined('APP_PATH')     or define('APP_PATH',       dirname($_SERVER['SCRIPT_FILENAME']).'/');
defined('APP_STATUS')   or define('APP_STATUS',     ''); //APP状态
defined('APP_DEBUG')    or define('APP_DEBUG',      false); //APP调试状态

if(function_exists('saeAutoLoader')){//是否存在sae自动加载方法
    defined('APP_MODE')     or define('APP_MODE',      'sae');
    defined('STORAGE_TYPE') or define('STORAGE_TYPE',  'Sae');
}else{
    defined('APP_MODE')     or define('APP_MODE',       'common'); //APP模式是普通模式
    defined('STORAGE_TYPE') or define('STORAGE_TYPE',   'File'); //存储方式是文件存储
}

defined('RUNTIME_PATH') or define('RUNTIME_PATH',   APP_PATH.'Runtime/');   //应用运行时的目录
defined('LIB_PATH')     or define('LIB_PATH',       realpath(THINK_PATH.'Library').'/'); //系统类库目录
defined('CORE_PATH')    or define('CORE_PATH',      LIB_PATH.'Think/'); //核心目录
defined('BEHAVIOR_PATH')or define('BEHAVIOR_PATH',  LIB_PATH.'Behavior/'); //行为目录
defined('MODE_PATH')    or define('MODE_PATH',      THINK_PATH.'Mode/'); //应用模式目录
defined('VENDOR_PATH')  or define('VENDOR_PATH',    LIB_PATH.'Vendor/'); //第三方拓展目录
defined('COMMON_PATH')  or define('COMMON_PATH',    APP_PATH.'Common/'); //公共模块目录
defined('CONF_PATH')    or define('CONF_PATH',      COMMON_PATH.'Conf/'); //公共配置地址
defined('LANG_PATH')    or define('LANG_PATH',      COMMON_PATH.'Lang/'); //公共语言包地址
defined('HTML_PATH')    or define('HTML_PATH',      APP_PATH.'Html/'); //静态缓存地址
defined('LOG_PATH')     or define('LOG_PATH',       RUNTIME_PATH.'Logs/'); //运行时日志
defined('TEMP_PATH')    or define('TEMP_PATH',      RUNTIME_PATH.'Temp/'); //缓存目录
defined('DATA_PATH')    or define('DATA_PATH',      RUNTIME_PATH.'Data/'); //应用数据目录
defined('CACHE_PATH')   or define('CACHE_PATH',     RUNTIME_PATH.'Cache/'); //项目模版缓存
defined('CONF_EXT')     or define('CONF_EXT',       '.php'); //配置后缀
defined('CONF_PARSE')   or define('CONF_PARSE',     '');    //配置文件解析方法
defined('ADDON_PATH')   or define('ADDON_PATH',     APP_PATH.'Addon');

// 判断php版本是否小于5.4
if(version_compare(PHP_VERSION,'5.4.0','<')) {
    ini_set('magic_quotes_runtime',0);
    define('MAGIC_QUOTES_GPC',get_magic_quotes_gpc()? true : false);
}else{
    define('MAGIC_QUOTES_GPC',false);
}
define('IS_CGI',(0 === strpos(PHP_SAPI,'cgi') || false !== strpos(PHP_SAPI,'fcgi')) ? 1 : 0 );
define('IS_WIN',strstr(PHP_OS, 'WIN') ? 1 : 0 );
define('IS_CLI',PHP_SAPI=='cli'? 1   :   0);

if(!IS_CLI) {
    //如果没有定义请求的PHP文件，则设置
    if(!defined('_PHP_FILE_')) {
        if(IS_CGI) {
            //CGI模式下的定义
            $_temp  = explode('.php',$_SERVER['PHP_SELF']);
            define('_PHP_FILE_',    rtrim(str_replace($_SERVER['HTTP_HOST'],'',$_temp[0].'.php'),'/'));
        }else {
            define('_PHP_FILE_',    rtrim($_SERVER['SCRIPT_NAME'],'/'));
        }
    }
    if(!defined('__ROOT__')) {
        $_root  =   rtrim(dirname(_PHP_FILE_),'/');
        define('__ROOT__',  (($_root=='/' || $_root=='\\')?'':$_root));
    }
}

//加载Think核心
require CORE_PATH.'Think'.EXT;
//应用开始
Think\Think::start();