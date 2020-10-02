<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class ShowController extends Controller {	
public $wxuser = array();
public function _initialize(){

}

    
public function index()
{
	//获取活动ID；
	$hdid = I('get.hdid');
	$news = M('Huodong')->getById($hdid);
	$news['jssj'] = strtotime($news['jssj']);
	$news['kssj'] = strtotime($news['kssj']);
	//处理样式表；	
	$news['style'] = unserialize($news['style']);
/*	$style['top']=I('post.image');
	$style['button']=I('post.image1');
	$style['end']=I('post.image2');
	$style['color']=I('post.image3');
	$style['fx']=I('post.image4');
	$style['title']=I('post.image5');*/
	
	$this->assign('news',$news);
	$this->assign('hdid',$hdid);

	// js分享功能数据；
	$weixin =M('weixin')->getById(910); 
	$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin);	
	if(!$signPackage['nonceStr']){		
			$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin,1);	
		}//判断是否读取到 读不到强制更新第三个参数为强制更新开关
	
	$this->assign('signPackage',$signPackage);

	$this->display(T('Show/index'));
}


public function weixinadd(){

	$hdid = I('post.hdid');
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id'); 
	$record=I('post.record'); 
	$zlb=I('post.zlb'); 
		//判断用户是否已经参加活动；
	$Model = new Model();
	$wx_has_in = $Model->query("select * from __PREFIX__zhuli where hdid = '$hdid' and uid = '$id' order by zlb desc");
	if(empty($wx_has_in))
	{			
		//更新用户信息，填写用户姓名和手机号；
		$User = M("Wxuser"); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['name'] = $name;
		$data['tel'] = $tel;
		$data['address'] = getClientIp();
		$data['wxtime'] = time();
		$User->where("id='$id'")->save($data); // 根据条件更新记录
		
		//填写用户助力信息，初始化50助力币；
		$User1 = M("Zhuli");
		$data['uid'] =$id;
		$data['tel']=$tel;
		$data['name']=$name;
		$data['record']=$record;
		$data['zlb']=100;
		$data['hdid'] = $hdid;
		$flag = $User1->add($data);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect(U('Zhuli/index?hdid='.$hdid)); 
	}else{
		//更新用户信息，填写用户姓名和手机号；
		/*$Model = new Model();
		$Model->execute("update __PREFIX__wxuser set name = 'wangwang' and tel = '15200000000' where id = '221097'");*/
		$User = M("Wxuser"); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['name'] = $name;
		$data['tel'] = $tel;
		$data['address'] = getClientIp();
		$data['wxtime'] = time();
		$User->where("id='$id'")->save($data); // 根据条件更新记
		
		redirect(U('Zhuli/index?hdid='.$hdid)); 
	}

	// $this->display();
}

	
/** 助力提示内容  **/
public function zhuliapi(){
	
$id= 910;

$hdid = I('get.hdid');

// 获取微信的公众号信息；
$weixin =M('weixin')->getById($id); 

//$weixin = Table::Fetch('weixin',1);
$news  = M('huodong')->getById($hdid); 

$news['jssj'] = strtotime($news['jssj']);
$news['kssj'] = strtotime($news['kssj']);
//Table::Fetch('wx_hd', $hdid);
//投票用户id；	
$wxid=I('get.wxid');
//$wxid=intval($_GET['wxid']);
//分享者的id;
$wx1=I('get.wx1');
//$wx1=intval($_GET['wx1']);
//投票用户的openid;
 $openid =I('get.uid');
//$openid = trim($_GET['uid']);

//投票用户信息；
$wxus=M('wxuser')->getById($wxid); 
//$wxus=Table::Fetch('wxuser',$wxid);
//分享用户信息;
$wxus1=M('wxuser')->getById($wx1); 
//$wxus1=Table::Fetch('wxuser',$wx1);

//设置刷票拦截机制
$black = array(242391);

if($news['jssj']>time()){	
	$wx_zl=M('zhuli');
	$wx_zl=$wx_zl->where('hdid='.$hdid.' and uid='.$wx1)->order('zlb desc')->find();
	//$wx_zl=$wx_zl[0];
	//如果当前记录不存在，跳转；
	if(empty($wx_zl)||$wx_zl['hdid']!=$hdid||$wx_zl['uid']!=$wx1)
	{
		echo '当前用户还没有参加活动噢,0';	
	}
	else	
	{
		//判断投票用户是否真实有效，防刷票功能；
		if((($openid && $wxus['wx']==$openid) || ($wxid == $wx1)) )
		{
			$zljl=explode(',',$wx_zl['zljl']);
			$j=1;
			
			//如果分享用户在黑名单中，直接返回；
			if(in_array($wx1,$black))
			{
				echo '您已经助力过,'.$wx_zl['zlb'];
				$j=0;	
			}
			else{
				foreach($zljl AS $one){
					if(($one==$wxid)){
						echo '您已经助力过了！,'.$wx_zl['zlb'];
						$j=0;
						break;
					}
				}
			}
			
			
			if($j){
				
				if (getenv("HTTP_CLIENT_IP"))
					$ip = getenv("HTTP_CLIENT_IP");
				else if(getenv("HTTP_X_FORWARDED_FOR"))
					$ip = getenv("HTTP_X_FORWARDED_FOR");
				else if(getenv("REMOTE_ADDR"))
					$ip = getenv("REMOTE_ADDR");
				else
					$ip = "127.0.0.1";
				//$ip =  empty($wxus['address'])? "127.0.0.1" : $wxus['address'];
			
				//$url = "http://ipapi.sinaapp.com/api.php?f=json&ip=".$ip;area1
				$url = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
				
				$homepage = file_get_contents($url);
				
				$json_data = json_decode($homepage,true);
				//if(mb_substr($json_data["data"]['region'],0,2, 'utf-8') == "山东")
				if(mb_substr($json_data["data"]['city'],0,2, 'utf-8') == "枣庄")
				{
					$upzhuli=M('zhuli');
					$updata=array('zlb' =>$wx_zl['zlb']+20,'zljl' =>$wx_zl['zljl'].$wxid.',') ;
					$upzhuli->where('id = '.$wx_zl['id'])->save($updata);
					echo '成功助力为您好友 增加20助力币,'.($wx_zl['zlb']+20);
				}
				else
				{
					echo '您已经助力过了,'.$wx_zl['zlb'];
				}
			}
		}
		else{
			echo '您的用户异常 请遵守规则,'.$wx_zl['zlb'];
		}
	}
}else{
	echo '活动已结束，请稍后过来领奖吧,'.$wx_zl['zlb'];
}

}



/** 助力提示内容 特殊功能提示内容  **/
public function zhuliapi1(){
	
$id= 910;

$hdid = I('get.hdid');

// 获取微信的公众号信息；
$weixin =M('weixin')->getById($id); 

//$weixin = Table::Fetch('weixin',1);
$news  = M('huodong')->getById($hdid); 

$news['jssj'] = strtotime($news['jssj']);
$news['kssj'] = strtotime($news['kssj']);
//Table::Fetch('wx_hd', $hdid);
//投票用户id；	
$wxid=I('get.wxid');
//$wxid=intval($_GET['wxid']);
//分享者的id;
$wx1=I('get.wx1');
//$wx1=intval($_GET['wx1']);
//投票用户的openid;
 $openid =I('get.uid');
//$openid = trim($_GET['uid']);

//投票用户信息；
$wxus=M('wxuser')->getById($wxid); 
//$wxus=Table::Fetch('wxuser',$wxid);
//分享用户信息;
$wxus1=M('wxuser')->getById($wx1); 
//$wxus1=Table::Fetch('wxuser',$wx1);

if($news['jssj']>time()){	
	$wx_zl=M('zhuli');
	$wx_zl=$wx_zl->where('hdid='.$hdid.' and uid='.$wx1)->order('zlb desc')->find();
	//$wx_zl=$wx_zl[0];
	//如果当前记录不存在，跳转；
	if(empty($wx_zl)||$wx_zl['hdid']!=$hdid||$wx_zl['uid']!=$wx1)
	{
		echo '当前用户还没有参加活动噢,0';	
	}
	else	
	{
		//判断投票用户是否真实有效，防刷票功能；
		if((($openid && $wxus['wx']==$openid) || ($wxid == $wx1)) )
		{
			$zljl=explode(',',$wx_zl['zljl']);
			$j=1;
			foreach($zljl AS $one){
				if($one==$wxid){
					echo '您已经助力过了！,'.$wx_zl['zlb'];
					$j=0;
					break;
				}
			}
			
			if($j){
				
				if (getenv("HTTP_CLIENT_IP"))
					$ip = getenv("HTTP_CLIENT_IP");
				else if(getenv("HTTP_X_FORWARDED_FOR"))
					$ip = getenv("HTTP_X_FORWARDED_FOR");
				else if(getenv("REMOTE_ADDR"))
					$ip = getenv("REMOTE_ADDR");
				else
					$ip = "127.0.0.1";
				//$ip =  empty($wxus['address'])? "127.0.0.1" : $wxus['address'];
			
				//$url = "http://ipapi.sinaapp.com/api.php?f=json&ip=".$ip;area1
				$url = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
				
				$homepage = file_get_contents($url);
				
				$json_data = json_decode($homepage,true);
			
				//mb_substr($json_data["data"]["region"],0,2, 'utf-8') == "山东"
				if(mb_substr($json_data["data"]["city"],0,2, 'utf-8') == "枣庄" )
				{
					if($wx_zl['zlb'] < 400)
					{
						$upzhuli=M('zhuli');
		            	$updata=array('zlb' =>$wx_zl['zlb']+20,'zljl' =>$wx_zl['zljl'].$wxid.',') ;
		            	$upzhuli->where('id = '.$wx_zl['id'])->save($updata);
						echo '成功助力为您好友 增加20助力币,'.($wx_zl['zlb']+20);
					}
					else
					{
						echo '您已集满398分助力基金,'.($wx_zl['zlb']);
					}
				}
				else
				{
					echo '您已经助力过了？,'.$wx_zl['zlb'];
				}
			}
		}
		else{
			echo '您的用户异常 请遵守规则,'.$wx_zl['zlb'];
		}
	}
}else{
	echo '活动已结束，请稍后过来领奖吧,'.$wx_zl['zlb'];
}

}



   protected function mtReturn($status,$info,$navTabId="",$closeCurrent=true) {
       
	    $udata['id']=session('uid');
        $udata['update_time']=time();
        $Rs=M("user")->save($udata);
        $dat['username'] = session('username');
        $dat['content'] = $info;
		$dat['os']=$_SERVER['HTTP_USER_AGENT'];
        $dat['url'] = U();
        $dat['addtime'] = date("Y-m-d H:i:s",time());
        $dat['ip'] = get_client_ip();
        M("log")->add($dat);
	   
	    
	    $result = array();
        $result['statusCode'] = $status; 
        $result['message'] = $info;
		$result['tabid'] = strtolower($navTabId).'/index';
        $result['forward'] = '';
		$result['forwardConfirm']='';
        $result['closeCurrent'] =$closeCurrent;
       
        if (empty($type))
            $type = C('DEFAULT_AJAX_RETURN');
        if (strtoupper($type) == 'JSON') {
            // 返回JSON数据格式到客户端 包含状态信息
            header("Content-Type:text/html; charset=utf-8");
            exit(json_encode($result));
        } elseif (strtoupper($type) == 'XML') {
            // 返回xml格式数据
            header("Content-Type:text/xml; charset=utf-8");
            exit(xml_encode($result));
        } elseif (strtoupper($type) == 'EVAL') {
            // 返回可执行的js脚本
            header("Content-Type:text/html; charset=utf-8");
            exit($data);
        } else {
            // TODO 增加其它格式
        }
	}
	
}