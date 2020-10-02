<?php
namespace Wap\Controller;
use Think\Controller;
use Think\Model;
class WxapiController extends Controller{	
public function _initialize(){
 }
public function index(){
header("Content-Type:text/html; charset=utf-8"); 
$weixin1=M('weixin')->getById(I('get.id'));	
define("TOKEN",$weixin1['token']);
define('DEBUG', true);
$weixin =new \Org\Util\Wxapilib(TOKEN,DEBUG); //实例化
$weixin->valid();
$weixin->getMsg();
$type = $weixin->msgtype;//消息类型
$username = $weixin->msg['FromUserName'];
$weixinuserm=M(tablecopy('wxuser',$weixin1['id']));
$weixinuser=$weixinuserm->getByWx($username);	
$sg=0;
if($weixinuser){
	$sg=1;
	}else{ 
	$sg=0;
$updata=array('wx'=>$username,'partner_id'=>$weixin1['id'],'wxtime'=>time(),'address'=>getClientIp());
	$wx_id = $weixinuserm->add($updata);		
$weixinuser=$weixinuserm->getById($wx_id); 
} 
$url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token='.D('Admin', 'Service')->getAccessToken($weixin1['id'],$weixin1).'&openid='.$username.'&lang=zh_CN'; 
$userinfo = cohttps_request($url);
$userinfo = json_decode($userinfo, true);
$uparray=array('id'=>$weixinuser['id'], 'name'=>$userinfo['nickname'],'pic'=>$userinfo['headimgurl'],'address'=>$userinfo['province'].$userinfo['city'],'sex'=>$userinfo['sex'],'hqxxzt' =>'1','wxtime'=>time());
$weixinuserm->save($uparray); 

if($type==='event'){
	if(($weixin->msg['Event']=='SCAN')||($weixin->msg['Event']=='subscribe'&&$weixin->msg['EventKey'])){
//		'qrscene_'.'110000'
if($weixin->msg['Event']=='SCAN'){
		$EventKey=intval($weixin->msg['EventKey']);
}else{
	$EventKey=intval(substr($weixin->msg['EventKey'],8));
	}
	if($EventKey>100000){
	$hongbao=M('hongbao')->getByScene_id($EventKey);
	if($hongbao['openid']==$username&&$hongbao['status']==3){
$token='oLEr3jkOAr4qKpZu';
$nonce=time();
$tmpArr = array($token, $hongbao['openid'], $nonce);
 sort($tmpArr, SORT_STRING);
$tmpStr = implode( $tmpArr );
$signature=$tmpStr = sha1( $tmpStr );
$url = "http://www.dscm.com.cn/diyapi/hongbao/index.php?yzstr=".$signature."&nonce=".$nonce."&openid=".$hongbao['openid']."&weixinid=".$hongbao['weixinid']."&id=".$hongbao['id'];
				$hongbao_json = cohttps_request($url);
				$hongbao_array = json_decode($hongbao_json, true);
				
		}
		
	}
	}else if($weixin->msg['Event']=='subscribe'){
			
		if($weixin1['apiurl']){
$url=htmlspecialchars_decode($weixin1['apiurl']);
$token=$weixin1['apitoken'];
$tmpArr = array($token, $_GET["timestamp"], $_GET["nonce"]);
        sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$signature=$tmpStr = sha1( $tmpStr );		
$jsonData=$GLOBALS["HTTP_RAW_POST_DATA"];
//$jsonData1= simplexml_load_string($jsonData, 'SimpleXMLElement', LIBXML_NOCDATA);
$url.='&signature='.$signature.'&timestamp='.$_GET["timestamp"].'&nonce='.$_GET["nonce"];
$xmldata=postxml($url,$jsonData);
$xmldata=strstr($xmldata,'<xml>');
//$xmldata=strstr($xmldata,'</item>',true);
$xmldata1=simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);
$xmldata1=$xmldata1->Articles;
$xmldata1=(array)$xmldata1->item; 
$record[]=array(
                    'title' =>$xmldata1['Title'].'第三方标题' ,
                    'description' =>$xmldata1['Description'],
                    'picurl' =>$xmldata1['PicUrl'],
                    'url' =>$xmldata1['Url'],
                );}
	if($sg==0&&$weixin1['hbtui']){
$hdid=$weixin1['glhd'];
$rs = D('Admin', 'Service')->choujiang('',$hdid);	 
if($rs['res']!=7&&$rs['res']){
list($usec, $sec) = explode(" ", microtime());
$strid=intval(((float)$usec + (float)$sec)*10)-intval(time()/100000000)*1000000000;
$qrcode=D('Admin', 'Service')->getqrcode(2,'',0,$strid);
$hongbao=M('hongbao'); 
$hbdata=array('scene_id'=>$strid,'openid1'=>$username,'ticket'=>$qrcode['ticket'],'createtime'=>time(),'status'=>3,'jx'=>$rs['res'],'weixinid'=>$weixin1['id']);
$hbid=$hongbao->add($hbdata);
				$record[]=array(
                    'title' =>'恭喜您中了一个红包点击去领取吧[测试]！',
                    'description' =>'打开后长按二维码识别点关注即可领红包',
                    'picurl' =>$qrcode['qrcode'],
                    'url' =>'http://www.dscm.com.cn/'.U('wxapi/qrcodeview').'&ticket='.$qrcode['ticket'].'&id='.$hbid,
					 );
				
}else{
		$record[]=array(
                    'title' =>'这个是空的扫下一个试试吧[测试]！'.$rs['res'].$rs['prize'],
                    'description' =>'打开后长按二维码识别点关注即可领红包',
                    'picurl' =>'132',
                    'url' =>'http://www.baidu.com',
					 );
	}
}
$results['items']=$record;
$reply = $weixin->makeNews($results);
	}
	
	}else if(($weixin->msg['Event']=='SCAN')||($weixin->msg['Event']=='subscribe'&&$weixin->msg['EventKey']=='qrscene_'.'110000')){
			$record[]=array(
 'title' =>'招财进宝A'.$weixin->msg['EventKey'],
 'description' =>'经过奢靡的贵族，走过皇室的雍雅，留恋在华丽的巴洛克；千回百转中，从至奢至华回归简单，该款木门整体造型设计时尚简约，褪去浮华。木门正中心的图案雕刻恰似一个元宝的横切面，寓意着招财进宝，财源滚滚。',
 'picurl' =>'http://www.dscm.com.cn/uploads/r/rihcxd1435202858/4/0/6/3/thumb_558b9dfa56b29.jpg',
 'url' =>'http://www.dscm.com.cn/index.php?g=Wap&m=Index&a=content&id=27&classid=56&token=rihcxd1435202858&wecha_id=oEKxUt2tjrZtG9e5CAjuFO41IjcU',
 );
 $results['items']=$record;
$reply = $weixin->makeNews($results);
 }else if($weixin->msg['Event']=='subscribe'){
	if($weixin1['apiurl']){$url=$weixin1['apiurl'];
$token=$weixin1['apitoken'];
$tmpArr = array($token, I("get.timestamp"), I("get.nonce"));
        sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$signature=$tmpStr = sha1( $tmpStr );		
$jsonData=$GLOBALS["HTTP_RAW_POST_DATA"];
//$jsonData1= simplexml_load_string($jsonData, 'SimpleXMLElement', LIBXML_NOCDATA);
$url.='&signature='.$signature.'&timestamp='. I("get.timestamp").'&nonce='.I("get.nonce");
$xmldata=postxml($url,$jsonData);
$xmldata=strstr($xmldata,'<xml>');
//$xmldata=strstr($xmldata,'</item>',true);
$xmldata1=simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);
$xmldata1=$xmldata1->Articles;
$xmldata1=(array)$xmldata1->item;
$record[]=array( 
                    'title' =>$xmldata1['Title'] ,
                    'description' =>$xmldata1['Description'],
                    'picurl' =>$xmldata1['PicUrl'],
                    'url' =>$xmldata1['Url'],
                );}
$record[]=array(
                    'title' =>'这里是标题' ,
                    'description' =>'内容',
                    'picurl' =>'http://这里是图片链接',
                    'url' =>'http://www.baidu.com/这里是链接',
                );
$results['items']=$record;
$reply = $weixin->makeNews($results);//整合所有
//$reply=$xmldata;

}else{
$url=htmlspecialchars_decode($weixin1['apiurl']);
$token=$weixin1['apitoken'];
$tmpArr = array($token, I("get.timestamp"), I("get.nonce"));
        sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$signature=$tmpStr = sha1( $tmpStr );		
$jsonData=$GLOBALS["HTTP_RAW_POST_DATA"];
//$jsonData1= simplexml_load_string($jsonData, 'SimpleXMLElement', LIBXML_NOCDATA);
$url.='&signature='.$signature.'&timestamp='. I("get.timestamp").'&nonce='.I("get.nonce");
$xmldata=postxml($url,$jsonData);
$xmldata=strstr($xmldata,'<xml>');
//$xmldata=strstr($xmldata,'</item>',true);
$xmldata1=simplexml_load_string($xmldata, 'SimpleXMLElement', LIBXML_NOCDATA);
$xmldata2=$xmldata1->Articles;
$xmldata3=(array)$xmldata2->item;
$record[]=array( 
                    'title' =>$xmldata3['Title'] ,
                    'description' =>$xmldata3['Description'],
                    'picurl' =>$xmldata3['PicUrl'],
                    'url' =>$xmldata3['Url'],
                );
//

$results['items']=$record;
$reply = $weixin->makeNews($results);
}
@ob_clean();
$weixin->reply($reply);
}
public function choujiang(){
	$wxuser=$this->wxuser;
	$id=I('get.id');$openid=I('get.openid');$uid=I('get.uid');
	if( $openid==$wxuser['wx']&&$uid=$wxuser['id']){
	 $rs = D('Admin', 'Service')->choujiang($wxuser['id'],19);	
	echo  json_encode($rs);
	}
	// $this->display();
}
	public function tablecs(){
 $rs = D('Admin', 'Service')->choujiang($wxuser['id'],19);	
print_r($rs);
echo $wxuser['id'];
/*
list($usec, $sec) = explode(" ", microtime());
$strid=intval(((float)$usec + (float)$sec)*10)-intval(time()/100000000)*1000000000;
$qrcode=D('Admin', 'Service')->getqrcode(2,'',0,$strid);
$hongbao=M('hongbao'); 
$hbdata=array('scene_id'=>$strid,'openid1'=>$username,'ticket'=>$qrcode['ticket'],'createtime'=>time(),'status'=>3,'jx'=>$rs['res'],'weixinid'=>$weixin1['id']);
echo $qrcode['ticket'];
$hbid=$hongbao->add($hbdata);*/

}
	public function qrcodeview(){	

$weixin =M('weixin')->getById(2); 

		$code=I('get.code');
		$hbid=I('get.id');
		$hongbao =M('hongbao')->getById($hbid); 
		$weixin =M('weixin')->getById(2); 
		$ticket=(I('get.ticket'));
		if($code){
				$access_token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$weixin['appid']."&secret=".$weixin['appsecret']."&code=".$code."&grant_type=authorization_code"; 
				$access_token_json = cohttps_request($access_token_url);
				$access_token_array = json_decode($access_token_json, true);
				$access_token = $access_token_array['access_token'];
				$openid = $access_token_array['openid'];
				if($openid&&!$hongbao['openid']&&urlencode($ticket)==$hongbao['ticket'])
				{
					 	$uphongbao=M('hongbao');
						$hongbaolist=$uphongbao->where(array('openid'=>$openid,'weixinid'=>$hongbao['weixinid']))->select();//保证同一公众号只领一次 
						if(!$hongbaolist){
				   		$updata=array('id'=>$hbid,'openid'=>$openid);
				  	   $uphongbao->save($updata);	
					   echo '红包登记成功';}else{
						   echo '非法操作';
						   }}
			}else
			{
				redirect("https://open.weixin.qq.com/connect/oauth2/authorize?appid=".$weixin['appid']."&redirect_uri=".'http://www.dscm.com.cn/'.urlencode(U('wxapi/qrcodeview').'&ticket='.$ticket.'&id='.$hbid)."&response_type=code&scope=snsapi_userinfo&state=STATE#wechat_redirect");
			}
	//echo $hongbao['openid'].htmlspecialchars_decode($hongbao['ticket']) ;
	echo "<img src=https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=".$ticket.">";
	echo '长按二维码识别领取红包（如没关注过红包发放平台须关注）';
	
	}
/** 助力提示内容  **/
	
}?>