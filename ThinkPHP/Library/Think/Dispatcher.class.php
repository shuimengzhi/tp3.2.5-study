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
namespace Think;
/**
 *
 *
 */
class Dispatcher {

    /**
     * url处理完调度控制器
     * @access public
     * @return void
     */
    static public function dispatch() {
        $varPath        =   C('VAR_PATHINFO');
        $varAddon       =   C('VAR_ADDON');
        $varModule      =   C('VAR_MODULE');
        $varController  =   C('VAR_CONTROLLER');
        $varAction      =   C('VAR_ACTION');
        $urlCase        =   C('URL_CASE_INSENSITIVE');
        if(isset($_GET[$varPath])) { // 兼容模式将URL值传给PATH_INFO
            $_SERVER['PATH_INFO'] = $_GET[$varPath];
            unset($_GET[$varPath]);
        }elseif(IS_CLI){ // cli模式将值传给PATH_INFO
            $_SERVER['PATH_INFO'] = isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
        }

        // 开启子域名部署则执行
        if(C('APP_SUB_DOMAIN_DEPLOY')) {
            $rules      = C('APP_SUB_DOMAIN_RULES');
            if(isset($rules[$_SERVER['HTTP_HOST']])) { // 如果设置了域名
                define('APP_DOMAIN',$_SERVER['HTTP_HOST']); // 定义当前域名
                $rule = $rules[APP_DOMAIN];
            }else{
                if(strpos(C('APP_DOMAIN_SUFFIX'),'.')){ // com.cn net.cn 
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -3);
                }else{
                    $domain = array_slice(explode('.', $_SERVER['HTTP_HOST']), 0, -2);                    
                }
                if(!empty($domain)) {
                    $subDomain = implode('.', $domain);
                    define('SUB_DOMAIN',$subDomain); // 定义子域名
                    $domain2   = array_pop($domain); // 二级域名
                    if($domain) { // 定义三级域名
                        $domain3 = array_pop($domain);
                    }
                    if(isset($rules[$subDomain])) { // 设置子域名
                        $rule = $rules[$subDomain];
                    }elseif(isset($rules['*.' . $domain2]) && !empty($domain3)){ // 泛三级域名
                        $rule = $rules['*.' . $domain2];
                        $panDomain = $domain3;
                    }elseif(isset($rules['*']) && !empty($domain2) && 'www' != $domain2 ){ // 泛二级域名
                        $rule      = $rules['*'];
                        $panDomain = $domain2;
                    }
                }                
            }

            if(!empty($rule)) {
                // 有规则则赋值
                if(is_array($rule)){
                    list($rule,$vars) = $rule;
                }
                $array      =   explode('/',$rule);
                // 模块绑定
                define('BIND_MODULE',array_shift($array));
                // 控制器绑定
                if(!empty($array)) {
                    $controller  =   array_shift($array);
                    if($controller){
                        define('BIND_CONTROLLER',$controller);
                    }
                }
                if(isset($vars)) { // URL字符串参数转为变量
                    parse_str($vars,$parms);
                    if(isset($panDomain)){
                        $pos = array_search('*', $parms);
                        if(false !== $pos) {
                            // 泛域名作为参数
                            $parms[$pos] = $panDomain;
                        }                         
                    }                   
                    $_GET   =  array_merge($_GET,$parms);
                }
            }
        }
        // 分析PATH_INFO
        if(!isset($_SERVER['PATH_INFO'])) {
            $types   =  explode(',',C('URL_PATHINFO_FETCH'));
            foreach ($types as $type){
                if(0===strpos($type,':')) {// 方法来获取PATH_INFO
                    $_SERVER['PATH_INFO'] =   call_user_func(substr($type,1));
                    break;
                }elseif(!empty($_SERVER[$type])) {
                    $_SERVER['PATH_INFO'] = (0 === strpos($_SERVER[$type],$_SERVER['SCRIPT_NAME']))?
                        substr($_SERVER[$type], strlen($_SERVER['SCRIPT_NAME']))   :  $_SERVER[$type];
                    break;
                }
            }
        }

        $depr = C('URL_PATHINFO_DEPR');
        define('MODULE_PATHINFO_DEPR',  $depr);

        if(empty($_SERVER['PATH_INFO'])) {
            $_SERVER['PATH_INFO'] = '';
            define('__INFO__','');
            define('__EXT__','');
        }else{
            define('__INFO__',trim($_SERVER['PATH_INFO'],'/'));
            // URL后缀
            define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'],PATHINFO_EXTENSION)));
            $_SERVER['PATH_INFO'] = __INFO__;     
            if(!defined('BIND_MODULE') && (!C('URL_ROUTER_ON') || !Route::check())){
                if (__INFO__ && C('MULTI_MODULE')){ //设置访问路径和模块
                    $paths      =   explode($depr,__INFO__,2);
                    $allowList  =   C('MODULE_ALLOW_LIST'); // 允许的模块列表
                    $module     =   preg_replace('/\.' . __EXT__ . '$/i', '',$paths[0]);
                    if( empty($allowList) || (is_array($allowList) && in_array_case($module, $allowList))){
                        $_GET[$varModule]       =   $module;
                        $_SERVER['PATH_INFO']   =   isset($paths[1])?$paths[1]:'';
                    }
                }
            }             
        }

        // 设置URL请求
        define('__SELF__',strip_tags($_SERVER[C('URL_REQUEST_URI')]));

        // 定义模块
        define('MODULE_NAME', defined('BIND_MODULE')? BIND_MODULE : self::getModule($varModule));
        
        // 加载各种配置文件
        if( MODULE_NAME && (defined('BIND_MODULE') || !in_array_case(MODULE_NAME,C('MODULE_DENY_LIST')) ) && is_dir(APP_PATH.MODULE_NAME)){
            // 定义模块路径
            define('MODULE_PATH', APP_PATH.MODULE_NAME.'/');
            // 设置缓存路径
            C('CACHE_PATH',CACHE_PATH.MODULE_NAME.'/');
            // 设置日志路径
	        C('LOG_PATH',  realpath(LOG_PATH).'/'.MODULE_NAME.'/');

            // 监听模块检查
            Hook::listen('module_check');

            // 加载模块配置文件
            if(is_file(MODULE_PATH.'Conf/config'.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/config'.CONF_EXT));
            //加载模块别的配置文件
            if('common' != APP_MODE && is_file(MODULE_PATH.'Conf/config_'.APP_MODE.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/config_'.APP_MODE.CONF_EXT));
            //加载模块应用状态配置文件
            if(APP_STATUS && is_file(MODULE_PATH.'Conf/'.APP_STATUS.CONF_EXT))
                C(load_config(MODULE_PATH.'Conf/'.APP_STATUS.CONF_EXT));

            // 加载模块别名
            if(is_file(MODULE_PATH.'Conf/alias.php'))
                Think::addMap(include MODULE_PATH.'Conf/alias.php');
            // 导入模块标签文件
            if(is_file(MODULE_PATH.'Conf/tags.php'))
                Hook::import(include MODULE_PATH.'Conf/tags.php');
            // 加载模块公共方法
            if(is_file(MODULE_PATH.'Common/function.php'))
                include MODULE_PATH.'Common/function.php';
            
            $urlCase        =   C('URL_CASE_INSENSITIVE');
            // 加载模块拓展配置
            load_ext_file(MODULE_PATH);
        }else{
            E(L('_MODULE_NOT_EXIST_').':'.MODULE_NAME);
        }

        if(!defined('__APP__')){
	        $urlMode        =   C('URL_MODEL');
	        if($urlMode == URL_COMPAT ){// 兼容模式判断
	            define('PHP_FILE',_PHP_FILE_.'?'.$varPath.'=');
	        }elseif($urlMode == URL_REWRITE ) {
	            $url    =   dirname(_PHP_FILE_);
	            if($url == '/' || $url == '\\')
	                $url    =   '';
	            define('PHP_FILE',$url);
	        }else {
	            define('PHP_FILE',_PHP_FILE_);
	        }
	        // 应用地址
	        define('__APP__',strip_tags(PHP_FILE));
	    }
        // 定义模块名字变量
        $moduleName    =   defined('MODULE_ALIAS')? MODULE_ALIAS : MODULE_NAME;
        define('__MODULE__',(defined('BIND_MODULE') || !C('MULTI_MODULE'))? __APP__ : __APP__.'/'.($urlCase ? strtolower($moduleName) : $moduleName));

        if('' != $_SERVER['PATH_INFO'] && (!C('URL_ROUTER_ON') ||  !Route::check()) ){   // PATH_INFO不为空，路由通过
            Hook::listen('path_info');
            // 如果是禁止访问的URL后缀则404并退出
            if(C('URL_DENY_SUFFIX') && preg_match('/\.('.trim(C('URL_DENY_SUFFIX'),'.').')$/i', $_SERVER['PATH_INFO'])){
                send_http_status(404);
                exit;
            }
            
            // 去掉URL后缀
            $_SERVER['PATH_INFO'] = preg_replace(C('URL_HTML_SUFFIX')? '/\.('.trim(C('URL_HTML_SUFFIX'),'.').')$/i' : '/\.'.__EXT__.'$/i', '', $_SERVER['PATH_INFO']);

            $depr   =   C('URL_PATHINFO_DEPR');
            $paths  =   explode($depr,trim($_SERVER['PATH_INFO'],$depr));

            if(!defined('BIND_CONTROLLER')) {// 获得控制器
                if(C('CONTROLLER_LEVEL')>1){// 控制器层次大于1
                    $_GET[$varController]   =   implode('/',array_slice($paths,0,C('CONTROLLER_LEVEL')));
                    $paths  =   array_slice($paths, C('CONTROLLER_LEVEL'));
                }else{
                    $_GET[$varController]   =   array_shift($paths);
                }
            }
            // 赋值操作
            if(!defined('BIND_ACTION')){
                $_GET[$varAction]  =   array_shift($paths);
            }
            // 解析剩余的URL参数
            $var  =  array();
            if(C('URL_PARAMS_BIND') && 1 == C('URL_PARAMS_BIND_TYPE')){
                //URL参数按顺序绑定变量
                $var    =   $paths;
            }else{
                preg_replace_callback('/(\w+)\/([^\/]+)/', function($match) use(&$var){$var[$match[1]]=strip_tags($match[2]);}, implode('/',$paths));
            }
            $_GET   =  array_merge($var,$_GET);
        }
        // 定义控制器路径
        define('CONTROLLER_PATH',   self::getSpace($varAddon,$urlCase));
        // 定义控制器名字
        define('CONTROLLER_NAME',   defined('BIND_CONTROLLER')? BIND_CONTROLLER : self::getController($varController,$urlCase));
        define('ACTION_NAME',       defined('BIND_ACTION')? BIND_ACTION : self::getAction($varAction,$urlCase));

        // 定义控制器名字的变量
        $controllerName    =   defined('CONTROLLER_ALIAS')? CONTROLLER_ALIAS : CONTROLLER_NAME;
        define('__CONTROLLER__',__MODULE__.$depr.(defined('BIND_CONTROLLER')? '': ( $urlCase ? parse_name($controllerName) : $controllerName )) );

        // 定义操作
        define('__ACTION__',__CONTROLLER__.$depr.(defined('ACTION_ALIAS')?ACTION_ALIAS:ACTION_NAME));

        // 定义请求
        $_REQUEST = array_merge($_POST,$_GET,$_COOKIE);	// -- 加了$_COOKIE.  保证哦..
    }

    /**
     * 获得命名空间
     */
    static private function getSpace($var,$urlCase) {
        $space  =   !empty($_GET[$var])?strip_tags($_GET[$var]):'';
        unset($_GET[$var]);
        return $space;
    }

    /**
     * 获得控制器
     */
    static private function getController($var,$urlCase) {
        $controller = (!empty($_GET[$var])? $_GET[$var]:C('DEFAULT_CONTROLLER'));
        unset($_GET[$var]);
        if($maps = C('URL_CONTROLLER_MAP')) {
            if(isset($maps[strtolower($controller)])) {
                // 定义控制器别名
                define('CONTROLLER_ALIAS',strtolower($controller));
                // 返回控制器别名
                return   ucfirst($maps[CONTROLLER_ALIAS]);
            }elseif(array_search(strtolower($controller),$maps)){
                // 返回空
                return   '';
            }
        }
        if($urlCase) {
            // 将C风格转换为Java的风格，控制器
            $controller = parse_name($controller,1);
        }
        return strip_tags(ucfirst($controller));
    }

    /**
     * 获得操作
     */
    static private function getAction($var,$urlCase) {
        $action   = !empty($_POST[$var]) ?
            $_POST[$var] :
            (!empty($_GET[$var])?$_GET[$var]:C('DEFAULT_ACTION'));
        unset($_POST[$var],$_GET[$var]);
        if($maps = C('URL_ACTION_MAP')) {
            if(isset($maps[strtolower(CONTROLLER_NAME)])) {
                $maps =   $maps[strtolower(CONTROLLER_NAME)];
                if(isset($maps[strtolower($action)])) {
                    // 定义操作另外的名字
                    define('ACTION_ALIAS',strtolower($action));
                    // 返回操作别名
                    if(is_array($maps[ACTION_ALIAS])){
                        parse_str($maps[ACTION_ALIAS][1],$vars);
                        $_GET   =   array_merge($_GET,$vars);
                        return $maps[ACTION_ALIAS][0];
                    }else{
                        return $maps[ACTION_ALIAS];
                    }
                    
                }elseif(array_search(strtolower($action),$maps)){
                    // 禁止访问原始操作
                    return   '';
                }
            }
        }
        return strip_tags( $urlCase? strtolower($action) : $action );
    }

    /**
     * 获得实际的模块名称
     */
    static private function getModule($var) {
        $module   = (!empty($_GET[$var])?$_GET[$var]:C('DEFAULT_MODULE'));
        unset($_GET[$var]);
        if($maps = C('URL_MODULE_MAP')) {
            if(isset($maps[strtolower($module)])) {
                // 记录当前别名
                define('MODULE_ALIAS',strtolower($module));
                //获取实际的模块名
                return   ucfirst($maps[MODULE_ALIAS]);
            }elseif(array_search(strtolower($module),$maps)){
                //禁止访问原始模块
                return   '';
            }
        }
        return strip_tags(ucfirst($module));
    }

}
