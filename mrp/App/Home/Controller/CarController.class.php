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

class CarController extends CommonController{

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

  }
  
  public function _befor_edit(){
     $model = D($this->dbname);
	 $info = $model->find(I('get.id'));
	 $attid=$info['attid'];
	 $this->assign('attid',$attid);
  }
   
  public function _befor_update($data){

  }
  
	public function _after_edit($data){
	 
	}
  public function _format_edit($vo){
		return $vo;
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
	
	// 上传图片插件
	public function upload()
	{
/*		$upload = new \Think\Upload();// 实例化上传类    
		$upload->maxSize   =     3145728 ;// 设置附件上传大小    
		$upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型    
		$upload->savePath  =      './Public/Uploads/'; // 设置附件上传目录    
		// 上传文件     
		$info   =   $upload->upload();    
		if(!$info) {
			// 上传错误提示错误信息        
			$this->error($upload->getError());    
		}else{
			// 上传成功        
			$this->success('上传成功！');    
		}
		*/
		
		$uploaddir = 'Public/images/uploads/';
		$uploadfile = $uploaddir.date("YmdHis").".jpg";
		
		if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
			echo json_encode(array('statusCode'=> 200,'message'=>"上传成功！","filename"=>$uploadfile));
		} else {
			echo json_encode(array('statusCode'=> 300,'message'=>"上传失败！","filename"=>$uploadfile));
		}
	}

}