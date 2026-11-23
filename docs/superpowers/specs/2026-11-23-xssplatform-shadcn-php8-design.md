# XSS Platform：shadcn/ui 前端 + PHP 8 兼容与安全加固设计

- 日期：2026-11-23
- 状态：待评审
- 决策：前端选 **方案 A（React SPA + shadcn/ui）**；安装向导同样用 shadcn；老地址保留可用；功能不允许增减。

---

## 1. 意图与目标

把现有基于 Smarty 3 + Bootstrap 3 + jQuery 的 PHP 平台改造成：

1. **前端**：React + TypeScript + Tailwind + shadcn/ui 的现代界面，信息架构与**仍在使用的功能**一一对应；保留原有中文文案与操作路径。
2. **兼容**：在 PHP 8.0–8.4 上可运行（以 8.3 实测），消除 PHP 8 致命错误与弃用告警对实际请求的影响。
3. **安全**：修复平台自身漏洞（SQL 注入、CSRF、会话、安装器、开放重定向、输出注入等），但不改变 XSS 收包链路的行为与响应内容。
4. **兼容既有部署**：`/index.php?do=...`、`/register/{key}`、`/do/auth/{auth}`、`/{6位urlKey}` 等老地址继续可用（页面类地址做跳转，收包/接口类地址原样保留）；已有数据库与已抓取数据可继续使用。
5. **清理过时/失效代码**：按用户要求直接删除，不保留兼容层——飞信短信（PHPFetion/SendSMS）、Sina 短网址、验证码（Captcha 从未启用）、图片上传类（Image 无入口）、邮箱验证（mailauth 无发送/验证流程）、Smarty/Bootstrap 3/jQuery 旧主题、旧 PHPMailer 5/Smtp、SaeFetchurl、未使用的 User 方法与 helper。清单见 §4.4。

## 2. 非目标与反目标

**非目标**

- 不改变收包端点（`code`、`api`、`do`、`keepsession`）的 URL、请求参数、响应体格式与副作用。
- 不新增业务功能（如团队、2FA、审计日志、导出、复制按钮之外的交互能力）。
- 不引入 Node 运行时作为生产依赖；生产部署仍是 PHP + Web 服务器。
- 不迁移到 Next.js、不做多租户、不更换数据库引擎（继续 MySQL/MyISAM + utf8）。
- 不重写收包模块代码本身（`xssplatform.sql` 内置的 payload 文本保持原样）。
- “功能不允许变”指**仍在使用中的业务能力**不变；用户已明确要求删除过时/失效代码（“飞信啥的”），删除项见 §4.4。

**明确删除的功能/代码（用户决定）**

- 手机号 + 飞信短信通知（`SendSMS`、`source/api/PHPFetion.php`、`user.settings` 的短信开关、注册页手机号字段）。数据库 `oc_user.phone` 列保留但不再读写。
- Sina 短网址（`LongUrltoShortUrl`、`vita_get_url_content`；旧页面本就已注释）。
- 验证码（`captcha.php`、`Captcha.class.php`；登录校验旧代码已注释，从未启用）。
- 图片上传（`Image.class.php`；无任何上传入口）。
- 邮箱验证（`mailauth` 配置、`validated/validateKey` 判断、`validate.html` 缺失导致的提示流程）。
- 旧 UI：Smarty 库、Bootstrap 3、jQuery、Blueprint CSS、旧模板、`source/js/`。
- 旧邮件类 `PHPMailer.class.php`（5.x）/`Smtp.class.php`（换成 PHPMailer 6）。
- `signature`/`avatarImg` 会话字段、`point` 积分配置、`GetPoints`/`CheckField*`/`UpdateSession`/`UrlInvite`/`GetTimeShow`/`UBBToHTML`/`DoCode`/`FileSuffix`/`HTTP_REFERER` 等无调用代码。

**反目标（视为失败）**

- 老链接 404 或老表单 POST 直接失效。
- 收包端点的输出字节、状态码或重定向行为发生变化。
- 现有数据在界面上显示为 `&lt;`、`\"` 等原始转义文本（旧界面浏览器会解码显示）。
- 生产部署需要安装 Node/npm。
- 以“优化”之名删除仍在使用中的功能（管理员审核、邀请码、邮件/钉钉通知、收包、keepsession 等）。

## 3. 约束

- 生产环境 Web 根目录为仓库根目录（README 的 nginx 配置把项目放根路径）；SPA 资源用绝对 `/assets/...`。
- PHP ≥ 8.0；MySQL ≥ 5.6（测试用 MySQL 8）。需要 `mysqli`、`curl`、`openssl`、`json`；**不再需要 `gd`/`mbstring`**（验证码与 Smarty 已删除）。
- 所有构建产物入库（`dist/`），部署方不需要 Node；开发者需要 Node 22 + npm 构建。
- 继续使用 `config.php` 作为运行时配置（安装器写入），`config.php.default` 为模板。
- 会话机制继续使用自定义 `ocKey` Cookie + `oc_session` 表（不引入 PHP session 作为登录态）。
- 前端只做同源请求（`/api.php`），不引入跨域。
- **不保留 Smarty 渲染路径**：服务端只输出 JSON、收包响应和 SPA HTML 壳；旧 UI 资源删除。

## 4. 现状

### 4.1 请求入口

- `index.php`：校验 PHP 版本与 `install/install.lock`，按 `do` 白名单分发到 `source/{do}.php`。
  - 页面类：`index`、`login`、`project`、`module`、`user`。
  - 接口/收包类：`code`（输出 JS）、`api`（收包）、`do`（authCode 数据接口）、`keepsession`（会话保持）。
- `admin/index.php`：管理员入口，`do=admin_index|admin_module`。
- `authtest.php`、`captcha.php`：独立端点。
- `install/step1.php`→`step2.php`→`step3.php`：安装向导；`install/install.lock` 标记完成。
- `scripts/keepsession.php`：cron 脚本，遍历 `oc_keepsession` 用 SaeFetchurl 续期（该类缺失，当前必然致命）。
- 重写规则见 `安装说明.txt`：`/{6位}`、`/register/{key}`、`/do/auth/{auth}[/domain/{domain}]`（`/register-validate` 随邮箱验证删除）。

### 4.2 数据表（`xssplatform.sql`）

`oc_config`、`oc_invite_reg`、`oc_keepsession`、`oc_module`、`oc_project`、`oc_project_content`、`oc_remind`、`oc_session`、`oc_user`。
注意：

- `oc_user.userPwd` 为 `varchar(50)`（不足 60 字符的 `password_hash` 结果）。
- `oc_session.data` 为 `varchar(255)`（序列化数据可能被静默截断）。
- `oc_session.ocKey` 为 `varchar(50)`；现值为 32 位 md5。
- `oc_project_content` 无分页，项目内容全量返回。
- `admin_index` 查询的 `oc_content`、`oc_comment` 表不存在（后台概况当前报错）。
- 内置模块 payload 中的 `http://xsser.me` / `http://xssplatform.net` 由安装器替换为当前域名。

### 4.3 已发现的问题

**PHP 8**

- Smarty 3.1.33 渲染即产生大量 dynamic property / `strftime` 弃用告警；若保留渲染路径则告警会进入页面。
- 旧 PHPMailer（5.x）使用 `each()`、`get_magic_quotes_runtime()`（PHP 8 已移除），邮件链路会致命。
- `Smtp.class.php` 同类问题（当前未被调用）。
- `Notice($str = '', $turnto = URL_ROOT, $time = 3, $style = 'success', $urltitle)`：可选参数在必选参数前（PHP 8.0 弃用）。
- `User` 使用未声明属性 `avatarImg_s`（PHP 8.2 动态属性弃用）。
- `htmlspecialchars($_SERVER['HTTP_REFERER'])` 等对 `null` 传参（PHP 8.1 弃用）；`$_SERVER` 键未定义告警。
- PHP 8.1 起 `mysqli` 默认 `MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT`，连接/SQL 失败会抛异常，而代码按返回 `false` 处理。
- `scripts/keepsession.php` 依赖不存在的 `SaeFetchurl`。
- `auto_install.json` 的 `php_versions` 仅到 74。

**安全**

- 全站 SQL 字符串拼接，依赖 `addslashes`（`StripStr`），不是可靠防护；大量 `id/auth/urlKey/domain` 直接入 SQL。
- 删除入口使用 GET + token（token 出现在 URL/Referer 中）；`CheckToken` 用 `==`。
- `source/api.php` 直接 `Location: $_SERVER['HTTP_REFERER']`，且未校验 scheme（可被构造成非 http 值）。
- `unserialize($row['data'])` 未限制 `allowed_classes`。
- `ocKey` 由 `md5(id-userName-userPwd)` 派生，可预测；Cookie 未设置 SameSite。
- 安装器以 `HTTP_REFERER` 作为步骤校验；`step3` 用 `addslashes` 拼接数据库名；`HTTP_HOST` 直接写进 SQL 与配置（Host 注入）。
- `index.php` 用 `HTTP_HOST` 拼安装跳转地址。
- `config.php.default` 用 `HTTP_HOST` 拼 `urlroot`；`config.php` 入库。
- `admin_index` 查询不存在的表；`user.php` 保存设置时**不保存 dingding 字段**（钉钉通知因此永远无法配置）；项目删除不删除关联的 `project_content`；`authtest.php` 重定向写死 `http://127.0.0.1:8080`。
- `dingdingNotice` 关闭了 TLS 证书校验。
- `SendMail` 的 `SMTPDebug=1` 会把调试输出写进 HTTP 响应。
- 未输出安全响应头；`debug=true` 时错误直出。

**样式/一致性**

- Bootstrap 3 老皮肤；`notice.html` 引用的 `css/style/*.css` 全部 404；`css.css` 仅 16 行。
- 模板硬编码 `/index.php`、`/index.php?do=...`，非根路径部署会断链。
- 通知页固定 500ms 跳转，忽略 `$time`；多处面板/表格样式不一致。

### 4.4 过时代码清单（直接删除，不保留兼容层）

| 类别 | 文件/代码 | 理由 |
|---|---|---|
| 飞信短信 | `source/api/PHPFetion.php`、`SendSMS()`、`source/api.php` 中 `phone && msg[1]==1` 分支、注册页 `phone` 字段、设置页“短信提醒”开关与手机号输入、`phone_format` 校验 | 外部服务早已失效，注册/设置里只是噪音；`oc_user.phone`/`message` 中段列保留以兼容旧数据 |
| Sina 短网址 | `LongUrltoShortUrl()`、`vita_get_url_content()`、相关注释 | API 已下线，代码本就是注释状态 |
| 验证码 | `captcha.php`、`source/class/Captcha.class.php`、README/rewrite 中的相关提及 | 登录验证码校验早已被注释，端点无任何入口 |
| 图片上传 | `source/class/Image.class.php`、`FileSuffix()`、`FILE_PATH`/`FILE_PREFIX`/`imagePath`/`avatarPath`/`fieldPath` | 无上传路由，头像/签名也未在界面使用 |
| 邮箱验证 | `mailauth` 配置、`MAIL_AUTH`、`validated/validateKey` 判断、`User::ToValidate()`、`/validate` 页面设想 | 注册不发验证邮件，登录提示页模板也缺失，流程不成立 |
| 旧 UI | `libs/Smarty.class.php`、`libs/SmartyBC.class.php`、`libs/sysplugins/`、`libs/plugins/`、`libs/Autoloader.php`、`libs/bootstrap.php`、`libs/debug.tpl`、`themes/default/` 全部、`source/js/`、旧 `source/{index,login,register,project,module,user,common}.php` 中的模板渲染代码 | 新 UI 由 React 承担；旧模板与 jQuery/Bootstrap 3 无回退价值 |
| 旧邮件/短信类 | `source/class/PHPMailer.class.php`、`source/class/Smtp.class.php` | PHP 8 不兼容且被 PHPMailer 6 替代 |
| 未使用类/方法 | `source/class/Pager.class.php`（改为 service 内分页）、`User::GetPoints/CheckFieldAuth/CheckFieldManage/UpdateSession`、`UrlInvite`、`GetTimeShow`、`UBBToHTML`、`DoCode`、`HTTP_REFERER` | 全仓库无调用；部分依赖不存在的表/表字段 |
| 无效 cron 依赖 | `scripts/keepsession.php` 的 `SaeFetchurl` | 类不存在；改用 curl（功能保留） |

删除后仍保留的“兼容”只有三类：老 URL 的 302/服务执行、收包端点的字节级行为、旧数据库结构（不删列、不删表）。

## 5. 方案对比与选型

| 方案 | 内容 | 结论 |
|---|---|---|
| A. React SPA + shadcn/ui，PHP 保留并新增 JSON API | 真 shadcn 组件；收包端点不动；构建产物入库；部署仍纯 PHP | **采用**（用户已选） |
| B. 保留 Smarty，用 Tailwind 复刻 shadcn 视觉 | 改动小、无 API，但不是真 shadcn/ui，后续仍要写 Smarty | 不采用 |
| C. Next.js 全栈 | 部署不再是纯 PHP，与目标冲突 | 不采用 |

用户已确认：**安装向导也做 shadcn；老地址保留（页面地址跳转，接口/收包地址原样）**。

## 6. 目标架构

### 6.1 目录布局

```
/                     仓库根 = Web 根
├── index.php         前端入口：安装态判断、老地址路由/跳转、收包端点分发、SPA 壳输出
├── api.php           JSON API 前控制器（?action=...）
├── authtest.php      保留端点（修复重定向与转义）
├── config.php        运行时配置（安装器写入；保留以兼容已有部署）
├── config.php.default
├── dist/             Vite 构建产物（入库）：index.html + assets/
├── frontend/         React 源码（Vite + TS + Tailwind + shadcn/ui）
│   ├── package.json / package-lock.json / vite.config.ts / components.json
│   └── src/
│       ├── main.tsx / App.tsx / routes.tsx
│       ├── components/ui/      shadcn 组件
│       ├── components/…        业务组件（布局、表格、表单等）
│       ├── lib/                api 客户端、解码、校验、常量
│       └── pages/              与旧页面一一对应
├── source/
│   ├── bootstrap.php         统一引导（常量、配置、DB、User）
│   ├── services/             Auth/Project/Module/User/Admin/Install/Receiver/Code/AuthApi/KeepSession
│   ├── class/DB.class.php    mysqli 封装 + 预处理语句
│   ├── class/User.class.php  会话、密码、CSRF
│   ├── function.php          仅保留在用 helper（DBConnect/StripStr/Val/IP/OCEncrypt/OCSetCookie/JsonEncode/Tb/ShortUrlCode/SendMail）
│   ├── global.func.php      仅保留 dingdingNotice
│   ├── api.php / code.php / do.php / keepsession.php  收包端点（保持 URL 与输出）
│   └── (删除) 旧页面控制器、Smarty 渲染、Image/Captcha/Pager/Smtp/PHPFetion
├── libs/phpmailer/           PHPMailer 6.x（内置自动加载，无 Composer 运行时）
├── install/
│   ├── install.lock
│   ├── step1.php/step2.php/step3.php  仅保留 302 跳转到 SPA 安装向导
│   └── upgrade.php           幂等升级脚本（老库 ALTER）
├── deploy/                   nginx / apache / php -S 示例配置
├── tests/                    集成测试脚本与前端测试
└── (删除) libs/Smarty*、themes/、source/js/、source/api/PHPFetion.php 等见 §4.4
```

- `frontend/dist` 指向仓库根 `dist/`（Vite `build.outDir = ../dist`，`base = '/'`）。
- 旧 Smarty、旧模板、旧前端资源全部删除；服务端不再有模板渲染路径。

### 6.2 请求流程

```
浏览器
  ├─ GET /、/login、/project/1、/modules …  → Web 服务器 try_files → index.php
  │       ├─ 未安装：输出 SPA 壳（安装路由）
  │       └─ 已安装：输出 dist/index.html（SPA 由 React Router 渲染）
  ├─ GET/POST /api.php?action=…             → api.php → services → JSON
  ├─ GET /index.php?do=…（老地址）           → 页面类：302 到新路由 / 执行老 POST 后跳转
  │                                          → 收包类：包含旧端点文件，行为不变
  ├─ GET /{6位urlKey}、/do/auth/{auth}       → 重写到 index.php 的收包端点
  └─ GET /assets/…、/xsser.crx               → 静态文件
```

- `index.php` 在 `require config.php` **之前**判断 `install/install.lock`，保证未安装时 SPA 安装向导可加载。
- `api.php` 同样先判断安装态：未安装只允许 `install.*` 与 `meta`，其余返回 `not_installed`。

### 6.3 前端技术栈

- Vite + React 18/19 + TypeScript。
- Tailwind CSS v4（`@tailwindcss/vite`），shadcn/ui（style: new-york，baseColor: neutral，CSS variables）。
- React Router（history 模式）。
- TanStack Query（请求缓存/失效）。
- react-hook-form + zod（表单校验，规则与服务端一致）。
- sonner（toast）、lucide-react（图标）、next-themes（明暗主题，仅前端偏好）。
- 不引入外部 CDN、不加载第三方字体（使用系统字体栈，保证离线部署）。

### 6.4 后端分层

- `source/bootstrap.php`：定义常量、读取 `config.php`、创建单例 DB、构造 `User`、加载通用函数。`init.php` 与 `api.php` 共用。
- `DBConnect()` 改为进程内单例，避免每个请求多次建连（现在每次调用都会新建连接）。
- `services/*`：纯业务，输入为已校验的参数，输出数组/抛 `AppException`。**legacy 路由与 JSON API 共用同一套 service**，保证功能一致。
- `api.php`：解析 `action`、鉴权、CSRF/同源校验、调用 service、统一 JSON 输出。
- 旧端点文件 `source/{code,api,do,keepsession}.php` 保留，改为调用对应 service 输出（输出字节保持）。

## 7. URL 与页面映射（兼容表）

### 7.1 SPA 路由

| 路由 | 对应旧页面 |
|---|---|
| `/install?step=1|2|3` | `install/step1..3` |
| `/login` | `do=login` 默认页 |
| `/register`（`?key=`） | `do=register` 默认页 |
| `/` | `do=index` |
| `/project/new` | `do=project&act=create` |
| `/project/:id`（`?domain=`） | `do=project&act=view` |
| `/project/:id/config`（`?created=1`） | `do=project&act=setcode` |
| `/project/:id/code` | `do=project&act=viewcode` |
| `/modules` | `do=module` 默认页 |
| `/modules/new` | `do=module&act=create` |
| `/modules/:id` | `do=module&act=view` |
| `/modules/:id/edit` | `do=module&act=set` |
| `/settings` | `do=user&act=seting` |
| `/invites` | `do=user&act=invite` |
| `/admin` | `admin/index.php?do=admin_index` |
| `/admin/modules` | `admin/index.php?do=admin_module` |
| `/logout` | `do=login&act=logout` |
| `*` | 404 页 |

（不再有 `/validate`：邮箱验证流程已删除，见 §4.4。）

### 7.2 老地址处理

**页面类（302 跳转）**

| 老地址 | 新地址 |
|---|---|
| `index.php?do=index` | `/` |
| `index.php?do=login` | `/login` |
| `index.php?do=register&key=K` | `/register?key=K` |
| `index.php?do=project&act=view&id=N&domain=D` | `/project/N?domain=D` |
| `index.php?do=project&act=create` | `/project/new` |
| `index.php?do=project&act=setcode&id=N&ty=T` | `/project/N/config` |
| `index.php?do=project&act=viewcode&id=N&ty=T` | `/project/N/code` |
| `index.php?do=module` | `/modules` |
| `index.php?do=module&act=create` | `/modules/new` |
| `index.php?do=module&act=view&id=N` | `/modules/N` |
| `index.php?do=module&act=set&id=N` | `/modules/N/edit` |
| `index.php?do=user&act=invite` | `/invites` |
| `index.php?do=user&act=seting` | `/settings` |
| `admin/index.php?do=admin_index` | `/admin` |
| `admin/index.php?do=admin_module` | `/admin/modules` |
| `install/step1.php`… | `/install?step=1…` |
| `/register/{key}`（重写后） | `/register?key=…` |

**老表单/动作（保留执行）**：对 `act=submit|create_submit|setcode_submit|set_submit|delete|delcontent|delcontents|createinvite|audit|logout` 等，老地址若收到请求，`index.php` 调用同一 service 后 302 到对应新页面；`checkue` 与 `delcontent(s)` 这类 AJAX 仍按老格式返回 `1` / `a|b|c`。

**收包/接口类（完全保留）**

| 地址 | 行为 |
|---|---|
| `/{6位}` / `index.php?do=code&urlKey=…` / `&id=…` | 输出 `application/x-javascript`，模块代码 + 自定义代码，`htmlspecialchars_decode`，`Cache-Control: nocache` |
| `index.php?do=api&id={urlKey}&…` | 收包、去重、通知、`302` 回 Referer |
| `index.php?do=do&auth=…[&domain=…]` | 返回 `[{"url","cookie"}]` JSON |
| `index.php?do=keepsession&id=…&url=…&cookie=…` | 记录 keepsession（每用户上限 10 条） |
| `authtest.php?id=…&info=…` | Basic Auth 钓鱼端点（重定向目标改为本平台 API） |

`captcha.php` 删除（从未启用，见 §4.4）；旧地址不再保留。

## 8. API 契约

### 8.1 通用约定

- 入口：`/api.php?action=<name>`，同源，`credentials: same-origin`。
- 请求体：`application/json`；老格式表单也接受。
- 成功：`{"ok":true,"data":...}`；失败：`{"ok":false,"error":{"code":"<code>","message":"<中文原文>","fields":{...}}}`，HTTP 状态 400/401/403/404/409/419/500。
- 鉴权：读 `ocKey` Cookie，由 `User` 解析；未登录访问受保护 action → 401。
- CSRF：所有已登录的写操作（POST）必须带 `X-CSRF-Token`，值为 `session` 返回的 `csrf`；失败 → 419 `operation_failed`（文案“操作失败”）。
- 同源校验：所有写操作校验 `Origin`/`Referer` 与 `urlroot` 主机一致（登录/注册/安装也校验，防登录 CSRF）。
- 安装态：未安装时非 `install.*`/`meta` action → 409 `not_installed`。
- 响应头：`Content-Type: application/json; charset=utf-8`、`Cache-Control: no-store`、`X-Content-Type-Options: nosniff`。
- 所有 SQL 走预处理语句；所有输出前不信任客户端。

### 8.2 action 列表

| action | 方法 | 权限 | 说明 |
|---|---|---|---|
| `meta` | GET | 公开 | 安装态、站点名、注册模式、urlroot、urlrewrite、当前登录态 |
| `session` | GET | 公开 | `meta` + `user{id,userName,adminLevel,token}` + `csrf` |
| `login` | POST | 公开 | `user,pwd,auto`；成功设置 Cookie |
| `logout` | POST | 登录 | 清除 Cookie 与该用户全部 session |
| `register.check` | POST | 公开 | `user,email,key` → `{userExisted,emailExisted,keyError}`（与旧 `a|b|c` 等价） |
| `register.submit` | POST | 公开 | `user,email,pwd,key`；规则/文案/邀请核销/自动登录同旧（不再收 `phone`） |
| `dashboard` | GET | 登录 | 项目列表（含 `contentNum`）+ 侧栏模块列表 |
| `project.create` | POST | 登录 | `title,description` → 返回 `{id}` |
| `project.get` | GET | 登录/归属 | `id,domain` → 项目 + 内容列表 + 域名列表 |
| `project.setcode.get` | GET | 登录/归属 | `id` → 项目、可用模块（含 `choosed`、`setkeys` 值与 `keys`、`code`）、`ty` |
| `project.setcode.save` | POST | 登录/归属 | `id,ty,modules[],code,setkey_*`；行为同旧 |
| `project.viewcode` | GET | 登录/归属 | `id` → `{code, codeurl, scriptShow1, scriptShow2, shortShow1, shortShow3, ty}`，字符串由服务端按旧算法生成 |
| `project.delete` | POST | 登录/归属 | 删除项目 + 关联内容 + keepsession |
| `project.contents.delete` | POST | 登录/归属 | 单条删除，返回 `1` |
| `project.contents.deleteMany` | POST | 登录/归属 | `ids` 数组，逐条校验归属，返回 `1` |
| `modules.list` | GET | 登录 | 侧栏/模块页所需列表（私有 + 公共已审核） |
| `module.create` | POST | 登录 | `title,description,keys[],setkeys[],code,isOpen` |
| `module.get` | GET | 登录/可见 | `id` → 模块详情 + `canEdit`（公共已审核且非管理员 → false） |
| `module.save` | POST | 登录/归属 | 同旧 `set_submit` 规则 |
| `module.delete` | POST | 登录/归属 | 删除模块 |
| `user.settings.get` | GET | 登录 | `email,dingding,emsg,dmsg`（不再有 phone/pmsg） |
| `user.settings.save` | POST | 登录 | `emsg,dmsg,dingding`；更新 `message` 为 `e|0|d`（中段保留给旧数据）+ 保存 dingding |
| `user.invites` | GET | 登录 | 未使用邀请码，分 `isWooyun` 两组 |
| `user.invite.create` | POST | 管理员 | `isWooyun`；最多 100 个未使用 |
| `admin.overview` | GET | 管理员 | 用户数、在线数、在线用户、系统环境 |
| `admin.modules` | GET | 管理员 | 分页（20/页），公共模块列表 |
| `admin.module.audit` | POST | 管理员 | `id,isAudit`；写 `managerId/managerName` |
| `install.status` | GET | 公开（未安装） | 锁、`config.php` 可写、PHP 版本/扩展检查 |
| `install.testDb` | POST | 公开（未安装） | 校验连接参数，返回连接结果 |
| `install.submit` | POST | 公开（未安装） | 写配置、建库、导入 SQL、设置管理员、写锁 |

### 8.3 关键响应形状

- `session`：
  ```json
  {"ok":true,"data":{
    "user":{"id":2,"userName":"admin","adminLevel":1,"token":"…"},
    "csrf":"…",
    "config":{"sitename":"XSS平台","sitedesc":"","register":"normal",
              "urlroot":"http://…","urlrewrite":false,"expires":3600},
    "installed":true
  }}
  ```
- `project.viewcode`：`code`/`scriptShow*` 为**旧模板在浏览器中最终显示的文本**（已 `htmlspecialchars_decode`，等价于旧 `<pre>` 的呈现），前端只作为纯文本渲染。
- `project.get`：内容项 `{id, content:{k:v}, serverContent:{k:v}, domain, addTime}`；`content`/`serverContent` 的值做 `html_entity_decode` 后再返回（旧界面由浏览器解码）。原始存储格式不变。

### 8.4 错误码（与旧文案对应）

`login_failed`（登录失败,请检查用户/邮箱或密码）、`login_empty_user`、`login_empty_pwd`、`already_logged_in`、`register_closed`、`register_invite_only`、`invite_invalid`、`username_format`、`email_format`、`pwd_format`、`username_exists`、`email_exists`、`register_failed`、`not_logged_in`（未登录或已超时）、`no_permission`（没有操作权限）、`project_not_found`（项目不存在或没有权限）、`module_not_found`（模块不存在或没有权限）、`module_locked`（已通过审核的公共模块不能修改）、`title_required`（模块/项目名称不能为空）、`operation_failed`（操作失败）、`not_installed`、`install_failed`、`internal_error`。

## 9. 功能等价清单（逐页）

| 旧页面 | 必须保留的交互/字段 |
|---|---|
| 登录 | 用户名或邮箱、密码、自动登录（360 天）、“已经登录”提示；成功回首页 |
| 注册 | 邀请码、用户名、邮箱、密码、确认密码（前端）；前端格式校验 + 服务端校验；`checkue` 预检查；邀请模式/关闭模式；注册后自动登录（手机号字段删除） |
| 首页 | 侧栏“我的项目/我的模块/公共模块”；项目表：名称、描述、内容数、创建时间、删除；创建入口 |
| 项目内容 | 域名筛选、全选/多选删除、逐行展开/折叠、时间、接收内容、Request Headers、删除、接口地址、authCode、安装插件（`/xsser.crx`）、查看代码、配置 |
| 项目代码 | 项目代码、三种植入方式、codeurl、短网址展示区（保留现有展示项）、完成/返回首页 |
| 项目配置 | 模块勾选、模块参数配置（模块 1 的 keepsession 单选保留）、展开查看模块参数与代码、自定义代码框、下一步/配置、取消 |
| 模块列表/创建/查看/编辑 | 标题、描述、接收参数（默认 location/toplocation/cookie/opener + 自定义）、配置参数、代码、私有/公开；公共已审核模块只读（管理员例外） |
| 个人设置 | 邮箱展示、邮件/钉钉两个开关、钉钉 Key、提交（手机号与短信开关删除） |
| 邀请 | 乌云币邀请码 / 其它邀请码两张表、生成按钮；非管理员点生成提示无权限 |
| 管理员 | 概况（用户数、在线数、在线用户、PHP/MySQL/OS/Server 信息）；公共模块分页列表、审核通过/取消 |
| 安装 | 协议同意（检查配置可写）、数据库地址/用户名/密码/库名、管理员账号密码、导入结果、进入首页、写入 `install.lock` |

> 明确移除的旧 UI：注册/设置中的手机号与短信开关、验证码、短网址展示区（`shortShow1/shortShow3` 保留原样，仍展示长链接与 `<img onerror>` 片段；仅删除失效的 Sina 转换代码）。

## 10. 数据与显示兼容

旧数据写入时经过 `StripStr = addslashes(htmlspecialchars($str))`：

- 存库值可能是 `&lt;`、`&quot;`、`&#039;`、`\"`；旧模板把值直接插入 HTML，浏览器会**解码实体**后显示。
- 新前端用 React 文本渲染（不做 HTML 注入），因此：
  - API 对**展示/编辑字段**先 `html_entity_decode(..., ENT_QUOTES)` 再返回；
  - 前端一律作为文本/`textarea` 值渲染，绝不 `dangerouslySetInnerHTML`；
  - 收包端点 `code.php` 沿用 `htmlspecialchars_decode`，输出字节不变。
- 写路径继续调用 `StripStr`（保持与旧数据/模块代码格式完全一致）；SQL 改预处理后不再依赖 `addslashes` 的安全性，但保留其数据格式副作用。
- `moduleSetKeys` 中值仍是 `urlencode` 存储：编辑页返回 `urldecode`，保存时再 `urlencode`；`viewcode`/`code.php` 里用存储值拼接（保持现状）。
- 列表中的 `keys`/`setkeys`（JSON）同样按实体解码后展示。
- 通知（邮件/钉钉）里的文案沿用旧值（实体未解码，保持邮件客户端的原呈现）。

## 11. 安全修复清单

1. **SQL 注入**：所有查询改 `mysqli` 预处理语句；`IN (...)` 列表逐项 `intval`；动态参数名（`setkey_{id}_{key}`）只从模块 `setkeys` 白名单取值。覆盖附录 A 全部查询。
2. **会话**
   - `ocKey` 改为 `bin2hex(random_bytes(24))`（48 字符，适配 `varchar(50)`）；老 Cookie 仍可识别；
   - Cookie：`HttpOnly`、`SameSite=Lax`、HTTPS 时 `Secure`；
   - `unserialize($data, ['allowed_classes' => false])`，非数组则视为无效会话；
   - 登录成功清除过期 session 行、写入随机 token；`CheckToken` 改 `hash_equals`。
3. **密码**：新注册/安装写 `password_hash(PASSWORD_DEFAULT)`；登录兼容旧 md5（32 位十六进制）并在成功后回写新哈希；`oc_user.userPwd` 扩到 `varchar(255)`。
4. **CSRF / 越权**：所有写操作 POST + `X-CSRF-Token` + Origin/Referer 同源；所有对象操作继续校验 `userId` 归属；管理员 action 校验 `adminLevel>0`。
5. **收包端点**：SQL 预处理；`HTTP_REFERER` 为空时回退首页；Location 前校验 scheme 为 `http/https`（保留正常回跳行为）；`X-Forwarded-For` 仅作为展示数据，保持现状。
6. **安装器**：去掉 Referer 步骤校验；`install.lock` 存在即拒绝；数据库名 `^[A-Za-z0-9_]+$`；`CREATE DATABASE` 用校验后的标识符；Host 只允许 `[A-Za-z0-9.:-]` 且 scheme 限定 http/https；配置文件原子写入（临时文件 + rename）。
7. **Host 头**：`index.php` 安装跳转改相对 `Location: /install/step1.php`；安装器入库 URL 使用校验后的 host。
8. **输出**：React 文本渲染；模板硬编码 URL 全部改路由；`authtest.php` 的 realm/参数做头与 URL 转义，重定向改用 `URL_ROOT`。
9. **通知**：钉钉开启 TLS 校验；`SendMail` 关闭 SMTPDebug（避免污染 HTTP 响应），配置支持 `port/secure`；飞信短信整条链路删除（含 `phone`/`pmsg` 输入与发信分支）。
10. **响应头**：`X-Content-Type-Options: nosniff`、`Referrer-Policy: same-origin`、`X-Frame-Options: SAMEORIGIN`、SPA HTML 加 CSP（`default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'self'`）。
11. **配置**：`debug` 默认 false；错误进 `error_log`；安装器写 `config.php` 权限 0640；`config.php.default` 不含真实凭据。
12. **数据一致性修复**（安全相关缺陷）：删除项目时级联删除 `project_content`；保存 dingding 字段；安装器管理员密码哈希。
13. **随机性**：`urlKey` 用 `random_int`；`authCode`、邀请码改用 `random_bytes`（已有值不受影响）。

## 12. PHP 8 兼容清单

1. `index.php` 版本门槛改 `8.0`，提示文案同步。
2. `mysqli`：`mysqli_report(MYSQLI_REPORT_OFF)`；连接/查询失败转内部异常，按 `debug` 输出或记录；`mysqli_set_charset` 替代 `SET NAMES` 拼接。
3. `Htmlspecialchars(null)`、未定义 `$_SERVER` 键：全部 `?? ''`。
4. 删除 `Notice/ShowSuccess/ShowError/InitSmarty` 的 Smarty 渲染实现；legacy 动作改为 302 到 SPA 并携带 `?error=`/`?ok=` 代码（不再渲染模板）。
5. `User` 不再保留 `avatarImg/avatarImg_s/signature` 字段（随旧 UI 删除），不产生动态属性。
6. 旧 PHPMailer/Smtp：替换为 **PHPMailer 6.x**（`libs/phpmailer/`，内置 autoload）；旧类文件删除。
7. `scripts/keepsession.php`：用 curl 替代 `SaeFetchurl`，保留“300 秒阈值 + 更新 updateTime”的语义。
8. `captcha.php`/`Captcha`：删除（不启用、无入口）。
9. `auto_install.json`：`php_versions` 改为 `80,81,82,83,84`。
10. Smarty：整个库与模板删除；不存在任何 PHP 8 模板渲染路径。
11. 全量 `php -l` + 运行时 `E_ALL` 冒烟：不得出现致命错误或未捕获警告。

## 13. 前端设计

### 13.1 视觉方向

- shadcn new-york + neutral；主色为冷静的蓝（`--primary`），中性灰背景，卡片化分区。
- 密度偏紧凑（安全工具/控制台气质）；数据用等宽字体（代码、Cookie、URL）。
- 顶部栏 + 左侧栏：顶栏品牌/用户菜单；侧栏“我的项目 / 我的模块 / 公共模块”，管理员多“管理后台”。
- 明暗主题切换（偏好存 `localStorage`，仅前端）。
- 移动端：侧栏收进 Sheet；表格横向滚动；表单单列。
- 空状态、加载骨架、错误页统一；危险操作（删除）用 `AlertDialog` 二次确认。
- 通知统一 toast；表单错误就地显示；不使用整页跳转的“提示页”。

### 13.2 组件与页面

- 基础：Button、Card、Input、Label、Textarea、Checkbox、Switch、Select、Table、Badge、Separator、Skeleton、Tooltip、Tabs、Dialog/AlertDialog、Sheet、DropdownMenu、Sonner。
- 登录/注册：居中卡片；注册保留全部字段与前端校验；`checkue` 实时反馈。
- 首页：项目表 + 侧栏；创建项目按钮。
- 项目内容：域名筛选 Select、全选/批量删除、可展开行（content/serverContent 键值列表，等宽字体）、删除确认、代码/配置入口、插件下载。
- 项目代码：分块展示代码与植入方式，每块提供复制按钮（纯展现便利）；“完成/返回首页”。
- 项目配置：模块列表（可展开查看参数与代码）、勾选、参数输入（模块 1 keepsession 用单选）、自定义代码 `Textarea`、提交/取消。
- 模块：列表、创建、只读查看、编辑；动态添加/删除参数（`keys[]`/`setkeys[]` 的交互等价）。
- 设置：邮箱展示、邮件/钉钉两个开关、钉钉 Key、提交。
- 邀请：两张表 + 生成按钮（管理员）。
- 管理员：概况统计卡 + 环境信息卡；模块分页表 + 审核按钮。
- 安装：三步 Stepper（协议 → 数据库/管理员 → 执行结果）；协议页检查 `config.php` 可写；失败展示服务端原因。

### 13.3 路由与状态

- `session` 拉取登录态与配置，全局缓存；401 统一跳登录。
- 未安装（`meta.installed=false`）时任意路由进 `/install`。
- 管理员路由按 `adminLevel` 守卫，非管理员显示 403 页（文案“没有操作权限”）。
- 表单提交后 invalidate 相关查询并 toast；删除后局部刷新。
- 网络错误统一 toast“网络错误，请稍后重试”（不改变业务文案）。

### 13.4 交互细节

- 所有值为纯文本渲染；`html_entity_decode` 已在服务端完成；代码块用 `<pre>` + 等宽字体，不解析 HTML。
- 复制按钮只复制服务端返回的字符串。
- `Textarea` 中代码保留换行；不做富文本。
- 旧页面的“+全部/+展开”行为以可展开行保留；批量删除逻辑等价。
- 空列表显示空状态，不报错。

## 14. 安装向导

1. `install.status`：`install.lock` 是否存在、`config.php` 可写、PHP 版本与 `mysqli/curl/openssl` 扩展。
2. Step 1 协议：与旧协议文本一致（可滚动），未满足可写条件时禁用“我同意”。
3. Step 2 表单：数据库地址/用户/密码/库名、管理员用户名/密码；`install.testDb` 可先测试连接。
4. Step 3 执行：`install.submit`
   - 连接（不选库）→ 校验并 `CREATE DATABASE IF NOT EXISTS`（库名白名单）→ 选库 → 导入 `xssplatform.sql` → 替换演示域名为当前 `urlroot`（host 校验）→ 更新首条用户为管理员（`password_hash`）→ 写 `config.php`（原子）→ 写 `install.lock`。
   - 失败：返回具体步骤与原因；成功：进入 `/login`。
5. 已安装访问 `/install`：显示“已安装”并提供回首页；`install.*` API 返回 403。
6. `install/step1..3.php` 保留为 302 跳转，避免老书签失效。

## 15. 构建与部署

- 构建：`cd frontend && npm ci && npm run build` → 输出 `../dist`（`index.html` + `assets/`）。
- 开发：`npm run dev`，Vite proxy `/api.php`、`/install` 到 PHP `127.0.0.1:8080`。
- 生产 nginx（示例 `deploy/nginx.conf`）：
  ```
  root /path/to/xssplatform;
  index index.php;
  rewrite "^/([0-9a-zA-Z]{6})$" /index.php?do=code&urlKey=$1 last;
  rewrite "^/do/auth/(\w+?)(/domain/([\w\.]+?))?$" /index.php?do=do&auth=$1&domain=$3 last;
  rewrite "^/register/(.*?)$" /index.php?do=register&key=$1 last;
  location / { try_files $uri $uri/ /index.php$is_args$args; }
  location ~ \.php$ { fastcgi_pass …; … }
  ```
  - `/assets/` 与 `/dist/` 作为静态目录由 nginx 直接服务；`/xsser.crx` 保持。
- Apache：`.htaccess` 重写 + fallback 到 `index.php`（示例 `deploy/.htaccess.example`）。
- PHP 内置服务器：`php -S 127.0.0.1:8080 deploy/router.php`（开发/测试用）。
- README 更新：PHP ≥ 8.0、前端构建、老库升级、nginx/apache 配置、开发/测试命令。
- `dist/` 入库；`frontend/node_modules` 不进库。

## 16. 测试与验收

**静态**

- `php -l` 全量通过。
- 前端 `tsc --noEmit`、`npm run build` 通过。
- `npm audit` 无高危（允许保留并说明）。
- PHP 8.3 下 `E_ALL` 冒烟无致命错误。

**集成（真实 MySQL）**

用 `sudo docker` 起 MySQL 8（或 MariaDB 10.11）与 PHP 内置服务器，在临时副本上执行（避免改动开发配置）：

1. 安装：status → submit → 校验 `config.php`、`install.lock`、表、管理员哈希。
2. 登录/退出：Cookie 属性、token、旧 md5 账号自动迁移、错误文案。
3. 注册：normal/invite/close、重复校验、`checkue`、邀请核销、自动登录。
4. 项目：创建 → 配置（模块与 setkey）→ 代码页 → 收包（模拟受害者请求，验证 content/serverContent/domain/去重）→ 内容页 → `do` 接口 → 单删/批删 → 项目删除（级联内容）。
5. 模块：创建/查看/编辑/删除；公共已审核模块非管理员不可改；管理员审核。
6. 用户设置：邮件/钉钉两开关、dingding 持久化（手机号/短信已删除，旧 POST 中的 `phone`/`pmsg` 被忽略）。
7. 邀请：管理员生成/上限/分组列表。
8. 管理员：概况统计、模块分页、审核。
9. 老地址：页面 302、老表单 POST 执行、`/{6位}`、`/do/auth/*`、`/register/{key}`。
10. 安全用例：SQLi 载荷（id/urlKey/auth/domain/setkey）、缺 CSRF、越权访问他人项目/模块、未登录访问 API、已安装重跑安装、Host 注入、开放重定向 scheme 校验。
11. 通知：邮件用本地 SMTP 捕获器（如 MailHog）验证不再致命且能发出；钉钉用 mock 端点验证请求体。

**端到端（可选但推荐）**

- Playwright 截图/流程：登录、首页、项目内容、代码页、模块创建、安装向导；确认视觉与移动端布局。

**验收标准**

- 第 9 节清单逐项通过。
- 收包端点响应与旧版逐字节比对（同一输入）。
- 老地址全部可用。
- PHP 8.3 日志无致命/未捕获错误。

## 17. 迁移与升级

- 新装：使用更新后的 `xssplatform.sql`（`userPwd varchar(255)`、`oc_session.data TEXT`）。
- 老库：运行 `install/upgrade.php`（CLI）：
  - `ALTER TABLE oc_user MODIFY userPwd varchar(255) NULL`；
  - `ALTER TABLE oc_session MODIFY data TEXT`；
  - 幂等检查，仅打印/执行缺少的变更；
  - 不批量重置密码，旧 md5 在首次登录时迁移。
- 老配置：保留 `config.php`；新增可选项（`email.port`、`email.secure`、`email.fromName`）有默认值，缺失不影响启动。
- 老前端缓存：`dist` 资源带内容哈希；部署后刷新即可。
- 老安装目录：`install/` 页面保留跳转；`install.lock` 语义不变。

## 18. 风险与缓解

| 风险 | 缓解 |
|---|---|
| 实体解码后显示与原界面不一致 | 服务端统一 `html_entity_decode`，前端纯文本渲染；用旧/新并排截图比对 |
| 老表单 POST 兼容遗漏 | service 层复用；测试覆盖每个 act |
| SPA 深链依赖服务器 fallback | 提供 nginx/apache/php -S 三套配置并测试 |
| `ocKey` 长度/`userPwd` 长度变化 | 48 字符 ocKey 适配 50；升级脚本扩列 |
| 构建产物入库导致 diff 大 | 独立 `dist/`、`assets` 带哈希；源码与产物分开提交说明 |
| 邮件/钉钉外部依赖不稳定 | 本地 mock 测试；失败记录日志不阻塞收包 |
| MySQL 8 默认严格模式/异常 | 预处理 + 显式错误处理；兼容测试 |
| 管理员概况旧表缺失 | 只保留可通过现有表计算的统计，环境信息照旧 |

## 19. 待用户确认 / 留给 builder

**待用户确认（会改变行为）**

1. **密码哈希迁移**：新密码用 `password_hash`，老 md5 首次登录自动升级，需要一次 `ALTER TABLE`（`userPwd` 扩到 255）。建议做；若要求“绝不改库结构”，则只保留 md5。
2. **项目配置页预填自定义代码**：旧页面保存配置时会用空 textarea 覆盖已有自定义代码（数据丢失缺陷）。建议预填现有 `code`，避免误删；若严格保持旧行为则继续清空。
3. **修复三个“当前已坏”的功能**：保存钉钉 Key、`authtest.php` 的重定向目标改为本平台、cron 脚本用 curl 替代缺失的 SaeFetchurl。建议修复（飞信短信已按你的要求直接删除，不在此列）。

**留给 builder**

- 具体 shadcn 组件版本与 Tailwind v4 配置细节。
- service 层的类/函数划分粒度、错误类型命名。
- 集成测试与 Playwright 的具体组织。
- 页面细节布局（在不改变信息项与操作的前提下）。
- `dist` 的提交方式（单次提交或分提交）。

## 附录 A：需要改为预处理语句的查询清单（按新结构）

- `User::__construct`（session 读取/更新）、`Login`（用户查询、session 清理/写入、loginTime）、`Logout`。
- `services/AuthService`：登录、注册校验、邀请码查询/核销、用户插入。
- `services/ProjectService`：项目列表/内容数、urlKey 列表、创建、归属校验、可用模块、setcode 读取/保存、内容列表（domain 过滤）、viewcode 模块查询、单删/批删、项目删除级联。
- `services/ModuleService`：config keys、创建、可见性/归属校验、查看、编辑、删除。
- `services/UserService`：设置读取/更新、邀请列表/计数/插入。
- `services/AdminService`：用户数、在线数、在线用户、MySQL 版本、模块分页/审核。
- `services/ReceiverService`（`source/api.php`）：项目/模块查询、去重计数、插入/更新、通知用户信息。
- `services/CodeService`（`source/code.php`）：项目/模块查询。
- `services/AuthApiService`（`source/do.php`）：项目查询、内容查询。
- `services/KeepSessionService`（`source/keepsession.php`）：项目查询、hash 计数、用户条数、插入。
- `services/InstallService`：建库、导库、管理员写入、系统检查。
- `source/class/DB.class.php` 是唯一 SQL 出口；`DBConnect` 为单例。

## 附录 B：通知链路

- 邮件：`oc_user.email` + `message[0]==1` → `SendMail`（PHPMailer 6，配置 `email.host/username/password/port/secure`）。
- 钉钉：`oc_user.dingding` + `message[2]==1` → `dingdingNotice`（`config.dingding.keyword`，TLS 校验开启，超时保留）。
- 短信（飞信）已删除；`message` 中段固定写 `0`，旧值不迁移。
- 触发点：仅在 `do=api` 首次插入内容（非去重更新）时；文案与旧版一致。
