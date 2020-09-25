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
 * ThinkPHP 视图类
 */
class View {
    /**
     *
     * @var tVar
     * @access protected
     */ 
    protected $tVar     =   array();

    /**
     *
     * @var theme
     * @access protected
     */ 
    protected $theme    =   '';

    /**
     *
     * @access public
     * @param mixed $name
     * @param mixed $value
     */
    public function assign($name,$value=''){
        if(is_array($name)) {
            $this->tVar   =  array_merge($this->tVar,$name);
        }else {
            $this->tVar[$name] = $value;
        }
    }

    /**
     *
     * @access public
     * @param string $name
     * @return mixed
     */
    public function get($name=''){
        if('' === $name) {
            return $this->tVar;
        }
        return isset($this->tVar[$name])?$this->tVar[$name]:false;
    }

    /**
     *
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $charset 模板输出字符集
     * @param string $contentType 输出类型
     * @param string $content 模板输出内容
     * @param string $prefix 模板缓存前缀
     * @return mixed
     */
    public function display($templateFile='',$charset='',$contentType='',$content='',$prefix='') {
        G('viewStartTime');
        //
        Hook::listen('view_begin',$templateFile);
        //
        $content = $this->fetch($templateFile,$content,$prefix);
        //
        $this->render($content,$charset,$contentType);
        //
        Hook::listen('view_end');
    }

    /**
     *
     * @access private
     * @param string $content 输出内容
     * @param string $charset 模板输出字符集
     * @param string $contentType 输出类型
     * @return mixed
     */
    private function render($content,$charset='',$contentType=''){
        if(empty($charset))  $charset = C('DEFAULT_CHARSET');
        if(empty($contentType)) $contentType = C('TMPL_CONTENT_TYPE');
        //
        header('Content-Type:'.$contentType.'; charset='.$charset);
        header('Cache-control: '.C('HTTP_CACHE_CONTROL'));  // 页面缓存控制
        header('X-Powered-By:ThinkPHP');
        //
        echo $content;
    }

    /**
     *
     * @access public
     * @param string $templateFile 模板文件名
     * @param string $content 模板输出内容
     * @param string $prefix 模板缓存前缀
     * @return string
     */
    public function fetch($templateFile='',$content='',$prefix='') {
        if(empty($content)) {
            $templateFile   =   $this->parseTemplate($templateFile);
            //
            if(!is_file($templateFile)) E(L('_TEMPLATE_NOT_EXIST_').':'.$templateFile);
        }else{
            defined('THEME_PATH') or    define('THEME_PATH', $this->getThemePath());
        }
        //
        ob_start();
        ob_implicit_flush(0);
        if('php' == strtolower(C('TMPL_ENGINE_TYPE'))) { //
            $_content   =   $content;
            //
            extract($this->tVar, EXTR_OVERWRITE);
            //
            empty($_content)?include $templateFile:eval('?>'.$_content);
        }else{
            //
            $params = array('var'=>$this->tVar,'file'=>$templateFile,'content'=>$content,'prefix'=>$prefix);
            Hook::listen('view_parse',$params);
        }
        //
        $content = ob_get_clean();
        //
        Hook::listen('view_filter',$content);
        //
        return $content;
    }

    /**
     *
     * @access protected
     * @param string $template 模板文件规则
     * @return string
     */
    public function parseTemplate($template='') {
        if(is_file($template)) {
            return $template;
        }
        $depr       =   C('TMPL_FILE_DEPR');
        $template   =   str_replace(':', $depr, $template);

        //
        $module   =  MODULE_NAME;
        if(strpos($template,'@')){ //
            list($module,$template)  =   explode('@',$template);
        }
        //
        defined('THEME_PATH') or    define('THEME_PATH', $this->getThemePath($module));

        //
        if('' == $template) {
            //
            $template = CONTROLLER_NAME . $depr . ACTION_NAME;
        }elseif(false === strpos($template, $depr)){
            $template = CONTROLLER_NAME . $depr . $template;
        }
        $file   =   THEME_PATH.$template.C('TMPL_TEMPLATE_SUFFIX');
        if(C('TMPL_LOAD_DEFAULTTHEME') && THEME_NAME != C('DEFAULT_THEME') && !is_file($file)){
            //
            $file   =   dirname(THEME_PATH).'/'.C('DEFAULT_THEME').'/'.$template.C('TMPL_TEMPLATE_SUFFIX');
        }
        return $file;
    }

    /**
     *
     * @access protected
     * @param  string $module 模块名
     * @return string
     */
    protected function getThemePath($module=MODULE_NAME){
        //
        $theme = $this->getTemplateTheme();
        //
        $tmplPath   =   C('VIEW_PATH'); //
        if(!$tmplPath){ 
            //
            $tmplPath   =   defined('TMPL_PATH')? TMPL_PATH.$module.'/' : APP_PATH.$module.'/'.C('DEFAULT_V_LAYER').'/';
        }
        return $tmplPath.$theme;
    }

    /**
     *
     * @access public
     * @param  mixed $theme 主题名称
     * @return View
     */
    public function theme($theme){
        $this->theme = $theme;
        return $this;
    }

    /**
     *
     * @access private
     * @return string
     */
    private function getTemplateTheme() {
        if($this->theme) { //
            $theme = $this->theme;
        }else{
            /*  */
            $theme =  C('DEFAULT_THEME');
            if(C('TMPL_DETECT_THEME')) {//
                $t = C('VAR_TEMPLATE');
                if (isset($_GET[$t])){
                    $theme = $_GET[$t];
                }elseif(cookie('think_template')){
                    $theme = cookie('think_template');
                }
                if(!in_array($theme,explode(',',C('THEME_LIST')))){
                    $theme =  C('DEFAULT_THEME');
                }
                cookie('think_template',$theme,864000);
            }
        }
        defined('THEME_NAME') || define('THEME_NAME',   $theme);                  //
        return $theme?$theme . '/':'';
    }

}