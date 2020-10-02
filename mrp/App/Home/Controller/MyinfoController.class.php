<?php

/**
 *      内部邮箱控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class MyinfoController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = "info";
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
	 //$data['addtime']=date("Y-m-d H:i:s",time());
	// return $data;
  }
  
  public function _befor_edit(){
     $model = D($this->dbname);
	 $info = $model->find(I('get.id'));
	 $attid=$info['attid'];
	 $this->assign('attid',$attid);
  }
   
  public function _befor_update($data){
     $data['hui']=I("hui1")."\r\n".session("truename").":".I('hui').date("Y-m-d H:i:s",time());
	 unset($data['hui1']);
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
		$list = $model->where($map)->field('id,juname,title,uname,addtime,uuname,updatetime')->select();
	    $headArr=array('ID','接收人','邮件标题','发件人','发信时间','最后回复','回复时间');
	    $filename='内部邮箱';
		$this->xlsout($filename,$headArr,$list);
	}

}