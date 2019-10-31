<?php
include "./common.php";

header("content-type:text/html;charset=utf-8");
$path = $_SERVER['HTTP_REFERER'];
if (!basename($path) == 'step1.php') {
    echo basename($path);
    exit('非法请求!');
}
?>
<html>
<head>
    <title><?= $project ?></title>
    <link href="..//themes/default/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div id="form" style="width: 400px;margin: 50 auto;">
    <form class="form-horizontal" action='step3.php' method='post'>
        数据库地址：<input class="form-control" type='text' name='DB_HOST' value='127.0.0.1'/><br/>
        数据库用户名：<input class="form-control" type='text' name='DB_USER' value='root'/><br/>
        数据库密码：<input class="form-control" type='password' name='DB_PASS'/><br/>
        数据库名称：<input class="form-control" type='text' name='DB_NAME' value='xssplatform'/><br/>

        <hr/>
        管理员：<input class="form-control" type='text' name='username'/><br/>
        管理员密码:<input class="form-control" type='password' name='password'/><br/>
        <input type='submit' class="btn btn-success" value='下一步'/>

    </form>
</div>
</body>
</html>