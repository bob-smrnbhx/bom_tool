<?php

/**
 *      我的去向控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class CstyleController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
   function _filter(&$map) {
		$map['uid'] = array('EQ', session("uid"));

	}
	
   public function _befor_index(){ 
   
   }
  
  
  public function _befor_add(){
	  $attid=time();
	  $this->assign('attid',$attid);
	     
  }
	
   public function _after_add($data){
    
   }

  public function _befor_insert($data){
	 $data['uid']=session("uid");
	 $data['uname']=session("truename");
	 $data['bumen']=session("depname");
	 $data['addtime']=date("Y-m-d H:i:s",time());
	 	 $data['xxsm']=I('post.xxsm');
	 $prize_arr = array( '0' => array('id'=>1,'min'=>5,'max'=>25,'prize'=>'一等奖','v'=>0,'sl'=>'0'), 
    '1' => array('id'=>2,'min'=>305,'max'=>325,'prize'=>'二等奖','v'=>0,'sl'=>0), 
    '2' => array('id'=>3,'min'=>245,'max'=>265,'prize'=>'三等奖','v'=>0,'sl'=>0), 
    '3' => array('id'=>4,'min'=>185,'max'=>205,'prize'=>'四等奖','v'=>0,'sl'=>0), 
    '4' => array('id'=>5,'min'=>125,'max'=>145,'prize'=>'五等奖','v'=>0,'sl'=>0), 
    '5' => array('id'=>6,'min'=>65,'max'=>85,'prize'=>'六等奖','v'=>0,'sl'=>0), 
    '6' => array('id'=>7,'min'=>array(35,95,155,215,275,335),'max'=>array(55,115,175,235,295,355),'prize'=>'没中奖','v'=>100000,'sl'=>'1000') ); 
 $jpname=I('post.jpname');
$jpgl=I('post.jpgl');
$jpsl=I('post.jpsl');
foreach($prize_arr as $key=> $c){
	 if($jpname[$key]){
		 $prize_arr[$key]['prize']=$jpname[$key];
		 $prize_arr[$key]['v']=$jpgl[$key];
		 $prize_arr[$key]['sl']=$jpsl[$key];
		 }
	 }
$data['jp']=serialize($prize_arr);
	 return $data;
  }
  
  public function _befor_edit(){
     $model = D($this->dbname);
	 $info = $model->find(I('get.id'));
	 $attid=$info['attid'];
	 $this->assign('attid',$attid);
  }
   
  public function _befor_update($data){
	 $data['uuid']=session("uid");
	 $data['uuname']=session("truename");
	 $data['updatetime']=date("Y-m-d H:i:s",time());
	 $data['xxsm']=I('post.xxsm');
$prize_arr = $prize_arr = array( '0' => array('id'=>1,'min'=>5,'max'=>25,'prize'=>'一等奖','v'=>0,'sl'=>'0'), 
    '1' => array('id'=>2,'min'=>305,'max'=>325,'prize'=>'二等奖','v'=>0,'sl'=>0), 
    '2' => array('id'=>3,'min'=>245,'max'=>265,'prize'=>'三等奖','v'=>0,'sl'=>0), 
    '3' => array('id'=>4,'min'=>185,'max'=>205,'prize'=>'四等奖','v'=>0,'sl'=>0), 
    '4' => array('id'=>5,'min'=>125,'max'=>145,'prize'=>'五等奖','v'=>0,'sl'=>0), 
    '5' => array('id'=>6,'min'=>65,'max'=>85,'prize'=>'六等奖','v'=>0,'sl'=>0), 
    '6' => array('id'=>7,'min'=>array(35,95,155,215,275,335),'max'=>array(55,115,175,235,295,355),'prize'=>'没中奖','v'=>100000,'sl'=>'1000') ); 
$jpname=I('post.jpname');
$jpgl=I('post.jpgl');
$jpsl=I('post.jpsl');
foreach($prize_arr as $key=> $c){
	 if($jpname[$key]){
		 $prize_arr[$key]['prize']=$jpname[$key];
		 $prize_arr[$key]['v']=$jpgl[$key];
		 $prize_arr[$key]['sl']=$jpsl[$key];
		 }
	 }
$data['jp']=serialize($prize_arr);
	 return $data;
  }
  
    public function _after_edit($data){
     
   }
  public function _format_edit($vo){
	  $vo['jxsz']=unserialize($vo['jp']) ;
	  return $vo;
	  }
   public function _befor_del($id){
	  
   }

   public function outxls() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$list = $model->where($map)->field('id,kssj,jssj,title,uname,bumen,addtime,updatetime')->select();
	    $headArr=array('ID','开始时间','结束时间','去向说明','外出人','所在部门','添加时间','更新时间');
	    $filename='我的去向';
		$this->xlsout($filename,$headArr,$list);
	}

}