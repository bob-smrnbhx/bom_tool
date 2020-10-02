/*
Navicat MySQL Data Transfer

Source Server         : root
Source Server Version : 50617
Source Host           : localhost:3306
Source Database       : xcrm

Target Server Type    : MYSQL
Target Server Version : 50617
File Encoding         : 65001

Date: 2016-11-04 12:31:17
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for xy_auth_group
-- ----------------------------
DROP TABLE IF EXISTS `xy_auth_group`;
CREATE TABLE `xy_auth_group` (
  `id` mediumint(8) NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL,
  `title` char(50) NOT NULL,
  `level` int(2) NOT NULL,
  `pid` int(4) NOT NULL,
  `sort` int(4) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `rules` varchar(2000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_auth_group_access
-- ----------------------------
DROP TABLE IF EXISTS `xy_auth_group_access`;
CREATE TABLE `xy_auth_group_access` (
  `uid` mediumint(8) NOT NULL,
  `group_id` mediumint(8) NOT NULL,
  KEY `uid` (`uid`),
  KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_auth_rule
-- ----------------------------
DROP TABLE IF EXISTS `xy_auth_rule`;
CREATE TABLE `xy_auth_rule` (
  `id` mediumint(8) NOT NULL AUTO_INCREMENT,
  `level` int(11) NOT NULL,
  `pid` int(11) NOT NULL,
  `name` char(80) NOT NULL DEFAULT '',
  `title` char(20) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '1',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `condition` char(100) NOT NULL DEFAULT '',
  `sort` tinyint(4) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM AUTO_INCREMENT=252 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_car
-- ----------------------------
DROP TABLE IF EXISTS `xy_car`;
CREATE TABLE `xy_car` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `card_number` int(20) DEFAULT NULL,
  `category` varchar(30) DEFAULT NULL,
  `belong` varchar(50) DEFAULT NULL,
  `name` varchar(30) DEFAULT NULL,
  `sex` tinyint(2) DEFAULT NULL,
  `idcard` varchar(18) DEFAULT NULL,
  `work` varchar(255) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `telphone` bigint(11) DEFAULT NULL,
  `qq` bigint(15) DEFAULT NULL,
  `weixin` varchar(200) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `join_time` date DEFAULT NULL,
  `money` double(20,0) DEFAULT NULL,
  `shzh_time` date DEFAULT NULL,
  `business` varchar(255) DEFAULT NULL,
  `car_number` varchar(50) DEFAULT NULL,
  `owners` varchar(50) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `brand` varchar(200) DEFAULT NULL,
  `style` varchar(200) DEFAULT NULL,
  `engine_number` varchar(200) DEFAULT NULL,
  `vin` varchar(200) DEFAULT NULL,
  `zjcx` varchar(255) DEFAULT NULL,
  `shch_time` date DEFAULT NULL,
  `insurer` varchar(255) DEFAULT NULL,
  `bx_time` date DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `status` tinyint(2) DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=227 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_config
-- ----------------------------
DROP TABLE IF EXISTS `xy_config`;
CREATE TABLE `xy_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fenlei` varchar(20) NOT NULL COMMENT '分类',
  `name` varchar(30) NOT NULL DEFAULT '' COMMENT '配置名称',
  `type` tinyint(3) NOT NULL DEFAULT '0' COMMENT '配置类型',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '配置说明',
  `extra` varchar(255) NOT NULL DEFAULT '' COMMENT '配置值',
  `remark` varchar(100) NOT NULL COMMENT '配置说明',
  `addtime` datetime NOT NULL COMMENT '创建时间',
  `updatetime` datetime NOT NULL COMMENT '更新时间',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '状态',
  `value` text NOT NULL COMMENT '配置值',
  `sort` smallint(3) NOT NULL DEFAULT '0' COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=41 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_contact
-- ----------------------------
DROP TABLE IF EXISTS `xy_contact`;
CREATE TABLE `xy_contact` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xingming` varchar(50) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `danwei` varchar(50) NOT NULL,
  `zhiwu` varchar(20) NOT NULL,
  `dianhua` varchar(50) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(200) NOT NULL,
  `shuxing` varchar(20) NOT NULL,
  `beizhu` text NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(2) NOT NULL DEFAULT '1',
  `qq` varchar(50) NOT NULL,
  `fenlei` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='通讯录';

-- ----------------------------
-- Table structure for xy_cust
-- ----------------------------
DROP TABLE IF EXISTS `xy_cust`;
CREATE TABLE `xy_cust` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xingming` varchar(50) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `beizhu` varchar(200) NOT NULL,
  `title` varchar(50) NOT NULL,
  `dizhi` varchar(200) NOT NULL,
  `email` varchar(200) NOT NULL,
  `qq` varchar(50) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `bumen` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `juid` varchar(1000) NOT NULL,
  `juname` varchar(1000) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `fenlei` varchar(20) NOT NULL,
  `xcrq` date NOT NULL,
  `addm` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='客户管理';

-- ----------------------------
-- Table structure for xy_custcon
-- ----------------------------
DROP TABLE IF EXISTS `xy_custcon`;
CREATE TABLE `xy_custcon` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xingming` varchar(50) NOT NULL,
  `jcid` int(11) NOT NULL,
  `jcname` varchar(200) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `bumen` varchar(50) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(200) NOT NULL,
  `qq` varchar(50) NOT NULL,
  `name` varchar(200) NOT NULL,
  `beizhu` varchar(255) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='联系人';

-- ----------------------------
-- Table structure for xy_custgd
-- ----------------------------
DROP TABLE IF EXISTS `xy_custgd`;
CREATE TABLE `xy_custgd` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jcid` int(11) NOT NULL,
  `jcname` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `fenlei` varchar(20) NOT NULL,
  `xcrq` date NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `value` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COMMENT='跟单记录';

-- ----------------------------
-- Table structure for xy_dmrd_det
-- ----------------------------
DROP TABLE IF EXISTS `xy_dmrd_det`;
CREATE TABLE `xy_dmrd_det` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dmrd_site` varchar(18) NOT NULL,
  `dmrd_part` varchar(18) NOT NULL,
  `dmrd_fqty` decimal(18,10) NOT NULL,
  `dmrd_tqty` decimal(18,10) NOT NULL,
  `dmrd_date` date NOT NULL,
  `dmrd_mtime` datetime NOT NULL,
  `dmrd_user` varchar(18) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_doc
-- ----------------------------
DROP TABLE IF EXISTS `xy_doc`;
CREATE TABLE `xy_doc` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `fenlei` varchar(20) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `juid` varchar(1000) NOT NULL,
  `juname` varchar(1000) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='我的文档';

-- ----------------------------
-- Table structure for xy_drps_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_drps_mstr`;
CREATE TABLE `xy_drps_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `drps_site` varchar(8) NOT NULL,
  `drps_part` varchar(18) NOT NULL,
  `drps_line` varchar(10) NOT NULL,
  `drps_qty` decimal(18,6) NOT NULL,
  `drps_date` date NOT NULL,
  `drps_ismrp` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `drps_part_date_site` (`drps_part`,`drps_site`,`drps_date`) USING BTREE,
  KEY `drps_part` (`drps_part`,`drps_site`) USING BTREE,
  CONSTRAINT `xy_drps_mstr_ibfk_1` FOREIGN KEY (`drps_part`, `drps_site`) REFERENCES `xy_ptp_det` (`ptp_part`, `ptp_site`)
) ENGINE=InnoDB AUTO_INCREMENT=5747 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_fenxiang
-- ----------------------------
DROP TABLE IF EXISTS `xy_fenxiang`;
CREATE TABLE `xy_fenxiang` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `uid` int(10) NOT NULL,
  `hdid` int(10) NOT NULL,
  `fxb` int(10) NOT NULL DEFAULT '0',
  `fxjl` text,
  `name` varchar(50) DEFAULT NULL,
  `tel` bigint(11) DEFAULT NULL,
  `time` varchar(20) DEFAULT NULL,
  `number` int(10) DEFAULT '0' COMMENT '自己分享的次数',
  `fx_number` int(10) DEFAULT NULL COMMENT '其他用户分享的次数',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7195 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_files
-- ----------------------------
DROP TABLE IF EXISTS `xy_files`;
CREATE TABLE `xy_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attid` int(11) NOT NULL,
  `folder` varchar(50) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filetype` varchar(50) NOT NULL,
  `filedesc` varchar(200) NOT NULL,
  `uid` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `status` int(2) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_fu
-- ----------------------------
DROP TABLE IF EXISTS `xy_fu`;
CREATE TABLE `xy_fu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `addm` varchar(20) NOT NULL,
  `jhid` int(11) NOT NULL,
  `jhname` varchar(200) NOT NULL,
  `type` varchar(50) NOT NULL,
  `fenlei` varchar(50) NOT NULL,
  `bianhao` varchar(50) NOT NULL,
  `jine` int(11) NOT NULL,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `beizhu` varchar(200) NOT NULL,
  `uid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `attid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='付款记录';

-- ----------------------------
-- Table structure for xy_hdconfig
-- ----------------------------
DROP TABLE IF EXISTS `xy_hdconfig`;
CREATE TABLE `xy_hdconfig` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `varname` varchar(80) NOT NULL,
  `info` varchar(80) NOT NULL,
  `value` text NOT NULL,
  `valuetype` varchar(20) NOT NULL,
  `grouptype` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_hetong
-- ----------------------------
DROP TABLE IF EXISTS `xy_hetong`;
CREATE TABLE `xy_hetong` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bianhao` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `jcid` int(11) NOT NULL,
  `jcname` varchar(200) NOT NULL,
  `xingming` varchar(50) NOT NULL,
  `dianhua` varchar(50) NOT NULL,
  `jine` int(11) NOT NULL,
  `yishou` int(11) NOT NULL,
  `weishou` int(11) NOT NULL,
  `yikai` int(11) NOT NULL,
  `fukuan` int(11) NOT NULL,
  `dqrq` date NOT NULL,
  `name` varchar(50) NOT NULL,
  `juid` varchar(1000) NOT NULL,
  `juname` varchar(1000) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `addm` varchar(20) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='合同管理';

-- ----------------------------
-- Table structure for xy_hongbao
-- ----------------------------
DROP TABLE IF EXISTS `xy_hongbao`;
CREATE TABLE `xy_hongbao` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `openid` varchar(500) DEFAULT NULL,
  `scene_id` int(20) DEFAULT NULL,
  `createtime` int(20) DEFAULT NULL,
  `status` int(1) NOT NULL DEFAULT '0',
  `openid1` varchar(500) DEFAULT NULL,
  `ticket` text,
  `jx` int(1) DEFAULT '0',
  `weixinid` int(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=165 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for xy_hr
-- ----------------------------
DROP TABLE IF EXISTS `xy_hr`;
CREATE TABLE `xy_hr` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `xingming` varchar(50) NOT NULL,
  `sex` varchar(10) NOT NULL,
  `shengri` date NOT NULL,
  `xuexiao` varchar(100) NOT NULL,
  `xueli` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `jiadizhi` varchar(255) NOT NULL,
  `beizhu` text NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `updatetime` datetime NOT NULL,
  `uid` int(11) NOT NULL,
  `gonghao` varchar(50) NOT NULL,
  `bumen` varchar(50) NOT NULL,
  `zhiwei` varchar(50) NOT NULL,
  `shenfenzheng` varchar(20) NOT NULL,
  `jiankang` varchar(50) NOT NULL,
  `hunyin` varchar(20) NOT NULL,
  `minzu` varchar(20) NOT NULL,
  `jiguan` varchar(50) NOT NULL,
  `zhengzhi` varchar(50) NOT NULL,
  `rudang` date NOT NULL,
  `hukou` varchar(20) NOT NULL,
  `hukoudi` varchar(200) NOT NULL,
  `jiadianhua` varchar(20) NOT NULL,
  `type` varchar(20) NOT NULL,
  `ruzhi` date NOT NULL,
  `zaizhi` varchar(20) NOT NULL,
  `lizhi` date NOT NULL,
  `biye` date NOT NULL,
  `xuewei` varchar(20) NOT NULL,
  `zhuanye` varchar(20) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `shehui` text NOT NULL,
  `xuexi` text NOT NULL,
  `gongzuo` text NOT NULL,
  `jineng` text NOT NULL,
  `attid` int(11) NOT NULL,
  `birthday` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='员工档案';

-- ----------------------------
-- Table structure for xy_hrdd
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrdd`;
CREATE TABLE `xy_hrdd` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `ddrq` date NOT NULL,
  `sxrq` date NOT NULL,
  `bumen` varchar(50) NOT NULL,
  `hbumen` varchar(50) NOT NULL,
  `zhiwei` varchar(50) NOT NULL,
  `hzhiwei` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='人事调动';

-- ----------------------------
-- Table structure for xy_hrgh
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrgh`;
CREATE TABLE `xy_hrgh` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `sj` date NOT NULL,
  `feiyong` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='员工关怀';

-- ----------------------------
-- Table structure for xy_hrht
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrht`;
CREATE TABLE `xy_hrht` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `bianhao` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `kssj` date NOT NULL,
  `jsrj` date NOT NULL,
  `jcrq` date NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='人事合同';

-- ----------------------------
-- Table structure for xy_hrjf
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrjf`;
CREATE TABLE `xy_hrjf` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `sxrq` date NOT NULL,
  `jine` varchar(20) NOT NULL,
  `gongzi` date NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='奖罚管理';

-- ----------------------------
-- Table structure for xy_hrpx
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrpx`;
CREATE TABLE `xy_hrpx` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `feiyong` varchar(20) NOT NULL,
  `kssj` date NOT NULL,
  `jssj` date NOT NULL,
  `zhengshu` varchar(50) NOT NULL,
  `didian` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='培训管理';

-- ----------------------------
-- Table structure for xy_hrzz
-- ----------------------------
DROP TABLE IF EXISTS `xy_hrzz`;
CREATE TABLE `xy_hrzz` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `title` varchar(50) NOT NULL,
  `bianhao` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `sxrq` date NOT NULL,
  `jsrq` date NOT NULL,
  `qzrq` date NOT NULL,
  `danwei` varchar(200) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='证照管理';

-- ----------------------------
-- Table structure for xy_huo
-- ----------------------------
DROP TABLE IF EXISTS `xy_huo`;
CREATE TABLE `xy_huo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jhid` int(11) NOT NULL,
  `jhname` varchar(100) NOT NULL,
  `title` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='发货记录';

-- ----------------------------
-- Table structure for xy_huodong
-- ----------------------------
DROP TABLE IF EXISTS `xy_huodong`;
CREATE TABLE `xy_huodong` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jssj` datetime NOT NULL,
  `kssj` datetime NOT NULL,
  `title` varchar(200) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(20) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `bumen` varchar(50) NOT NULL,
  `jxsz` text,
  `bzgl` int(20) DEFAULT '10000',
  `img` varchar(200) DEFAULT NULL,
  `xxsm` varchar(500) DEFAULT NULL,
  `hdlx` int(11) DEFAULT '0',
  `cs` int(5) DEFAULT '5',
  `zj` text,
  `jp` text,
  `style` text,
  `weixinid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=75 DEFAULT CHARSET=utf8 COMMENT='我的去向';

-- ----------------------------
-- Table structure for xy_info
-- ----------------------------
DROP TABLE IF EXISTS `xy_info`;
CREATE TABLE `xy_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` varchar(1000) NOT NULL,
  `juname` varchar(1000) NOT NULL,
  `title` varchar(50) NOT NULL,
  `value` text NOT NULL,
  `attid` int(11) NOT NULL,
  `hui` text NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `jzrq` date NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=utf8 COMMENT='通知公告';

-- ----------------------------
-- Table structure for xy_in_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_in_mstr`;
CREATE TABLE `xy_in_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `in_site` varchar(8) NOT NULL,
  `in_part` varchar(18) NOT NULL,
  `in_qty_oh` decimal(18,6) NOT NULL,
  `in_loc` varchar(10) NOT NULL,
  `in_loc_type` varchar(10) NOT NULL,
  `in_ismrp` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `in_part_site` (`in_part`,`in_site`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_log
-- ----------------------------
DROP TABLE IF EXISTS `xy_log`;
CREATE TABLE `xy_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `addtime` datetime NOT NULL,
  `username` char(20) NOT NULL,
  `content` char(100) NOT NULL,
  `os` varchar(100) NOT NULL,
  `url` char(100) NOT NULL,
  `ip` char(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2041 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_menu
-- ----------------------------
DROP TABLE IF EXISTS `xy_menu`;
CREATE TABLE `xy_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `level` tinyint(1) NOT NULL,
  `pid` int(4) NOT NULL,
  `catename` char(20) NOT NULL DEFAULT '',
  `alink` char(100) NOT NULL DEFAULT '',
  `sort` int(4) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=86 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_mmrd_det
-- ----------------------------
DROP TABLE IF EXISTS `xy_mmrd_det`;
CREATE TABLE `xy_mmrd_det` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mmrd_site` varchar(18) NOT NULL,
  `mmrd_part` varchar(18) NOT NULL,
  `mmrd_fqty` decimal(18,10) NOT NULL,
  `mmrd_tqty` decimal(18,10) NOT NULL,
  `mmrd_month` varchar(18) NOT NULL,
  `mmrd_mtime` datetime NOT NULL,
  `mmrd_user` varchar(18) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_mrps_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_mrps_mstr`;
CREATE TABLE `xy_mrps_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mrps_site` varchar(8) NOT NULL,
  `mrps_part` varchar(18) NOT NULL,
  `mrps_line` varchar(10) NOT NULL,
  `mrps_qty` decimal(18,6) NOT NULL,
  `mrps_month` varchar(18) NOT NULL,
  `mrps_ismrp` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mrps_part_month_site_domain` (`mrps_part`,`mrps_site`,`mrps_month`) USING BTREE,
  KEY `mrps_part` (`mrps_part`,`mrps_site`) USING BTREE,
  CONSTRAINT `xy_mrps_mstr_ibfk_1` FOREIGN KEY (`mrps_part`, `mrps_site`) REFERENCES `xy_ptp_det` (`ptp_part`, `ptp_site`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=502 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_mygo
-- ----------------------------
DROP TABLE IF EXISTS `xy_mygo`;
CREATE TABLE `xy_mygo` (
  `jssj` datetime NOT NULL,
  `kssj` datetime NOT NULL,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(20) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `bumen` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COMMENT='我的去向';

-- ----------------------------
-- Table structure for xy_pay_log
-- ----------------------------
DROP TABLE IF EXISTS `xy_pay_log`;
CREATE TABLE `xy_pay_log` (
  `id` int(6) NOT NULL AUTO_INCREMENT,
  `openid` varchar(100) NOT NULL,
  `trade_no` varchar(32) DEFAULT NULL,
  `transaction_id` varchar(32) DEFAULT NULL,
  `total_fee` int(10) DEFAULT NULL,
  `pay_type` int(2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=1376 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for xy_piao
-- ----------------------------
DROP TABLE IF EXISTS `xy_piao`;
CREATE TABLE `xy_piao` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jhid` int(11) NOT NULL,
  `jhname` varchar(100) NOT NULL,
  `title` varchar(200) NOT NULL,
  `jine` int(11) NOT NULL,
  `bianhao` varchar(50) NOT NULL,
  `beizhu` varchar(200) NOT NULL,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `addm` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='开票记录';

-- ----------------------------
-- Table structure for xy_praise
-- ----------------------------
DROP TABLE IF EXISTS `xy_praise`;
CREATE TABLE `xy_praise` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `uid` int(20) NOT NULL DEFAULT '0',
  `zlb` int(10) NOT NULL DEFAULT '0',
  `zljl` text,
  `hdid` int(20) NOT NULL DEFAULT '0',
  `time` varchar(20) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `tel` bigint(11) DEFAULT NULL,
  `share` int(3) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=16130 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_pro
-- ----------------------------
DROP TABLE IF EXISTS `xy_pro`;
CREATE TABLE `xy_pro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `fenlei` varchar(20) NOT NULL,
  `jiage` int(11) NOT NULL,
  `type` varchar(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `beizhu` text NOT NULL,
  `sjiage` int(11) NOT NULL,
  `kucun` int(11) NOT NULL,
  `ruku` int(11) NOT NULL,
  `chuku` int(11) NOT NULL,
  `tuiku` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COMMENT='产品管理';

-- ----------------------------
-- Table structure for xy_proin
-- ----------------------------
DROP TABLE IF EXISTS `xy_proin`;
CREATE TABLE `xy_proin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jpid` int(11) NOT NULL,
  `jpname` varchar(50) NOT NULL,
  `jpjiage` int(11) NOT NULL,
  `jpdanwei` varchar(20) NOT NULL,
  `jpguige` varchar(50) NOT NULL,
  `shuliang` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='入库记录';

-- ----------------------------
-- Table structure for xy_proout
-- ----------------------------
DROP TABLE IF EXISTS `xy_proout`;
CREATE TABLE `xy_proout` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jpid` int(11) NOT NULL,
  `jpname` varchar(50) NOT NULL,
  `jpjiage` int(11) NOT NULL,
  `jpdanwei` varchar(20) NOT NULL,
  `jpguige` varchar(50) NOT NULL,
  `shuliang` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `jhid` int(11) NOT NULL,
  `jhname` varchar(50) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='出库记录';

-- ----------------------------
-- Table structure for xy_ps_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_ps_mstr`;
CREATE TABLE `xy_ps_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ps_site` varchar(8) NOT NULL DEFAULT '1000',
  `ps_par` varchar(18) NOT NULL,
  `ps_comp` varchar(18) NOT NULL,
  `ps_qty_per` decimal(18,9) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ps_par_comp_site` (`ps_par`,`ps_comp`,`ps_site`) USING BTREE,
  KEY `ps_comp` (`ps_comp`) USING BTREE,
  KEY `ps_par_site` (`ps_par`,`ps_site`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=134229 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_ptp_det
-- ----------------------------
DROP TABLE IF EXISTS `xy_ptp_det`;
CREATE TABLE `xy_ptp_det` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ptp_site` varchar(8) NOT NULL,
  `ptp_line` varchar(18) NOT NULL,
  `ptp_part` varchar(18) NOT NULL,
  `ptp_cpart` varchar(24) NOT NULL,
  `ptp_desc1` varchar(24) DEFAULT NULL,
  `ptp_desc2` varchar(24) DEFAULT NULL,
  `ptp_peizhi` varchar(18) DEFAULT NULL,
  `ptp_promo` varchar(18) NOT NULL,
  `ptp_timfnce` tinyint(4) NOT NULL,
  `ptp_ord_per` tinyint(4) NOT NULL,
  `ptp_pm_code` varchar(1) NOT NULL,
  `ptp_sfty_sfk` decimal(17,5) NOT NULL,
  `ptp_vend` varchar(8) NOT NULL,
  `ptp_buyer` varchar(8) NOT NULL,
  `ptp_ord_mult` decimal(17,5) NOT NULL,
  `ptp_yld_pct` decimal(12,11) NOT NULL,
  `ptp_ismrp` tinyint(4) DEFAULT '0',
  `ptp_mtime` datetime DEFAULT NULL,
  `ptp_desgin` varchar(8) DEFAULT NULL,
  `ptp_added` datetime DEFAULT NULL,
  `ptp_pallet_qty` smallint(6) DEFAULT NULL,
  `ptp_box_qty` smallint(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ptp_site_part` (`ptp_part`,`ptp_site`) USING BTREE,
  KEY `ptp_site` (`ptp_site`),
  KEY `ptp_part` (`ptp_part`)
) ENGINE=InnoDB AUTO_INCREMENT=10725 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_report
-- ----------------------------
DROP TABLE IF EXISTS `xy_report`;
CREATE TABLE `xy_report` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `type` varchar(20) NOT NULL,
  `title` varchar(50) NOT NULL,
  `value` varchar(500) NOT NULL,
  `attid` int(11) NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `updatetime` datetime NOT NULL,
  `beizhu` text NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `juid` varchar(1000) NOT NULL,
  `juname` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='工作汇报';

-- ----------------------------
-- Table structure for xy_scene
-- ----------------------------
DROP TABLE IF EXISTS `xy_scene`;
CREATE TABLE `xy_scene` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `uid` int(20) NOT NULL DEFAULT '0',
  `hdid` int(20) NOT NULL DEFAULT '0',
  `time` varchar(20) DEFAULT NULL,
  `name` varchar(20) DEFAULT NULL,
  `tel` bigint(11) DEFAULT NULL,
  `is_grab` int(2) DEFAULT NULL,
  `money` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=38904 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_score
-- ----------------------------
DROP TABLE IF EXISTS `xy_score`;
CREATE TABLE `xy_score` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `score` int(11) NOT NULL,
  `joindate` datetime NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=91 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_shengchan
-- ----------------------------
DROP TABLE IF EXISTS `xy_shengchan`;
CREATE TABLE `xy_shengchan` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `createtime` int(20) DEFAULT NULL,
  `scname` varchar(200) DEFAULT NULL,
  `kstime` int(11) DEFAULT NULL,
  `jstime` int(20) DEFAULT NULL,
  `yaoqiou` varchar(200) DEFAULT NULL,
  `orderid` varchar(500) DEFAULT NULL,
  ` wuliaoid` varchar(500) DEFAULT NULL,
  `wuliaosl` varchar(500) DEFAULT NULL,
  `status` int(1) DEFAULT NULL,
  `ordergroup` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for xy_shop
-- ----------------------------
DROP TABLE IF EXISTS `xy_shop`;
CREATE TABLE `xy_shop` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shopcode` varchar(20) NOT NULL,
  `shopname` varchar(38) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2400 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_shou
-- ----------------------------
DROP TABLE IF EXISTS `xy_shou`;
CREATE TABLE `xy_shou` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jhid` int(11) NOT NULL,
  `jhname` varchar(200) NOT NULL,
  `type` varchar(50) NOT NULL,
  `bianhao` varchar(50) NOT NULL,
  `jine` int(11) NOT NULL,
  `juid` int(11) NOT NULL,
  `juname` varchar(50) NOT NULL,
  `beizhu` varchar(200) NOT NULL,
  `uid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `addm` varchar(50) NOT NULL,
  `attid` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COMMENT='收款记录';

-- ----------------------------
-- Table structure for xy_task
-- ----------------------------
DROP TABLE IF EXISTS `xy_task`;
CREATE TABLE `xy_task` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `juid` varchar(500) NOT NULL,
  `kssj` datetime NOT NULL,
  `juname` varchar(500) NOT NULL,
  `jssj` datetime NOT NULL,
  `title` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `zhuangtai` varchar(20) NOT NULL,
  `wancheng` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `jhid` int(11) NOT NULL,
  `jhname` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COMMENT='任务管理';

-- ----------------------------
-- Table structure for xy_tb_gonggao
-- ----------------------------
DROP TABLE IF EXISTS `xy_tb_gonggao`;
CREATE TABLE `xy_tb_gonggao` (
  `id` int(4) NOT NULL AUTO_INCREMENT,
  `title` varchar(200) DEFAULT NULL,
  `content` text,
  `time` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=23 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for xy_tran_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_tran_mstr`;
CREATE TABLE `xy_tran_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tran_site` varchar(8) NOT NULL,
  `tran_vend` varchar(10) NOT NULL,
  `tran_part` varchar(18) NOT NULL,
  `tran_qty` decimal(18,0) NOT NULL,
  `tran_date` date NOT NULL,
  `tran_buyer` varchar(10) NOT NULL,
  `tran_mtime` datetime DEFAULT NULL,
  `tran_director` tinyint(4) DEFAULT NULL,
  `tran_manager` tinyint(4) DEFAULT NULL,
  `tran _name` varchar(18) DEFAULT NULL,
  `tran_ctime` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tran_part_site_vend` (`tran_part`,`tran_site`,`tran_vend`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_user
-- ----------------------------
DROP TABLE IF EXISTS `xy_user`;
CREATE TABLE `xy_user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` char(20) NOT NULL DEFAULT '',
  `password` char(32) NOT NULL DEFAULT '',
  `memo` varchar(50) NOT NULL,
  `depname` varchar(50) NOT NULL,
  `posname` varchar(50) NOT NULL,
  `truename` char(30) NOT NULL,
  `sex` char(5) NOT NULL,
  `tel` varchar(20) NOT NULL,
  `phone` char(11) NOT NULL,
  `neixian` varchar(50) NOT NULL,
  `email` varchar(200) NOT NULL,
  `qq` varchar(20) NOT NULL,
  `logintime` datetime NOT NULL,
  `loginip` char(15) NOT NULL,
  `logins` int(11) NOT NULL,
  `status` tinyint(4) NOT NULL DEFAULT '1',
  `update_time` int(11) NOT NULL,
  `bian` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=9 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_vd_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_vd_mstr`;
CREATE TABLE `xy_vd_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `vd_addr` varchar(18) DEFAULT NULL,
  `vd_name` varchar(255) DEFAULT NULL,
  `vd_fday` int(11) DEFAULT NULL,
  `vd_buyer` varchar(10) DEFAULT NULL,
  `vd_cal` set('Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vd_name_addr` (`vd_name`,`vd_addr`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_weixin
-- ----------------------------
DROP TABLE IF EXISTS `xy_weixin`;
CREATE TABLE `xy_weixin` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `partner_id` int(10) DEFAULT NULL,
  `weixin` varchar(16) DEFAULT NULL,
  `content` text,
  `leibie` int(10) DEFAULT NULL,
  `appsecret` varchar(100) DEFAULT NULL,
  `appid` varchar(100) DEFAULT NULL,
  `token` varchar(100) DEFAULT NULL,
  `image` varchar(128) CHARACTER SET utf8 DEFAULT NULL,
  `createtime` int(20) DEFAULT NULL,
  `weixinhao` varchar(20) DEFAULT NULL,
  `moban` int(1) DEFAULT '2',
  `zljf` int(5) NOT NULL DEFAULT '1',
  `qdjf` int(5) DEFAULT '2',
  `logo` varchar(128) DEFAULT NULL,
  `spic` varchar(128) DEFAULT NULL,
  `pic1` varchar(128) DEFAULT NULL,
  `pic2` varchar(128) DEFAULT NULL,
  `pic3` varchar(128) DEFAULT NULL,
  `pic4` varchar(128) DEFAULT NULL,
  `pic5` varchar(128) DEFAULT NULL,
  `xhjkg` int(1) NOT NULL DEFAULT '1',
  `plkg` int(1) NOT NULL DEFAULT '1',
  `banquan` varchar(50) DEFAULT NULL,
  `access_token` text CHARACTER SET utf8,
  `expires_in` int(20) NOT NULL DEFAULT '0',
  `hyksm` varchar(500) DEFAULT NULL,
  `tel` varchar(13) NOT NULL DEFAULT '0',
  `sc` int(1) NOT NULL DEFAULT '0',
  `qx` int(1) NOT NULL DEFAULT '1',
  `ewmjs` int(6) DEFAULT '0',
  `mrhf` varchar(500) DEFAULT NULL,
  `zhuobiao` varchar(20) DEFAULT NULL,
  `bjyy` varchar(200) DEFAULT NULL,
  `nrhf` varchar(500) DEFAULT NULL,
  `dz` varchar(200) DEFAULT NULL,
  `syurl` varchar(300) CHARACTER SET utf8 DEFAULT NULL,
  `zhanghu` varchar(100) DEFAULT NULL,
  `fxjf` int(5) NOT NULL DEFAULT '0',
  `rzlx` int(1) NOT NULL DEFAULT '0',
  `hykt` varchar(128) DEFAULT NULL,
  `gzjf` int(10) NOT NULL DEFAULT '0',
  `dkf` int(1) NOT NULL DEFAULT '0',
  `gzhf` text,
  `dhkg` int(1) NOT NULL DEFAULT '1',
  `yjgz` varchar(150) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `dqtime` int(20) NOT NULL DEFAULT '0',
  `jssdk` text,
  `accesstoken` text,
  `apiurl` varchar(500) DEFAULT NULL,
  `apitoken` varchar(500) DEFAULT NULL,
  `hbtui` int(1) DEFAULT '0',
  `glhd` int(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=912 DEFAULT CHARSET=gbk;

-- ----------------------------
-- Table structure for xy_wmrd_det
-- ----------------------------
DROP TABLE IF EXISTS `xy_wmrd_det`;
CREATE TABLE `xy_wmrd_det` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wmrd_site` varchar(18) NOT NULL,
  `wmrd_part` varchar(18) NOT NULL,
  `wmrd_fqty` decimal(18,10) NOT NULL,
  `wmrd_tqty` decimal(18,10) NOT NULL,
  `wmrd_week` varchar(18) NOT NULL,
  `wmrd_mtime` datetime NOT NULL,
  `wmrd_user` varchar(18) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_wrps_mstr
-- ----------------------------
DROP TABLE IF EXISTS `xy_wrps_mstr`;
CREATE TABLE `xy_wrps_mstr` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `wrps_site` varchar(8) NOT NULL,
  `wrps_part` varchar(18) NOT NULL,
  `wrps_line` varchar(10) NOT NULL,
  `wrps_qty` decimal(18,6) NOT NULL,
  `wrps_week` varchar(18) NOT NULL,
  `wrps_ismrp` tinyint(4) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `wrps_part_week_site_domain` (`wrps_part`,`wrps_site`,`wrps_week`) USING BTREE,
  KEY `wrps_part` (`wrps_part`,`wrps_site`) USING BTREE,
  CONSTRAINT `xy_wrps_mstr_ibfk_1` FOREIGN KEY (`wrps_part`, `wrps_site`) REFERENCES `xy_ptp_det` (`ptp_part`, `ptp_site`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=502 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_wxuser
-- ----------------------------
DROP TABLE IF EXISTS `xy_wxuser`;
CREATE TABLE `xy_wxuser` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `wx` varchar(100) NOT NULL DEFAULT '',
  `shouji` int(14) DEFAULT NULL,
  `wxtime` int(20) DEFAULT NULL,
  `wxuser` varchar(16) DEFAULT NULL,
  `partner_id` int(20) NOT NULL DEFAULT '0',
  `qiandao` int(20) DEFAULT NULL,
  `jifen` int(20) NOT NULL DEFAULT '0',
  `name` varchar(50) DEFAULT NULL,
  `tel` bigint(11) NOT NULL DEFAULT '0',
  `piczt` int(1) NOT NULL DEFAULT '0',
  `pic` varchar(200) DEFAULT NULL,
  `sex` int(1) DEFAULT NULL,
  `address` varchar(20) DEFAULT NULL,
  `QQ` int(11) NOT NULL DEFAULT '0',
  `weixin` varchar(16) DEFAULT NULL,
  `is` varchar(100) DEFAULT NULL,
  `zt` varchar(200) DEFAULT NULL,
  `gl` int(1) NOT NULL DEFAULT '0',
  `qx` int(1) NOT NULL DEFAULT '0',
  `xxzt` int(1) NOT NULL DEFAULT '0',
  `zxzt` int(20) NOT NULL DEFAULT '0',
  `password` varchar(32) NOT NULL DEFAULT '''''',
  `ticket` varchar(300) DEFAULT NULL,
  `scene_id` int(6) NOT NULL DEFAULT '0',
  `tjrid` int(10) NOT NULL DEFAULT '0',
  `hqxxzt` int(11) NOT NULL DEFAULT '0',
  `lxzt` int(1) NOT NULL DEFAULT '0',
  `hdjs` text,
  `tgjf` int(10) NOT NULL DEFAULT '0',
  `gbk` varchar(16) DEFAULT NULL,
  `jinbi` int(10) NOT NULL DEFAULT '3000',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `prizesec` int(5) DEFAULT '0',
  `is_exchange` tinyint(4) NOT NULL DEFAULT '0',
  `sncode` varchar(100) DEFAULT NULL,
  `shareid` int(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=620668 DEFAULT CHARSET=gbk COMMENT='微信用户';

-- ----------------------------
-- Table structure for xy_wxuser_2
-- ----------------------------
DROP TABLE IF EXISTS `xy_wxuser_2`;
CREATE TABLE `xy_wxuser_2` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `wx` varchar(100) NOT NULL DEFAULT '',
  `shouji` int(14) DEFAULT NULL,
  `wxtime` int(20) DEFAULT NULL,
  `wxuser` varchar(16) DEFAULT NULL,
  `partner_id` int(20) NOT NULL DEFAULT '0',
  `qiandao` int(20) DEFAULT NULL,
  `jifen` int(20) NOT NULL DEFAULT '0',
  `name` varchar(50) DEFAULT NULL,
  `tel` bigint(11) NOT NULL DEFAULT '0',
  `piczt` int(1) NOT NULL DEFAULT '0',
  `pic` varchar(200) DEFAULT NULL,
  `sex` int(1) DEFAULT NULL,
  `address` varchar(20) DEFAULT NULL,
  `QQ` int(11) NOT NULL DEFAULT '0',
  `weixin` varchar(16) DEFAULT NULL,
  `is` varchar(100) DEFAULT NULL,
  `zt` varchar(200) DEFAULT NULL,
  `gl` int(1) NOT NULL DEFAULT '0',
  `qx` int(1) NOT NULL DEFAULT '0',
  `xxzt` int(1) NOT NULL DEFAULT '0',
  `zxzt` int(20) NOT NULL DEFAULT '0',
  `password` varchar(32) NOT NULL DEFAULT '''''',
  `ticket` varchar(300) DEFAULT NULL,
  `scene_id` int(6) NOT NULL DEFAULT '0',
  `tjrid` int(10) NOT NULL DEFAULT '0',
  `hqxxzt` int(11) NOT NULL DEFAULT '0',
  `lxzt` int(1) NOT NULL DEFAULT '0',
  `hdjs` text,
  `tgjf` int(10) NOT NULL DEFAULT '0',
  `gbk` varchar(16) DEFAULT NULL,
  `jinbi` int(10) NOT NULL DEFAULT '3000',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `prizesec` int(5) DEFAULT '0',
  `is_exchange` tinyint(4) NOT NULL DEFAULT '0',
  `sncode` varchar(100) DEFAULT NULL,
  `shareid` int(20) DEFAULT NULL,
  `pay` tinyint(2) unsigned DEFAULT '0',
  `grade` int(2) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=59982 DEFAULT CHARSET=gbk COMMENT='微信用户';

-- ----------------------------
-- Table structure for xy_yzjl
-- ----------------------------
DROP TABLE IF EXISTS `xy_yzjl`;
CREATE TABLE `xy_yzjl` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `mid` int(20) DEFAULT NULL,
  `qx` int(20) DEFAULT NULL,
  `uid` int(20) DEFAULT NULL,
  `time` int(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7521 DEFAULT CHARSET=latin1;

-- ----------------------------
-- Table structure for xy_zhishi
-- ----------------------------
DROP TABLE IF EXISTS `xy_zhishi`;
CREATE TABLE `xy_zhishi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL,
  `type` varchar(50) NOT NULL,
  `beizhu` text NOT NULL,
  `attid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `uname` varchar(50) NOT NULL,
  `addtime` datetime NOT NULL,
  `uuid` int(11) NOT NULL,
  `uuname` varchar(50) NOT NULL,
  `updatetime` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 COMMENT='知识管理';

-- ----------------------------
-- Table structure for xy_zhuli
-- ----------------------------
DROP TABLE IF EXISTS `xy_zhuli`;
CREATE TABLE `xy_zhuli` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `uid` int(20) NOT NULL DEFAULT '0',
  `zlb` int(10) NOT NULL DEFAULT '0',
  `zljl` text,
  `hdid` int(20) NOT NULL DEFAULT '0',
  `wx` smallint(6) NOT NULL DEFAULT '0',
  `time` varchar(20) DEFAULT NULL,
  `record` text,
  `name` varchar(20) DEFAULT NULL,
  `tel` bigint(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=38904 DEFAULT CHARSET=utf8;

-- ----------------------------
-- Table structure for xy_zjjl
-- ----------------------------
DROP TABLE IF EXISTS `xy_zjjl`;
CREATE TABLE `xy_zjjl` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `hdid` int(20) DEFAULT NULL,
  `uid` int(20) DEFAULT NULL,
  `jpname` varchar(500) CHARACTER SET utf8 DEFAULT NULL,
  `creatitime` int(20) DEFAULT NULL,
  `tel` bigint(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=latin1;

-- ----------------------------
-- View structure for xy_prodecomp
-- ----------------------------
DROP VIEW IF EXISTS `xy_prodecomp`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER  VIEW `xy_prodecomp` AS SELECT
xy_ps_mstr.id,
xy_ps_mstr.ps_site,
xy_ps_mstr.ps_comp,
xy_ps_mstr.ps_par,
xy_ps_mstr.ps_qty_per,
xy_ptp_det.id AS ptp_id,
xy_drps_mstr.drps_ismrp,
xy_drps_mstr.drps_date,
xy_drps_mstr.drps_qty,
xy_drps_mstr.id AS drps_id,
xy_wrps_mstr.wrps_ismrp,
xy_wrps_mstr.wrps_week,
xy_wrps_mstr.wrps_qty,
xy_wrps_mstr.id AS wrps_id,
xy_mrps_mstr.mrps_ismrp,
xy_mrps_mstr.mrps_month,
xy_mrps_mstr.mrps_qty,
xy_mrps_mstr.id AS mrps_id,
xy_comp_det.ptp_line AS comp_line,
xy_comp_det.ptp_cpart AS comp_cpart,
xy_comp_det.ptp_desc1 AS comp_desc1,
xy_comp_det.ptp_desc2 AS comp_desc2,
xy_comp_det.ptp_promo AS comp_promo,
xy_comp_det.ptp_vend AS comp_vend,
xy_comp_det.ptp_buyer AS comp_buyer,
xy_comp_det.ptp_ismrp AS comp_ismrp
FROM
xy_ptp_det
INNER JOIN xy_ps_mstr ON xy_ps_mstr.ps_par = xy_ptp_det.ptp_part AND xy_ps_mstr.ps_site = xy_ptp_det.ptp_site
INNER JOIN xy_drps_mstr ON xy_drps_mstr.drps_part = xy_ptp_det.ptp_part AND xy_drps_mstr.drps_site = xy_ptp_det.ptp_site
INNER JOIN xy_wrps_mstr ON xy_wrps_mstr.wrps_part = xy_ptp_det.ptp_part AND xy_wrps_mstr.wrps_site = xy_ptp_det.ptp_site
INNER JOIN xy_mrps_mstr ON xy_mrps_mstr.mrps_part = xy_ptp_det.ptp_part AND xy_mrps_mstr.mrps_site = xy_ptp_det.ptp_site

INNER JOIN xy_ptp_det as xy_comp_det ON xy_ps_mstr.ps_comp = xy_comp_det.ptp_part AND xy_ps_mstr.ps_site = xy_comp_det.ptp_site ;

-- ----------------------------
-- View structure for xy_proplan
-- ----------------------------
DROP VIEW IF EXISTS `xy_proplan`;
CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER  VIEW `xy_proplan` AS SELECT
xy_ptp_det.id,
xy_ptp_det.ptp_site,
xy_ptp_det.ptp_part,
xy_ptp_det.ptp_peizhi,
xy_ptp_det.ptp_desc1,
xy_ptp_det.ptp_desc2,
xy_ptp_det.ptp_buyer,
xy_ptp_det.ptp_vend,
xy_drps_mstr.drps_qty,
xy_drps_mstr.drps_date,
xy_drps_mstr.drps_ismrp,
xy_wrps_mstr.wrps_qty,
xy_wrps_mstr.wrps_week,
xy_wrps_mstr.wrps_ismrp,
xy_mrps_mstr.mrps_qty,
xy_mrps_mstr.mrps_month,
xy_mrps_mstr.mrps_ismrp,
xy_drps_mstr.id AS drps_id,
xy_wrps_mstr.id AS wrps_id,
xy_mrps_mstr.id AS mrps_id,
xy_ptp_det.ptp_cpart,
xy_ptp_det.ptp_promo,
xy_ptp_det.ptp_timfnce,
xy_ptp_det.ptp_ord_per,
xy_ptp_det.ptp_pm_code,
xy_ptp_det.ptp_sfty_sfk,
xy_ptp_det.ptp_ord_mult,
xy_ptp_det.ptp_yld_pct,
xy_ptp_det.ptp_ismrp,
xy_ptp_det.ptp_mtime,
xy_ptp_det.ptp_desgin,
xy_ptp_det.ptp_added,
xy_ptp_det.ptp_pallet_qty,
xy_ptp_det.ptp_box_qty,
xy_ptp_det.ptp_line
FROM
	xy_wrps_mstr,
	xy_mrps_mstr,
	xy_drps_mstr,
	xy_ptp_det
WHERE
xy_wrps_mstr.wrps_part = xy_mrps_mstr.mrps_part AND
xy_wrps_mstr.wrps_site = xy_mrps_mstr.mrps_site AND
xy_wrps_mstr.wrps_part = xy_drps_mstr.drps_part AND
xy_wrps_mstr.wrps_site = xy_drps_mstr.drps_site AND
xy_wrps_mstr.wrps_part = xy_ptp_det.ptp_part AND
xy_wrps_mstr.wrps_site = xy_ptp_det.ptp_site ;
