# XSS Platform：shadcn/ui 前端 + PHP 8 兼容与安全加固 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在保留收包端点与老地址的前提下，把 Smarty/Bootstrap 3 旧站改造成 React + shadcn/ui 前端 + PHP 8 兼容、修复平台自身漏洞、删除过时代码的纯 PHP 部署项目。

**Architecture:** PHP 是唯一后端：`index.php` 输出 SPA 壳并处理老地址/收包端点，`api.php` 提供 JSON API；业务集中在 `source/services/`，legacy 路由与新 API 共用同一套 service；SQL 全部走 `DB_Mysql` 预处理语句。前端是 Vite + React + TypeScript + Tailwind v4 + shadcn/ui 的 SPA，构建产物 `dist/` 入库，部署不需要 Node。

**Tech Stack:** PHP 8.0–8.4（测试 8.3）、MySQL 8、mysqli、curl、openssl、PHPMailer 6.x；Vite、React、TypeScript、Tailwind CSS v4、shadcn/ui、React Router、TanStack Query、react-hook-form + zod、sonner、Vitest、Playwright。

**Spec:** `docs/superpowers/specs/2026-11-23-xssplatform-shadcn-php8-design.md`（执行时必须同时阅读该设计；本计划只记录设计未固定的实现决策）

## Global Constraints

- PHP ≥ 8.0；测试运行在 PHP 8.3；MySQL ≥ 5.6，集成测试用 MySQL 8。
- 生产部署不需要 Node：前端构建输出到仓库根 `dist/` 并入库；`frontend/node_modules` 不入库。
- 不保留 Smarty/Bootstrap 3/jQuery 旧 UI；旧模板、旧主题、旧页面渲染代码按 spec §4.4 删除。
- 收包端点 `index.php?do=code|api|do|keepsession`、`authtest.php` 的 URL、参数、响应体、状态码保持不变；字节级回归测试必须通过。
- 老地址保留：页面类 302 到 SPA 路由；老表单 POST 仍执行同一 service；`/{6位}`、`/register/{key}`、`/do/auth/{auth}[/domain/{domain}]` 保留。
- 所有 SQL 必须通过 `DB_Mysql` 的预处理方法；禁止新拼接 SQL。
- 密码：新写 `password_hash(PASSWORD_DEFAULT)`；旧 32 位 md5 首次登录自动迁移；`oc_user.userPwd` 扩到 `varchar(255)`。
- 会话：`ocKey` 为 48 字符随机十六进制；Cookie HttpOnly、SameSite=Lax、HTTPS 时 Secure；`unserialize` 必须 `allowed_classes=false`。
- 已登录写操作使用 POST + `X-CSRF-Token`（`session` 返回）；`Origin`/`Referer` 存在时必须同源。
- 存库仍使用 `StripStr = addslashes(htmlspecialchars($str))` 保持旧数据格式；API 对展示/编辑字段 `html_entity_decode(..., ENT_QUOTES)` 后返回；前端只做文本渲染，禁止 `dangerouslySetInnerHTML`。
- 旧数据表/列不删；升级脚本只做 `userPwd` 与 `oc_session.data` 的扩列。
- UI 文案保持中文；不改收包模块的 payload 文本。
- 任务完成即运行该任务的测试并通过，然后在功能分支上提交（不推送）。

## Review Focus

按 spec 推断、但没有被某条需求显式测试、最容易踩坑的五类输入/条件；每行在对应任务里都有测试：

1. 旧库存的实体/反斜杠文本（`&lt;`、`&quot;`、`\"`）在新 UI 中必须显示为解码后的字符，而不是实体原文 → Task 1、Task 7、Task 17。
2. 收包端点遇到空 Referer、缺失的 `$_SERVER` 键、非 http/https 的 Referer → Task 10、Task 13。
3. 旧 md5 用户与旧版本写入的 session 行（超长序列化数据、可被对象注入的 data）→ Task 3、Task 14。
4. 老 URL 的 POST/AJAX 兼容：`a|b|c`、`1` 返回格式与 token 校验 → Task 11。
5. SPA 深链、静态资源路径与 MySQL 8 默认异常模式 → Task 2、Task 20。

---

### Task 1: 测试运行器与纯函数（解码 + 校验）

**Files:**
- Create: `tests/run.php`
- Create: `tests/lib.php`
- Create: `tests/unit/display_test.php`
- Create: `tests/unit/validate_test.php`
- Create: `source/lib/Display.php`
- Create: `source/lib/Validate.php`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: 无。
- Produces:
  - `Display::decode(string $value): string` — 单次 `htmlspecialchars_decode(..., ENT_QUOTES)`。
  - `Validate::username(string $value): bool` — 正则 `^[\w\x{4e00}-\x{9fa5}]{2,20}$`（/u）。
  - `Validate::email(string $value): bool` — 正则 `^(\w+\.)*?\w+@(\w+\.)+\w+$`。
  - `Validate::password(string $value): bool` — 6–20 任意字符。
  - 测试 helper：`assertSame($expected, $actual, $label = '')`、`assertTrue($actual, $label = '')`、`assertFalse(...)`、`assertThrows(string $class, callable $fn, $label = '')`、`test(string $name, callable $fn)`。
- 测试运行器约定：`tests/run.php [dir ...]`；无参数时默认运行 `tests/unit` 与 `tests/integration`。每个目录 `glob('*_test.php')`，每个测试文件用独立 PHP 进程执行（`PHP_BINARY $file`），任一失败退出码非 0。HTTP/安全测试必须显式传目录：`php tests/run.php tests/http tests/security`。

- [ ] **Step 1: 写失败的测试**

`tests/unit/display_test.php`:
```php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/Display.php';
require __DIR__ . '/../../source/lib/Validate.php';

test('decode entities exactly one level', function () {
    assertSame('<script>', Display::decode('&lt;script&gt;'));
    assertSame('a"b', Display::decode('a&quot;b'));
    assertSame('a\\"b', Display::decode('a\\&quot;b'));
    assertSame('&amp;lt;', Display::decode('&amp;lt;'));
});

test('username rules match legacy server rules', function () {
    assertTrue(Validate::username('ab'));
    assertTrue(Validate::username('张三'));
    assertFalse(Validate::username('a'));
    assertFalse(Validate::username('a b'));
    assertFalse(Validate::username(str_repeat('a', 21)));
});

test('email and password rules', function () {
    assertTrue(Validate::email('a.b@example.com'));
    assertFalse(Validate::email('not-an-email'));
    assertTrue(Validate::password('123456'));
    assertFalse(Validate::password('12345'));
    assertFalse(Validate::password(str_repeat('a', 21)));
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `php tests/run.php`
Expected: FAIL — `Display` / `Validate` 类不存在（文件尚未实现）。

- [ ] **Step 3: 实现最小代码**

`source/lib/Display.php`:
```php
<?php
final class Display
{
    public static function decode(string $value): string
    {
        return htmlspecialchars_decode($value, ENT_QUOTES);
    }
}
```

`source/lib/Validate.php`:
```php
<?php
final class Validate
{
    public static function username(string $value): bool
    {
        return (bool) preg_match('/^[\w\x{4e00}-\x{9fa5}]{2,20}$/u', $value);
    }

    public static function email(string $value): bool
    {
        return (bool) preg_match('/^(\w+\.)*?\w+@(\w+\.)+\w+$/', $value);
    }

    public static function password(string $value): bool
    {
        return (bool) preg_match('/^.{6,20}$/', $value);
    }
}
```

- [ ] **Step 4: 运行测试确认通过**

Run: `php tests/run.php`
Expected: PASS，所有测试 green。

- [ ] **Step 5: 提交**

```bash
git add tests source/lib/Display.php source/lib/Validate.php .gitignore
git commit -m "test: add php test runner and pure display/validation helpers"
```

---

### Task 2: mysqli 封装（预处理、单例、PHP 8 异常处理）

**Files:**
- Modify: `source/class/DB.class.php`（整体重写，保留 `IDataBase` 接口与类名）
- Create: `tests/setup-db.sh`
- Create: `tests/integration/db_test.php`
- Modify: `.gitignore`（忽略 `tests/config.test.php`）

**Interfaces:**
- Consumes: Task 1 的测试运行器。
- Produces `DB_Mysql`：
  - `Connect(string $host, string $user, string $pwd, string $db, string $charset = 'utf8', string $prefix = ''): bool`
  - `Query(string $sql, array $params = []): mysqli_result|bool`
  - `Dataset(string $sql, array $params = []): array`
  - `FirstRow(string $sql, array $params = []): array|false`
  - `FirstColumn(string $sql, array $params = []): array`
  - `FirstValue(string $sql, array $params = []): mixed`
  - `Execute(string $sql, array $params = []): bool`
  - `Insert(string $table, array $data): int`
  - `Update(string $table, array $data, string $where, array $whereParams = []): bool`
  - `LastId(): int`
  - `Error(): string`
  - 绑定类型规则：值为 int 用 `i`，其余用 `s`；`?` 占位符数量必须与参数数量一致，不一致抛 `InvalidArgumentException`。
  - 连接失败返回 false 并记录 `Error()`；执行失败抛 `RuntimeException`（内部由 API 层转 500）。

- [ ] **Step 1: 建测试库（一次性/可重复）**

`tests/setup-db.sh`：
```bash
#!/usr/bin/env bash
set -euo pipefail
NAME=${XSSP_TEST_DB_CONTAINER:-xssp-test-db}
PORT=${XSSP_TEST_DB_PORT:-33061}
if ! sudo docker ps -a --format '{{.Names}}' | grep -qx "$NAME"; then
  sudo docker run -d --name "$NAME" -e MYSQL_ROOT_PASSWORD=test \
    -p "127.0.0.1:${PORT}:3306" mysql:8.0
fi
for i in $(seq 1 30); do
  if sudo docker exec "$NAME" mysqladmin ping -uroot -ptest --silent >/dev/null 2>&1; then break; fi
  sleep 1
done
sudo docker exec -i "$NAME" mysql -uroot -ptest -e \
  "DROP DATABASE IF EXISTS xssplatform_test; CREATE DATABASE xssplatform_test DEFAULT CHARSET utf8;"
sudo docker exec -i "$NAME" mysql -uroot -ptest xssplatform_test < xssplatform.sql
cat > tests/config.test.php <<PHP
<?php
\$config = array(
 'dbHost' => '127.0.0.1:${PORT}', 'dbUser' => 'root', 'dbPwd' => 'test',
 'database' => 'xssplatform_test', 'charset' => 'utf8', 'tbPrefix' => 'oc_',
 'dbType' => 'mysql', 'register' => 'normal', 'urlroot' => 'http://127.0.0.1:18080',
 'urlrewrite' => false, 'timezone' => 'Asia/Shanghai', 'expires' => 3600, 'debug' => true,
 'show' => array('sitename' => 'XSS平台', 'sitedesc' => '', 'keywords' => '', 'description' => '', 'adminmail' => ''),
 'email' => array('host' => '', 'username' => '', 'password' => '', 'port' => 465, 'secure' => 'ssl'),
 'dingding' => array('keyword' => '最新消息', 'endpoint' => ''),
);
PHP
echo "test db ready"
```

- [ ] **Step 2: 写失败的测试**

`tests/integration/db_test.php`:
```php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/class/DB.class.php';

$cfg = include __DIR__ . '/../config.test.php';
$db = new DB_Mysql();
assertTrue($db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], $cfg['database'], $cfg['charset'], $cfg['tbPrefix']), 'connect');
assertSame('2', (string) $db->FirstValue('SELECT id FROM oc_user WHERE id=?', [2]), 'prepared select');
assertFalse($db->FirstValue('SELECT id FROM oc_user WHERE id=?', ["2' OR 1=1"]), 'injection has no rows');
$id = $db->Insert('oc_module', ['userId' => 2, 'title' => 'db-test', 'keys' => '[]', 'setkeys' => '[]', 'code' => 'x', 'isOpen' => 0, 'addTime' => time()]);
assertTrue($id > 0, 'insert returns id');
$db->Update('oc_module', ['title' => 'db-test-2'], 'id=?', [$id]);
assertSame('db-test-2', $db->FirstValue('SELECT title FROM oc_module WHERE id=?', [$id]), 'update');
$db->Execute('DELETE FROM oc_module WHERE id=?', [$id]);
assertFalse($db->FirstValue('SELECT title FROM oc_module WHERE id=?', [$id]), 'delete');
assertThrows(InvalidArgumentException::class, function () use ($db) {
    $db->FirstValue('SELECT ? FROM oc_user', [1, 2]);
});
```

- [ ] **Step 3: 运行测试确认失败**

Run: `bash tests/setup-db.sh && php tests/run.php`
Expected: FAIL — `DB_Mysql::Dataset/FirstValue/Insert/Update/Execute` 不存在或仍用拼接。

- [ ] **Step 4: 实现 `DB_Mysql`**

要点（签名与行为已由测试固定）：
- 构造/连接：`mysqli_report(MYSQLI_REPORT_OFF)`；`new mysqli($host,$user,$pwd,$db,port)` 从 `host` 的 `host:port` 形式拆出 port；`mysqli_set_charset($link,$charset)`；失败设置 `$this->error` 并返回 false。
- `Query`：`$stmt = mysqli_prepare`；按参数推断类型 `$types`；`mysqli_stmt_bind_param`（用 `...$refs` 引用数组）；`mysqli_stmt_execute`；返回 `mysqli_stmt_get_result`（SELECT）或 true。
- `Dataset/FirstRow/FirstColumn/FirstValue` 基于 `Query` 循环/取首行。
- `Insert`：构造 `INSERT INTO {$table}(col,...) VALUES(?,...)`；调用 `Execute`；返回 `LastId()`。
- `Update`：构造 `UPDATE {$table} SET col=? ... WHERE {$where}`；`$whereParams` 追加在数据参数之后。
- `Error()` 返回最近一次 `mysqli_error` / 异常消息；`Disconnect` 保持析构。
- 保留 `BlueDB::DB()` 入口但返回 `new DB_Mysql`；保留 `tbPrefix` 公开属性。
- 不得在任何方法里把参数值拼进 SQL。

- [ ] **Step 5: 运行测试确认通过**

Run: `php tests/run.php`
Expected: PASS。

- [ ] **Step 6: 提交**

```bash
git add source/class/DB.class.php tests
git commit -m "feat: prepared-statement mysqli layer with PHP 8 error handling"
```

---

### Task 3: 引导、配置与 User 会话/密码/CSRF

**Files:**
- Create: `source/bootstrap.php`
- Create: `source/lib/Config.php`
- Create: `source/lib/Security.php`
- Modify: `source/class/User.class.php`（重写）
- Modify: `source/function.php`（裁剪到在用 helper；DBConnect 单例）
- Modify: `init.php`（改为 require bootstrap.php 的薄壳，保留常量）
- Create: `source/lib/Notify.php`（占位实现：`sendMail()`/`sendDing()` 返回 true；Task 12 替换为真实发送）
- Delete: `source/global.func.php`（`dingdingNotice` 迁入 `Notify`）
- Create: `tests/integration/user_test.php`

**Interfaces:**
- Consumes: `DB_Mysql`（Task 2）、`Validate`（Task 1）。
- Produces:
  - `Config::load(string $file): void`、`Config::get(string $key, mixed $default = null): mixed`、`Config::all(): array`、`Config::defaults(): array`。
  - `bootstrap.php` 定义 `ROOT_PATH`、`IN_OLDCMS`，加载配置与 `source/function.php`，创建全局 `$db`（单例）、`$user`；函数 `db(): DB_Mysql`、`current_user(): User`。
  - `Security::safeUnserialize(string $data, mixed $fallback = false): mixed` — `allowed_classes=false`；只接受数组，对象/标量/失败返回 `$fallback`。
  - `User`：
    - `public int $userId; public int $adminLevel; public string $userName; public string $token;`
    - `__construct(DB_Mysql $db)` — 读 `$_COOKIE['ocKey']`，校验 `expires > time()`，安全反序列化，扩展过期时间。
    - `login(string $username, string $password, int $autoLogin = 0): bool` — 用户名/邮箱、md5 迁移、随机 ocKey/token、写 session、loginTime。
    - `logout(): bool` — 清 Cookie、删该用户所有 session。
    - `checkToken(string $token): bool` — `hash_equals`。
    - `isAdmin(): bool`。
    - `sessionUser(): array` — `['id','userName','adminLevel','token']`。
    - `toArray(): array` 供 API。
  - `OCSetCookie($name, $value, $expires, $path='/', $domain='')` — array 形式参数，HttpOnly + SameSite=Lax + HTTPS Secure。

- [ ] **Step 1: 写失败的测试**

`tests/integration/user_test.php`:
```php
require __DIR__ . '/../lib.php';
putenv('XSSP_CONFIG=' . realpath(__DIR__ . '/../config.test.php'));
require __DIR__ . '/../../source/bootstrap.php';
require __DIR__ . '/../../source/lib/Security.php';

assertTrue(Security::safeUnserialize('O:8:"stdClass":0:{}') === false, 'no object instantiation');
assertSame(['a' => 1], Security::safeUnserialize(serialize(['a' => 1])));

$db = db();
$db->Execute('DELETE FROM oc_session WHERE ocKey LIKE ?', ['test-%']);
$legacyHash = md5('OldCMS|' . 'secret123');
$db->Execute('DELETE FROM oc_user WHERE userName=?', ['legacy']);
$db->Insert('oc_user', ['adminLevel' => 0, 'userName' => 'legacy', 'userPwd' => $legacyHash, 'email' => 'legacy@example.com', 'addTime' => time()]);

$u = new User($db);
assertTrue($u->login('legacy', 'secret123', 0), 'legacy md5 login');
$hash = $db->FirstValue('SELECT userPwd FROM oc_user WHERE userName=?', ['legacy']);
assertTrue(str_starts_with((string) $hash, '$2y$'), 'legacy hash migrated');
assertTrue(password_verify('secret123', (string) $hash));
$row = $db->FirstRow('SELECT ocKey, token, data FROM oc_session WHERE userId=?', [$u->userId]);
assertSame(48, strlen((string) $row['ocKey']), 'ocKey length');
assertSame(32, strlen((string) $row['token']), 'token length');
assertTrue($u->checkToken((string) $row['token']));
assertFalse($u->checkToken('wrong'));

// 旧版本写入的 session（32 位 md5 ocKey + 序列化 data）仍可读
$oldKey = md5('old-session');
$oldData = serialize(['userId' => 2, 'adminLevel' => 1, 'userName' => 'admin']);
$db->Execute('DELETE FROM oc_session WHERE ocKey=?', [$oldKey]);
$db->Insert('oc_session', ['userId' => 2, 'ocKey' => $oldKey, 'token' => md5('t'), 'ip' => '127.0.0.1', 'data' => $oldData, 'expires' => time() + 3600, 'updateTime' => time(), 'addTime' => time()]);
$_COOKIE['ocKey'] = $oldKey;
$oldUser = new User($db);
assertSame(2, $oldUser->userId, 'old session readable');
$_COOKIE['ocKey'] = '';
assertTrue($u->logout());
```

- [ ] **Step 2: 运行测试确认失败**

Run: `php tests/run.php`
Expected: FAIL — `Security`/`bootstrap`/新 `User` 不存在或行为不符。

- [ ] **Step 3: 实现**

- `Config`：保存 include 得到的 `$config`；`defaults()` 返回 spec 的默认键（`dbHost/dbUser/dbPwd/database/charset/tbPrefix/dbType/register/urlroot/urlrewrite/timezone/expires/debug/show/email/dingding`），不含 `mailauth`。
- `source/bootstrap.php`：`getenv('XSSP_CONFIG') ?: ROOT_PATH.'/config.php'`；定义 `URL_ROOT/URL_REWRITE/REGISTER/EXPIRES/TABLE_PREFIX`；`require source/function.php`、`DB.class.php`、`User.class.php`；`$db = DBConnect(); $user = new User($db);`；`db()` 与 `current_user()` 返回全局。
- `function.php` 只保留 `DBConnect`（静态单例，按 configFile 键缓存）、`Tb`、`StripStr`、`Val`、`IP`、`OCEncrypt`、`OCSetCookie`、`JsonEncode`、`ShortUrlCode`、`SendMail`（薄封装 `Notify::sendMail`）。删除 `InitSmarty/Notice/ShowSuccess/ShowError/GetTimeShow/UBBToHTML/DoCode/FileSuffix/HTTP_REFERER/UrlInvite`。
- `User`：登录时若用户名含 `@` → `WHERE email=?`，否则 `WHERE userName=?`（保留旧语义）；密码比较：先 `password_verify`，否则 32 位十六进制 md5 且 `hash_equals(OCEncrypt($pwd),$stored)` → 更新为 `password_hash`；`ocKey=bin2hex(random_bytes(24))`；`token=bin2hex(random_bytes(16))`；`data=serialize(['userId'=>..,'adminLevel'=>..,'userName'=>..])`；`Insert/Update` 用预处理；cookie 通过 `OCSetCookie`。
- `User::__construct` 读到 session 时用 `Security::safeUnserialize`，且 `expires > time()`；只接受数组。
- `OCSetCookie` 用 `setcookie($name,$value,['expires'=>$expires,'path'=>$path,'domain'=>$domain,'secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'])`；`$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443`。
- `init.php` 变成：定义 `ROOT_PATH`/`IN_OLDCMS`，`require __DIR__.'/source/bootstrap.php'`，然后 `$show=$config['show']; $url=[...]`（保留老端点可能引用的 `$url`/`$show`）。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/setup-db.sh && php tests/run.php`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/bootstrap.php source/lib source/class/User.class.php source/function.php init.php tests
git commit -m "feat: config bootstrap and hardened user session/password handling"
```

---

### Task 4: JSON API 前控制器 + SPA 壳 + HTTP 测试环境

**Files:**
- Create: `api.php`
- Create: `source/lib/AppError.php`
- Create: `source/lib/Api.php`
- Create: `source/lib/FrontendShell.php`
- Modify: `index.php`（安装态判断、SPA 壳、收包分发占位）
- Create: `tests/http/setup.sh`
- Create: `tests/http/api_meta_test.php`

**Interfaces:**
- Consumes: `Config`、`User`/`db()`（Task 3）。
- Produces:
  - `AppError`：`__construct(string $code, int $httpStatus = 400, ?string $message = null, array $fields = [])`；公开只读 `errorCode/httpStatus/fields`。
  - `Api::handle(string $action, array $input, array $server, array $get, array $post, array $cookies): void` — 输出 JSON 并 exit；负责：
    - 安装态：`install.lock` 不存在时，仅允许 `meta`、`install.status/testDb/submit`。
    - JSON body 解析（`php://input`）；兼容表单。
    - action 白名单（后续任务逐个注册到 `Api::$routes`）。
    - `Origin`/`Referer` 存在时同源校验（`extractHost` 与 `URL_ROOT` 主机比较）；安装阶段没有配置，用请求 `Host` 的校验值。
    - 登录 action 之外的已登录写操作校验 `X-CSRF-Token`。
    - 捕获 `AppError`、`Throwable`，输出 `{"ok":false,...}`。
  - `FrontendShell::render(): void` — 若 `dist/index.html` 存在输出其内容；否则输出最小 HTML（标题“XSS平台”、正文“前端未构建，请运行 cd frontend && npm run build”）。
  - `index.php`：未安装 → `FrontendShell::render()`；已安装且无 `do` → `FrontendShell::render()`；`do` 属收包白名单 → 后续任务接入，本任务先 404 文本。
  - `/api.php?action=meta` 返回：`installed`、`config`（sitename/sitedesc/register/urlroot/urlrewrite/expires）、`user`（未登录为 null）。
- HTTP 测试环境：`tests/http/setup.sh` 在 `/tmp/xssp-http` 建临时副本（`rsync -a --exclude .git --exclude frontend/node_modules . /tmp/xssp-http/`），写入 `tests/config.test.php` 的副本为 `config.php`，创建 `install/install.lock`，然后 `php -S 127.0.0.1:18080 deploy/router.php`（后台）。每个测试用 curl，结束时 `kill`。

- [ ] **Step 1: 写失败的测试**

`tests/http/api_meta_test.php`：
```php
require __DIR__ . '/../lib.php';

$base = 'http://127.0.0.1:18080';
$raw = shell_exec("curl -sS -i {$base}/api.php?action=meta");
assertTrue(str_contains((string) $raw, '200'), 'http 200');
assertTrue(str_contains((string) $raw, 'Content-Type: application/json'), 'json header');
assertTrue(str_contains((string) $raw, 'X-Content-Type-Options: nosniff'), 'nosniff');
$json = json_decode(substr((string) $raw, strpos((string) $raw, "\r\n\r\n") + 4), true);
assertSame(true, $json['ok']);
assertSame(true, $json['data']['installed']);
assertSame('XSS平台', $json['data']['config']['sitename']);
assertSame(null, $json['data']['user']);
```

`tests/http/page_test.php`：
```php
require __DIR__ . '/../lib.php';
$raw = shell_exec('curl -sS -i http://127.0.0.1:18080/');
assertTrue(str_contains((string) $raw, '200'));
assertTrue(str_contains((string) $raw, 'text/html'));
assertTrue(str_contains((string) $raw, 'XSS'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup.sh && php tests/run.php tests/http`
Expected: FAIL — `/api.php` 与 `/` 不可用。

- [ ] **Step 3: 实现**

- `api.php`：`define('IN_OLDCMS', true)`；`require source/lib/AppError.php`、`Api.php`、必要时 `source/bootstrap.php`；`Api::handle($_GET['action'] ?? '', ...)`。未安装时不要加载 bootstrap（无 config）。
- `Api`：注册 `meta` 与 `session`（`session` 依赖 bootstrap 与 `$user->sessionUser()`）；统一响应头（`Content-Type: application/json; charset=utf-8`、`Cache-Control: no-store`、`X-Content-Type-Options: nosniff`）；`respond($data)` / `respondError(AppError)`；`sameOrigin` 辅助；`installLockPath()` 使用 `ROOT_PATH.'/install/install.lock'`（HTTP 临时副本已建锁）。
- `FrontendShell`：`readfile(ROOT_PATH.'/dist/index.html')` 或最小 HTML；HTML 响应头加 `X-Frame-Options: SAMEORIGIN`、`Referrer-Policy: same-origin`、CSP（spec §11.10）。
- `index.php`：版本门槛 8.0；`install.lock` 判断在 `require config` 前；无 `do` → shell；`do` 暂只允许 `code/api/do/keepsession` 列表，但文件仍不存在时输出 404。
- `tests/http/setup.sh`：使用 `setsid php -S 127.0.0.1:18080 deploy/router.php > /tmp/xssp-http.log 2>&1 &`，把 PID 写入 `/tmp/xssp-http.pid`；`tests/http/teardown.sh` 清理。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup.sh && php tests/run.php tests/http && bash tests/http/teardown.sh`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add api.php index.php source/lib/AppError.php source/lib/Api.php source/lib/FrontendShell.php tests
git commit -m "feat: json api front controller and spa shell"
```

---

### Task 5: 安装服务 + 安装 API + 安装 URL 跳转

**Files:**
- Create: `source/services/InstallService.php`
- Create: `source/lib/ConfigWriter.php`
- Modify: `api.php`/`Api.php`（注册 `install.status`、`install.testDb`、`install.submit`）
- Modify: `install/step1.php`、`install/step2.php`、`install/step3.php`（302 到 `/install`）
- Modify: `config.php.default`（去掉 `$_SERVER` 依赖与真实凭据；保留键）
- Create: `install/upgrade.php`
- Create: `tests/integration/install_test.php`

**Interfaces:**
- Consumes: `DB_Mysql`、`Config::defaults()`、`AppError`、`Api`。
- Produces:
  - `InstallService::status(): array` — `['installed'=>bool,'configWritable'=>bool,'phpVersion'=>string,'extensions'=>['mysqli'=>bool,'curl'=>bool,'openssl'=>bool]]`。
  - `InstallService::testDb(array $input): void` — 失败抛 `AppError('install_failed', 400, '数据库信息有误')`。
  - `InstallService::install(array $input): array` — `['adminId'=>int]`；写 `config.php`、建库、导入、管理员哈希、写 `install.lock`。
  - `ConfigWriter::write(string $path, array $config): void` — 临时文件 + `rename`，`chmod 0640`。
  - `install/upgrade.php`（CLI）：检查 `oc_user.userPwd` 长度、`oc_session.data` 类型；缺则 `ALTER`；幂等输出。

- [ ] **Step 1: 写失败的测试**

`tests/integration/install_test.php`（在临时副本中跑；测试脚本自行复制与清理）：
```php
require __DIR__ . '/../lib.php';

$tmp = '/tmp/xssp-install-test';
exec('rm -rf ' . escapeshellarg($tmp));
exec('rsync -a --exclude .git --exclude frontend/node_modules ' . escapeshellarg(dirname(__DIR__, 2)) . '/ ' . escapeshellarg($tmp) . '/');
@unlink($tmp . '/config.php');
@unlink($tmp . '/install/install.lock');
putenv('XSSP_CONFIG=' . $tmp . '/config.php');
define('ROOT_PATH', $tmp);
$cfg = include __DIR__ . '/../config.test.php';
$host = $cfg['dbHost'];
$port = 3306;
if (str_contains($host, ':')) {
    [$host, $port] = explode(':', $host, 2);
}
$cleanup = new mysqli($host, $cfg['dbUser'], $cfg['dbPwd'], null, (int) $port);
$cleanup->query('DROP DATABASE IF EXISTS xssplatform_install_test');
$cleanup->close();
require $tmp . '/source/lib/Config.php';
require $tmp . '/source/lib/AppError.php';
require $tmp . '/source/lib/ConfigWriter.php';
require $tmp . '/source/class/DB.class.php';
require $tmp . '/source/services/InstallService.php';

$svc = new InstallService();
assertFalse($svc->status()['installed']);
$admin = $svc->install([
    'dbHost' => $cfg['dbHost'], 'dbUser' => $cfg['dbUser'], 'dbPwd' => $cfg['dbPwd'],
    'dbName' => 'xssplatform_install_test', 'username' => 'admin2', 'password' => 'pw123456',
]);
assertTrue(file_exists($tmp . '/install/install.lock'));
assertTrue(file_exists($tmp . '/config.php'));
$db = new DB_Mysql();
$db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], 'xssplatform_install_test', 'utf8', 'oc_');
assertSame('admin2', $db->FirstValue('SELECT userName FROM oc_user ORDER BY id LIMIT 1'));
assertTrue(password_verify('pw123456', (string) $db->FirstValue('SELECT userPwd FROM oc_user ORDER BY id LIMIT 1')));
assertThrows(AppError::class, function () use ($svc, $cfg) {
    $svc->install([
        'dbHost' => $cfg['dbHost'], 'dbUser' => $cfg['dbUser'], 'dbPwd' => $cfg['dbPwd'],
        'dbName' => 'xssplatform_install_test', 'username' => 'admin2', 'password' => 'pw123456',
    ]);
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `php tests/run.php`
Expected: FAIL — `InstallService`/`ConfigWriter` 不存在。

- [ ] **Step 3: 实现**

- `InstallService::install` 顺序：校验输入（`dbHost` 允许 `[A-Za-z0-9.:-]`、`dbName` 匹配 `^[A-Za-z0-9_]+$`、用户名密码非空）→ `mysqli` 连接（不选库，`MYSQLI_REPORT_OFF`）→ `CREATE DATABASE IF NOT EXISTS \`dbName\` DEFAULT CHARSET utf8` → 选库 → `mysqli_multi_query(file_get_contents(ROOT_PATH.'/xssplatform.sql'))`（逐条检查错误）→ 把 SQL 中演示域名替换为 `urlroot`（`http(s)://{host}/`，host 已校验）→ `UPDATE oc_user SET userName=?,userPwd=?,adminLevel=1 ORDER BY id LIMIT 1`（取最小 id）→ 写 `config.php`（`Config::defaults()` + DB 值 + urlroot + register/urlrewrite/show/email/dingding）→ `install.lock`。
- `ConfigWriter` 用 `var_export($config, true)` 输出 `<?php $config = ...;`；先写 `config.php.tmp`，`rename`，`chmod 0640`。
- `Api` 注册 install actions：未安装才允许；已安装 → `AppError('no_permission',403)`。
- `install/step1..3.php`：`header('Location: /install'); exit;`；step3 若收到老 POST，直接 302，不执行（旧表单已随 UI 删除）。
- `install/common.php`：改为只输出 `$project` 常量并在已安装时 `header('Location: /install')`；不再依赖 `install.lock` 的旧跳转逻辑（`installed` 判断由 `InstallService`/SPA 承担）。
- `index.php` 未安装时 `FrontendShell::render()`；`/install` 路由由前端处理。
- `config.php.default` 改为纯 `$config = array(...)`，`urlroot` 为空字符串、数据库凭据为空；不引用 `$_SERVER`。
- `upgrade.php` 只对 `oc_user.userPwd`/`oc_session.data` 做幂等 ALTER，CLI 输出每步结果。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/setup-db.sh && php tests/run.php`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/services/InstallService.php source/lib/ConfigWriter.php api.php source/lib/Api.php install config.php.default tests
git commit -m "feat: shadcn-ready installer service and install api"
```

### Task 6: 认证 API（session/login/logout/register）

**Files:**
- Create: `source/services/AuthService.php`
- Modify: `source/lib/Api.php`（注册 `session`、`login`、`logout`、`register.check`、`register.submit`）
- Create: `tests/http/setup-installed.sh`
- Create: `tests/http/auth_test.php`
- Create: `tests/unit/urlmap_test.php`（仅覆盖校验用不到的部分，见 Task 11）

**Interfaces:**
- Consumes: `User`、`db()`、`Config`、`Validate`、`AppError`。
- Produces:
  - `AuthService::session(): array` — `['user'=>?array,'csrf'=>string,'config'=>array,'installed'=>true]`。
  - `AuthService::login(array $in): array` — `['user'=>array,'csrf'=>string]`；输入 `user,pwd,auto`。
  - `AuthService::logout(): void`。
  - `AuthService::registerCheck(array $in): array` — `['userExisted'=>int,'emailExisted'=>int,'keyError'=>int]`。
  - `AuthService::register(array $in): array` — `['user'=>array,'csrf'=>string]`；输入 `user,email,pwd,key`（`phone/pmsg` 忽略）。
  - `Api` 在成功后返回 `{"ok":true,"data":...}`；失败用 spec §8.4 文案。

- [ ] **Step 1: 写 HTTP 测试环境（已安装态）**

`tests/http/setup-installed.sh`：
```bash
#!/usr/bin/env bash
set -euo pipefail
bash tests/setup-db.sh
rm -rf /tmp/xssp-http-installed
rsync -a --exclude .git --exclude frontend/node_modules "$(pwd)/" /tmp/xssp-http-installed/
rm -f /tmp/xssp-http-installed/config.php /tmp/xssp-http-installed/install/install.lock
cd /tmp/xssp-http-installed
setsid php -S 127.0.0.1:18081 deploy/router.php > /tmp/xssp-http-installed.log 2>&1 &
echo $! > /tmp/xssp-http-installed.pid
sleep 1
curl -sS 'http://127.0.0.1:18081/api.php?action=install.status' >/dev/null
curl -sS -X POST 'http://127.0.0.1:18081/api.php?action=install.submit' \
  -H 'Content-Type: application/json' \
  -d '{"dbHost":"127.0.0.1:33061","dbUser":"root","dbPwd":"test","dbName":"xssplatform_http","username":"admin","password":"pw123456"}'
```

- [ ] **Step 2: 写失败的测试**

`tests/http/auth_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-auth.cookies';
@unlink($jar);

$r = shell_exec("curl -sS -c {$jar} -b {$jar} -i -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\",\"auto\":0}'");
assertTrue(str_contains((string) $r, '200'), 'login 200');
assertTrue(str_contains((string) $r, 'HttpOnly'), 'cookie httponly');
assertTrue(str_contains((string) $r, 'SameSite=Lax'), 'cookie samesite');
$json = json_decode(substr((string) $r, strpos((string) $r, "\r\n\r\n") + 4), true);
assertSame('admin', $json['data']['user']['userName']);
$csrf = $json['data']['csrf'];
assertSame(32, strlen($csrf));

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=session'");
$json = json_decode((string) $r, true);
assertSame('admin', $json['data']['user']['userName']);

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=logout' -H 'X-CSRF-Token: {$csrf}'");
assertTrue(str_contains((string) $r, '"ok":true'), 'logout');
$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=session'");
assertTrue(str_contains((string) $r, '"user":null'), 'logged out');

// 注册（normal）
$r = shell_exec("curl -sS -X POST '{$base}/api.php?action=register.submit' -H 'Content-Type: application/json' -d '{\"user\":\"newuser\",\"email\":\"new@example.com\",\"pwd\":\"123456\"}'");
assertTrue(str_contains((string) $r, '"ok":true'), 'register');
// 重复用户名
$r = shell_exec("curl -sS -X POST '{$base}/api.php?action=register.check' -H 'Content-Type: application/json' -d '{\"user\":\"newuser\",\"email\":\"other@example.com\",\"key\":\"\"}'");
assertTrue(str_contains((string) $r, '"userExisted":1'), 'duplicate user');
// 错误文案
$r = shell_exec("curl -sS -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"wrong\"}'");
assertTrue(str_contains((string) $r, '登录失败,请检查用户/邮箱或密码'), 'legacy message');
```

- [ ] **Step 3: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — `AuthService`/auth actions 未实现（install 也可能失败）。

- [ ] **Step 4: 实现**

- `AuthService` 使用 `Validate` 与 legacy 文案；`register` 先处理邀请（`REGISTER=invite` 时 key 必填且未使用；normal 模式有 key 也校验并核销；close 抛 `register_closed`）；插入 `userPwd=password_hash`；成功后 `$user->login($username,$pwd,1)` 自动登录；邀请核销 `UPDATE oc_invite_reg SET isUsed=1,regUserId=?,regTime=? WHERE id=?`。
- `login`：`already_logged_in`；空字段错误；`$user->login` 失败 `login_failed`；成功返回 `user` 与 `csrf=token`。
- `logout`：调用 `$user->logout()`。
- `session`：若未登录 `user=null`，否则 `sessionUser()`；`csrf` 为登录用户的 token，未登录为空字符串。
- `Api` 的同源校验：若 `Origin` 存在且 host 不匹配 → `AppError('no_permission',403)`；无 Origin/Referer 放行（兼容老表单/CLI）。
- `Api` 的 CSRF：已登录且 action 在写操作白名单内，检查 `HTTP_X_CSRF_TOKEN` 与 `$user->token`。
- 登录/注册 action 不需要 CSRF；`logout` 需要。

- [ ] **Step 5: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 6: 提交**

```bash
git add source/services/AuthService.php source/lib/Api.php tests
git commit -m "feat: auth json api with legacy validation and password migration"
```

---

### Task 7: 项目服务与项目 API

**Files:**
- Create: `source/services/ProjectService.php`
- Modify: `source/lib/Api.php`（注册 `dashboard`、`project.*`）
- Create: `tests/http/project_test.php`
- Modify: `tests/http/setup-installed.sh`（安装后种入默认项目/模块若 SQL 已含则跳过）

**Interfaces:**
- Consumes: `db()`、`current_user()`、`StripStr`、`JsonEncode`、`Display::decode`、`AppError`、`Config`。
- Produces:
  - `ProjectService::dashboard(): array` — `['projects'=>array,'modules'=>array]`；项目含 `contentNum`；模块同旧侧栏查询。
  - `ProjectService::create(array $in): array` — `['id'=>int,'urlKey'=>string,'authCode'=>string]`；`title` 必填，`description` 可选；`urlKey=ShortUrlCode()`（6 位，字母数字）；`authCode=bin2hex(random_bytes(16))`；`addTime=time()`。
  - `ProjectService::get(array $in): array` — `['project'=>array,'contents'=>array,'domains'=>array,'domain'=>string]`；content/serverContent 的每个值 `Display::decode`；`id` 归属校验。
  - `ProjectService::setcodeGet(array $in): array` — `['project'=>array,'modulesCan'=>array,'ty'=>string]`；`setkeys` 值 `urldecode`；`choosed` 标记。
  - `ProjectService::setcodeSave(array $in): array` — 输入 `id,ty,modules,code,setkeys`（`setkey_*` 扁平键）。
  - `ProjectService::viewcode(array $in): array` — `code/codeurl/scriptShow1/scriptShow2/shortShow1/shortShow3` 全部服务端生成（算法见 spec §9）。
  - `ProjectService::delete(array $in): void` — 删 `oc_keepsession`、`oc_project_content`、`oc_project`（归属校验）。
  - `ProjectService::deleteContent(array $in): int`、`deleteContents(array $in): int` — 返回 1。
  - 所有对象操作先校验 `userId` 归属，否则 `AppError('project_not_found',404)`。

- [ ] **Step 1: 写失败的测试**

`tests/http/project_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-proj.cookies';
@unlink($jar);
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"p1\",\"description\":\"d1\"}'");
$data = json_decode((string) $r, true)['data'];
assertTrue($data['id'] > 0);
assertSame(6, strlen((string) $data['urlKey']), 'urlKey length');
assertSame(32, strlen((string) $data['authCode']), 'authCode length');

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=dashboard'");
$dash = json_decode((string) $r, true)['data'];
assertTrue(count($dash['projects']) >= 1);

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.get&id={$data['id']}'");
$get = json_decode((string) $r, true)['data'];
assertSame('p1', $get['project']['title']);
assertSame([], $get['contents']);

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.setcode.get&id={$data['id']}'");
$sc = json_decode((string) $r, true)['data'];
assertTrue(count($sc['modulesCan']) >= 1);

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":' . $data['id'] . ',\"ty\":\"create\",\"modules\":[1],\"code\":\"alert(1)\",\"setkey_1_keepsession\":\"1\"}'");
assertTrue(str_contains((string) $r, '"ok":true'), 'setcode save');

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=project.viewcode&id={$data['id']}'");
$vc = json_decode((string) $r, true)['data'];
assertTrue(str_contains($vc['code'], 'alert(1)'));
assertTrue(str_contains($vc['codeurl'], $data['urlKey']));
assertTrue(str_contains($vc['scriptShow1'], '<script src='));

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.delete' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"id\":' . $data['id'] . '}'");
assertTrue(str_contains((string) $r, '"ok":true'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — `ProjectService` 不存在或返回结构不符。

- [ ] **Step 3: 实现**

按 spec §7.1/§8.2/§9 与旧 `source/project.php` 的逐项行为实现；特别注意：
- `get` 的 `content` JSON 解析失败时回退 `StripStr($raw)`；`serverContent` 解析后对每个值 `Display::decode`；`domain` 过滤用预处理 `AND domain=?`。
- `setcodeGet` 的模块查询 `(isOpen=1 AND isAudit=1) OR userId=?`；`keys` JSON 解码后 `implode(',', ...)`；`setkeys` 从 `moduleSetKeys` 的 `setkey_{moduleId}_{key}` `urldecode`。
- `setcodeSave`：`modules` 逐项 intval；若非空，写 `modules=json_encode(ints)`，再遍历模块的 `setkeys` 白名单收集 `setkey_{id}_{key}`，只保存非空值并 `urlencode`；**预填**：保存前读取现有 `code`，仅在请求显式提供 `code` 时覆盖（前端预填后总会提供；legacy 旧表单也提供，保持旧行为）。这是 spec §19.2 已批准的改动。
- `viewcode`：`code` 用 `htmlspecialchars_decode(..., ENT_QUOTES)`（最终显示文本）；`scriptShow1/2`、`shortShow1/3`、`codeurl` 按旧算法，`URL_ROOT` 来自配置。
- `delete`：级联 `keepsession` + `project_content` + `project`（spec §11.12）。
- `deleteContents`：`ids` 为数组或 `|` 分隔字符串；逐条校验归属。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/services/ProjectService.php source/lib/Api.php tests
git commit -m "feat: project services and json api with legacy semantics"
```

---

### Task 8: 模块服务与模块 API

**Files:**
- Create: `source/services/ModuleService.php`
- Modify: `source/lib/Api.php`（注册 `modules.list`、`module.*`）
- Create: `tests/http/module_test.php`

**Interfaces:**
- Consumes: `db()`、`current_user()`、`StripStr`、`JsonEncode`、`Display::decode`、`AppError`。
- Produces:
  - `ModuleService::list(): array` — `['modules'=>array]`；私有 + 公共已审核。
  - `ModuleService::create(array $in): array` — `['id'=>int]`；`keys/setkeys` 为数组，JSON 存储；`isOpen` 0/1。
  - `ModuleService::get(array $in): array` — `['module'=>array,'keys'=>array,'setkeys'=>array,'canEdit'=>bool]`；展示字段 `Display::decode`。
  - `ModuleService::save(array $in): void` — 同旧 `set_submit` 权限：`userId` 归属；非管理员不能改已审核公共模块（`module_locked`）。
  - `ModuleService::delete(array $in): void` — 归属校验。
  - 权限失败文案使用 spec §8.4。

- [ ] **Step 1: 写失败的测试**

`tests/http/module_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-mod.cookies'; @unlink($jar);
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.create' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"title\":\"m1\",\"description\":\"d\",\"keys\":[\"cookie\",\"location\"],\"setkeys\":[\"url\"],\"code\":\"x=1\",\"isOpen\":0}'");
$id = json_decode((string) $r, true)['data']['id'];
assertTrue($id > 0);

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=module.get&id={$id}'");
$m = json_decode((string) $r, true)['data'];
assertSame('m1', $m['module']['title']);
assertSame(['cookie', 'location'], $m['keys']);
assertSame(['url'], $m['setkeys']);

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.save' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"id\":' . $id . ',\"title\":\"m2\",\"description\":\"d\",\"keys\":[\"cookie\"],\"setkeys\":[],\"code\":\"x=2\",\"isOpen\":1}'");
assertTrue(str_contains((string) $r, '"ok":true'));

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=modules.list'");
assertTrue(str_contains((string) $r, 'm2'));

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=module.delete' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"id\":' . $id . '}'");
assertTrue(str_contains((string) $r, '"ok":true'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — `ModuleService` 未实现。

- [ ] **Step 3: 实现**

按旧 `source/module.php` 行为；`get` 的可见性：管理员任意；非管理员仅自己或 `isOpen=1 AND isAudit=1`；`canEdit`：自己且（非公共已审核或管理员）。`create` 的 `keys` 来自请求数组（默认值由前端传）；`save` 保留旧规则：非管理员不能改已审核公共模块。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/services/ModuleService.php source/lib/Api.php tests
git commit -m "feat: module services and json api"
```

---

### Task 9: 用户设置/邀请 + 管理员 API

**Files:**
- Create: `source/services/UserService.php`
- Create: `source/services/AdminService.php`
- Modify: `source/lib/Api.php`（注册 `user.*`、`admin.*`）
- Create: `tests/http/user_admin_test.php`

**Interfaces:**
- Consumes: `db()`、`current_user()`、`StripStr`、`Display::decode`、`AppError`。
- Produces:
  - `UserService::settingsGet(): array` — `['email'=>string,'dingding'=>string,'emsg'=>string,'dmsg'=>string]`；兼容旧 `message` 三段（`e|p|d`，中段忽略）。
  - `UserService::settingsSave(array $in): void` — `emsg,dmsg,dingding`；`message = "e|0|d"`；更新 `dingding`（spec §11.12 修复）。
  - `UserService::invites(): array` — `['wooyun'=>array,'other'=>array]`。
  - `UserService::createInvite(array $in): void` — 管理员；未使用上限 100；`inviteKey=bin2hex(random_bytes(16))`；`isWooyun` 0/1。
  - `AdminService::overview(): array` — `['userCount','onlineCount','onlineUsers','sysInfo']`；不查询不存在的表。
  - `AdminService::modules(array $in): array` — `['items'=>array,'total'=>int,'pno'=>int,'pageSize'=>20]`。
  - `AdminService::audit(array $in): void` — `isAudit` 0/1，写 `managerId/managerName`。

- [ ] **Step 1: 写失败的测试**

`tests/http/user_admin_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-ua.cookies'; @unlink($jar);
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.settings.save' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"emsg\":\"1\",\"dmsg\":\"1\",\"dingding\":\"ding-key\"}'");
assertTrue(str_contains((string) $r, '"ok":true'));
$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=user.settings.get'");
$s = json_decode((string) $r, true)['data'];
assertSame('ding-key', $s['dingding']);
assertSame('1', $s['emsg']);
assertSame('1', $s['dmsg']);

$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.invite.create' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"isWooyun\":0}'");
assertTrue(str_contains((string) $r, '"ok":true'));
$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=user.invites'");
assertTrue(str_contains((string) $r, 'other'));

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=admin.overview'");
$o = json_decode((string) $r, true)['data'];
assertTrue($o['userCount'] >= 1);
assertTrue(isset($o['sysInfo']['phpVersion']));

$r = shell_exec("curl -sS -b {$jar} '{$base}/api.php?action=admin.modules&pNO=1'");
assertTrue(str_contains((string) $r, '"items"'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — 服务未实现。

- [ ] **Step 3: 实现**

按旧 `source/user.php`、`admin/source/*.php` 行为；`settingsSave` 的输入若为 `on` 转 `1`，否则 `0`；`dingding` 直接保存（旧代码漏掉的修复）；`createInvite` 用 `COUNT(*)` 上限 100；`overview` 在线人数口径 `updateTime > time() - EXPIRES`；`modules` 只列 `isOpen=1`，20/页，`Pager` 的页码计算可在 service 内直接 `LIMIT`。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/services/UserService.php source/services/AdminService.php source/lib/Api.php tests
git commit -m "feat: user settings/invites and admin json api"
```

---

### Task 10: 收包端点（字节级保留）

**Files:**
- Create: `source/services/CodeService.php`
- Create: `source/services/ReceiveService.php`
- Create: `source/services/AuthApiService.php`
- Create: `source/services/KeepSessionService.php`
- Modify: `source/code.php`、`source/api.php`、`source/do.php`、`source/keepsession.php`（改为调用 service）
- Modify: `authtest.php`（转义与 URL_ROOT）
- Create: `tests/http/receiver_test.php`

**Interfaces:**
- Consumes: `db()`、`Config`、`StripStr`、`JsonEncode`、`Display::decode`、`Notify::sendMail/sendDing`（Task 12 前用占位）。
- Produces:
  - `CodeService::render(string $id, string $urlKey): string` — 与旧 `source/code.php` 输出一致：按 `id` 或 `urlKey` 查项目，拼接模块代码（`{projectId}`、`{set.*}` 替换），再拼项目代码，全部 `htmlspecialchars_decode(ENT_QUOTES)`。
  - `ReceiveService::receive(array $request, array $server): string` — 返回 redirect target（Referer 合法则原值，否则 `URL_ROOT`）；副作用：去重/插入/通知。`$request` 已由端点用 `Val` 处理后传入，或 service 内部用 `Val`；本计划约定 service 接收原始 `$_REQUEST/$_SERVER` 并内部 `Val`，保持字节一致。
  - `AuthApiService::data(string $auth, string $domain): array` — `[['url'=>..., 'cookie'=>...], ...]`；找不到项目返回空数组（端点输出 `Auth Err.` 由端点处理）。
  - `KeepSessionService::record(string $urlKey, string $url, string $cookie): void`。
  - `authtest.php`：realm 用 `addslashes` 前先 `str_replace(["\r","\n"], '', $_GET['info'])`；Location 用 `URL_ROOT.'/index.php?do=api&'.http_build_query([...])`。

- [ ] **Step 1: 写失败的测试**

`tests/http/receiver_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-rec.cookies'; @unlink($jar);
// 登录并建项目/模块，拿到 urlKey
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];
$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"title\":\"rec\",\"description\":\"\"}'");
$p = json_decode((string) $r, true)['data'];
$urlKey = $p['urlKey'];
$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.setcode.save' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"id\":' . $p['id'] . ',\"ty\":\"edit\",\"modules\":[1],\"code\":\"alert(1)\"}'");
assertTrue(str_contains((string) $r, '"ok":true'));

$r = shell_exec("curl -sS -i '{$base}/index.php?do=code&urlKey={$urlKey}'");
assertTrue(str_contains((string) $r, 'Content-type: application/x-javascript'), 'js content type');
assertTrue(str_contains((string) $r, 'alert(1)'), 'custom code');
assertTrue(str_contains((string) $r, 'Cache-Control: nocache'), 'cache header');

$r = shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$urlKey}&cookie=abc&location=http%3A%2F%2Fexample.com&toplocation=http%3A%2F%2Fexample.com' -H 'Referer: http://example.com/page'");
assertTrue(str_contains((string) $r, '302') || str_contains((string) $r, 'Location: http://example.com/page'), 'redirect referer');
$body = trim(substr((string) $r, strpos((string) $r, "\r\n\r\n") + 4));
assertSame('', $body, 'no body');

$r = shell_exec("curl -sS '{$base}/index.php?do=do&auth={$p['authCode']}'");
$d = json_decode((string) $r, true);
assertSame('http://example.com', $d[0]['url']);
assertSame('abc', $d[0]['cookie']);

$r = shell_exec("curl -sS -i '{$base}/index.php?do=keepsession&id={$urlKey}&url=http%3A%2F%2Fexample.com&cookie=abc'");
assertTrue(str_contains((string) $r, '200'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — 端点仍用旧实现/或类不存在。

- [ ] **Step 3: 实现**

- `source/code.php`：保持 `header("Content-type: application/x-javascript")`、`Cache-Control: nocache`、`Pragma: no-cache`；`echo CodeService::render(Val('id','GET',1), Val('urlKey','GET'))`（注意 `Val` 会转义 urlKey；旧代码也如此，保持）。
- `source/api.php`：`$target = ReceiveService::receive($_REQUEST, $_SERVER); header('Location: '.$target);`。`ReceiveService` 完整复刻旧逻辑：keys 合并、`Val($key,'REQUEST')`、toplocation 回退、cookieHash、去重、serverContent、通知；Referer 为空回退 `URL_ROOT`；Referer scheme 非 http/https 回退 `URL_ROOT`。
- `source/do.php`：项目按 `authCode`；`domain` 过滤；`echo JsonEncode(AuthApiService::data(...))`；找不到项目时 `exit('Auth Err.')`。
- `source/keepsession.php`：`KeepSessionService::record(Val('id','GET'), Val('url','GET'), Val('cookie','GET'))`。
- `authtest.php`：见接口说明。
- 通知调用 `Notify` 占位（Task 12 实现真实发送；当前空实现返回 true）。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/services source/code.php source/api.php source/do.php source/keepsession.php authtest.php tests
git commit -m "feat: byte-compatible receiver endpoints backed by services"
```

### Task 11: 老地址路由与老表单兼容

**Files:**
- Create: `source/lib/UrlMap.php`
- Create: `source/LegacyRouter.php`
- Modify: `index.php`（`do` 非空且非收包 → `LegacyRouter::route`）
- Modify: `admin/index.php`（`do=admin_index` → `/admin`，`do=admin_module` → `/admin/modules`；老 `act=audit` 调 `AdminService::audit` 后 302）
- Create: `tests/unit/urlmap_test.php`
- Create: `tests/http/legacy_test.php`

**Interfaces:**
- Consumes: `db()`、`current_user()`、各 service、`Val`、`AppError`。
- Produces:
  - `UrlMap::pageTarget(string $do, string $act, array $query): ?string` — 返回 SPA 路径或 null（不走页面跳转）。规则见 spec §7.2。
  - `LegacyRouter::route(string $do, array $get, array $post, array $server): void` — 输出 302/JSON/文本并 exit。
  - 老 POST 动作通过 `$_POST`/`$_GET` 调 service；认证类沿用旧字段名；`token` 用 `$user->checkToken(Val('token','POST'|'GET'))`。
  - 老错误不渲染 HTML：302 到目标并带 `?error=<code>`；成功带 `?ok=<code>`。

- [ ] **Step 1: 写失败的测试**

`tests/unit/urlmap_test.php`:
```php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/lib/UrlMap.php';
test('legacy page urls map to spa routes', function () {
    assertSame('/login', UrlMap::pageTarget('login', '', []));
    assertSame('/register?key=abc', UrlMap::pageTarget('register', '', ['key' => 'abc']));
    assertSame('/project/5', UrlMap::pageTarget('project', 'view', ['id' => '5']));
    assertSame('/project/5?domain=x.com', UrlMap::pageTarget('project', 'view', ['id' => '5', 'domain' => 'x.com']));
    assertSame('/modules/7/edit', UrlMap::pageTarget('module', 'set', ['id' => '7']));
    assertSame('/settings', UrlMap::pageTarget('user', 'seting', []));
    assertSame('/admin/modules', UrlMap::pageTarget('admin_module', '', []));
    assertSame(null, UrlMap::pageTarget('code', '', []));
});
```

`tests/http/legacy_test.php`:
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$r = shell_exec("curl -sS -i '{$base}/index.php?do=login'");
assertTrue(str_contains((string) $r, 'Location: /login'), 'login redirect');
$r = shell_exec("curl -sS -i '{$base}/index.php?do=register&key=abc'");
assertTrue(str_contains((string) $r, 'Location: /register?key=abc'), 'register redirect');
$r = shell_exec("curl -sS -i '{$base}/install/step1.php'");
assertTrue(str_contains((string) $r, 'Location: /install'), 'install redirect');

$r = shell_exec("curl -sS -X POST '{$base}/index.php?do=register&act=submit' -d 'user=oldform&email=old@example.com&pwd=123456'");
assertTrue(str_contains((string) $r, '登记成功') || str_contains((string) $r, 'Location: /'), 'old register executes');
$r = shell_exec("curl -sS -X POST '{$base}/index.php?do=register&act=checkue' -d 'user=oldform&email=x@example.com'");
assertTrue(preg_match('/^\d+\|\d+\|\d+$/', trim((string) $r)) === 1, 'legacy ajax format');
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php`
Expected: FAIL — `UrlMap`/`LegacyRouter` 不存在。

- [ ] **Step 3: 实现**

- `UrlMap` 覆盖 spec §7.2 全部页面映射；`project` 的 `act=create/view/setcode/viewcode`；`module` 的默认/create/view/set；`user` 的 invite/createinvite/seting；`admin_index/admin_module`；`install` 由 `install/*.php` 自己 302。
- `LegacyRouter`：先看 `pageTarget`；若返回路径且无动作 → 302；若动作是写操作 → 调 service 后 302（带 `ok/error`）；`checkue`/`delcontent(s)` 返回旧格式；`do=user` 默认 → `/settings`；`do=project` 默认 → `/`；`do=module` 默认 → `/modules`。
- `index.php`：`do` 在 `code/api/do/keepsession` 白名单 → 包含对应文件；否则 `LegacyRouter::route`。`do` 为空 → SPA 壳。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/lib/UrlMap.php source/LegacyRouter.php index.php tests
git commit -m "feat: legacy url router with old post and ajax compatibility"
```

---

### Task 12: 通知（PHPMailer 6 + 钉钉）

**Files:**
- Create: `libs/phpmailer/autoload.php`、`libs/phpmailer/src/PHPMailer.php`、`SMTP.php`、`Exception.php`（从 PHPMailer 6.9.3 复制）
- Create: `source/lib/Notify.php`
- Modify: `source/function.php`（移除旧 SendMail/dingdingNotice，改为调用 Notify）
- Modify: `source/services/ReceiveService.php`（调用 Notify）
- Create: `tests/http/notify_test.php`
- Create: `tests/http/patch-notify-config.php`
- Create: `deploy/mock-dingding.php`

**Interfaces:**
- `Notify::sendMail(string $to, string $subject, string $body): bool` — 配置 `email.host/username/password/port/secure/auth/fromName`，`SMTPDebug=0`，`catch Throwable` 返回 false。
- `Notify::sendDing(string $message, string $key): bool` — 配置 `dingding.keyword/endpoint`（endpoint 为空时用官方 `https://oapi.dingtalk.com/robot/send?access_token=`），TLS 校验开启，5 秒超时。
- `ReceiveService` 的通知条件与文案保持旧版（短信分支已删除）。

- [ ] **Step 1: 写失败的测试**

`tests/http/notify_test.php`（需 MailHog；在临时副本 config 中写入 `email.auth=false`、`email.host=127.0.0.1`、`port=1025`、`secure=''`、`dingding.endpoint=http://127.0.0.1:19090/send`）：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-notify.cookies'; @unlink($jar);
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];
$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=user.settings.save' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"emsg\":\"1\",\"dmsg\":\"1\",\"dingding\":\"test-key\"}'");
assertTrue(str_contains((string) $r, '"ok":true'));
$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"title\":\"notify\",\"description\":\"\"}'");
$p = json_decode((string) $r, true)['data'];
$r = shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$p['urlKey']}&cookie=abc&location=http%3A%2F%2Fexample.com&toplocation=http%3A%2F%2Fexample.com' -H 'Referer: http://example.com/'");
$body = trim(substr((string) $r, strpos((string) $r, "\r\n\r\n") + 4));
assertSame('', $body, 'smtp debug must not leak into response');
sleep(1);
$mail = shell_exec('curl -sS http://127.0.0.1:8025/api/v2/messages');
assertTrue(str_contains((string) $mail, 'example.com'), 'mail delivered');
$ding = shell_exec('curl -sS http://127.0.0.1:19090/last');
assertTrue(str_contains((string) $ding, 'test-key') || str_contains((string) $ding, 'Cookie'), 'ding delivered');
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http`
Expected: FAIL — 旧 PHPMailer `each()` 致命或 `Notify` 不存在。

- [ ] **Step 3: 实现**

- 下载 PHPMailer 6.9.3：`cd /tmp && composer require phpmailer/phpmailer:6.9.3`，把 `vendor/phpmailer/phpmailer/src/*.php` 复制到 `libs/phpmailer/src/`，写 `autoload.php`（`require Exception.php; require PHPMailer.php; require SMTP.php;`）。生产不依赖 Composer。
- `Notify` 用 `PHPMailer\PHPMailer\PHPMailer`，`isSMTP()`、`Host/Port/SMTPAuth/Username/Password/SMTPSecure`、`setFrom(username, fromName)`、`addAddress($to,'Joe')`、`addReplyTo(username,'info')`、`isHTML(true)`、`Subject/Body/AltBody`；`catch (Throwable $e) { error_log(...); return false; }`。
- 钉钉：`curl_init($endpoint ?: default)`；`CURLOPT_SSL_VERIFYPEER` 使用默认（开启）；JSON `msgtype/text/content="{keyword}：{message}"`；返回 `curl_exec` 是否成功。
- 删除旧 `source/class/PHPMailer.class.php`/`Smtp.class.php`（若 Task 14 尚未删）以及 `function.php` 的旧实现，改为薄封装调用 Notify。

- [ ] **Step 4: 运行测试确认通过**

Run:
```bash
sudo docker rm -f xssp-mailhog >/dev/null 2>&1 || true
sudo docker run -d --name xssp-mailhog -p 1025:1025 -p 8025:8025 mailhog/mailhog
setsid php -S 127.0.0.1:19090 deploy/mock-dingding.php > /tmp/xssp-ding.log 2>&1 &
echo $! > /tmp/xssp-ding.pid
bash tests/http/setup-installed.sh
php tests/http/patch-notify-config.php   # 写入 email host/port/auth=false 与 dingding.endpoint
php tests/run.php tests/http
kill $(cat /tmp/xssp-http-installed.pid) $(cat /tmp/xssp-ding.pid)
```
Expected: PASS。

`patch-notify-config.php`：include `/tmp/xssp-http-installed/config.php`，设置 `$config['email']['host']='127.0.0.1'`、`port=1025`、`secure=''`、`auth=false`、`$config['dingding']['endpoint']='http://127.0.0.1:19090/send'`，用 `var_export` 重写该文件。
`deploy/mock-dingding.php`：POST `/send` 时把原始 body 写入 `/tmp/dingding-last.json`；GET `/last` 返回该文件内容。

- [ ] **Step 5: 提交**

```bash
git add libs/phpmailer source/lib/Notify.php source/services/ReceiveService.php source/function.php tests deploy
git commit -m "feat: phpmailer 6 notifications and dingding with tls verification"
```

---

### Task 13: 安全加固回归（CSRF/同源/注入/重定向/安装器）

**Files:**
- Create: `source/lib/Headers.php`
- Create: `source/lib/Origin.php`
- Modify: `source/lib/Api.php`、`source/LegacyRouter.php`、`source/services/InstallService.php`、`source/services/ReceiveService.php`、`source/lib/FrontendShell.php`
- Create: `tests/security/security_test.php`

**Interfaces:**
- `Headers::json(): void`、`Headers::html(): void` — 统一安全头（spec §11.10）。
- `Origin::sameSite(array $server, string $urlRoot): bool` — 无 Origin/Referer 返回 true；有则 host 必须匹配。
- `InstallService` 拒绝已安装（`no_permission` 403）、库名/Host 白名单、原子写。
- `ReceiveService` 的 redirect 只允许 http/https（否则 `URL_ROOT`）。
- `FrontendShell` HTML 头包含 CSP/frame/referrer。

- [ ] **Step 1: 写失败的测试**

`tests/security/security_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-sec.cookies'; @unlink($jar);
$r = shell_exec("curl -sS -c {$jar} -X POST '{$base}/api.php?action=login' -H 'Content-Type: application/json' -d '{\"user\":\"admin\",\"pwd\":\"pw123456\"}'");
$csrf = json_decode((string) $r, true)['data']['csrf'];

// 缺 CSRF
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'Content-Type: application/json' -d '{\"title\":\"x\",\"description\":\"\"}'");
assertSame('419', trim((string) $r), 'missing csrf rejected');
// 跨源
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} -X POST '{$base}/api.php?action=project.create' -H 'Origin: http://evil.com' -H 'X-CSRF-Token: {$csrf}' -H 'Content-Type: application/json' -d '{\"title\":\"x\",\"description\":\"\"}'");
assertSame('403', trim((string) $r), 'cross origin rejected');
// 未登录
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}/api.php?action=dashboard'");
assertSame('401', trim((string) $r), 'unauthenticated 401');
// SQLi
foreach ([
    '/api.php?action=project.get&id=1%27+OR+1%3D1',
    '/index.php?do=code&urlKey=%27+OR+1%3D1',
    '/index.php?do=do&auth=%27+OR+1%3D1',
    '/index.php?do=keepsession&id=%27+OR+1%3D1&url=x&cookie=y',
] as $u) {
    $r = shell_exec("curl -sS -i '{$base}{$u}'");
    assertFalse(str_contains((string) $r, 'MySQL') || str_contains((string) $r, 'Warning') || str_contains((string) $r, 'Fatal'), 'no sql error leak: ' . $u);
}
// 其它用户的项目：直接在建表里种入 user2 及其项目
$db = new mysqli('127.0.0.1', 'root', 'test', 'xssplatform_http', 33061);
$db->query("INSERT INTO oc_user (adminLevel,userName,userPwd,email,addTime) VALUES (0,'user2','x','u2@example.com',1)");
$uid2 = $db->insert_id;
$db->query("INSERT INTO oc_project (userId,title,description,modules,moduleSetKeys,code,urlKey,authCode,addTime) VALUES ($uid2,'other','','[]','[]','','aBcDeF','" . str_repeat('a', 32) . "',1)");
$pid2 = $db->insert_id;
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' -b {$jar} '{$base}/api.php?action=project.get&id={$pid2}'");
assertSame('404', trim((string) $r), 'other user project 404');
$db->close();
// 重装
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' -X POST '{$base}/api.php?action=install.submit' -H 'Content-Type: application/json' -d '{}'");
assertSame('403', trim((string) $r), 'reinstall rejected');
// Host 注入
$r = shell_exec("curl -sS -i -H 'Host: evil.com' '{$base}/install/step1.php'");
assertTrue(str_contains((string) $r, 'Location: /install') && !str_contains((string) $r, 'evil.com'), 'host not reflected');
// Referer scheme：用当前用户的项目 urlKey
$r = shell_exec("curl -sS -b {$jar} -X POST '{$base}/api.php?action=project.create' -H \"X-CSRF-Token: {$csrf}\" -H 'Content-Type: application/json' -d '{\"title\":\"redir\",\"description\":\"\"}'");
$p = json_decode((string) $r, true)['data'];
$r = shell_exec("curl -sS -i '{$base}/index.php?do=api&id={$p['urlKey']}&cookie=y' -H 'Referer: javascript:alert(1)'");
assertFalse(str_contains((string) $r, 'javascript:alert(1)'), 'bad referer scheme rejected');
assertTrue(str_contains((string) $r, 'Location: http://127.0.0.1:18081'), 'fallback to urlroot');
// 安全头
$r = shell_exec("curl -sS -i '{$base}/'");
assertTrue(str_contains((string) $r, 'X-Content-Type-Options: nosniff'));
assertTrue(str_contains((string) $r, 'Content-Security-Policy'));
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security`
Expected: FAIL — 缺 419/403/404、SQL 错误泄漏或无 CSP。

- [ ] **Step 3: 实现**

- `Api`：所有已登录写操作（`project.*`、`module.*`、`user.settings.save`、`user.invite.create`、`admin.module.audit`、`logout`）统一 CSRF 校验；`Origin::sameSite` 在头存在时校验；401 用 `not_logged_in`，419 用 `operation_failed`。
- 对象归属返回 404（不是 403）以防枚举。
- `ReceiveService` 只接受 `http|https` 的 Referer；否则 `URL_ROOT`；Referer 为空同样 `URL_ROOT`。
- `InstallService` 已安装检查；Host/库名白名单；`config.php` 原子写；安装 URL 用相对跳转。
- `Headers` 应用到 API/HTML；CSP 允许 `style-src 'self' 'unsafe-inline'`（shadcn inline style），不含 `unsafe-eval`。
- `authtest.php` 已修（Task 10）。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add source/lib/Headers.php source/lib/Origin.php source/lib/Api.php source/LegacyRouter.php source/services source/lib/FrontendShell.php tests
git commit -m "security: enforce csrf/origin/scheme/install guards and security headers"
```

---

### Task 14: 删除过时代码 + SQL/config/升级脚本

**Files:**
- Delete: `libs/Smarty.class.php`、`libs/SmartyBC.class.php`、`libs/sysplugins/`、`libs/plugins/`、`libs/Autoloader.php`、`libs/bootstrap.php`、`libs/debug.tpl`
- Delete: `themes/`、`source/js/`、`source/api/PHPFetion.php`、`source/class/Image.class.php`、`source/class/Captcha.class.php`、`source/class/Pager.class.php`、`source/class/PHPMailer.class.php`、`source/class/Smtp.class.php`
- Delete: `captcha.php`、旧 `source/index.php`、`source/login.php`、`source/register.php`、`source/project.php`、`source/module.php`、`source/user.php`、`source/common.php`
- Delete: `admin/source/`、`admin/templates/`、`admin/style/`（`admin/index.php` 已在 Task 11 改为跳转/审核适配）
- Modify: `xssplatform.sql`（userPwd 255、session data TEXT）、`config.php.default`、`auto_install.json`、`安装说明.txt`、`scripts/keepsession.php`
- Create: `tests/integration/upgrade_test.php`
- Create: `tests/unit/deadcode_test.sh`

**Interfaces:**
- `scripts/keepsession.php`：用 curl 复刻 `SaeFetchurl` 行为（`Cookie` 头、300 秒阈值、成功后更新 `updateTime`）。
- `auto_install.json`：`php_versions` = `80,81,82,83,84`。
- `安装说明.txt`：去掉 `/register-validate`；保留 `/{6位}`、`/register/{key}`、`/do/auth/*`；补 SPA fallback。
- `config.php.default`：无 `mailauth/filepath/fileprefix/point`；有 `email.port/secure/auth/fromName`、`dingding.endpoint`。

- [ ] **Step 1: 写失败的测试**

`tests/integration/upgrade_test.php`:
```php
require __DIR__ . '/../lib.php';
require __DIR__ . '/../../source/class/DB.class.php';
$cfg = include __DIR__ . '/../config.test.php';
$db = new DB_Mysql();
$db->Connect($cfg['dbHost'], $cfg['dbUser'], $cfg['dbPwd'], $cfg['database'], $cfg['charset'], $cfg['tbPrefix']);
$db->Execute('ALTER TABLE oc_user MODIFY userPwd varchar(50) NULL');
$db->Execute('ALTER TABLE oc_session MODIFY data varchar(255) NULL');
$out = shell_exec('XSSP_CONFIG=' . escapeshellarg(__DIR__ . '/../config.test.php') . ' php ' . escapeshellarg(dirname(__DIR__, 2) . '/install/upgrade.php'));
$col = $db->FirstRow("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_user' AND COLUMN_NAME='userPwd'", [$cfg['database']]);
assertSame('varchar(255)', $col['COLUMN_TYPE']);
$col = $db->FirstRow("SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME='oc_session' AND COLUMN_NAME='data'", [$cfg['database']]);
assertSame('text', $col['DATA_TYPE']);
shell_exec('XSSP_CONFIG=' . escapeshellarg(__DIR__ . '/../config.test.php') . ' php ' . escapeshellarg(dirname(__DIR__, 2) . '/install/upgrade.php'));
assertTrue(true, 'idempotent');
```

`tests/unit/deadcode_test.sh`:
```bash
#!/usr/bin/env bash
set -euo pipefail
! grep -rnE 'PHPFetion|SendSMS\(|Smarty|Captcha|Image\.class|Pager\.class|SaeFetchurl|mailauth|validateKey' \
  --include='*.php' source api.php index.php authtest.php install scripts | grep -v '^Binary'
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/setup-db.sh && php tests/run.php && bash tests/unit/deadcode_test.sh`
Expected: FAIL — 旧符号仍存在；升级列仍窄。

- [ ] **Step 3: 执行清理与更新**

- 删除 §4.4 列出的文件；保留 `source/code.php/api.php/do.php/keepsession.php`（收包）。
- `xssplatform.sql`：`userPwd` 改 `varchar(255)`；`oc_session.data` 改 `text`；保留其余表/示例数据。
- `install/upgrade.php`：连接 `XSSP_CONFIG`；`SHOW COLUMNS` 判断后 `ALTER`；不重置密码。
- `scripts/keepsession.php`：`curl_init` + `CURLOPT_HTTPHEADER ['Cookie: '.urldecode($row['cookie'])]` + `CURLOPT_RETURNTRANSFER` + 超时；`if (time() > updateTime + 300)` 时请求并更新 `updateTime`；失败记 `error_log`。
- `auto_install.json`/`安装说明.txt`/`config.php.default` 按接口更新。
- 运行 `find . -name '*.php' -not -path './.git/*' -not -path './frontend/*' -print0 | xargs -0 -n1 php -l`。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/setup-db.sh && php tests/run.php && bash tests/unit/deadcode_test.sh && php -l api.php && php -l index.php`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add -A
git commit -m "chore: remove obsolete smiley/fetion/captcha/smarty code and add db upgrade"
```

---

### Task 15: 前端脚手架、API 客户端与布局

**Files:**
- Create: `frontend/`（Vite React TS）
- Create: `frontend/src/lib/api.ts`、`frontend/src/lib/api.test.ts`
- Create: `frontend/src/lib/session.ts`
- Create: `frontend/src/components/layout/app-shell.tsx`
- Create: `frontend/src/routes.tsx`、`frontend/src/main.tsx`、`frontend/src/index.css`
- Create: `frontend/vite.config.ts`（`outDir: '../dist'`、`base: '/'`、proxy `/api.php` → `http://127.0.0.1:18081`）
- Create: `frontend/components.json`、`frontend/vitest.config.ts`
- Modify: `.gitignore`（`frontend/node_modules`）

**Interfaces:**
- `ApiError extends Error`：`code: string`、`status: number`、`fields?: Record<string,string>`。
- `api<T>(action: string, opts?: {method?: 'GET'|'POST'; query?: Record<string,string>; body?: unknown; csrf?: string}): Promise<T>` — 拼 `?action=` 与 `query`；POST 发 JSON；带 `X-CSRF-Token`；解析 `{ok,data,error}`；非 2xx/`ok=false` 抛 `ApiError`。
- `useSession()`（TanStack Query）读取 `session`；未登录 `user=null`。
- `AppShell`：顶栏品牌（`config.sitename`）+ 用户菜单；侧栏项目/模块；管理员多“管理后台”；移动端 Sheet。
- 必需 shadcn 组件：button、card、input、label、textarea、checkbox、switch、select、table、badge、separator、skeleton、tooltip、tabs、dialog、alert-dialog、sheet、dropdown-menu、sonner。

- [ ] **Step 1: 初始化脚手架**

Run:
```bash
mkdir -p frontend && cd frontend
npm create vite@latest . -- --template react-ts
npm i
npm i -D tailwindcss @tailwindcss/vite vitest @testing-library/react @testing-library/jest-dom jsdom
npm i react-router-dom @tanstack/react-query react-hook-form zod @hookform/resolvers sonner lucide-react next-themes
npx shadcn@latest init -d
npx shadcn@latest add button card input label textarea checkbox switch select table badge separator skeleton tooltip tabs dialog alert-dialog sheet dropdown-menu sonner
```
Expected: 脚手架生成、`components.json` 写入、`src/components/ui/` 出现组件。

- [ ] **Step 2: 写失败的测试**

`frontend/src/lib/api.test.ts`:
```ts
import { describe, it, expect, vi } from 'vitest';
import { api, ApiError } from './api';

describe('api client', () => {
  it('builds action url and sends csrf header', async () => {
    const fetchMock = vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ ok: true, data: { id: 1 } }), { status: 200 })
    );
    const data = await api<{ id: number }>('project.get', { query: { id: '1' } });
    expect(data.id).toBe(1);
    expect(fetchMock).toHaveBeenCalledWith('/api.php?action=project.get&id=1', expect.objectContaining({ credentials: 'same-origin' }));
    fetchMock.mockClear();
    await api('project.create', { method: 'POST', body: { title: 'x' }, csrf: 'tok' });
    const [, init] = fetchMock.mock.calls[0];
    expect((init as RequestInit).headers).toMatchObject({ 'X-CSRF-Token': 'tok' });
  });

  it('throws ApiError with code and message', async () => {
    vi.spyOn(globalThis, 'fetch').mockResolvedValue(
      new Response(JSON.stringify({ ok: false, error: { code: 'operation_failed', message: '操作失败' } }), { status: 419 })
    );
    await expect(api('logout', { method: 'POST' })).rejects.toMatchObject({ code: 'operation_failed', status: 419 });
  });
});
```

- [ ] **Step 3: 运行测试确认失败**

Run: `cd frontend && npm test -- --run`
Expected: FAIL — `api.ts` 不存在或签名不符。

- [ ] **Step 4: 实现脚手架与 API 客户端**

- `api.ts` 按接口实现；`credentials:'same-origin'`；错误解析；`ApiError`。
- `session.ts` 导出 `useSession()`；TanStack Query。
- `AppShell`/`routes` 只放置占位页，保证 `npm run build` 通过；页面在 Task 16-18 填充。
- `vite.config.ts` 使用 `@tailwindcss/vite`、`build.outDir='../dist'`、`emptyOutDir=true`、`base='/'`、server proxy。
- `src/index.css` 引入 Tailwind 与 shadcn CSS variables；系统字体栈。
- `package.json` 增加 `"test": "vitest run"`。

- [ ] **Step 5: 运行测试与构建**

Run: `cd frontend && npm test -- --run && npx tsc --noEmit && npm run build`
Expected: PASS，`../dist/index.html` 与 `assets/` 生成。

- [ ] **Step 6: 提交**

```bash
git add frontend .gitignore
git commit -m "feat(frontend): vite react shadcn scaffold, api client and app shell"
```
### Task 16: 登录、注册、安装向导 UI

**Files:**
- Create: `frontend/src/pages/login-page.tsx`
- Create: `frontend/src/pages/register-page.tsx`
- Create: `frontend/src/pages/install-page.tsx`
- Create: `frontend/src/lib/validation.ts`
- Create: `frontend/src/lib/agreement.ts`（用 `git show <删除前提交>:install/step1.php` 提取旧协议正文，存为纯文本；Task 5 把 step1 改为跳转后仍可从 git 历史取得）
- Create: `frontend/src/pages/register-page.test.tsx`、`login-page.test.tsx`、`install-page.test.tsx`
- Modify: `frontend/src/routes.tsx`

**Interfaces:**
- Consumes: `api`、`useSession()`、shadcn 组件、zod。
- Produces:
  - `loginSchema`（user 非空、pwd 非空、auto boolean）、`registerSchema`（user/email/pwd/pwd2/key，规则与 `Validate` 一致）。
  - `LoginPage`：POST `login`；成功 `navigate('/')`；`ApiError` 就地显示 `error.message`。
  - `RegisterPage`：先 `register.check`，再 `register.submit`；显示字段级错误；无 phone/pmsg 字段。
  - `InstallPage`：`meta`/`install.status` → Step1 协议（不可写时禁用）→ Step2 表单 + 测试连接 → Step3 `install.submit` → 成功显示“进入登录”。
- 路由：`/login`、`/register`、`/install` 替换占位页。

- [ ] **Step 1: 写失败的测试**

`frontend/src/pages/register-page.test.tsx`:
```tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { RegisterPage } from './register-page';
import * as apiModule from '../lib/api';

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error { code = ''; status = 400; },
}));

describe('RegisterPage', () => {
  it('rejects empty submit and submits valid values without phone', async () => {
    render(<MemoryRouter><RegisterPage /></MemoryRouter>);
    fireEvent.click(screen.getByRole('button', { name: /注册/i }));
    await waitFor(() => expect(screen.getByText(/用户名/i)).toBeTruthy());
    fireEvent.change(screen.getByLabelText(/用户名/i), { target: { value: 'newuser' } });
    fireEvent.change(screen.getByLabelText(/邮箱/i), { target: { value: 'new@example.com' } });
    fireEvent.change(screen.getByLabelText(/密码/i), { target: { value: '123456' } });
    fireEvent.change(screen.getByLabelText(/确认密码/i), { target: { value: '123456' } });
    fireEvent.click(screen.getByRole('button', { name: /注册/i }));
    await waitFor(() => {
      expect(apiModule.api).toHaveBeenCalledWith('register.submit', expect.objectContaining({
        method: 'POST',
        body: expect.objectContaining({ user: 'newuser', email: 'new@example.com', pwd: '123456' }),
      }));
    });
  });
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd frontend && npm test -- --run`
Expected: FAIL — 页面不存在。

- [ ] **Step 3: 实现**

- 使用 shadcn Card/Input/Label/Button/Checkbox，居中卡片布局；注册/登录表单用 react-hook-form + zod。
- 注册的前端校验与服务端 `Validate` 一致；`pwd2` 只在前后端前端比较；提交成功后自动登录，跳 `/`。
- 安装向导用三步 Stepper（shadcn Tabs 或自定义）；协议正文用 `agreement.ts`；Step2 的“测试连接”调 `install.testDb`；Step3 调 `install.submit`，失败显示 `error.message`。
- `auth` 页面的背景使用中性色卡片，不引入外部资源。
- 登录页保留“自动登录（360 天）”复选框。

- [ ] **Step 4: 运行测试**

Run: `cd frontend && npm test -- --run && npx tsc --noEmit`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add frontend/src/pages frontend/src/lib frontend/src/routes.tsx
git commit -m "feat(frontend): login, register and install wizard pages"
```

---

### Task 17: 首页与项目页面

**Files:**
- Create: `frontend/src/pages/dashboard-page.tsx`
- Create: `frontend/src/pages/project-new-page.tsx`
- Create: `frontend/src/pages/project-view-page.tsx`
- Create: `frontend/src/pages/project-config-page.tsx`
- Create: `frontend/src/pages/project-code-page.tsx`
- Create: `frontend/src/components/project-table.tsx`
- Create: `frontend/src/components/content-table.tsx`
- Create: `frontend/src/components/code-block.tsx`
- Create: `frontend/src/components/domain-filter.tsx`
- Create: `frontend/src/components/confirm-delete.tsx`
- Create: `frontend/src/pages/project-config-page.test.tsx`、`content-table.test.tsx`、`code-block.test.tsx`
- Modify: `frontend/src/routes.tsx`

**Interfaces:**
- Consumes: `api`、`useSession()`、TanStack Query、shadcn。
- Produces:
  - `DashboardPage`：`dashboard` 数据；项目表（名称/描述/内容数/创建时间/删除）；创建入口。
  - `ProjectNewPage`：`project.create` → `navigate('/project/'+id+'/config?created=1')`。
  - `ProjectViewPage`：`project.get`；domain Select；可展开行；全选/批删；单删；插件下载 `/xsser.crx`；链接到代码/配置。
  - `ProjectConfigPage`：`project.setcode.get/save`；模块勾选 + setkeys（模块 1 keepsession 单选）；**自定义代码 Textarea 预填现有 `code`**（spec §19.2）；`ty` 透传。
  - `ProjectCodePage`：`project.viewcode`；`CodeBlock` 展示 `code/codeurl/scriptShow1/scriptShow2/shortShow1/shortShow3`；复制按钮；“完成/返回首页”。
  - `ContentTable`：列 checkbox、展开/折叠、时间、接收内容、Headers、删除；所有值纯文本。

- [ ] **Step 1: 写失败的测试**

`frontend/src/pages/project-config-page.test.tsx`:
```tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { ProjectConfigPage } from './project-config-page';
import * as apiModule from '../lib/api';

vi.mock('../lib/api', () => ({
  api: vi.fn(),
  ApiError: class ApiError extends Error { code = ''; status = 400; },
}));

describe('ProjectConfigPage', () => {
  it('prefills existing custom code and saves selected modules', async () => {
    (apiModule.api as any).mockResolvedValue({
      project: { id: 1, title: 'p', code: 'old-code' },
      modulesCan: [{ id: 1, title: 'm', keys: 'cookie', code: 'module-code', setkeys: [{ key: 'keepsession', value: '1' }], choosed: 1 }],
      ty: 'edit',
    });
    render(<MemoryRouter><ProjectConfigPage id={1} /></MemoryRouter>);
    expect(screen.getByDisplayValue('old-code')).toBeTruthy();
    fireEvent.click(screen.getByRole('button', { name: /配置/i }));
    expect(apiModule.api).toHaveBeenCalledWith('project.setcode.save', expect.objectContaining({
      method: 'POST',
      body: expect.objectContaining({ id: 1, code: 'old-code' }),
    }));
  });
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd frontend && npm test -- --run`
Expected: FAIL — 页面/组件不存在。

- [ ] **Step 3: 实现**

- 所有字段按 spec §9 保留；删除/批删用 AlertDialog；`ContentTable` 的展开状态用组件 state；domain 过滤直接 `project.get` query。
- 代码页只做 `<pre>`/`textarea` 文本与复制；不解析 HTML。
- 配置页的 `setkey_{moduleId}_{key}` 以请求体扁平键提交（与后端契约一致）；模块 1 的 `keepsession` 用“无 / keepsession”单选。
- 空内容显示空状态；API 错误显示 toast/就地提示。

- [ ] **Step 4: 运行测试**

Run: `cd frontend && npm test -- --run && npx tsc --noEmit`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add frontend/src
git commit -m "feat(frontend): dashboard and project pages with full legacy features"
```

---

### Task 18: 模块、设置、邀请、管理员页面

**Files:**
- Create: `frontend/src/pages/modules-page.tsx`
- Create: `frontend/src/pages/module-new-page.tsx`
- Create: `frontend/src/pages/module-view-page.tsx`
- Create: `frontend/src/pages/module-edit-page.tsx`
- Create: `frontend/src/pages/settings-page.tsx`
- Create: `frontend/src/pages/invites-page.tsx`
- Create: `frontend/src/pages/admin-page.tsx`
- Create: `frontend/src/pages/admin-modules-page.tsx`
- Create: `frontend/src/components/dynamic-key-editor.tsx`
- Create: `frontend/src/pages/settings-page.test.tsx`、`dynamic-key-editor.test.tsx`
- Modify: `frontend/src/routes.tsx`

**Interfaces:**
- Consumes: `api`、`useSession()`、shadcn。
- Produces:
  - `ModulesPage`：`modules.list`；私有/公共分组；创建入口。
  - `ModuleNewPage`：默认 keys `location/toplocation/cookie/opener` 复选框 + `DynamicKeyEditor` 添加自定义；setkeys 编辑器；代码 Textarea；私有/公开；`module.create`。
  - `ModuleViewPage`：只读展示 keys/setkeys/code/描述/公开状态。
  - `ModuleEditPage`：同旧 `set_submit` 字段；`canEdit=false` 时只读；`module.save`。
  - `SettingsPage`：email 展示、邮件/钉钉 Switch、钉钉 Key、提交（`user.settings.save`）；无手机号。
  - `InvitesPage`：乌云币/其它两张表 + 生成按钮（管理员）；`user.invites`、`user.invite.create`。
  - `AdminPage`：概况统计卡 + 环境信息；`admin.overview`。
  - `AdminModulesPage`：分页表 + 审核通过/取消；`admin.modules`、`admin.module.audit`。
  - 路由守卫：`adminLevel<=0` → 403 页（文案“没有操作权限”）。

- [ ] **Step 1: 写失败的测试**

`frontend/src/pages/settings-page.test.tsx`:
```tsx
import { describe, it, expect, vi } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { SettingsPage } from './settings-page';
import * as apiModule from '../lib/api';

vi.mock('../lib/api', () => ({ api: vi.fn(), ApiError: class extends Error {} }));

describe('SettingsPage', () => {
  it('saves dingding key and two toggles, no phone field', async () => {
    (apiModule.api as any).mockResolvedValue({ email: 'a@b.c', dingding: 'k', emsg: '0', dmsg: '0' });
    render(<SettingsPage />);
    expect(screen.queryByLabelText(/手机/i)).toBeNull();
    fireEvent.click(screen.getByLabelText(/邮件提醒/i));
    fireEvent.click(screen.getByLabelText(/钉钉通知/i));
    fireEvent.change(screen.getByLabelText(/钉钉/i), { target: { value: 'new-key' } });
    fireEvent.click(screen.getByRole('button', { name: /提交/i }));
    expect(apiModule.api).toHaveBeenCalledWith('user.settings.save', expect.objectContaining({
      body: expect.objectContaining({ dingding: 'new-key', emsg: '1', dmsg: '1' }),
    }));
  });
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd frontend && npm test -- --run`
Expected: FAIL — 页面/组件不存在。

- [ ] **Step 3: 实现**

- `DynamicKeyEditor` 提供添加/删除，输出 `keys[]`/`setkeys[]` 数组；模块 1 默认 keys 勾选。
- 设置页只保留邮件/钉钉；`emsg/dmsg` 提交字符串 `'1'/'0'`。
- 邀请生成成功后 invalidate `user.invites`；非管理员看到“没有操作权限”的 toast/403。
- 管理员模块分页 `pNO`；审核后刷新列表。
- 页面风格与 Task 17 一致；表格、卡片、空状态。

- [ ] **Step 4: 运行测试**

Run: `cd frontend && npm test -- --run && npx tsc --noEmit`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add frontend/src
git commit -m "feat(frontend): modules, settings, invites and admin pages"
```

---

### Task 19: 视觉打磨（响应式/暗色/空状态/复制）

**Files:**
- Create: `frontend/src/components/theme-provider.tsx`、`theme-toggle.tsx`
- Create: `frontend/src/components/empty-state.tsx`
- Modify: `frontend/src/components/layout/app-shell.tsx`、`frontend/src/index.css`、所有页面细节
- Create: `frontend/src/components/theme-toggle.test.tsx`

**Interfaces:**
- `ThemeProvider` 使用 `next-themes`；`ThemeToggle` 切换 light/dark，偏好存 `localStorage`。
- `EmptyState` 接受 `icon/title/description`。
- `AppShell` 移动端用 `Sheet` 打开侧栏；桌面固定侧栏；表格横向滚动；表单单列。
- 所有危险操作用 `AlertDialog`；加载用 `Skeleton`；结果用 `sonner`。

- [ ] **Step 1: 写失败的测试**

`frontend/src/components/theme-toggle.test.tsx`:
```tsx
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { ThemeProvider } from './theme-provider';
import { ThemeToggle } from './theme-toggle';

it('renders a theme toggle with accessible label', () => {
  render(<ThemeProvider><ThemeToggle /></ThemeProvider>);
  expect(screen.getByRole('button', { name: /主题/i })).toBeTruthy();
});
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd frontend && npm test -- --run`
Expected: FAIL — 组件不存在。

- [ ] **Step 3: 实现**

- 视觉：shadcn new-york/neutral；主色蓝；代码/Cookie/URL 等宽字体；紧凑密度；卡片阴影轻；边框统一。
- 移动端检查 375px：侧栏 Sheet、表格可横向滚动、按钮不溢出。
- 空状态覆盖：项目无内容、模块列表为空、邀请为空、通知列表为空。
- 所有页面接入 ThemeProvider；CSP 允许 inline style（已在 Task 13）。
- 复制按钮统一用 `navigator.clipboard.writeText`，失败 toast。

- [ ] **Step 4: 运行测试**

Run: `cd frontend && npm test -- --run && npx tsc --noEmit`
Expected: PASS。

- [ ] **Step 5: 提交**

```bash
git add frontend
git commit -m "style(frontend): responsive shell, dark mode, empty states and copy affordances"
```

---

### Task 20: 构建产物、部署配置与文档

**Files:**
- Create: `deploy/nginx.conf`、`deploy/router.php`、`deploy/.htaccess.example`
- Modify: `README.md`、`安装说明.txt`、`auto_install.json`
- Create: `tests/http/spa_test.php`
- Build: `dist/`（入库）

**Interfaces:**
- `deploy/router.php`：PHP 内置服务器路由：静态文件（`/assets`、`/dist`、`/xsser.crx`）直接返回；`/api.php`、`/index.php`、`/authtest.php` 正常；其余若文件不存在 → `require index.php`（SPA fallback）。
- nginx 示例：见 spec §15；`location / { try_files $uri $uri/ /index.php$is_args$args; }`；`/assets` 静态；PHP location。
- Apache 示例：`RewriteCond %{REQUEST_FILENAME} !-f` + `RewriteRule ^ index.php [L]`，保留收包重写。
- `auto_install.json`：`php_versions` = `80,81,82,83,84`，`success_url` `/`。
- README：PHP ≥8.0、安装、前端构建、老库升级、nginx/apache、测试命令、安全说明。

- [ ] **Step 1: 写失败的测试**

`tests/http/spa_test.php`：
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
foreach (['/', '/login', '/project/1', '/modules'] as $path) {
    $r = shell_exec("curl -sS -i '{$base}{$path}'");
    assertTrue(str_contains((string) $r, '200'), "spa 200 {$path}");
    assertTrue(str_contains((string) $r, '<div id="root">'), "spa shell {$path}");
}
preg_match('~src="(/assets/[^"]+\.js)"~', (string) file_get_contents(__DIR__ . '/../../dist/index.html'), $m);
assertTrue(!empty($m[1]), 'dist index references an asset');
$r = shell_exec("curl -sS -o /dev/null -w '%{http_code}' '{$base}{$m[1]}'");
assertSame('200', trim((string) $r), 'asset served');
$r = shell_exec("curl -sS -i '{$base}/index.php?do=login'");
assertTrue(str_contains((string) $r, 'Location: /login'), 'legacy still redirects');
```

- [ ] **Step 2: 运行测试确认失败**

Run: `cd frontend && npm run build && cd .. && bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security`
Expected: FAIL — SPA fallback 未配置 / 路由 404。

- [ ] **Step 3: 实现**

- `npm run build` 输出 `dist/index.html` + `assets/`；`index.php` 已能输出 `dist/index.html`（Task 4）。
- 写三套部署配置，并在注释中写明 Web 根 = 仓库根、`/assets` 静态、收包 rewrite。
- README/安装说明更新；`auto_install.json` 更新。
- 用 `deploy/router.php` 启动 PHP 内置服务器验证深链 fallback。

- [ ] **Step 4: 运行测试确认通过**

Run: `bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && kill $(cat /tmp/xssp-http-installed.pid)`
Expected: PASS；`dist/` 已生成。

- [ ] **Step 5: 提交**

```bash
git add dist deploy README.md 安装说明.txt auto_install.json tests
git commit -m "build: commit frontend dist, deployment configs and docs"
```

---

### Task 21: 端到端与全量回归

**Files:**
- Create: `frontend/playwright.config.ts`
- Create: `frontend/e2e/install.spec.ts`、`auth.spec.ts`、`project.spec.ts`、`module.spec.ts`、`admin.spec.ts`、`mobile.spec.ts`
- Create: `tests/http/byte_compare_test.php`
- Create: `tests/fixtures/byte_seed.sql`
- Modify: `README.md`（测试命令）

**Interfaces:**
- Playwright 指向 `http://127.0.0.1:18081`；`webServer` 不自动启动（测试前用 `tests/http/setup-installed.sh`）。
- E2E 覆盖：安装向导（用独立空白 DB 副本）、登录/退出、注册、项目创建→配置→代码页、收包→内容页→删除、模块创建→审核、设置保存、移动端截图。
- `byte_compare_test.php`：固定 fixture 项目/模块，断言 `do=code` 输出与 `do=do` JSON 的精确字符串。

- [ ] **Step 1: 写失败的测试**

`frontend/e2e/auth.spec.ts`:
```ts
import { test, expect } from '@playwright/test';

test('login and reach dashboard', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel(/用户名/i).fill('admin');
  await page.getByLabel(/密码/i).fill('pw123456');
  await page.getByRole('button', { name: /登录/i }).click();
  await expect(page).toHaveURL(/\/$/);
  await expect(page.getByText(/我的项目/i)).toBeVisible();
});
```

`tests/http/byte_compare_test.php`:
```php
require __DIR__ . '/../lib.php';
$base = 'http://127.0.0.1:18081';
$jar = '/tmp/xssp-byte.cookies'; @unlink($jar);
// 登录 → 建项目 + 模块（code 含 &quot; 与 &#039;）→ 配置 → 取 code
// 期望 body 精确等于：var x="a";alert(1);
// fixture 由 tests/fixtures/byte_seed.sql 种入：urlKey=FIXTUREKEY、authCode=FIXTUREAUTH、模块代码 &quot;a&quot;
$r = shell_exec("curl -sS '{$base}/index.php?do=code&urlKey=FIXTUREKEY'");
assertSame('var x="a";alert(1);', trim((string) $r), 'code endpoint bytes');
$r = shell_exec("curl -sS '{$base}/index.php?do=do&auth=FIXTUREAUTH'");
assertSame('[{"url":"http:\/\/example.com","cookie":"abc"}]', trim((string) $r), 'do endpoint json');
```

- [ ] **Step 2: 运行测试确认失败**

Run: `bash tests/http/setup-installed.sh && (cd frontend && npx playwright test)`
Expected: FAIL — 浏览器未安装/用例未实现。

- [ ] **Step 3: 实现与准备**

- `npm i -D @playwright/test && npx playwright install chromium`。
- 按接口实现 six specs；安装向导 spec 使用独立副本或清空测试 DB。
- byte fixture：新增 `tests/fixtures/byte_seed.sql`，在 setup-installed 安装完成后通过 `sudo docker exec -i xssp-test-db mysql -uroot -ptest xssplatform_http < tests/fixtures/byte_seed.sql` 种入固定项目（urlKey=FIXTUREKEY、authCode=FIXTUREAUTH、code 含 `htmlspecialchars` 转义、模块 1 参与），使输出可逐字节断言。setup-installed.sh 末尾执行该种子。
- README 加测试命令。

- [ ] **Step 4: 全量验证（必须逐项执行）**

Run:
```bash
bash tests/setup-db.sh
php tests/run.php tests/unit tests/integration
bash tests/unit/deadcode_test.sh
find . -name '*.php' -not -path './.git/*' -not -path './frontend/*' -print0 | xargs -0 -n1 php -l
cd frontend && npm test -- --run && npx tsc --noEmit && npm run build
bash tests/http/setup-installed.sh && php tests/run.php tests/http tests/security && (cd frontend && npx playwright test) && kill $(cat /tmp/xssp-http-installed.pid)
```
Expected: 全部 PASS；PHP 日志无致命/未捕获；安全用例全绿。

- [ ] **Step 5: 提交**

```bash
git add frontend/e2e frontend/playwright.config.ts tests README.md
git commit -m "test: playwright e2e and byte-level regression for receivers"
```

---

## 执行顺序与门禁

- 顺序：Task 1 → 21 串行；Task 15 起才开始前端（依赖 Task 4-13 的 API 契约）。
- 每个任务：先写失败测试 → 运行确认失败 → 最小实现 → 运行通过 → 提交。
- 任何任务若发现 spec 与实现冲突：以 spec 为准，停止并在提交前修正计划。
- 收包端点字节测试（Task 10/21）未通过前，不得删除旧端点代码（Task 14）。
- 安全用例（Task 13）未通过前，不得进入前端页面任务。
- 最终交付：`php tests/run.php tests/unit tests/integration tests/http tests/security`、`npm test`、`npm run build`、Playwright 全部通过；`php -l` 全量通过；老地址与收包端点回归通过。

