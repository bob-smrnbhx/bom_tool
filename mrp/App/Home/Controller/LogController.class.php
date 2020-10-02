<?php
namespace Home\Controller;
use Think\Controller;

Class LogController extends CommonController{

    public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
	function _filter(&$map) {
		//$map['id'] = array('egt', 2);

	}



  
  public function Del() {
	$jztime=time()-180*24*60*60;
    $jztime=date("Y-m-d H:i:s",$jztime);
    $list = M('log')->where("addtime<'".$jztime."'")->delete();
	$this->mtReturn(200,'清理180天以前的日志记录成功','','',U('index'));
  }
}