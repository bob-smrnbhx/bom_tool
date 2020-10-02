<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class PraiseController extends Controller {	
public $wxuser = array();
public function _initialize(){
if(I('get.from')){
redirect(U('Praise/'.ACTION_NAME.'?fx_id='.I('get.fx_id').'&hdid='.I('get.hdid')));
	}
	if(ACTION_NAME!='praiseapi'||ACTION_NAME!='weixinadd'){
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
					$getuserurl='https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token .'&openid='.$openid.'&lang=zh_CN';
 					$userinfo=cohttps_request($getuserurl);
					$userinfo = json_decode($userinfo, true);

					$this->wxuser=$wx_user =M('wxuser')->getByWx($openid); 
					if(!$wx_user)// 添加用户，提交用户；
					{
					 	$upuser=M('wxuser');
				   		$updata=array('wx'=>$openid,'partner_id'=>$weixinid,'wxtime'=>time(),'address'=>getClientIp(), 'wxuser'=>$userinfo['nickname'],'pic'=>$userinfo['headimgurl'],'address'=>$userinfo['province'].$userinfo['city'],'sex'=>$userinfo['sex']);
				  		$wx_id = $upuser->add($updata);		
						$this->wxuser=$wx_user = M('wxuser')->getById($wx_id); 
					}
					else //更新用户；
					{
						$User = M('wxuser'); // 实例化User对象// 要修改的数据对象属性赋值
						$data=$updata=array('id'=>$wx_user['id'],'wxtime'=>time(),'address'=>getClientIp(), 'wxuser'=>$userinfo['nickname'],'pic'=>$userinfo['headimgurl'],'address'=>$userinfo['province'].$userinfo['city'],'sex'=>$userinfo['sex']);
						$User->save($data);
					}

					session('uid',$wx_user['id']);
					session('wx',$wx_user['wx']);
					redirect(U('Praise/index?fx_id='.$fx_id.'&hdid='.$hdid));
				}
			}
			else
			{
				header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn'.urlencode('/xcrm/index.php?s=/Wap/Praise/index/fx_id/'.$fx_id.'/hdid/'.$hdid)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect");
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

	$this->display(T('Praise/show'.$hdid));
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
			
	//排行榜TOP名；
	$Model = new Model();
	$praisers = $Model->query("select * from __PREFIX__praise where hdid = '$hdid' and uid != 0  GROUP BY uid order by zlb desc limit 100");
	$this->assign('praisetop',$praisers);

	/**进入当前页面，增加活动的阅读次数  **/
	$Model = new Model();
	$Model->execute("update __PREFIX__huodong set cs = cs+1 where id = '$hdid'");

	// 获取活动的阅读次数,参加人数；
	$Huodong =  M('Huodong');
	$ydcs = $Huodong->where("id='$hdid'")->sum('cs');
	$this->assign('ydcs',$ydcs); 
	$Praise = M('Praise');
	$cjrs = $Praise->where("hdid='$hdid'")->count();
	$this->assign('cjrs',$cjrs);
			
			
	//总助力人数/次数；
	$Model = new Model();
	$praisers = $Model->query("select sum(zlb) as total from __PREFIX__praise where hdid ='$hdid'");
	$zlrs = $praisers[0]['total']; //向上向下取整；
	//print_r($praisers);
	$this->assign('zlrs',$zlrs);  
	
	$wxuser=$this->wxuser;
	
	// 如果获取用户的ID,则直接从数据表中获取信息；
	if($id)
	{
		$Praise = M('Praise');
		$wx_zl = $Praise->where("hdid = '$hdid' and uid = '$id'")->order('zlb desc')->find();
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
	
	//核销提示信息；
	$tips = "当前不是您的页面，请先到自己的页面！";
	
	//是否是分享页面的内容；是的话，需要获取当前id是否已投票；
	if($fx_id && $id) 
	{
		$fx_user = M('Wxuser')->getById($fx_id);
		//获取分享者的助力信息；
		$Praise = M("Praise"); // 实例化User对象

		$map['uid'] = $fx_id;
		$map['hdid'] = $hdid;// 把查询条件传入查询方法
		$fx_zl = $Praise->where($map)->order('zlb desc')->find(); 
		//$fx_zl = M('Praise')->where("uid='$fx_id' and hdid='$hdid'")->order('zlb desc')->find();
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
			$usermodel = M('Wxuser');
			$zljl = explode(",",$fx_zl['zljl']);
			foreach($zljl AS $one){
				$userinfo = $usermodel->getById($one);
				$praise_list[]=array('wxuser'=>$userinfo['wxuser'],'pic'=>$userinfo['pic']);
			}
		}
		
		//没有核销；
		if($fx_zl['zlb'] == 20)
		{
			if($fx_zl['share'])
			{
				$tips = "您已经领取完啦.";
			}
			else
			{
				$tips = "您可以领取产品.";
			}
		}
		else
		{
			$tips = "您还没有点赞到20点赞币哦.";
		}
		
		
		
		
			
		//分享的用户的排名；
		$Model = new Model();
		$sql_ph = $Model->query("select (select count(*)+1 as count from __PREFIX__praise as zl where zl.zlb > wzl.zlb and hdid='$hdid' order by zl.zlb DESC,zl.id DESC) as count from __PREFIX__praise as wzl where wzl.uid= '$fx_id' and hdid='$hdid'");
		//print_r($sql_ph);
		$position = $sql_ph[0]['count'];
	}
	
	//成功点赞列表，用户名、照片等；
	$this->assign('praise_list',$praise_list);
	$this->assign('tips',$tips); 
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
	//echo T('Praise/index'.$hdid);
	$this->display(T('Praise/index'));
}


public function weixinadd(){

	$hdid = I('post.hdid');
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id'); 
	$zlb=I('post.zlb'); 
		//判断用户是否已经参加活动；
	$Model = new Model();
	$wx_has_in = $Model->query("select * from __PREFIX__praise where hdid = '$hdid' and uid = '$id' order by zlb desc");
	if(empty($wx_has_in))
	{			
		//添加用户信息，填写用户姓名和手机号；
		$User = M("Wxuser"); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['address'] = getClientIp();
		$data['wxtime'] = time();
		$User->where("id='$id'")->save($data); // 根据条件更新记录
		
		//填写用户助力信息，初始化50助力币；
		$User1 = M("Praise");
		$data['uid'] =$id;
		$data['tel']=$tel;
		$data['name']=$name;
		$data['share']=0;
		$data['zlb']=0;
		$data['hdid'] = $hdid;
		$flag = $User1->add($data);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect(U('Praise/index?hdid='.$hdid)); 
	}
}

	
/** 助力提示内容  **/
public function praiseapi(){
	
$id= 910;

$hdid = I('get.hdid');

// 获取微信的公众号信息；
$weixin =M('weixin')->getById($id); 

//$weixin = Table::Fetch('weixin',1);
$news  = M('huodong')->getById($hdid); 

$news['jssj'] = strtotime($news['jssj']);
$news['kssj'] = strtotime($news['kssj']);
//投票用户id；	
$wxid=I('get.wxid');
//分享者的id;
$wx1=I('get.wx1');
//投票用户的openid;
 $openid =I('get.uid');
//投票用户信息；
$wxus=M('wxuser')->getById($wxid); 
//分享用户信息;
$wxus1=M('wxuser')->getById($wx1); 

//设置刷票拦截机制
$black = array(242391);
//设置集满多少停止；
/*if($hdid == 57)
{
	$top_praise = 18;
}
elseif($hdid == 63)
{
	$top_praise = 20;
}
elseif($hdid == 65)
{
	$top_praise = 38;
}
elseif($hdid == 66)
{
	$top_praise = 108;
}*/


if($news['jssj']>time()){	
	$wx_zl=M('praise');
	$wx_zl=$wx_zl->where('hdid='.$hdid.' and uid='.$wx1)->order('zlb desc')->find();
	//$wx_zl=$wx_zl[0];
	//如果当前记录不存在，跳转；
	if(empty($wx_zl)||$wx_zl['hdid']!=$hdid||$wx_zl['uid']!=$wx1)
	{
		echo '当前用户还没有参加活动噢,0';	
	}
	else	
	{

		//判断投票用户是否真实有效，防刷票功能 start；
		if((($openid && $wxus['wx']==$openid) || ($wxid == $wx1)) )
		{
			$zljl=explode(',',$wx_zl['zljl']);
			$j=1;
			
			//如果分享用户在黑名单中，直接返回；
			if(in_array($wx1,$black))
			{
				echo '您已经帮助点过赞了,'.$wx_zl['zlb'];
				$j=0;	
			}
			else
			{
				foreach($zljl AS $one){
					if(($one==$wxid)){
						echo '您已经帮助点过赞了！,'.$wx_zl['zlb'];
						$j=0;
						break;
					}
				}
			}
					
			if($j){
				
/*					if (getenv("HTTP_CLIENT_IP"))
					$ip = getenv("HTTP_CLIENT_IP");
				else if(getenv("HTTP_X_FORWARDED_FOR"))
					$ip = getenv("HTTP_X_FORWARDED_FOR");
				else if(getenv("REMOTE_ADDR"))
					$ip = getenv("REMOTE_ADDR");
				else
					$ip = "127.0.0.1";

				$url = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
				
				$homepage = file_get_contents($url);
				
				$json_data = json_decode($homepage,true);
			
				if(mb_substr($json_data["data"]['region'],0,2, 'utf-8') == "山东" )
				{*/
					$uppraise=M('praise');
					$updata=array('zlb' =>$wx_zl['zlb']+1,'zljl' =>$wx_zl['zljl'].$wxid.',') ;
					$uppraise->where('id = '.$wx_zl['id'])->save($updata);
					echo '成功帮您好友 增加1个点赞币,'.($wx_zl['zlb']+1);
			/*	}
				else
				{
					echo '您已经点过赞了,'.$wx_zl['zlb'];
				}*/
			}
		}
		else{
			echo '您的用户异常 请遵守规则,'.$wx_zl['zlb'];
		}
		//判断投票用户是否真实有效，防刷票功能 end；

	}
}
else
{
	echo '活动已结束，请过来领奖吧,'.$wx_zl['zlb'];
}
}


	
/** 验证是否集满，以及核销功能  **/
public function yanzheng(){
	
$id= 910;

$hdid = I('get.hdid');

$code = I('get.code');

// 获取微信的公众号信息；
$weixin =M('weixin')->getById($id); 

//$weixin = Table::Fetch('weixin',1);
$news  = M('huodong')->getById($hdid); 

$news['jssj'] = strtotime($news['jssj']);
$news['kssj'] = strtotime($news['kssj']);
//投票用户id；	
$wxid=I('get.wxid');
//分享者的id;
$wx1=I('get.wx1');

//投票用户信息；
$wxus=M('wxuser')->getById($wxid); 
//分享用户信息;
$wxus1=M('wxuser')->getById($wx1); 


if($news['jssj']>time()){	
	$wx_zl=M('praise');
	$wx_zl=$wx_zl->where('hdid='.$hdid.' and uid='.$wx1)->order('zlb desc')->find();

	if($wxid != $wx1)
	{
		
		echo '这不是您自己的页面，请先跳到您自己的页面！';
	}
	else
	{
		if($wx_zl['share'] == 0)
		{
			if($code == $news['bzgl'])
			{
			$uppraise=M('praise');
			$updata=array('share' => 1) ;
			$uppraise->where('id = '.$wx_zl['id'])->save($updata);
			echo '恭喜您领取成功!!!';
			}
			else
			{
				echo '您的核销码不正确!!!';
			}
		}
		else
		{
			echo '您已经领取过啦！';
		}
	}
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