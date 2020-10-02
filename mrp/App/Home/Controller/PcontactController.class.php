<?php

/**
 *      通讯录控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class PcontactController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = "contact";
    }
	
   function _filter(&$map) {
		$map['shuxing'] = array('EQ', "公共");

	}
	
   public function _after_add($data){
    
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
		$list = $model->where($map)->field('id,xingming,sex,danwei,zhiwu,dianhua,phone,email,qq,fenlei,uuname,updatetime')->select();
	    $headArr=array('ID','姓名','性别','单位','职位','固定电话','手机号码','电子邮箱','QQ','分类','更新人员','更新时间');
	    $filename='通讯录';
		$this->xlsout($filename,$headArr,$list);
	}

}