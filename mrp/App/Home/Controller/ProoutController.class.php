<?php

/**
 *      出库记录控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class ProoutController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
   function _filter(&$map) {
	   //if(!in_array(session('uid'),C('ADMINISTRATOR'))){
		//$map['id'] = array('EQ', session("uid"));
	   //}

	}
	
   public function _befor_index(){ 
   
   }
  
  
  public function _befor_add(){
	  $attid=time();
	  $this->assign('attid',$attid);
    
  }
	
   public function _after_add($id){
    
   }

  public function _befor_insert($data){
	 M("pro")->where('id='.I("jpid"))->setInc('chuku',I("shuliang"));
	 M("pro")->where('id='.I("jpid"))->setDec('kucun',I("shuliang"));
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
		$list = $model->where($map)->field('id,juname,jpname,jpjiage,jpdanwei,jpguige,shuliang,title,jhname,uname,addtime')->select();
	    $headArr=array('ID','经办人','产品名称','产品价格','计量单位','规格型号','出库数量','说明','关联合同','操作人','入库时间');
	    $filename='出库记录';
		$this->xlsout($filename,$headArr,$list);
	}
	
	public function fenxi(){
	 $this->display();
	}
	
	

}