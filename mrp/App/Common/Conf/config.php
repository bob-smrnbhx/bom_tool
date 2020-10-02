<?php
return array( 


    'URL_MODEL'   =>  1,       // URL访问模式,可选参数0、1、2、3,代表以下四种模式：
	
    'URL_CASE_INSENSITIVE' =>true, //表示URL访问不区分大小写
   
	//权限验证设置
	'AUTH_CONFIG'=>array(
        'AUTH_ON' => true, 
        'AUTH_TYPE' => 1, // 认证方式，1为时时认证；2为登录认证。
        'AUTH_GROUP' => 'xy_auth_group', 
        'AUTH_GROUP_ACCESS' => 'xy_auth_group_access', 
        'AUTH_RULE' => 'xy_auth_rule', 
        'AUTH_USER' => 'xy_user'
    ),
	'NOT_AUTH_MODULE' => 'Public,Index', // 默认无需认证模块
    //超级管理员id,拥有全部权限,只要用户uid在这个角色组里的,就跳出认证.可以设置多个值,如array('1','2','3')
    'ADMINISTRATOR'=>array('1'),
	'SESSION_OPTIONS' =>  array('expire'=>36000),
	'SESSION_PREFIX'        =>  'xykj', 
	
	 // 加载扩展配置文件 多个用,隔开
	'LOAD_EXT_CONFIG' => 'web,db', 
	

        
	//上传设置
	'UPLOAD_MAXSIZE'=>31457280,
	'UPLOAD_EXTS'=>array('jpg','gif','png','jpeg','txt','doc','docx','xls','xlsx','ppt','pptx','pdf','rar','zip','wps','wpt','dot','rtf','dps','dpt','pot','pps','et','ett','xlt'),// 设置附件上传类型 
	'UPLOAD_SAVEPATH'=>'./Public/',

    'MAIL_HOST' =>'smtp.163.com',//smtp服务器的名称
    'MAIL_SMTPAUTH' =>TRUE, //启用smtp认证
    'MAIL_USERNAME' =>'ghost9698@163.com',//你的邮箱名
    'MAIL_FROM' =>'ghost9698@163.com',//发件人地址
    'MAIL_FROMNAME'=>'胜维德赫华翔汽车镜有限公司mrp系统',//发件人姓名
    'MAIL_PASSWORD' =>'123456',//邮箱密码
    'MAIL_CHARSET' =>'utf-8',//设置邮件编码
    'MAIL_ISHTML' =>TRUE, // 是否HTML格式邮件
    
);
