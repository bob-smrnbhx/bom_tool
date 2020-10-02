<?php

//寻宝游戏控制类

namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class TreasureController extends Controller {	
public $wxuser = array();
public function _initialize(){
if(I('get.from')){
redirect(U('Treasure/'.ACTION_NAME.'?fx_id='.I('get.fx_id').'&hdid='.I('get.hdid')));
	}
	if(ACTION_NAME!='zhuliapi'||ACTION_NAME!='weixinadd'){
		//$id = abs(intval($_GET['id']));
		$weixinid=910;	
		$weixin =M('weixin')->getById($weixinid); 
		//$uid = $_GET['uid'];
		$code=I('get.code');
		$fx_id = I('get.fx_id');
		$id =  I('get.id'); //获取传的用户ID值；
		$hdid = I('get.hdid');
		if(empty($id))
		{
			$udata=M('wxuser')->getById(session('uid'));
		}
		else
		{
			$udata=M('wxuser')->getById($id);
			session('uid',$id);
			session('wx',$udata['wx']);
		}
		if(!$udata||!( $udata['id']==session('uid')&&$udata['wx']==session('wx'))||$udata['partner_id']!=$weixinid){
			if($code){
				$access_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$weixin['appid']."&secret=".$weixin['appsecret']."&code=".$code."&grant_type=authorization_code"; 
				$access_token_json = cohttps_request($access_token_url);
				$access_token_array = json_decode($access_token_json, true);
				$access_token = $access_token_array['access_token'];
				$openid = $access_token_array['openid'];
				if($openid)
				{
					//$wx_user=Table::Fetch('xy_wxuser', $openid,'wx');
					$this->wxuser=$wx_user =M('wxuser')->getByWx($openid); 
					if(!$wx_user)// 否则添加用户，提交用户；
					{
					 	$upuser=M('wxuser');
				   		$updata=array('wx'=>$openid,'partner_id'=>$weixin['id'],'wxtime'=>time(),'address'=>getClientIp());
				  		$wx_id = $upuser->add($updata);		
						$this->wxuser=$wx_user =M('wxuser')->getById($wx_id); 
					}	
					else //更新用户；
					{
						$User = M("wxuser"); // 实例化User对象// 要修改的数据对象属性赋值
						$data['wxtime'] = time();
						$data['address'] = getClientIp();
						$User->where("wx='$openid'")->save($data);
					}	
					session('uid',$wx_user['id']);
					session('wx',$wx_user['wx']);
					redirect(U('Treasure/index?fx_id='.$fx_id.'&hdid='.$hdid));
				}
			}else
			{
				header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn'.urlencode('/xcrm/index.php?s=/Wap/Treasure/index/fx_id/'.$fx_id.'/hdid/'.$hdid)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect");
			}
		}else{	
			$this->wxuser=$udata;
		}
	}
}

//参团活动show展示
public function show()
{
	//获取活动ID；
	$hdid = I('get.hdid');
	$news = M('Huodong')->getById($hdid);
	$news['jssj'] = strtotime($news['jssj']);
	$news['kssj'] = strtotime($news['kssj']);
	$this->assign('news',$news);
	
	// js分享功能数据；
	$weixin =M('weixin')->getById(910); 
	$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin);	
	if(!$signPackage['nonceStr']){		
			$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin,1);	
		}//判断是否读取到 读不到强制更新第三个参数为强制更新开关
	
	$this->assign('signPackage',$signPackage);
	
	$this->assign('hdid',$hdid);

	$this->display(T('Treasure/show'.$hdid));
}
    
public function index()
{
	//获取活动ID；
	$hdid = I('get.hdid');
	$news = M('Huodong')->getById($hdid);
	$news['jssj'] = strtotime($news['jssj']);
	$news['kssj'] = strtotime($news['kssj']);
	$this->assign('news',$news);

	// js分享功能数据；
	$weixin =M('weixin')->getById(910); 
	$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin);	
	if(!$signPackage['nonceStr']){		
			$signPackage=D('Admin', 'Service')->GetSignPackage(910,$weixin,1);	
		}//判断是否读取到 读不到强制更新第三个参数为强制更新开关
	
	$this->assign('signPackage',$signPackage);

	$id =  empty(I('get.id'))? session('uid') : I('get.id') ; //获取传的用户ID值；
	$fx_id = I('get.fx_id'); // 获取是否存在分享者 */
	
	// 获取用户填写的用户信息；
	$is_add = I('get.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('get.name');
	$tel = I('get.tel');
			
	//总助力人数/次数；
	$Model = new Model();
	$zhulirs = $Model->query("select sum(zlb) as total from __PREFIX__zhuli where hdid ='$hdid'");
	$zlrs = round($zhulirs[0]['total']/20); //向上向下取整；
	//print_r($zhulirs);
	$this->assign('zlrs',$zlrs);  
	
	//排行榜TOP名；
	$Model = new Model();
	$zhulirs = $Model->query("select * from __PREFIX__zhuli where hdid = '$hdid' and uid != 0  GROUP BY uid order by zlb desc limit 100");
	$this->assign('zhulitop',$zhulirs);

	/**进入当前页面，增加活动的阅读次数  **/
	$Model = new Model();
	$Model->execute("update __PREFIX__huodong set cs = cs+1 where id = '$hdid'");

	// 获取活动的阅读次数,参加人数；
	$Huodong =  M('Huodong');
	$ydcs = $Huodong->where("id='$hdid'")->sum('cs');
	$this->assign('ydcs',$ydcs); 
	$Zhuli = M('Zhuli');
	$cjrs = $Zhuli->where("hdid='$hdid'")->count();
	$this->assign('cjrs',$cjrs);
	
	$wxuser=$this->wxuser;
	
	// 如果获取用户的ID,则直接从数据表中获取信息；
	if($id)
	{
		$Zhuli = M('Zhuli');
		$wx_zl = $Zhuli->where("hdid = '$hdid' and uid = '$id'")->order('zlb desc')->find();
		//print_r($wx_zl);
		if($wx_zl['tel'] && $wx_zl['name']&&$wx_zl ) //用户已经参加活动，显示助力币； 
		{
			$is_join = 1;
			$coin = $wx_zl['zlb'];
		}
		else{ // 没有参加活动，提示用户参加活动；
			$is_join = 0;
		}
	}
	
	// 当前是首次进入活动页面；
	if(empty($fx_id) && $id){ 
		$fx_id = $id;				
	}
	
	//是否是分享页面的内容；是的话，需要获取当前id是否已投票；
	if($fx_id && $id) 
	{
		$fx_user = M('Wxuser')->getById($fx_id);
		//获取分享者的助力信息；
		$Zhuli = M("Zhuli"); // 实例化User对象
		$fx_user
		$map['uid'] = $fx_id;
		$map['hdid'] = $hdid;// 把查询条件传入查询方法
		$fx_zl = $Zhuli->where($map)->order('zlb desc')->find(); 
		//$fx_zl = M('Zhuli')->where("uid='$fx_id' and hdid='$hdid'")->order('zlb desc')->find();
		//获取分享者已获取的助力币,判断分享者是否参加活动；
		if(empty($fx_zl))
		{
			$fx_coin = 0;
			$is_fx_join = 0;
		}
		else
		{
			$fx_coin = $fx_zl['zlb']; 
			$is_fx_join = 1;
		}
			
		//分享的用户的排名；
		$Model = new Model();
		$sql_ph = $Model->query("select (select count(*)+1 as count from __PREFIX__zhuli as zl where zl.zlb > wzl.zlb and hdid='$hdid' order by zl.zlb DESC,zl.id DESC) as count from __PREFIX__zhuli as wzl where wzl.uid= '$fx_id' and hdid='$hdid'");
		//print_r($sql_ph);
		$position = $sql_ph[0]['count'];
	}
	
	$this->assign('wx_zl',$wx_zl); 
	$this->assign('fx_zl',$fx_zl); 
	$this->assign('wx_user',$wxuser); 
	$this->assign('fx_user',$fx_user); 
	$this->assign('is_join',$is_join); 
	$this->assign('is_fx_join',$is_fx_join); 
	$this->assign('coin',$coin); 
	$this->assign('fx_coin',$fx_coin); 
	$this->assign('wx_id',$id); 
	$this->assign('fx_id',$fx_id); 
	$this->assign('hdid',$hdid);
	//$this->assign('id',22); 
	$this->assign('openid',session('wx')); 
	$this->assign('position',$position);  //分享用户排行榜
	//echo T('Treasure/index'.$hdid);
	$this->display(T('Treasure/index'.$hdid));
}
/*	public function addwx(){
		$Model = new Model();
		$Model->execute("update __PREFIX__wxuser set name = 'wangwang' and tel = '15200000000' where id = '221099'");
		print_r($Model);
	}*/
public function weixinadd(){

	$hdid = I('post.hdid');
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id'); 
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
		$User = M("Zhuli");
		$data['uid'] =$id;
		$data['zlb'] = 100;
		$data['tel']=$tel;
		$data['name']=$name;
		$data['hdid'] = $hdid;
		$flag = $User->add($data);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect(U('Treasure/index?hdid='.$hdid)); 
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
		
		redirect(U('Treasure/index?hdid='.$hdid)); 
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
			
				if(mb_substr($json_data["data"]['region'],0,2, 'utf-8') == "山东" )
				{
					$upzhuli=M('zhuli');
					$updata=array('zlb' =>$wx_zl['zlb']+20,'zljl' =>$wx_zl['zljl'].$wxid.',') ;
					$upzhuli->where('id = '.$wx_zl['id'])->save($updata);
					echo '成功助力为您好友 增加20助力币,'.($wx_zl['zlb']+20);
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
			
				if(mb_substr($json_data["data"]['region'],0,2, 'utf-8') == "山东" )
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