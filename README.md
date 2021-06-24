## 推荐使用docker方式搭建

参考文章：https://segmentfault.com/a/1190000021899373          使用docker快速搭建xssPlatform测试平台实践

## 一、背景
XSS Platform 是一个非常经典的XSS渗透测试管理系统，原作者在2011年所开发，由于后来长时间没有人维护，导致目前在PHP7环境下无法运行。

笔者最近花了一点时间将源码移植到了PHP7环境中，同时增加安装功能；另外还修复之前的代码的一些不严谨语法的问题，并调整了一些表单的样式，同时将源代码放到GitHub当中，给有需要的同行研究，为了简化安装步骤，特意写一篇文章来帮助大家。

## 二、操作概要
1. 源码下载
2. 安装配置
3. 攻击测试


## 三、下载源码

github地址:`https://github.com/78778443/xssplatform`

首先通过cd命令将代码放到指定位置，参考命令如下


```
cd /Users/song/mycode/safe/
```
之后通过git下载源码，参考命令如下:

```
git clone https://github.com/78778443/xssplatform.git
```


## 四、安装配置


### 4.1 增加虚拟主机

XSS Platform 需要在根目录中运行，因此需要单独添加一个虚拟主机，笔者以nginx环境为例，配置虚拟主机的配置代码如下所示:

```
server {
    listen       80;
    server_name  xss.localhost;
    root  /Users/song/mycode/safe/xssplatform/;


    rewrite "^/([0-9a-zA-Z]{6})$" /index.php?do=code&urlKey=$1 last;
    rewrite "^/do/auth/(\w+?)(/domain/([\w\.]+?))?$" /index.php?do=do&auth=$1&domain=$3 last;
    rewrite "^/register/(.*?)$" /index.php?do=register&key=$1 last;
    rewrite "^/register-validate/(.*?)$" /index.php?do=register&act=validate&key=$1 last;

    location / {

        index index.html index.htm index.php;
    }

    location ~ \.php$ {
        fastcgi_pass   127.0.0.1:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME  $document_root$fastcgi_script_name;
        include        fastcgi_params;
    }

}
```

修改配置文件后，需要重启nginx让其配置生效，重启命令参考如下:

```
nginx -s reload
```

### 4.2 添加HOST记录

hosts文件位置是`/etc/hosts`，通过vim命令进行编辑，参考命令如下所示:
```
vim /etc/hosts
```
在文件中添加一行记录，内容如下所示：
```
127.0.0.1	xss.localhost
```

### 4.3 系统安装

通过前面添加虚拟主机和添加host解析之后，便可以通过浏览器访问此平台，URL地址为`http://xss.localhost/`,打开后会自动跳转到安装界面，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/1.png)


点击 `我同意此协议`按钮之后，将跳转到第二步的填写配置信息界面，在此界面需要填写数据库信息，和管理员账号信息，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/2.png)

如果数据库信息填写无误，将会看到导入数据成功的提，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/3.png)

此时便代表安装成功



### 4.4 功能简介
先来熟悉一些XSS Platform的一些功能，在安装完成界面点击进入首页，会要求先登录，在登录界面输入刚才安装时所填写的管理员账号信息，点击登录即可，登录成功之后会自动跳转到首页，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/4.png)

在首页中可以看到有一个默认项目，点击`default`后可以看到受害者列表，不过刚刚安装肯定是还没有数据的，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/5.png)

在图中右上方有一个查看代码的链接，点击进去便可以查看XSS Platform预备好的攻击代码，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/6.png)

## 五、攻击测试

现在笔者将正是开始进行一些实践演示，首先会找出一个permeate渗透测试系统的XSS漏洞，将XSS Platform的攻击代码插入进去；

然后模拟受害者访问到被攻击的页面，会到XSS platform系统中查看收到的cookie值，最后使用接收到的cookie来冒充受害者。

permeate 渗透测试系统源码和搭建教程地址可以参考：`https://github.com/78778443/permeate`




### 5.1 插入XSS代码

笔者此前已经将permeate渗透测试系统搭建成功，下面将在此系统发表一个帖子，并在帖子标题中插入`XSS Platform`中预备好的攻击代码，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/7.png)

点击发表按钮，便将帖子发布成功，此时假定自己为受害者，访问了此帖子列表，在列表中会读取帖子的标题，帖子`<script>`标签别浏览器执行便不会显示出来，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/8.png)


### 5.2 接收cookie

可以看到并没有显示出来，再回到XSS Platform当中，查看`default`项目中的受害者列表，可以看到一个受害者，如下图所示

![image](http://tuchuang.songboy.site/xssplatform/9.png)

说明受害者已经成功中招，并且通过攻击代码已经获取到对方的cookie值和header信息

### 5.3 替换cookie

有了cookie值之后，笔者将使用另外一个浏览器，通过修改cookie的方式来登录受害者的账户，如下图修改cookie的操作
![image](http://tuchuang.songboy.site/xssplatform/10.png)

再次刷新时，已经变成了登录身份，如下图所示
![image](http://tuchuang.songboy.site/xssplatform/11.png)


## 六、图书推荐

如果对笔者的文章较为感兴趣，可以关注笔者新书《PHP Web安全开发实战》，现已在各大平台上架销售，封面如下图所示

![image](http://tuchuang.songboy.site/xss2/19.png?1)

--------------
作者：汤青松

日期：2018-12-08

微信：songboy8888
