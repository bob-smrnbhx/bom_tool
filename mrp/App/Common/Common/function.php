<?php

// 校验验证码	@return boolean
   function checkVerify(){
	$verify = new \Think\Verify();
	return $verify->check(I('verify'));
   }

   
   /**
    * WZ自定义方法，用以将原始的只是用”模型+控制器+动作“的检查名称，扩展为”模型+控制器+动作+地点参数“的检查名称。
    * @param $actionName : string: 可另选的动作名称
    * @return string
    */
   function getAuthCheckName ($actionName = ACTION_NAME, $controllerName = CONTROLLER_NAME, $moduleName = MODULE_NAME)
   {
       if (isset($_REQUEST["site"])) {
           return $moduleName . '/' . $controllerName . '/' . $actionName . "/site/" . $_REQUEST["site"];
       } else {
           return $moduleName . '/' . $controllerName . '/' . $actionName;
       }
       
   }
   
	/**
      * 检查权限
      * @param name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
      * @param uid  int           认证用户的id
      * @param string mode        执行check的模式
      * @param relation string    如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
      * @return boolean           通过验证返回true;失败返回false
     */
	function authcheck($name, $uid, $type=1, $mode='url', $relation='or'){
		if(!in_array($uid,C('ADMINISTRATOR'))){ 
	    	$auth=new \Think\Auth();
	    	return $auth->check($name, $uid, $type, $mode, $relation)?true:false;
	    }else{
	    	return true;
	    }
	}
   
   function display($name){
   	$name='Home/'.$name;
	$uid=session('uid');
	if(!in_array($uid,C('ADMINISTRATOR'))){
	if(!authcheck($name, $uid, $type=1, $mode='url', $relation='or')){
	return "style='display:none'";
	 }
	}
   }
	


function cateTree($pid=0,$level=0,$db=0){
    $cate=M(''.$db.'');
    $array=array();
    $tmp=$cate->where(array('pid'=>$pid))->order("sort")->select();
    if(is_array($tmp)){
        foreach($tmp as $v){
            $v['level']=$level;
            //$v['pid']>0;
            $array[count($array)]=$v;
            $sub=cateTree($v['id'],$level+1,$db);
            if(is_array($sub))$array=array_merge($array,$sub);
        }
    }
    return $array;
}

function orgcateTree($pid=0,$level=0,$type=0){
    $cate=M('auth_group');
    $array=array();
    $tmp=$cate->where(array('pid'=>$pid,'type'=>$type))->order("sort")->select();
    if(is_array($tmp)){
        foreach($tmp as $v){
            $v['level']=$level;
            //$v['pid']>0;
            $array[count($array)]=$v;
            $sub=orgcateTree($v['id'],$level+1,$type);
            if(is_array($sub))$array=array_merge($array,$sub);
        }
    }
    return $array;
}

function cateTreed($pid=0,$level=0){
    $cate=M('datalist');
    $array=array();
    $tmp=$cate->where(array('pid'=>$pid))->order("sort")->select();
    if(is_array($tmp)){
        foreach($tmp as $v){
		    $v['level']=$level;
            //$v['pid']>0;
            $array[count($array)]=$v;
            $sub=cateTreed($v['id'],$level+1);
            if(is_array($sub))$array=array_merge($array,$sub);
        }
    }
    return $array;
}

/**
 * 格式化字节大小
 * @param  number $size      字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function format_bytes($size, $delimiter = '') {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 调用系统的API接口方法（静态方法）
 * api('User/getName','id=5'); 调用公共模块的User接口的getName方法
 * api('Admin/User/getName','id=5');  调用Admin模块的User接口
 * @param  string  $name 格式 [模块名]/接口名/方法名
 * @param  array|string  $vars 参数
 */
function api($name,$vars=array()){
    $array     = explode('/',$name);
    $method    = array_pop($array);
    $classname = array_pop($array);
    $module    = $array? array_pop($array) : 'Common';
    $callback  = $module.'\\Api\\'.$classname.'Api::'.$method;
    if(is_string($vars)) {
        parse_str($vars,$vars);
    }
    return call_user_func_array($callback,$vars);
}

function check_table_exist($tableName){
    $tableName = C('DB_PREFIX') . strtolower($tableName);
    $tables = M()->query('show tables');
    if(empty($tables)){
        exit('数据库中没有表');
    }
    foreach($tables as $v){
        if($v['tables_in_test']==$tableName){
            return true ;
        }
    }
    exit('数据库中没有 '.$tableName.' 表，请创建');
}

/**
 * 根据条件字段获取指定表的数据
 * @param mixed $value 条件，可用常量或者数组
 * @param string $condition 条件字段
 * @param string $field 需要返回的字段，不传则返回整个数据
 * @param string $table 需要查询的表
 * @author huajie <banhuajie@163.com>
 */
function get_table_field($value = null, $condition = 'id', $field = null, $table = null){
    if(empty($value) || empty($table)){
        return false;
    }

    //拼接参数
    $map[$condition] = $value;
    $info = M(ucfirst($table))->where($map);
    if(empty($field)){
        $info = $info->field(true)->find();
    }else{
        $info = $info->getField($field);
    }
    return $info;
}


function Hex($indata){
	$lX8 = $indata & 0x80000000;
	if($lX8)
	{
		$indata=$indata & 0x7fffffff;
	}
	while ($indata>16)
	{
		$temp_1=$indata % 16;
		$indata=$indata /16 ;
		if($temp_1<10)
		   $temp_1=$temp_1+0x30;
		else
		   $temp_1=$temp_1+0x41-10; 
		
		$outstring= chr($temp_1) . $outstring ; 
		   
	}
	$temp_1=$indata;
	if($lX8)$temp_1=$temp_1+8;
	if($temp_1<10)
	   $temp_1=$temp_1+0x30;
	else
	   $temp_1=$temp_1+0x41-10; 
	
	$outstring= chr($temp_1) . $outstring ; 
	
	return $outstring;
		 
}

/**
 * 字符串转换为数组，主要用于把分隔符调整到第二个参数
 * @param  string $str  要分割的字符串
 * @param  string $glue 分割符
 * @return array
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function str2arr($str, $glue = ','){
    return explode($glue, $str);
}

/**
 * 数组转换为字符串，主要用于把分隔符调整到第二个参数
 * @param  array  $arr  要连接的数组
 * @param  string $glue 分割符
 * @return string
 * @author 麦当苗儿 <zuojiazi@vip.qq.com>
 */
function arr2str($arr, $glue = ','){
    return implode($glue, $arr);
}

/**
 * 字符串截取，支持中文和其他编码
 * @static
 * @access public
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start=0, $length) {
	$charset="utf-8";
	$suffix=true;
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
        if(false === $slice) {
            $slice = '';
        }
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice.'' : $slice;
}



function getuserid(){
	return session("uid");
}


function gettruename(){
	return session("truename");
}

function gettime(){
	return date('Y-m-d H:i:s',time());
}

    function encrypt($data) {
        //return md5(C('AUTH_MASK') . md5($data));
		return md5(md5($data));
    }

//html代码输出
function html_out($str){
    if(function_exists('htmlspecialchars_decode')){
        $str=htmlspecialchars_decode($str);
    }else{
        $str=html_entity_decode($str);
    }
    $str = stripslashes($str);
    return $str;
}

function truename($id){
 $data=M('user')->where('id='.$id)->getField('truename');
 return $data;
}


function comname($id){
 $data=M('cust')->where('id='.$id)->getField('title');
 return $data;
}


// extender's functions

function compactNumber($n)
{
    if (!is_numeric($n)) {
        // if not numeric, return it as original
        return $n;
    }
    
    if (intval($n) == $n) {
        return intval($n);
    } else {
        return number_format(floatval($n), 2);
    }
    
    
}

/**
 * 邮件发送函数
 */
function sendMail($to, $title, $content) {
     
    Vendor('PHPMailer.PHPMailerAutoload');
    $mail = new PHPMailer(); //实例化
    $mail->IsSMTP(); // 启用SMTP
    $mail->Host=C('MAIL_HOST'); //smtp服务器的名称（这里以QQ邮箱为例）
    $mail->SMTPAuth = C('MAIL_SMTPAUTH'); //启用smtp认证
    $mail->Username = C('MAIL_USERNAME'); //你的邮箱名
    $mail->Password = C('MAIL_PASSWORD') ; //邮箱密码
    $mail->From = C('MAIL_FROM'); //发件人地址（也就是你的邮箱地址）
    $mail->FromName = C('MAIL_FROMNAME'); //发件人姓名
    $mail->AddAddress($to,"尊敬的客户");
    $mail->WordWrap = 50; //设置每行字符长度
    $mail->IsHTML(C('MAIL_ISHTML')); // 是否HTML格式邮件
    $mail->CharSet=C('MAIL_CHARSET'); //设置邮件编码
    $mail->Subject =$title; //邮件主题
    $mail->Body = $content; //邮件内容
    $mail->AltBody = "这是一个纯文本的身体在非营利的HTML电子邮件客户端"; //邮件正文不支持HTML的备用显示
    $success = $mail->Send();
 
    return $success;
}

