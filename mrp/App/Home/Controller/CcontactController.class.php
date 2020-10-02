<?php
namespace Home\Controller;
use Think\Controller;
class CcontactController extends CommonController {
   
    public function _initialize() {
        parent::_initialize();
        $this->dbname = 'user';
    }
	
	function _filter(&$map) {
		if(IS_POST&&isset($_REQUEST['filter']) && $_REQUEST['filter'] != ''){
		 $map['depname'] = array('EQ', I('filter'));
		}
	}
	
	 public function _befor_index(){
     $filters=orgcateTree($pid=0,$level=0,$type=0);
     $this->assign('filters',$filters);
  }
  
    public function outxls() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$list = $model->where($map)->field('id,username,truename,sex,depname,posname,tel,phone,email,qq,neixian')->select();
	    $headArr=array("编号","用户名","姓名","性别","部门","职务","电话","手机号码","电子邮箱","QQ","内线/短号");
	    $filename='公司通讯录';
		$this->xlsout($filename,$headArr,$list);
	}
	
}