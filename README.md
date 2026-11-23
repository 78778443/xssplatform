# XSS Platform

一个用于授权安全测试的 XSS 收包平台。前端为 React + shadcn/ui 单页应用，后端为 PHP（无需 Node 运行时即可部署）。

> 本工具仅面向合法授权的企业安全建设与安全测试行为。使用前请确保你已获得目标系统的明确授权，并遵守当地法律法规。

## 环境要求

- PHP ≥ 8.0（推荐 8.1–8.4）
- MySQL ≥ 5.6（推荐 8.0）
- PHP 扩展：`mysqli`、`curl`、`openssl`、`json`
- 生产部署**不需要** Node.js；只有修改前端时才需要 Node 22 + npm

## 安装

1. 把仓库放到 Web 根目录（`index.php` 所在目录即站点根）。
2. 按 `deploy/nginx.conf` 或 `deploy/.htaccess.example` 配置重写规则（收包地址 `/{6位}`、`/do/auth/...`、`/register/{key}` 必须保留，其余路径回落到 `index.php`）。
3. 浏览器打开 `/install`，按向导填写数据库信息与管理员账号。
4. 安装完成后用管理员账号登录。

安装向导会写入 `config.php` 与 `install/install.lock`。旧的 `install/step1.php` 等地址会自动跳转到 `/install`。

## 已安装的数据库升级

老版本升级时运行一次：

```bash
XSSP_CONFIG=/path/to/config.php php install/upgrade.php
```

脚本会把 `oc_user.userPwd` 扩到 `varchar(255)`（用于 `password_hash`）并把 `oc_session.data` 改为 `TEXT`，可重复执行。老用户的 md5 密码会在首次登录成功后自动升级为 `password_hash`。

## 常用功能

- 注册（普通 / 邀请 / 关闭三种模式）、登录、自动登录、邀请码管理
- 项目 CRUD、内容收包查看、域名过滤、批量删除、代码查看
- 模块 CRUD、公共模块审核
- 邮件提醒（PHPMailer 6）、钉钉机器人通知
- 收包端点：`index.php?do=code|api|do|keepsession`、`authtest.php`
- keepsession 定时任务：`XSSP_CONFIG=/path/to/config.php php scripts/keepsession.php`

## 老地址兼容

`index.php?do=...` 等旧页面地址会 302 到新的 SPA 路由；旧表单 POST 仍会执行。收包端点与 `/do/auth/{authCode}` 的 URL、参数、响应保持不变。

## 开发

```bash
# 后端测试（需要 Docker 拉取 MySQL 8）
bash tests/setup-db.sh
php tests/run.php tests/unit tests/integration

# HTTP / 安全测试（临时副本 + PHP 内置服务器，端口 18081）
bash tests/http/setup-installed.sh
php tests/run.php tests/http tests/security
kill $(cat /tmp/xssp-http-installed.pid)

# 前端开发与测试
cd frontend
npm install
npm run dev          # http://127.0.0.1:5173，代理 /api.php 到 18081
npm test
npm run build        # 输出到仓库根 dist/（构建产物已入库）
```

通知链路的集成测试需要 MailHog（`8025`）与 mock 钉钉端点（`19090`）；未启动时该用例会显示 SKIP。

## 代码结构

```
index.php              SPA 壳 / 老地址路由 / 收包端点分发
api.php                JSON API 前控制器（?action=...）
source/services/       业务服务（认证、项目、模块、用户、管理员、安装、收包）
source/class/          DB（mysqli 预处理语句）、User（会话/密码/CSRF）
source/lib/            Config、Api、UrlMap、Notify 等
frontend/              React + shadcn/ui 源码
dist/                  前端构建产物
deploy/                nginx / apache / PHP 内置服务器示例
tests/                 PHP 测试与前端测试
```

## 安全说明

- 所有 SQL 走预处理语句
- 写操作使用 POST + `X-CSRF-Token`，并校验同源
- 会话 Cookie 为 HttpOnly + SameSite=Lax，HTTPS 下自动 Secure
- 密码使用 `password_hash`，兼容旧 md5 并在登录时迁移
- 安装完成后 `install.submit` 等安装接口返回 403
- 收包端点的回跳地址只接受 http/https，空 Referer 回落到站点根

## 许可

本工具禁止进行未授权商业用途，禁止二次开发后进行未授权商业用途。详见仓库内原授权协议（安装向导第一步）。
