<?php
return array(
	/* 数据库设置 */
    'DB_TYPE'               =>  'mysqli',     // 数据库类型
	'DB_HOST'               =>  'localhost', // 服务器地址
    'DB_NAME'               =>  'dev',          // 数据库名
	'DB_USER'               =>  'swc',      // 用户名
	'DB_PWD'                =>  'swc',          // 密码
    'DB_PORT'               =>  '3306',        // 端口
    'DB_PREFIX'             =>  'xy_',    // 数据库表前缀
    'DB_FIELDTYPE_CHECK'    =>  false,       // 是否进行字段类型检查
    'DB_FIELDS_CACHE'       =>  true,        // 启用字段缓存
    'DB_CHARSET'            =>  'utf8',      // 数据库编码默认采用utf8
    'DB_BIND_PARAM'         =>    true,
    'DB_SQL_BUILD_CACHE' => true,
);