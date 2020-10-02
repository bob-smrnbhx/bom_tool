<?php
namespace Wap\Controller;
class WxapiController {	
public function _initialize(){
 }
public function index(){
if( I('server.REQUEST_METHOD')=='GET' ){
echo I('get.echostr');
  exit;
  }
}
		
/** 助力提示内容  **/
	
}?>