<?php
include "./common.php"; ?>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title><?= $project ?></title>
        <link href="..//themes/default/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
    <div style="width: 900px;margin: 50 auto;">
        <?php
        //接收参数
        $host = addslashes($_POST['DB_HOST'] ?? '');
        $user = addslashes($_POST['DB_USER'] ?? '');
        $password = addslashes($_POST['DB_PASS'] ?? '');
        $dbname = addslashes($_POST['DB_NAME'] ?? '');

        //连接mysql数据库
        $link = mysqli_connect($host, $user, $password);

        if (!$link) {
            echo "<script>";
            echo "alert('数据库信息有误');";
            echo "window.history.back();";
            echo "</script>";
        }

        //修改配置文件
        changeConfig($host, $user, $password, $dbname);

        //导入数据
        addOldData($link, $dbname);
        ?>
    </div>
    </body>
    </html>

<?php


function changeConfig(string $host, string $user, string $password, string $dbname)
{
    //读取配置项中的默认值
    $config = [];
    include "../config.php.default";

    //更改默认值
    $config['dbHost'] = $host;
    $config['dbUser'] = $user;
    $config['dbPwd'] = $password;
    $config['database'] = $dbname;

    //修改配置文件
    $confStr = "<?php \n\$config = ";
    $confStr .= var_export($config, true) . ';';
    if (!file_put_contents("../config.php", $confStr)) {
        exit('修改配置文件失败');
    }
}

function addOldData($link, $dbname)
{


    //账户信息
    $username = addslashes($_POST['username']);
    $password = md5('OldCMS|' . $_POST['password']);
//    var_dump($username,$_POST['password']);die;;
    //导入最新的数据格式
    $sql = "CREATE DATABASE IF NOT EXISTS $dbname DEFAULT CHARSET utf8 COLLATE utf8_general_ci;";
    $sql .= "use {$dbname};\n";
    $sql .= file_get_contents("../xssplatform.sql") . "\n";
    $sql .= "UPDATE $dbname.oc_user SET userName='{$username}',userPwd='$password',adminLevel=1 ORDER BY id ASC LIMIT 1";

    //正则替换域名
    $pattern = "/http:\/\/(.*?)\//";
    $newStr = "http://{$_SERVER['HTTP_HOST']}/";
    $sql = preg_replace($pattern, $newStr, $sql);


    //批量插入用户名
    mysqli_multi_query($link, $sql);
    if (mysqli_errno($link)) {
        exit("导入数据失败:" . mysqli_error($link));
    } else {
        echo "导入数据成功!" . PHP_EOL;
        echo "<form action='../index.php'>
                <input class=\"btn btn-success\" type='submit' value='进入首页'/>
              </form>";

        file_put_contents('install.lock', '');
    }
}