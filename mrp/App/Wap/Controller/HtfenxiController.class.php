<?php

/**
 *      合同管理控制器
 *      [X-Mis] (C)2007-2099  
 *      This is NOT a freeware, use is subject to license terms
 *      http://www.xinyou88.com
 *      tel:400-000-9981
 *      qq:16129825
 */

namespace Wap\Controller;
use Think\Controller;

class HtfenxiController extends Controller{


	
   function _filter(&$map) {
	   if(!in_array(session('uid'),C('ADMINISTRATOR'))){
	    $map[]=array("uid"=>array('EQ', session("uid")),"juid"=>array('like','%'.session("uid").'%'),"_logic"=>"or");
	   }

	}
	
   public function index(){
	 $this->display();		
	}

	public function lastyear(){
	 $this->display();		
	}
	
	public function yewuyuan(){
	 $this->display();		
	}
	
public function name(){
	$user=I('get.user');
	import("Org.Util.Chart");
    $chart = new \Chart;
	for($i=1;$i<=12;$i++){ 	
			$info=$info.",".$i;
			if($i<10){
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('jine');
			}else{
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('jine');
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
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('yishou');
			}else{
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('yishou');
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
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('weishou');
			}else{
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('weishou');
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
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-0".$i))->SUM('fukuan');
			}else{
			$co =M('hetong')->where(array('name'=>$user))->where(array('addm'=>date("Y",time())."-".$i))->SUM('fukuan');
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
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-0".$i))->SUM('jine');
			}else{
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-".$i))->SUM('jine');
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
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-0".$i))->SUM('yishou');
			}else{
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-".$i))->SUM('yishou');
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
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-0".$i))->SUM('weishou');
			}else{
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-".$i))->SUM('weishou');
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
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-0".$i))->SUM('fukuan');
			}else{
			$co =M('hetong')->where(array('addm'=>date("Y",time())."-".$i))->SUM('fukuan');
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
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('jine');
			}else{
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('jine');
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
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('yishou');
			}else{
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('yishou');
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
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('weishou');
			}else{
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('weishou');
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
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-0".$i))->sum('fukuan');
			}else{
			$co = M('hetong')->where(array('addm'=>date("Y",strtotime("-1 year"))."-".$i))->sum('fukuan');
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