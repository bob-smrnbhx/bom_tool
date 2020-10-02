<?php

/**
 *      合同管理控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Home\Controller;
use Think\Controller;

class HetongController extends CommonController{

   public function _initialize() {
        parent::_initialize();
        $this->dbname = CONTROLLER_NAME;
    }
	
   function _filter(&$map) {
	   if(!in_array(session('uid'),C('ADMINISTRATOR'))){
		 $map[]=array("uid"=>array('EQ', session("uid")),"juid"=>array('like','%'.session("uid").'%'),"_logic"=>"or");
	   }
	   
	   if(IS_POST&&isset($_REQUEST['time1']) && $_REQUEST['time1'] != ''&&isset($_REQUEST['time2']) && $_REQUEST['time2'] != ''){
		 $map['addtime'] =array(array('egt',I('time1')),array('elt',I('time2'))) ;
		}

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
	$data['addm']=date("Y-m",time());
	$data['weishou']=I('jine');
	return $data;
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
		$list = $model->where($map)->field('id,title,addtime,jcname,yikai,weishou,jine,yishou,fukuan,name,uname,dqrq,updatetime')->select();
	    $headArr=array('ID','合同名称','签约日期','客户名称','已开票','未收款','合同金额','已收款','已付款','业务员','添加人','到期日期','更新时间');
	    $filename='合同管理';
		$this->xlsout($filename,$headArr,$list);
	}
	
	public function daoqi() {
		$model = D($this->dbname);
		$map = $this->_search();
		if (method_exists($this, '_filter')) {
			$this->_filter($map);
		}
		$map['dqrq']  =  array(array('egt',date("Y-m-d",strtotime("-2 month"))),array('elt',date("Y-m-d",strtotime("+1 month"))));
		$list = $model->where($map)->select();
	    $this->assign('list', $list);
		$this->display("index");
	}
	
	public function fenxi(){
	 $this->display();
	}
	


public function name(){
	$user=I('get.user');
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('jine');
			}else{
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('jine');
			}
			$count=$count.",".$co;
		}
    $title = $user.date("Y",time()).'年合同总金额'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}

	public function names(){
	$user=I('get.user');
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('yishou');
			}else{
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('yishou');
			}
			$count=$count.",".$co;
		}
    $title = $user.date("Y",time()).'年合同已收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function namew(){
	$user=I('get.user');
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('weishou');
			}else{
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('weishou');
			}
			$count=$count.",".$co;
		}
    $title = $user.date("Y",time()).'年合同未收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function namef(){
	$user=I('get.user');
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('fukuan');
			}else{
			$co =M($this->dbname)->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('fukuan');
			}
			$count=$count.",".$co;
		}
    $title = $user.date("Y",time()).'年合同已付款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
public function jinnian(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-0".$i))->SUM('jine');
			}else{
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-".$i))->SUM('jine');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",time()).'年合同金额'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function jinnians(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-0".$i))->SUM('yishou');
			}else{
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-".$i))->SUM('yishou');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",time()).'年合同已收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function jinnianw(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-0".$i))->SUM('weishou');
			}else{
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-".$i))->SUM('weishou');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",time()).'年合同未收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function jinnianf(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-0".$i))->SUM('fukuan');
			}else{
			$co =M($this->dbname)->where(array('addm'=>date("Y",time())."-".$i))->SUM('fukuan');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",time()).'年合同已付款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}

public function qunian(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('jine');
			}else{
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('jine');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",strtotime("-1 year")).'年合同金额'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}

	public function qunians(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('yishou');
			}else{
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('yishou');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",strtotime("-1 year")).'年合同已收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function qunianw(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('weishou');
			}else{
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('weishou');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",strtotime("-1 year")).'年合同未收款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}
	
	public function qunianf(){
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('fukuan');
			}else{
			$co = M($this->dbname)->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('fukuan');
			}
			$count=$count.",".$co;
		}
    $title = date("Y",strtotime("-1 year")).'年合同已付款'; 
    $data = explode(",", substr ($count, 1)); 
    $size = 140; 
    $width = 750; 
    $height = 300; 
    $legend = explode(",", substr ($info, 1));
    ob_end_clean();
    $chart->createcolumnar($title,$data,$size,$height,$width,$legend);
	}



}