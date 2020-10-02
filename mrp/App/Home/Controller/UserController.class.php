<?php
namespace Home\Controller;
use Think\Controller;
class UserController extends CommonController {
   
    public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
	
  
   public function _befor_add(){
     $list=orgcateTree($pid=0,$level=0,$type=0);
     $this->assign('list',$list);
  }
  
  public function _befor_insert($data){
	 $password=md5(md5(I('pwd')));
	 $data['password']=$password;
	 unset($data['pwd']);
	 return $data;
  }
  
  
  
  public function _befor_edit(){
     $list=orgcateTree($pid=0,$level=0,$type=0);
     $this->assign('list',$list);
  }
  
    public function _befor_update($data){
	 if (strlen(I('pwd'))!==32){
	 $password=md5(md5(I('pwd')));
	 $data['password']=$password;
	 }
	 unset($data['pwd']);
	 return $data;
  }

   
   public function editrule(){
	$uid=I('get.id');
	//if ($uid<>session('uid')){
	M('auth_group_access')->where('uid='.$uid.'')->delete(); 
	$gcdata['uid']=$uid;
	$gcdata['group_id']=M('auth_group')->where(array("title"=>I('get.depname')))->getField('id');
	M('auth_group_access')->data($gcdata)->add();
	$gcdata['group_id']=M('auth_group')->where(array("title"=>I('get.posname')))->getField('id');
	M('auth_group_access')->data($gcdata)->add();
	$this->mtReturn(200,"设置成功".$id,$_REQUEST['navTabId'],false); 
	//}
  }

   public function _befor_del($id){
	  $uid=$id; 
	  M('auth_group_access')->where('uid='.$uid.'')->delete(); 
   }
	
}