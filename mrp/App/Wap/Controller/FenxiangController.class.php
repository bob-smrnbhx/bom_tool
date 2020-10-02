<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class FenxiangController extends Controller {	
public $wxuser = array();
public function _initialize(){

	if(ACTION_NAME!='weixinadd'){
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
						$User = M("wxuser"); // 实例化User对象
						$data['wxtime'] = time();
						$data['address'] = getClientIp();
						$User->where("wx='$openid'")->save($data);
					}	
					session('uid',$wx_user['id']);
					session('wx',$wx_user['wx']);
					redirect(U('Fenxiang/index?fx_id='.$fx_id.'&hdid='.$hdid));
				}
			}else
			{
				header("location: https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn'.urlencode('/xcrm/index.php?s=/Wap/Fenxiang/index/fx_id/'.$fx_id.'/hdid/'.$hdid)."&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect");
			}
		}else{	
			$this->wxuser=$udata;
		}
	}
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
	$this->assign('signPackage',$signPackage);

	$id =  empty(I('get.id'))? session('uid') : I('get.id') ; //获取传的用户ID值；
	$fx_id = I('get.fx_id'); // 获取是否存在分享者 */
	
	/** 分享币数据前100名； **/
	$Model = new Model();
	$fx_list = $Model->query("select * from __PREFIX__fenxiang where hdid = '$hdid' and uid != 0 GROUP BY uid order by fxb desc limit 100");
	$this->assign('fx_list',$fx_list);

	/**进入当前页面，增加活动的阅读次数  **/
	$Model = new Model();
	$Model->execute("update __PREFIX__huodong set cs = cs+1 where id = '$hdid'");

	// 获取活动的阅读次数；
	$Huodong =  M('Huodong');
	$ydcs = $Huodong->where("id='$hdid'")->sum('cs');
	$this->assign('ydcs',$ydcs); 
	//活动参加人数；
	$Fenxiang = M('Fenxiang');
	$t_join = $Fenxiang->where("hdid='$hdid'")->count();
	$this->assign('t_join',$t_join);
	//活动分享次数；
	$Fenxiang = M('Fenxiang');
	$t_fxcs = $Fenxiang->where("hdid='$hdid'")->sum("number");
	$this->assign('t_fxcs',$t_fxcs);
	//活动总分享币；
	$Fenxiang = M('Fenxiang');
	$t_fxb = $Fenxiang->where("hdid='$hdid'")->sum("fxb");
	$this->assign('t_fxb',$t_fxb);
	
	$wxuser=$this->wxuser;
	
	// 如果获取用户的ID,则直接从数据表中获取信息；
	if($id)
	{
		$Fenxiang = M('Fenxiang');
		$wx_fx = $Fenxiang->where("hdid = '$hdid' and uid = '$id'")->order('fxb desc')->find();
		//print_r($wx_zl);
		if($wx_fx['tel'] && $wx_fx['name']&&$wx_fx ) //用户已经参加活动，显示助力币； 
		{
			$is_join = 1;
			$coin = $wx_fx['zlb'];
		}
		else{ // 没有参加活动，提示用户参加活动；
			$is_join = 0;
		}
	}
	
	// 当前是首次进入活动页面；
	if(empty($fx_id) && $id){ 
		$fx_id = $id;				
	}
	
	//增加分享者的阅读量；
	if($fx_id && $id)
	{
		$Fenxiang = M("Fenxiang"); // 实例化User对象
		$map['uid'] = $fx_id;
		$map['hdid'] = $hdid;// 把查询条件传入查询方法
		$wx_fx = $Fenxiang->where($map)->order('fxb desc')->find();
		$user_list = explode(',',$wx_fx['fxjl']);
		//判断活动状态；
		if($news['jssj'] > time())
		{
			//用户不在列表时，更新操作，增加助力值；
			if((!in_array($id,$user_list) && $wx_fx) || ($id == 224662))
			{                                     
				$ip = getClientIp();
			
				$url = "http://ip.taobao.com/service/getIpInfo.php?ip=".$ip;
				
				$homepage = file_get_contents($url);
				
				$json_data = json_decode($homepage,true);
				//mb_substr($json_data["data"]["city"],0,2, 'utf-8') == "山东"
				if(mb_substr($json_data["data"]["city"],0,2, 'utf-8') == "枣庄" )
				{
					if($news['jssj']>time()){
						$User = M("Fenxiang"); // 实例化对象
						$data['fxb'] = $wx_fx['fxb']+20;
						$data['fxjl'] = $wx_fx['fxjl'].$id.',';
						$User->where('id='.$wx_fx['id'])->save($data); 	
						$is_help = 0; // 没有帮其增加阅读数；
					}
					else
					{
						$is_help = 1;
					}
				}
				else
				{
					$is_help = 1; // 已经帮其增加阅读数；
				}
			}
			else
			{
				$is_help = 1; // 已经帮其增加阅读数；
			}
		}
		else
		{
			$is_help = 2;
		}
		//分享者的用户信息；
		$fx_user = $wx_fx;
		
		//获取分享者已获取的分享币，分享次数；
		$fx_coin = $wx_fx['fxb'];
		$fx_count = $wx_fx['number'];
		
		//分享的用户的排名；
		$Model = new Model();
		$sql_ph = $Model->query("select (select count(*)+1 as count from __PREFIX__fenxiang as fx where fx.fxb > wfx.fxb and fx.hdid = '$hdid' order by fx.fxb DESC,fx.id DESC) as count from __PREFIX__fenxiang as wfx where wfx.hdid = '$hdid' and  wfx.uid= '$fx_id'");
		$position = $sql_ph[0]['count'];
	}
	
	
	$this->assign('wx_user',$wxuser); 
	$this->assign('fx_user',$fx_user); 
	$this->assign('is_join',$is_join); 
	$this->assign('is_help',$is_help); 
	$this->assign('fx_count',$fx_count); 
	$this->assign('fx_coin',$fx_coin); 
	$this->assign('wx_id',$id); 
	$this->assign('fx_id',$fx_id); 
	$this->assign('hdid',$hdid);
	//$this->assign('id',22); 
	$this->assign('openid',session('wx')); 
	$this->assign('position',$position);  //分享用户排行榜
	//echo T('Fenxiang/index'.$hdid);
	$this->display(T('Fenxiang/index'.$hdid));
}
/*	public function addwx(){
		$Model = new Model();
		$Model->execute("update __PREFIX__wxuser set name = 'wangwang' and tel = '15200000000' where id = '221099'");
		print_r($Model);
	}*/
	
// 分享活动，添加用户操作；
public function weixinadd(){

	$hdid = I('post.hdid');
		//$is_add = I('post.is_add'); // 判断微信用户是否提交用户信息；
	$name = I('post.name');
	$tel = I('post.tel');
	$id=I('post.id'); 
		//判断用户是否已经参加活动；
	$Model = new Model();
	$wx_has_in = $Model->query("select * from __PREFIX__fenxiang where hdid = '$hdid' and uid = '$id' order by fxb desc");
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
		$Fenxiang = M("Fenxiang");
		$data['uid'] =$id;
		$data['fxb'] = 100;
		$data['tel']=$tel;
		$data['name']=$name;
		$data['hdid'] = $hdid;
		$flag = $Fenxiang->add($data);
		//如果用户参加活动成功，则跳转到自己的页面；
		redirect(U('Fenxiang/index?hdid='.$hdid)); 
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
		
		redirect(U('Fenxiang/index?hdid='.$hdid)); 
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
	
/** 分享内容到朋友圈的操作  **/
public function fenxiangapi(){

	$hdid=I('get.hdid');
	$wx_id=I('get.wx_id');
	$fx_id=I('get.fx_id');
	
	//分享用户信息；
	$wx_user=M('wxuser')->getById($wx_id); 
	//分享信息；
	$Fenxiang=M('Fenxiang');
	$fx_res=$Fenxiang->where('hdid='.$hdid.' and uid='.$wx_id)->order('fxb desc')->find();
	
	if($news['jssj']>time()){
		//如果用户参加活动；
		if($fx_res && $fx_res['name'] && $fx_res['tel'])
		{
			// 如果是自己分享自己的内容；
			if($wx_id && $fx_id == $wx_id)
			{
				$time = getdate($fx_res['time']);
				$time_n = getdate(time());
				if($time['yday'] !=  $time_n['yday'])
				{
					$Fenxiang = M("Fenxiang"); // 实例化User对象
					$data['time'] = time();
					$data['fxb'] = $fx_res['fxb']+100;
					$data['number'] = $fx_res['number']+1;
					$Fenxiang->where("id=".$fx_res['id'])->save($data);
				}
			}
			// 其他用户分享的内容；
			else
			{
				$Fenxiang = M("Fenxiang"); // 实例化User对象
				$data['fx_number'] = $fx_res['number']+1;
				$Fenxiang->where("id=".$fx_res['id'])->save($data);
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