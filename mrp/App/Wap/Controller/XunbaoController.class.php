<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class XunbaoController extends Controller {	
public $wxuser = array();	
public function _initialize(){
$weixinid=2;
$shareid = empty(I('get.shareid'))? 0:I('get.shareid');
	if(ACTION_NAME!='zhuliapi'||ACTION_NAME!='weixinadd'){
		if(I('get.from')){
redirect(U('Xunbao/'.ACTION_NAME.'?fx_id='.I('get.fx_id').'&hdid='.I('get.hdid').'&shareid='.$shareid));
	}

	//$id = abs(intval($_GET['id']));
	$weixin =M('weixin')->getById($weixinid); 
	//$uid = $_GET['uid'];
	$code=I('get.code');
	$fx_id = I('get.fx_id');
	$id =  I('get.id'); //获取传的用户ID值；

	if(empty($id))
	{
		if(session('uid')){
		$udata=M(tablecopy('wxuser',$weixinid))->getById(session('uid'));
	}}
		else
		{
			$udata=M(tablecopy('wxuser',$weixinid))->getById($id);
			session('uid',$id);
			session('wx',$udata['wx']);
		}
		if(!$udata||!( $udata['id']==session('uid')&&$udata['wx']==session('wx'))||$udata['partner_id']!=$weixinid||0){
			if($code){
				$access_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$weixin['appid']."&secret=".$weixin['appsecret']."&code=".$code."&grant_type=authorization_code"; 
				$access_token_json = cohttps_request($access_token_url); 
				$access_token_array = json_decode($access_token_json, true);
				$access_token = $access_token_array['access_token'];
				$openid = $access_token_array['openid'];	

				if($openid)
				{
$getuserurl='https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token .'&openid='.$openid.'&lang=zh_CN';
 $userinfo=cohttps_request($getuserurl);
 $userinfo = json_decode($userinfo, true);
					//$wx_user=Table::Fetch('xy_wxuser', $openid,'wx');
		$this->wxuser=$wx_user =M(tablecopy('wxuser',$weixinid))->getByWx($openid); 
					if(!$wx_user)// 否则添加用户，提交用户；
					{
					 	$upuser=M(tablecopy('wxuser',$weixinid));
				   		$updata=array('wx'=>$openid,'partner_id'=>$weixinid,'wxtime'=>time(),'address'=>getClientIp(), 'wxuser'=>$userinfo['nickname'],'pic'=>$userinfo['headimgurl'],'address'=>$userinfo['province'].$userinfo['city'],'sex'=>$userinfo['sex'],'hqxxzt' =>'1');
				  		$wx_id = $upuser->add($updata);		
						$this->wxuser=$wx_user =M(tablecopy('wxuser',$weixinid))->getById($wx_id); 
					}
					else //更新用户；
					{
						$User = M(tablecopy('wxuser',$weixinid)); // 实例化User对象// 要修改的数据对象属性赋值
						$data=$updata=array('id'=>$wx_user['id'],'wxtime'=>time(),'address'=>getClientIp(), 'wxuser'=>$userinfo['nickname'],'pic'=>$userinfo['headimgurl'],'address'=>$userinfo['province'].$userinfo['city'],'sex'=>$userinfo['sex'],'hqxxzt' =>'1');
						$User->save($data);
					}
					session('uid',$wx_user['id']);
					session('wx',$wx_user['wx']);
					redirect(U('xunbao/index?fx_id='.$fx_id.'&shareid='.$shareid));
				}
			}else
			{
				header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn'.urlencode('/xcrm/index.php?s=/Wap/xunbao/index/fx_id/'.$fx_id.'/shareid/'.$shareid)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect");
			}
		}else{	
			$this->wxuser=$udata;
		}
	}
}
    
public function index()
{
	
	//获取活动ID；
	//$hdid = I();
	$hdid = 45;
	$news = M('Huodong')->getById($hdid);
	$news['jssj'] = strtotime($news['jssj']);
	$news['kssj'] = strtotime($news['kssj']);
	$this->assign('news',$news);

	// js分享功能数据；
	$weixin =M('weixin')->getById(2); 
	$signPackage=D('Admin', 'Service')->GetSignPackage(2,$weixin);	
	if(!$signPackage['nonceStr']){		
			$signPackage=D('Admin', 'Service')->GetSignPackage(2,$weixin,1);	
		}//判断是否读取到 读不到强制更新第三个参数为强制更新开关
	$this->assign('signPackage',$signPackage);

	$id =  empty(I('get.id'))? session('uid') : I('get.id') ; //获取传的用户ID值；
	$fx_id = empty(I('get.fx_id'))? 0:I('get.fx_id'); // 获取是否存在分享者 */
	
	// 获取用户填写的用户信息；
	$is_add = I('get.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('get.name');
	$tel = I('get.tel');    
	//总助力人数/次数；
	$Model = M(tablecopy('zhuli',$hdid));
	$zhulirs = $Model->where(' hdid ='.$hdid)->sum('zlb');
	//$zhulirs = $Model->query("select sum(zlb) as total from __PREFIX__zhuli where hdid ='$hdid'");
	$zlrs = round($zhulirs/20); //向上向下取整；
	$this->assign('zlrs',$zlrs);  
	
	//排行榜TOP名；
	//$Model = new Model();
	//$Model = M('zhuli');	
	$zhulirs = $Model->where('hdid = '.$hdid.' and uid != 0')->order('zlb desc')->limit(1000)->select();
	//$zhulirs = $Model->query("select * from __PREFIX__zhuli where hdid = '$hdid' and uid != 0  GROUP BY uid order by  limit 100");
	$this->assign('zhulitop',$zhulirs);

	/**进入当前页面，增加活动的阅读次数  **/
	$huodong = new Model();
	$huodong->execute("update __PREFIX__huodong set cs = cs+1 where id = '$hdid'");

	// 获取活动的阅读次数,参加人数；
	$Huodong =  M('Huodong');
	$ydcs = $Huodong->where("id=".$hdid)->sum('cs');
	$this->assign('ydcs',$ydcs); 
	$cjrs = $Model->where("hdid=".$hdid)->count();
	$this->assign('cjrs',$cjrs);
	
	
	$usermodel=M(tablecopy('wxuser',2));
	$userinfo=$usermodel->getById($id);
	$shareid = empty($userinfo['shareid'])? (empty(I('get.shareid'))? 0:I('get.shareid')):$userinfo['shareid']; // 获取是否存在分享者 */
	
	$wxuser=$this->wxuser;
	if($wxuser['hdjs']){
	$hdjs=unserialize($wxuser['hdjs']);
	}else{
	$hdjs=array('1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'6'=>0,'7'=>0,'8'=>0,'9'=>0,'10'=>0,'11'=>0,	'12'=>0);	
	}// 如果获取用户的ID,则直接从数据表中获取信息；
	
	if($id)
	{

		$wx_zl = $Model->where("hdid = '$hdid' and uid = '$id'")->order('zlb desc')->find();
		//print_r($wx_zl);		

		if($wx_zl['tel'] && $wx_zl['name']&&$wx_zl ) //用户已经参加活动，显示助力币； 
		{
			$is_join = 1;
		$zljl=explode(',',$wx_zl['cgzl']);
		foreach($zljl AS $one){
			$userinfo=$usermodel->getById($one);
			$cgzl[]=array('wxuser'=>$userinfo['wxuser'],'pic'=>$userinfo['pic']);
			}
				if(count($cgzl)>36){
		$coin =array_slice($cgzl,0,36);
		}else{
					$coin =$cgzl1;
			}
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
	$is_self=0;
	if($fx_id && $id) 
	{
		$fx_user = $usermodel->getById($fx_id);
			
	
		if($wxuser['id']==$fx_user['id']){
			$is_self=1;
			}
		
		//获取分享者的助力信息；
	 // 实例化User对象
		$map['uid'] = $fx_id;
		$map['hdid'] = $hdid;// 把查询条件传入查询方法
		$fx_zl = $Model->where($map)->order('zlb desc')->find(); 
		
		$zljl1=explode(',',$fx_zl['cgzl']);
		foreach($zljl1 AS $one){
			$userinfo=$usermodel->getById($one);
			$cgzl1[]=array('wxuser'=>$userinfo['wxuser'],'pic'=>$userinfo['pic']);
			}
			if(count($cgzl1)>36){
		$coin =array_slice($cgzl1,0,36);
		}else{
					$coin =$cgzl1;
			}
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
	}else{
		$is_self=1;
		}

	$Fxuser=M(tablecopy('zhuli',$hdid));
	$fxweixin=$Fxuser->where('hdid='.$hdid.' and uid='.$fx_id)->find();
	$this->assign('fxweixin',$fxweixin); 
		$this->assign('fx_zl',$fx_zl); 
	$this->assign('is_self',$is_self); 
	$this->assign('wx_user',$wxuser); 
	$this->assign('fx_user',$fx_user); 
	$this->assign('is_join',$is_join); 
	$this->assign('is_fx_join',$is_fx_join); 
	$this->assign('coin',$coin); 
	$this->assign('hdjs',$hdjs); 
	$this->assign('fx_coin',$fx_coin); 
	$this->assign('wx_id',$id); 
	$this->assign('fx_id',$fx_id); 
	$this->assign('shareid',$shareid); 
	//$this->assign('id',22); 
	$this->assign('openid',session('wx')); 
	$this->assign('position',$position);  //分享用户排行榜

	$this->display();	
}
public function addwx(){
	$str = get_client_ip();
	$ip = getClientIp();
	//	print_r($Model);
	echo $str."----------".$ip;
}
public function weixinadd(){
$weixinid=2;
	$hdid = 45;
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id');
	$shareid=I('post.shareid');
	$birthday = I('post.record');
//	$Model = M(tablecopy('zhuli',$hdid));
//	$wx_has_in = $Model->where('hdid = '.$hdid.' and uid = '.$id)->select();
	$Model = new Model();
	$wx_has_in = $Model->query("select * from __PREFIX__zhuli_45 where hdid = 45 and uid = '$id' order by zlb desc");
	
	if(empty($wx_has_in))
	{			
		//更新用户信息，填写用户姓名和手机号；
		$wxUser= M(tablecopy('wxuser',$weixinid)); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['name']=$name;
		$data['tel']= $tel;
		$data['wxtime'] = time();
		$data['shareid'] = $shareid;
		$data['id'] = $id;
		//exit(var_dump($data));
		$data['hdjs']=serialize(array('1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'6'=>0,'7'=>0,'8'=>0,'9'=>0,'10'=>0,'11'=>0,'12'=>0));	
		$wxUser->save($data); // 根据条件更新记录
		//填写用户助力信息，初始化50助力币；
		$User1 =M(tablecopy('zhuli',$hdid));
		$data_['uid'] =$id;
		$data_['zlb'] = 100;
		$data_['tel']=$tel;
		$data_['name']=$name;
		$data_['record']=$birthday;
		$data_['hdid'] = $hdid;
		$flag = $User1->add($data_);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect("http://www.dscm.com.cn/weixinpay/demo/js_api_call.php"); 
	}
	else
	{
		redirect(U('xunbao/index?id='.$id));
	}

	// $this->display();
}
/** 抽奖 **/
public function choujiang(){
	$wxuser=$this->wxuser;
	$id=I('get.id');$openid=I('get.openid');$uid=I('get.uid');
	if( $openid==$wxuser['wx']&&$uid=$wxuser['id']){
	 $rs = D('Admin', 'Service')->choujiang($wxuser['id'],19);	
	echo  json_encode($rs);
	}
	// $this->display();
}
	
/** 助力提示内容  **/
public function zhuliapi(){
	
$id= 2;

$hdid = 45;

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
$muser=M(tablecopy('wxuser',$id));
$wxus=$muser->getById($wxid); 
//$wxus=Table::Fetch('wxuser',$wxid);
//分享用户信息;
$wxus1=$muser->getById($wx1); 
//$wxus1=Table::Fetch('wxuser',$wx1);

if($news['jssj']>time()){	
	$wx_zl=	M(tablecopy('zhuli',$hdid));
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
			$zljl=explode(',',$wx_zl['cgzl']);
			$j=1;
			foreach($zljl AS $one){
				if($one==$wxid  && ($wxid != 224662)){
					echo '您已经帮过TA过了！,'.$wx_zl['zlb'];
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
				//mb_substr($json_data["data"]["city"],0,2, 'utf-8') == "枣庄"
				if(mb_substr($json_data["data"]["region"],0,2, 'utf-8') == "山东")
				{
					$upzhuli= M(tablecopy('zhuli',$hdid));
					
					if(($wxus['wxuser']&&$wxus['pic']&&$wxus['address']=='山东枣庄')|| 1){
						$updata['cgzl']=$wx_zl['cgzl'].$wxid.',';
						$cgzls=floor (count(explode(',',$updata['cgzl']))/3);	//获取第几关；
						if($cgzls>11){	//设置最多12关；
							$cgzls=12;
						}
						/*if($cgzls == 12)
						{
							$updata['cgzl'] = $wx_zl['cgzl'];
						}*/
						
						//开始之前，判断是否支付；
						if(!$wxus1['pay'])
						{
							$updata['cgzls']= 0;
							$updata['cgzl']=$wx_zl['cgzl'];
							$cgzly='你的朋友遇到了困难请通知TA，赶紧支付好寻找更多大奖哦.';
							$check['pay']=11;
						}			
						else
						{
							$updata['cgzls']= $cgzls;
							$updata['zlb']= $wx_zl['zlb']+20;
							/*if($cgzls == 12)
							{
								$updata['zlb']= $wx_zl['zlb'];
							}else
							{
								$updata['zlb']= $wx_zl['zlb']+20;
							}*/
							
							if(mt_rand(1,10)%10==1){
							$cgzly='路遇大河你帮'.$wxus1['name'].'找到一条船,向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==2){
							$cgzly='在沙漠里找到绿洲'.$wxus1['name'].',向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==3){
							$cgzly='您帮'.$wxus1['name'].'找到一条捷径,向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==4){
							$cgzly='怪兽被驯服带你们向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==5){
							$cgzly='你的聪明才智助'.$wxus1['name'].'通过远古石阵,向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==6){
							$cgzly='找到一大堆好吃的'.$wxus1['name'].'向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==7){
							$cgzly='找到魔毯带'.$wxus1['name'].'飞行了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==8){
							$cgzly='使出鳞波微步带'.$wxus1['name'].'向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}else if(mt_rand(1,10)%10==9){
							$cgzly='龙卷风来袭把你们向前吹了1000米宝藏更近了并获取了一枚状元币.';
							}else{
							$cgzly='在您的帮助下'.$wxus1['name'].'向前行进了1000米宝藏更近了并获取了一枚状元币.';
							}
							
							if($wxus1['hdjs']){
								$hdjs=unserialize($wxus1['hdjs']);
							}else{
								$hdjs=array('1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'6'=>0,'7'=>0,'8'=>0,'9'=>0,'10'=>0,'11'=>0,'12'=>0);	}
								for(;$cgzls>0;$cgzls--){
									if($hdjs[$cgzls]==0){
										//$hdjs[$cgzls]=1;
									}
								}		
							}		
					}
					else{
						
						if(mt_rand(1,10)%10==1){
							$cgzly='路遇大河你帮'.$wxus1['name'].'没有船,停在了原地.';
							}else if(mt_rand(1,10)%10==2){
							$cgzly='在沙漠里迷路'.$wxus1['name'].',回到原地.';
							}else if(mt_rand(1,10)%10==3){
							$cgzly='被土著人捉去'.$wxus1['name'].'关了几天回到原地.';
							}else if(mt_rand(1,10)%10==4){
							$cgzly='掉入猎人洞里原地求援.';
							}else if(mt_rand(1,10)%10==5){
							$cgzly='你的小聪明让'.$wxus1['name'].'误入远古石阵,出不去了.';
							}else if(mt_rand(1,10)%10==6){
							$cgzly='在与野兽的追逐中丢失了食物'.$wxus1['name'].'损失惨重.';
							}else if(mt_rand(1,10)%10==7){
							$cgzly='路过磁石山指南针失灵,'.$wxus1['name'].'迷路了.';
							}else if(mt_rand(1,10)%10==8){
							$cgzly='由于意见不统一丢下'.$wxus1['name'].'自已走了.';
							}else if(mt_rand(1,10)%10==9){
							$cgzly='好不容易爬到山顶发现前面是悬崖过不去只要又回去.';
							}else{
						$cgzly='您和'.$wxus1['name'].'遭遇怪兽，虽然打败了怪兽但只走了1米.';
							}
						
					}

					//exit(var_dump($updata));
					$row = $upzhuli->where('id = '.$wx_zl['id'])->save($updata); 
					
					$user = M(tablecopy('wxuser',$id)); 
					if($updata['cgzls']){
					$data['grade']=$updata['cgzls'];}
					if($hdjs){
					$data['hdjs']=serialize($hdjs);}
					
					$user->where('id='.$wxus1['id'])->save($data);
				
					if($check['pay'])
					{
						//如果是同一个人，才会弹出支付框，否则只提示信息，不弹框；
						if($wx1 == $wxid)
						{
							echo $cgzly.',0';
						}
						else{
							echo $cgzly.',1';
						}
						
					}else
					{
						echo $cgzly.','.($wx_zl['zlb']+20);
					}
					

				} 
				else
				{
					echo '您已经帮过他了？,'.$wx_zl['zlb'];
				}
			}
		}
		else{
			echo '遭遇怪兽，虽然打败了怪兽但只走了1米,'.$wx_zl['zlb'];
		}
	}
}else{
	echo '活动已结束，请尽快过来领奖吧,3';
}

}

//获取客户端IP地址字
/*function getClientIp() {
    global $ip;  
    if (getenv("HTTP_CLIENT_IP"))  
        $ip = getenv("HTTP_CLIENT_IP");  
    else if(getenv("HTTP_X_FORWARDED_FOR"))  
        $ip = getenv("HTTP_X_FORWARDED_FOR");  
    else if(getenv("REMOTE_ADDR"))  
        $ip = getenv("REMOTE_ADDR");  
    else $ip = "127.0.0.1";  
    return $ip; 
}*/


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