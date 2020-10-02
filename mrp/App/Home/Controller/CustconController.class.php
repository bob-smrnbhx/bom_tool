<?php

/**
 *      联系人控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class CustconController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
   function _filter(&$map) {
	   if(!in_array(session('uid'),C('ADMINISTRATOR'))){
	    $map[]=array("uid"=>array('EQ', session("uid")));
	   }

	}
	
   public function _befor_index(){ 
   
   }
  
  
  public function _befor_add(){
	  if(I("get.cid")!==""){
		$jcid=I("get.cid");
	    $jcname=comname($jcid);
	    $this->assign('jcid',$jcid);
	    $this->assign('jcname',$jcname);  
	  }
	  $attid=time();
	  $this->assign('attid',$attid);
    
  }
	
   public function _after_add($id){
    
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

  }
  
    public function _after_edit($id){
     
   }

   public function _befor_del($id){
	  
   }

   public function outxls() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$list = $model->where($map)->field('id,jcname,xingming,sex,bumen,phone,email,qq,addtime,updatetime')->select();
	    $headArr=array('ID','客户名称','姓名','性别','部门职务','手机号码','EMAIL','QQ','添加时间','更新时间');
	    $filename='联系人';
		$this->xlsout($filename,$headArr,$list);
	}

}