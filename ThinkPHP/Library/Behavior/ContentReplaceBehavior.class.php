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
/**
 *
 */
class ContentReplaceBehavior {

    //
    public function run(&$content){
        $content = $this->templateContentReplace($content);
    }

    /**
     *
     * @access protected
     * @param string $content 模板内容
     * @return string
     */
    protected function templateContentReplace($content) {
        //
        $replace =  array(
            '__ROOT__'      =>  __ROOT__,       // 当前网站地址
            '__APP__'       =>  __APP__,        // 当前应用地址
            '__MODULE__'    =>  __MODULE__,
            '__ACTION__'    =>  __ACTION__,     // 当前操作地址
            '__SELF__'      =>  htmlentities(__SELF__),       // 当前页面地址
            '__CONTROLLER__'=>  __CONTROLLER__,
            '__URL__'       =>  __CONTROLLER__,
            '__PUBLIC__'    =>  __ROOT__.'/Public',// 站点公共目录
        );
        //
        if(is_array(C('TMPL_PARSE_STRING')) )
            $replace =  array_merge($replace,C('TMPL_PARSE_STRING'));
        $content = str_replace(array_keys($replace),array_values($replace),$content);
        return $content;
    }

}