<?php

/**
 *      任务管理控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class MytaskController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = "task";
    }
	
   function _filter(&$map) {
	    if(!in_array(session('uid'),C('ADMINISTRATOR'))){
		$map['juid'] = array('like','%'.session("uid").'%');
		}

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
	 $data['addtime']=date("Y-m-d H:i:s",time());
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
	 $data['wancheng']=I("wancheng1")."\r\n".session("truename").":".I('wancheng').date("Y-m-d H:i:s",time());
	 unset($data['wancheng1']);
	 return $data;
  }
  
    public function _after_edit($data){
     
   }

   public function _befor_del($id){
	  
   }

   public function outxls() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$list = $model->where($map)->field('id,juname,kssj,jssj,title,zhuangtai,uname,addtime,uuname')->select();
	    $headArr=array('ID','接收人','开始时间','结束时间','任务标题','状态','发布人','发布时间','更新人');
	    $filename='任务管理';
		$this->xlsout($filename,$headArr,$list);
	}

}