<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class YanzhengController extends Controller {	
public $wxuser = array();	
public function _initialize(){
$weixinid=2;
/*if(I('get.from')){
redirect(U('Xunbao/'.ACTION_NAME.'?fx_id='.I('get.fx_id').'&hdid='.I('get.hdid')));
	}*/
	
	//$id = abs(intval($_GET['id']));
$weixin =M('weixin')->getById($weixinid); 
	//$uid = $_GET['uid'];
	$code=I('get.code');	
	
	//$code=I('get.uid'); 
$fx_id = I('get.fx_id');
/*		$id =  I('get.id'); //获取传的用户ID值；*/
		if(session('uid')){
				$this->wxuser=$udata=M(tablecopy('wxuser',$weixinid))->getById(session('uid'));
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
		$this->wxuser=$wx_user =M(tablecopy('wxuser',$weixinid))->getByWx($openid); 
					session('uid',$wx_user['id']);
					session('wx',$wx_user['wx']);
					redirect(U('yanzheng/index?fx_id='.$fx_id));
				}
			}else
			{
				header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn'.urlencode('/xcrm/index.php?s=/Wap/yanzheng/index/fx_id/'.$fx_id)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect");
			}
	
}
	}

    
public function index()
{$weixinid=2;
$hdid=45;
$Model= M(tablecopy('zhuli',$hdid));
$wxuser=$this->wxuser;
$fx_user = M(tablecopy('wxuser',$weixinid))->getById(I('fx_id'));
$wx_zl= $Model->where("hdid = '$hdid' and uid = '".$fx_user['id']."'")->order('zlb desc')->find();
$zljl1=explode(',',$wx_zl['cgzl']);
$hdjs=unserialize($fx_user['hdjs']);
$cgzls=count($zljl1)-1-$wx_zl['xfjfs'];
$klq=floor ($cgzls/3);
if($wxuser['qx']>0){
if($hdjs[$wxuser['qx']]==0){
if($cgzls>0){
	$ms= '在本店可以兑换一枚(共可领取'.(($klq>12)?12:$klq).'枚)';
	if(IS_POST){
		
		$hdjs[$wxuser['qx']]=1;
		$wx_zl['xfjfs']=$wx_zl['xfjfs']+3;
		$fx_user['hdjs']=serialize($hdjs);
		  if(M(tablecopy('wxuser',$weixinid))->save($fx_user)&&$Model->save($wx_zl)){
			  $data['mid']=$wxuser['id'];
			   $data['uid']=$fx_user['id'];
			    $data['qx']=$wxuser['qx'];
			   $data['time']=time();
			   M('yzjl')->add($data);
			 $ms= '成功兑换一枚';
		  }	
		}
	
	}else{
$ms= '您没有足购的状元币';	
	}
	
	}else{
		$ms= '此勋章您已兑换过了';	

		}
	}else{
		$ms= '您不是管理员';
}
	$this->assign('ms',$ms); 
	$this->assign('fx_user',$fx_user); 
	$this->assign('wx_zl',$wx_zl);  //分享用户排行榜
$this->display();	
}
public function addwx(){
	$str = get_client_ip();
	$ip = getClientIp();
	//	print_r($Model);
	echo $str."----------".$ip;
}
public function weixinadd(){

	$hdid = 45;
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id');
	$tjrid=I('post.tjrid');
	$Model = M(tablecopy('zhuli',$hdid));
	$wx_has_in = $Model->where('hdid = '.$hdid.' and uid = '.$id)->order('zlb desc')->select();
	if(empty($wx_has_in))
	{			
		//更新用户信息，填写用户姓名和手机号；
		$User = M(tablecopy('wxuser',$weixinid)); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['name'] = $name;
		$data['tel'] = $tel;
		$data['address'] = getClientIp();
		$data['wxtime'] = time();
		$data['tjrid'] = $tjrid;
		$data['hdjs']=serialize(array('1'=>0,'2'=>0,'3'=>0,'4'=>0,'5'=>0,'6'=>0,'7'=>0,'8'=>0,'9'=>0,'10'=>0,'11'=>0,	'12'=>0));	
		$User->where("id=".$id)->save($data); // 根据条件更新记录
		//填写用户助力信息，初始化50助力币；
		$User =M(tablecopy('zhuli',$hdid));
		$data['uid'] =$id;
		$data['zlb'] = 100;
		$data['tel']=$tel;
		$data['name']=$name;
		$data['hdid'] = $hdid;
		$flag = $User->add($data);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect(U('yazheng/index')); 
	}else{
		//更新用户信息，填写用户姓名和手机号；
		/*$Model = new Model();
		$Model->execute("update __PREFIX__wxuser set name = 'wangwang' and tel = '15200000000' where id = '221097'");*/
		$User = M(tablecopy('wxuser',$weixinid)); // 实例化User对象
		// 要修改的数据对象属性赋值
		$data['name'] = $name;
		$data['tel'] = $tel;
		$data['address'] = getClientIp();
		$data['wxtime'] = time();
		$User->where("id=".$id)->save($data); // 根据条件更新记
		
		redirect(U('xunbao/index'));
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
			$zljl=explode(',',$wx_zl['zljl']);
			$j=1;
			foreach($zljl AS $one){
				if($one==$wxid  && ($wxid != 224662)){
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
					$upzhuli= M(tablecopy('zhuli',$hdid));;
		     	$updata=array('zlb' =>$wx_zl['zlb']+20,'zljl' =>$wx_zl['zljl'].$wxid.',');
						if($wxus['wxus']&&$wxus['pic']&&$wxus['address']=='山东枣庄'){
						$updata['cgzl']=$wx_zl['cgzl'].$wxid.',';
						$cgzls=floor (count(explode(',',$updata['cgzl']))/5);		
						if($cgzls>11){
							$cgzls=11;
							}				
						$hdjs=unserialize($wxus1['hdjs']);
						for($i=1;$i<=$cgzls;$i++){
							if($hdjs[$i]==0){
								$hdjs[$i]==1;
								}
							}
							
							$cgzly='在您的帮助下'.$wxus1['name'].'向前行进了1000米宝藏更近了.';
							}
							else{
							$cgzly='您和'.$wxus1['name'].'遭遇怪兽，虽然打败了怪兽但只走了1米.';

								}
						$upzhuli->where('id = '.$wx_zl['id'])->save($updata);
				
					echo $cgzly.','.($wx_zl['zlb']+20);
					
		            
					
				}
				else
				{
					echo '您已经帮过他了？,'.$wx_zl['zlb'];
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