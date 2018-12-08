<?php
	return array(

		"create database ".DB_NAME.";",
		
		"CREATE TABLE `bbs_cate` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `pid` int(10) unsigned NOT NULL default '0',
		  `cname` varchar(255) NOT NULL default '默认板块',
		  `uid` int(1) unsigned NOT NULL default '0',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5 ;",

		"CREATE TABLE `bbs_fri` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `title` varchar(255) NOT NULL default '百度',
		  `desc1` varchar(255) NOT NULL default '百度一下,你就知道',
		  `url` varchar(255) NOT NULL default 'http://www.baidu.com',
		  `pic` varchar(255) NOT NULL default 'default_fri.gif',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=9 ;",


		"CREATE TABLE `bbs_part` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `pname` varchar(255) NOT NULL default '默认分区',
		  `padmins` int(10) NOT NULL default '6',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=41 ;",

		"CREATE TABLE `bbs_post` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `cid` int(10) unsigned NOT NULL default '0',
		  `title` varchar(32) NOT NULL default '帖子标题',
		  `content` text,
		  `ptime` int(10) unsigned NOT NULL default '0',
		  `uid` int(10) unsigned NOT NULL default '0',
		  `pip` int(11) NOT NULL default '0',
		  `count` int(10) unsigned NOT NULL default '0',
		  `del` int(1) unsigned NOT NULL default '1',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=55 ;",

		"CREATE TABLE `bbs_reply` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `pid` int(10) unsigned NOT NULL default '0',
		  `content` text,
		  `uid` int(10) unsigned NOT NULL default '0',
		  `ptime` int(10) unsigned NOT NULL default '0',
		  `pip` int(10) unsigned NOT NULL default '0',
		  `xx` int(1) unsigned NOT NULL default '1',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=49 ;",

		"CREATE TABLE `bbs_user` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `username` varchar(32) NOT NULL default '72user',
		  `email` varchar(32) NOT NULL default '',
		  `password` char(32) NOT NULL default '72pass',
		  `rtime` int(10) unsigned NOT NULL default '0',
		  `rip` bigint(11) NOT NULL default '0',
		  `admins` int(1) unsigned NOT NULL default '0',
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;",

		"CREATE TABLE `bbs_user_detail` (
		  `uid` int(10) unsigned NOT NULL default '0',
		  `t_name` varchar(32) default '汤青松',
		  `age` int(10) unsigned NOT NULL default '0',
		  `sex` int(10) unsigned NOT NULL default '0',
		  `edu` int(10) unsigned NOT NULL default '0',
		  `signed` text,
		  `pic` varchar(255) NOT NULL default '../../resorec/images/userhead/default.gif',
		  `telphone` varchar(32) NOT NULL default '13888888888',
		  `qq` int(10) unsigned NOT NULL default '888888',
		  `email` varchar(255) NOT NULL default 'soupqingsong@foxmail.com',
		  `brithday` int(10) unsigned NOT NULL default '0',
		  `picm` varchar(255) NOT NULL default '../../resorec/images/userhead/defaultm.gif',
		  `pics` varchar(255) NOT NULL default '../../resorec/images/userhead/defaults.gif'
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;",
		
		"CREATE TABLE `bbs_iprefuse` (
		  `id` int(10) unsigned NOT NULL auto_increment,
		  `ipmin` varchar(20) NOT NULL,
		  `ipmax` varchar(20) NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=8 ;",
		
		"CREATE TABLE `bbs_fil` (
		  `id` int(10) NOT NULL auto_increment,
		  `hinge` varchar(32) NOT NULL,
		  PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=6 ;",

        "CREATE TABLE `bbs_home_follow` (
          `id` int(10) NOT NULL AUTO_INCREMENT,
          `uid` int(10) NOT NULL DEFAULT '0' COMMENT '用户id',
          `username` char(15) NOT NULL COMMENT '用户名',
          `followuid` int(10) NOT NULL DEFAULT '0' COMMENT '被关注用户ID',
          `fusername` char(15) NOT NULL COMMENT '被关注用户名称',
          `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:正常 1:特殊关注 -1:不能再关注此人',
          `mutual` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0:单向 1:已互相关注',
          `uptiem` int(10) NOT NULL DEFAULT '0',
          PRIMARY KEY (`id`)
        ) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=latin1;"
);
?>