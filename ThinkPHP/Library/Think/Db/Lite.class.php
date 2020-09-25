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

namespace Think\Db;
use Think\Config;
use Think\Debug;
use Think\Log;
use PDO;

class Lite {
    //
    protected $PDOStatement = null;
    //
    protected $model      = '_think_';
    //
    protected $queryStr   = '';
    protected $modelSql   = array();
    //
    protected $lastInsID  = null;
    //
    protected $numRows    = 0;
    //
    protected $transTimes = 0;
    //
    protected $error      = '';
    //
    protected $linkID     = array();
    //
    protected $_linkID    = null;
    //
    protected $config     = array(
        'type'              =>  '',     //
        'hostname'          =>  '127.0.0.1', //
        'database'          =>  '',          //
        'username'          =>  '',      //
        'password'          =>  '',          //
        'hostport'          =>  '',        //
        'dsn'               =>  '', //          
        'params'            =>  array(), //
        'charset'           =>  'utf8',      //
        'prefix'            =>  '',    //
        'debug'             =>  false, //
        'deploy'            =>  0, //
        'rw_separate'       =>  false,       //
        'master_num'        =>  1, //
        'slave_no'          =>  '', //
    );
    //
    protected $comparison = array('eq'=>'=','neq'=>'<>','gt'=>'>','egt'=>'>=','lt'=>'<','elt'=>'<=','notlike'=>'NOT LIKE','like'=>'LIKE','in'=>'IN','notin'=>'NOT IN');
    //
    protected $selectSql  = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%COMMENT%';
    //
    protected $queryTimes   =   0;
    //
    protected $executeTimes =   0;
    //
    protected $options = array(
        PDO::ATTR_CASE              =>  PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           =>  PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      =>  PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES =>  false,
    );

    /**
     *
     * @access public
     * @param array $config 数据库配置数组
     */
    public function __construct($config=''){
        if(!empty($config)) {
            $this->config           =   array_merge($this->config,$config);
            if(is_array($this->config['params'])){
                $this->options  +=   $this->config['params'];
            }
        }
    }

    /**
     *
     * @access public
     */
    public function connect($config='',$linkNum=0) {
        if ( !isset($this->linkID[$linkNum]) ) {
            if(empty($config))  $config =   $this->config;
            try{
                if(empty($config['dsn'])) {
                    $config['dsn']  =   $this->parseDsn($config);
                }
                if(version_compare(PHP_VERSION,'5.3.6','<=')){ //禁用模拟预处理语句
                    $this->options[PDO::ATTR_EMULATE_PREPARES]  =   false;
                }
                $this->linkID[$linkNum] = new PDO( $config['dsn'], $config['username'], $config['password'],$this->options);
            }catch (\PDOException $e) {
                E($e->getMessage());
            }
        }
        return $this->linkID[$linkNum];
    }

    /**
     *
     * @access public
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn($config){}

    /**
     *
     * @access public
     */
    public function free() {
        $this->PDOStatement = null;
    }

    /**
     *
     * @access public
     * @param string $str  sql指令
     * @param array $bind  参数绑定
     * @return mixed
     */
    public function query($str,$bind=array()) {
        $this->initConnect(false);
        if ( !$this->_linkID ) return false;
        $this->queryStr     =   $str;
        if(!empty($bind)){
            $that   =   $this;
            $this->queryStr =   strtr($this->queryStr,array_map(function($val) use($that){ return '\''.$that->escapeString($val).'\''; },$bind));
        }
        //
        if ( !empty($this->PDOStatement) ) $this->free();
        $this->queryTimes++;
        N('db_query',1); // 兼容代码        
        //
        $this->debug(true);
        $this->PDOStatement = $this->_linkID->prepare($str);
        if(false === $this->PDOStatement)
            E($this->error());
        foreach ($bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $result =   $this->PDOStatement->execute();
        // 调试结束
        $this->debug(false);
        if ( false === $result ) {
            $this->error();
            return false;
        } else {
            return $this->getResult();
        }
    }

    /**
     *
     * @access public
     * @param string $str  sql指令
     * @param array $bind  参数绑定
     * @return integer
     */
    public function execute($str,$bind=array()) {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        $this->queryStr = $str;
        if(!empty($bind)){
            $that   =   $this;
            $this->queryStr =   strtr($this->queryStr,array_map(function($val) use($that){ return '\''.$that->escapeString($val).'\''; },$bind));
        }      
        //
        if ( !empty($this->PDOStatement) ) $this->free();
        $this->executeTimes++;
        N('db_write',1); // 兼容代码        
        //
        $this->debug(true);
        $this->PDOStatement =   $this->_linkID->prepare($str);
        if(false === $this->PDOStatement) {
            E($this->error());
        }
        foreach ($bind as $key => $val) {
            if(is_array($val)){
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            }else{
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $result =   $this->PDOStatement->execute();
        $this->debug(false);
        if ( false === $result) {
            $this->error();
            return false;
        } else {
            $this->numRows = $this->PDOStatement->rowCount();
            if(preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                $this->lastInsID = $this->_linkID->lastInsertId();
            }
            return $this->numRows;
        }
    }

    /**
     *
     * @access public
     * @return void
     */
    public function startTrans() {
        $this->initConnect(true);
        if ( !$this->_linkID ) return false;
        //
        if ($this->transTimes == 0) {
            $this->_linkID->beginTransaction();
        }
        $this->transTimes++;
        return ;
    }

    /**
     *
     * @access public
     * @return boolean
     */
    public function commit() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->commit();
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @access public
     * @return boolean
     */
    public function rollback() {
        if ($this->transTimes > 0) {
            $result = $this->_linkID->rollback();
            $this->transTimes = 0;
            if(!$result){
                $this->error();
                return false;
            }
        }
        return true;
    }

    /**
     *
     * @access private
     * @return array
     */
    private function getResult() {
        //
        $result =   $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->numRows = count( $result );
        return $result;
    }

    /**
     *
     * @access public
     * @param boolean $execute 是否包含所有查询
     * @return integer
     */
    public function getQueryTimes($execute=false){
        return $execute?$this->queryTimes+$this->executeTimes:$this->queryTimes;
    }

    /**
     *
     * @access public
     * @return integer
     */
    public function getExecuteTimes(){
        return $this->executeTimes;
    }

    /**
     *
     * @access public
     */
    public function close() {
        $this->_linkID = null;
    }

    /**
     *
     *
     * @access public
     * @return string
     */
    public function error() {
        if($this->PDOStatement) {
            $error = $this->PDOStatement->errorInfo();
            $this->error = $error[1].':'.$error[2];
        }else{
            $this->error = '';
        }
        if('' != $this->queryStr){
            $this->error .= "\n [ SQL语句 ] : ".$this->queryStr;
        }
        //
        trace($this->error,'','ERR');
        if($this->config['debug']) {//
            E($this->error);
        }else{
            return $this->error;
        }
    }

    /**
     *
     * @param string $model  模型名
     * @access public
     * @return string
     */
    public function getLastSql($model='') {
        return $model?$this->modelSql[$model]:$this->queryStr;
    }

    /**
     *
     * @access public
     * @return string
     */
    public function getLastInsID() {
        return $this->lastInsID;
    }

    /**
     *
     * @access public
     * @return string
     */
    public function getError() {
        return $this->error;
    }

    /**
     *
     * @access public
     * @param string $str  SQL字符串
     * @return string
     */
    public function escapeString($str) {
        return addslashes($str);
    }

    /**
     *
     * @access public
     * @param string $model  模型名
     * @return void
     */
    public function setModel($model){
        $this->model =  $model;
    }

    /**
     *
     * @access protected
     * @param boolean $start  调试开始标记 true 开始 false 结束
     */
    protected function debug($start) {
        if($this->config['debug']) {//
            if($start) {
                G('queryStartTime');
            }else{
                $this->modelSql[$this->model]   =  $this->queryStr;
                //$this->model  =   '_think_';
                //
                G('queryEndTime');
                trace($this->queryStr.' [ RunTime:'.G('queryStartTime','queryEndTime').'s ]','','SQL');
            }
        }
    }

    /**
     *
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function initConnect($master=true) {
        if(!empty($this->config['deploy']))
            //
            $this->_linkID = $this->multiConnect($master);
        else
            //
            if ( !$this->_linkID ) $this->_linkID = $this->connect();
    }

    /**
     *
     * @access protected
     * @param boolean $master 主服务器
     * @return void
     */
    protected function multiConnect($master=false) {
        //
        $_config['username']    =   explode(',',$this->config['username']);
        $_config['password']    =   explode(',',$this->config['password']);
        $_config['hostname']    =   explode(',',$this->config['hostname']);
        $_config['hostport']    =   explode(',',$this->config['hostport']);
        $_config['database']    =   explode(',',$this->config['database']);
        $_config['dsn']         =   explode(',',$this->config['dsn']);
        $_config['charset']     =   explode(',',$this->config['charset']);

        //
        if($this->config['rw_separate']){
            //
            if($master)
                //
                $r  =   floor(mt_rand(0,$this->config['master_num']-1));
            else{
                if(is_numeric($this->config['slave_no'])) {//
                    $r = $this->config['slave_no'];
                }else{
                    //
                    $r = floor(mt_rand($this->config['master_num'],count($_config['hostname'])-1));   //
                }
            }
        }else{
            //
            $r = floor(mt_rand(0,count($_config['hostname'])-1));   //
        }
        $db_config = array(
            'username'  =>  isset($_config['username'][$r])?$_config['username'][$r]:$_config['username'][0],
            'password'  =>  isset($_config['password'][$r])?$_config['password'][$r]:$_config['password'][0],
            'hostname'  =>  isset($_config['hostname'][$r])?$_config['hostname'][$r]:$_config['hostname'][0],
            'hostport'  =>  isset($_config['hostport'][$r])?$_config['hostport'][$r]:$_config['hostport'][0],
            'database'  =>  isset($_config['database'][$r])?$_config['database'][$r]:$_config['database'][0],
            'dsn'       =>  isset($_config['dsn'][$r])?$_config['dsn'][$r]:$_config['dsn'][0],
            'charset'   =>  isset($_config['charset'][$r])?$_config['charset'][$r]:$_config['charset'][0],
        );
        return $this->connect($db_config,$r);
    }

   /**
     *
     * @access public
     */
    public function __destruct() {
        //
        if ($this->PDOStatement){
            $this->free();
        }
        //
        $this->close();
    }
}