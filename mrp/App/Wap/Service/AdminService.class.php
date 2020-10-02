<?php
namespace Wap\Service;

class AdminService extends CommonService {
	  private function httpGet($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 500);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_URL, $url);

    $res = curl_exec($curl);
    curl_close($curl);

    return $res;
  }  
 public function postxml($url, $jsonData){
 $ch = curl_init($url) ;
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
// curl_setopt($curl, CURLOPT_REFERER, $fromurl);
 if($jsonData){
 curl_setopt($ch, CURLOPT_POSTFIELDS,$jsonData);}
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: text/xml'));
$result = curl_exec($ch) ;
 curl_close($ch) ;
 return $result;
 }
private   function httppost($url, $jsonData){
 $ch = curl_init($url) ;
 curl_setopt($ch, CURLOPT_POST, 1);
 curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
 if($jsonData){
 curl_setopt($ch, CURLOPT_POSTFIELDS,$jsonData);}
 curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
 curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
 curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
 curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
$result = curl_exec($ch) ;
 curl_close($ch) ;
 return $result;
 }
private function createNonceStr($length = 16) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
      $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
  }
public function getAccessToken($pid,$weixin,$new=0) {
    // access_token 应该全局存储与更新，以下代码以写入到文件中做示例
	if(!$weixin){
	$weixin =M('weixin')->getById($pid); 
	}
    $data = unserialize($weixin['access_token']);
    if ($data['expire_time'] < time()||$new) {
      // 如果是企业号用以下URL获取access_token
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
      $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$weixin['appid']."&secret=".$weixin['appsecret'];
      $res = json_decode($this->httpGet($url));
      $access_token = $res->access_token;
      if ($access_token) {
        $data['expire_time'] = time() + 600;
        $data['access_token'] = $access_token;
		$upda=array('id'=>$pid,'access_token'=>serialize($data));
		$weixin =M('weixin')->save($upda);
      }
    } else {
      $access_token = $data['access_token'];
    }
    return $access_token;
  }
public function getJsApiTicket($pid,$weixin,$new=0) {
    // jsapi_ticket 应该全局存储与更新，以下代码以写入到文件中做示例
	if(!$weixin){
	$weixin =M('weixin')->getById($pid);  
	}
	$data =unserialize($weixin['jssdk']);
    if ($data['expire_time'] < time()||$new) {
     $accessToken = $this->getAccessToken($pid,$weixin,$new);
      // 如果是企业号用以下 URL 获取 ticket
      // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
      $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=".$accessToken;
      $res = json_decode($this->httpGet($url));
      $ticket = $res->ticket;
      if ($ticket) {
        $data['expire_time'] = time() + 7000;
        $data['jsapi_ticket'] = $ticket;
		$upda=array('id'=>$pid,'jssdk'=>serialize($data));
		$weixin =M('weixin')->save($upda);
      }
    } else {
      $ticket = $data['jsapi_ticket'];
    }

    return $ticket;
  }
public function getSignPackage($pid,$weixin,$new=0) {
	 	if(!$weixin){
	$weixin =M('weixin')->getById($pid); 
	}
 $jsapiTicket = $this->getJsApiTicket($pid,$weixin,$new);
  // 注意 URL 一定要动态获取，不能 hardcode.
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
    $timestamp = time();
    $nonceStr = $this->createNonceStr();

    // 这里参数的顺序要按照 key 值 ASCII 码升序排序
    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";

    $signature = sha1($string);

    $signPackage = array(
      "appId"     =>$weixin ['appid'],
      "nonceStr"  => $nonceStr,
      "timestamp" => $timestamp,
      "url"       => $url,
      "signature" => $signature,
      "rawString" => $string
    );
    return $signPackage; 
  }   
  
public function getqrcode($pid,$weixin,$uid,$scene_id='') {
    // qrcode  存储与更新， 传入商户ID，微信（数组），用户ID 和 场景ID
	if(!$weixin){
	$weixin =M('weixin')->getById($pid); 
	}
	$scene=0;
	if($scene_id){
	
	$wxuser =M('wxuser')->getByScene_id($scene_id);
	if(!$wxuser){
		$scene=1;
		}
}else{
	$scene_id=$weixin['ewmjs']+1;
   $wdata['id'] =$pid;
   $wdata['ewmjs'] =$scene_id;
	M('weixin') ->save($wdata);
	$wxuser =M('wxuser')->getById($uid);
	} 
	$data =$wxuser['ticket'];
    if (!$data) {
     $accessToken = $this->getAccessToken($pid,$weixin,1);
      $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$accessToken;
	  if($scene_id<100000){
	  $postdata='{"action_name": "QR_LIMIT_SCENE", "action_info": {"scene": {"scene_id": '.$scene_id.'}}}';
	  }else{
		  $postdata='{"expire_seconds": 604800, "action_name": "QR_SCENE", "action_info": {"scene": {"scene_id": '.$scene_id.'}}}';
	  }
      $res = json_decode($this->httppost($url,$postdata));
      $ticket = $res->ticket;
      if ($ticket&&!$scene&&$scene_id<100000) {
        $udata['id'] =$uid;
        $udata['ticket'] = $ticket;  
		$udata['scene_id'] = $scene_id;
		$weixin =M('wxuser')->save($udata);
      }else if($ticket&&$scene){
		  //怎么用这一块。。。。。。。。。。。。
		  }
    } else {
      $ticket = $data;
    }
	$qrcodedate['qrcode']='https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.urlencode($ticket);
	$qrcodedate['scene_id']=$scene_id;$qrcodedate['ticket']=urlencode($ticket);
   return $qrcodedate;
  }

public function choujiang($uid,$id){

$news=M('huodong')->getById($id);
$weixinuserm=M(tablecopy('wxuser',$news['weixinid']));
$wxuser=$weixinuserm->getById($uid);

$prize_arr=unserialize($news['jp']);

foreach ($prize_arr as $key => $val) { 

    $arr[$val['id']] = $val['v']; 

}

 $i=0;

 $zj=unserialize($news['zj']);

 foreach($zj as $one){

	if ($one['uid']==$uid){

		$yzj=1;

		$zjx=$one['jx'];

		break;

		}

	}
	if($wxuser ['hdjs']){
	 $cs=unserialize($wxuser ['hdjs']);
		$csjs=0;
		$idjc=0;
		$i=0;
	foreach($cs as $one){
		$csjs=0;
		if(!intval($one['time']))
		{ $upuser=$weixinuserm;
		   $updata=array('id' => $uid,'hdjs'=>serialize(array(array('hdid'=>$id,'cs'=>'1','time'=>date("md")))));
		   $upuser->save($updata);
	//		DB::Update('xy_wxuser', array('id' => $uid),array('hdjs'=>serialize(array(array('hdid'=>$id,'cs'=>'1','time'=>date("md"))))));
		$csjs=1;
		$idjc=1;
		break;	}		
	if (intval($one['hdid'])==$id ){
		$idjc=1;
		if(intval($one['time'])==intval(date("md"))){
		if(intval($one['cs'])<intval($news['cs'])){
		$cs[$i]['cs']++;
		
		 $upuser=$weixinuserm;
		   $updata=array('id' => $uid,'hdjs'=>serialize($cs));
		   $upuser->save($updata);		
	//	DB::Update('xy_wxuser', array('id' => $uid),array('hdjs'=>serialize($cs)));
		$csjs=1;
		break;
		}
		}else{
$cs[$i]['time']=intval(date("md"));
 $upuser=$weixinuserm;
		   $updata=array('id' => $uid,'hdjs'=>serialize($cs));
		   $upuser->save($updata);		
//DB::Update('xy_wxuser', array('id' => $uid),array('hdjs'=>serialize($cs)));
		$csjs=1;
		break;	
		}
}
$i++;

	}
	if(!$idjc){
		$cs[]=array('hdid'=>$id,'cs'=>'1','time'=>date("md"));
		 $upuser=$weixinuserm;
		   $updata=array('id' => $uid,'hdjs'=>serialize($cs));
		   $upuser->save($updata);		
		//DB::Update('xy_wxuser', array('id' => $uid),array('hdjs'=>serialize($cs)));
$csjs=1;
		}
	
		
		}else{
			 $upuser=$weixinuserm;
		   $updata=array('id' => $uid,'hdjs'=>serialize(array(array('hdid'=>$id,'cs'=>'1','time'=>date("md")))));
		   $upuser->save($updata);		
//DB::Update('xy_wxuser', array('id' => $uid),array('hdjs'=>serialize(array(array('hdid'=>$id,'cs'=>'1','time'=>date("md"))))));
$csjs=1;}
	if(!$csjs){
		
		$rid=7;

		$prize_arr[$rid-1]['prize']='今天的机会用完了明天再来吧';

		}
		
		 if($yzj==1||0){//先关闭中奖控制

		$rid=7;

		$prize_arr[$rid-1]['prize']='已中['.$prize_arr[$zjx-1]['prize'].'] 请继续关注';

		}else if($csjs){
$i=0;
	while($i==0){

	 $rid = $this->getRand($arr); //根据概率获取奖项id 

 if($prize_arr[$rid-1]['sl']>0){
	 $i=1;
  }else{
	  	$rid=7;

		$prize_arr[$rid-1]['prize']='未中奖请继续努力';

	  }
 }}
$res = $prize_arr[$rid-1]; //中奖项
//echo $prize_arr[$rid-1]['sl'];
if($rid!=7&&$res['id']){
$zj[]=array('uid'=>$uid,'jx'=>$res['id'],'zt'=>0);
//$jp=$prize_arr[$rid-1]['prize'];
$prize_arr[$rid-1]['sl']=$prize_arr[$rid-1]['sl']-1;
if($res['id']&&$uid){
	 $upuser=M('huodong');
		   $updata=array('id' => $id,'zj'=>serialize($zj),'jp'=>serialize($prize_arr));
		   $upuser->save($updata);		 
		   $upuser=M('zjjl');
		   $updata=array('createtime'=>time,'uid'=>$uid,'hdid'=>$id,'jpname'=>$prize_arr[$rid-1]['prize'],'tel'=>$wxuser['tel']);
		   $upuser->add($updata);
	//DB::Update('xy_huodong', array('id' => $id),array('zj'=>serialize($zj),'jp'=>serialize($prize_arr)));
}
	} 
$result=array('angle'=>'0','prize'=>'没奖了','res'=>$res['id']);
//$rid = getRand($arr); //根据概率获取奖项id 
 //$res = $prize_arr[$rid-1]; //中奖项 

$min = $res['min']; 

$max = $res['max']; 

if($res['id']==7){ //七等奖 

    $i = mt_rand(0,5); 

    $result['angle'] = mt_rand($min[$i],$max[$i]); 
		$res['prize']='未中奖请继续努力';

}else{ 

    $result['angle'] = mt_rand($min,$max); //随机生成一个角度 
if($res['prize']){
	$res['prize']='恭喜你!中得'.$res['prize'];
}else{
		$res['prize']='未中奖请继续努力';

	}
}
 $result['prize'] =$res['prize']; 
return $result;
}  
    public function login($admin) {
	
       
        if (!$this->existAccount($admin['username'])) {
			return array('status' => 0,
                     'data' => '用户不存在！');
        }

        $account = M('user')->getByUsername($admin['username']);
       
        if ($account['password'] != $this->encrypt($admin['password'])) {
			return array('status' => 0,
                     'data' => '密码不正确！');
        }

       session('uid',$account['id']);
	   session('username',$account['username']);
	   session("truename",$account['truename']);
	   session('depname',$account['depname']);
	   session('posname',$account['posname']);
	   session('loginip',get_client_ip());
	   session('logintime',date("Y-m-d H:i:s",time()));
	   session('logins',$account['logins']);

        $data['id'] = session('uid');
        $data['logintime'] = date("Y-m-d H:i:s",time());
        $data['loginip'] = get_client_ip();
		$data['logins'] = array('exp','logins+1');
		$data['update_time']=time();
        M("user")->save($data);
		  
		 
          
        $dat['username'] = session('username');
        $dat['content'] = '登录成功！';
		$dat['os']=$_SERVER['HTTP_USER_AGENT'];
        $dat['url'] = U();
        $dat['addtime'] = date("Y-m-d H:i:s",time());
        $dat['ip'] = get_client_ip();
        M("log")->add($dat);

        return array('status' => 1);
    }


    public function logout() {
		$dat['username'] = session('username');
        $dat['content'] = '退出成功！';
		$dat['os']=$_SERVER['HTTP_USER_AGENT'];
        $dat['url'] = U();
        $dat['addtime'] = date("Y-m-d H:i:s",time());
        $dat['ip'] = get_client_ip();
        M("log")->add($dat);
        session_unset();
		session_destroy();
    }
public 	function getRand($proArr) { 
    $result = ''; 
    //概率数组的总概率精度 
    $proSum = array_sum($proArr); 
     //概率数组循环 
    foreach ($proArr as $key => $proCur) { 
        $randNum = mt_rand(1, $proSum); 
        if ($randNum <= $proCur) { 
            $result = $key; 
            break; 
        } else { 
            $proSum -= $proCur; 

        } 

    }

    unset ($proArr); 

    return $result; 

} 



 public function existAccount($username) {
        if (M('user')->where(array("username"=>$username,"status"=>1))->count() > 0) {
            return true;
        }
        return false;
    }
	
	
    public function encrypt($data) {
        //return md5(C('AUTH_MASK') . md5($data));
		return md5(md5($data));
    }
}
