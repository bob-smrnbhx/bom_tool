<?php

/**
 *      员工档案控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class HrController extends CommonController{

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
	$data['birthday']=substr(I('shengri'),5,2);
	 return $data;
  }
  
  public function _befor_edit(){
     $model = D($this->dbname);
	 $info = $model->find(I('get.id'));
	 $attid=$info['attid'];
	 $this->assign('attid',$attid);
  }
   
  public function _befor_update($data){
    $data['birthday']=substr(I('shengri'),5,2);
	 return $data;
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
		$list = $model->where($map)->field('id,xingming,zhiwei,bumen,gonghao,sex,shengri,type,ruzhi,zaizhi,zhuanye,xueli')->select();
	    $headArr=array('ID','姓名','职位','部门','工号','性别','生日','员工类型','入职时间','在职状态','专业','学历');
	    $filename='员工档案';
		$this->xlsout($filename,$headArr,$list);
	}
	
	public function birthday() {
		$model = D($this->dbname);
		$list = $model->where("birthday=".date("m",time())." or birthday=".date("m",strtotime("+1 month")))->select();
	    $this->assign('list', $list);
		$this->display("index");
	}
	
	
	public function fenxi(){
	 $this->display();
	}
	
	 public function sex(){
$this->_fenxi('sex','性别',1);
}

 public function bumen(){
$this->_fenxi('bumen','部门',2);
}

 public function hunyin(){
$this->_fenxi('hunyin','婚姻状况',4);
}

 public function zhengzhi(){
$this->_fenxi('zhengzhi','政治面貌',1);
}

 public function hukou(){
$this->_fenxi('hukou','户口类别',1);
}

 public function type(){
$this->_fenxi('type','员工类型',4);
}

 public function zaizhi(){
$this->_fenxi('zaizhi','在职状态',1);
}

 public function zhuanye(){
$this->_fenxi('zhuanye','专业',2);
}

 public function xuewei(){
$this->_fenxi('xuewei','学位',2);
}

 public function xueli(){
$this->_fenxi('xueli','学历',2);
}



}