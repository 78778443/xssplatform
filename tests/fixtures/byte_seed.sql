SET NAMES utf8mb4;
-- Fixed rows used by tests/http/byte_compare_test.php
DELETE FROM oc_project_content WHERE projectId=90;
DELETE FROM oc_project WHERE id=90;
DELETE FROM oc_module WHERE id=90;

INSERT INTO oc_module (id,userId,title,`keys`,setkeys,code,`level`,isOpen,description,isAudit,managerId,managerName,addTime)
VALUES (90,1,'fixture','["cookie"]',NULL,'var x=&quot;a&quot;;',0,0,'fixture',0,0,'',1);

INSERT INTO oc_project (id,userId,title,description,modules,moduleSetKeys,code,urlKey,authCode,addTime)
VALUES (90,2,'fixture','','[90]','[]','alert(1);','FIXTUREKEY','FIXTUREAUTH',1);

INSERT INTO oc_project_content (id,projectId,content,serverContent,domain,cookieHash,num,updateTime,addTime)
VALUES (90,90,'{"opener":"","toplocation":"http:\\/\\/example.com","cookie":"abc"}','{"REMOTE_ADDR":"1.2.3.4"}','example.com','hash90',1,1,1);

-- Legacy default module referenced by receiver regression tests.
INSERT IGNORE INTO oc_module (id,userId,title,`keys`,setkeys,code,`level`,isOpen,description,isAudit,managerId,managerName,addTime)
VALUES (1,1,'默认模块','["location","toplocation","cookie","opener"]','["keepsession"]','',0,1,'基础收包模块',1,1,'blue',1341846542);
