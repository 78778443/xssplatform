<?php /* Smarty version 2.6.26, created on 2013-12-07 23:24:25
         compiled from register.html */ ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>XSS Platform</title>
<link rel="stylesheet" href="<?php echo $this->_tpl_vars['url']['themePath']; ?>
/style/screen.css" type="text/css" media="screen, projection"> 
<link rel="stylesheet" href="<?php echo $this->_tpl_vars['url']['themePath']; ?>
/style/print.css" type="text/css" media="print"> 
<!--[if lt IE 8]><link rel="stylesheet" href="<?php echo $this->_tpl_vars['url']['themePath']; ?>
/style/ie.css" type="text/css" media="screen, projection"><![endif]-->
<link rel="stylesheet" href="<?php echo $this->_tpl_vars['url']['themePath']; ?>
/style/style.css" type="text/css" media="screen, projection">
<script type="text/javascript" src="<?php echo $this->_tpl_vars['url']['root']; ?>
/source/js/jquery.js"></script>
<style type="text/css">
<?php echo '
tbody tr:nth-child(even) td, tbody tr.even td { background: #FFFFFF }
tbody tr td { background: #FFFFFF; height:55px }
tbody tr td .error { margin-bottom:0 }
'; ?>

</style>
</head>
<body>
<div class="container">
	<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => "header.html", 'smarty_include_vars' => array()));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>
	<div class="main">
		<form id="formRegister" action="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=register&act=submit" method="post">
		<fieldset>
			<legend>注册</legend>
			<table class="formTable">
			<tr>
				<th width="100">邀请码</th>
				<td width="150"><input id="key" name="key" type="text" style="width:200px" value="<?php echo $this->_tpl_vars['key']; ?>
" /></td>
				<td><div id="key_check"></div></td>
			</tr>
			<tr>
				<th width="100">用户名</th>
				<td width="150"><input id="user" name="user" type="text" style="width:200px" maxlength="20" /></td>
				<td><div id="user_check">4-20个字符(字母、汉字、数字、下划线)</div></td>
			</tr>
			<tr>
				<th>邮箱</th>
				<td><input id="email" name="email" type="text"  style="width:200px" maxlength="50" /></td>
				<td><div id="email_check">可以使用邮箱登录</div></td>
			</tr>
			<tr>
				<th>密码</th>
				<td><input id="pwd" name="pwd" type="password" style="width:200px" maxlength="20" /></td>
				<td><div id="pwd_check">6-20个字符(字母、数字、下划线)</div></td>
			</tr>
			<tr>
				<th>密码确认</th>
				<td><input id="pwd2" name="pwd2" type="password" style="width:200px" maxlength="20" /></td>
				<td><div id="pwd2_check"></div></td>
			</tr>
			<tr><th></th>
				<td colspan="2"><input id="btnRegister" type="submit" value="提交注册" />  
						<span style="margin-left:20px">
							已经拥有账号? <a href="<?php echo $this->_tpl_vars['url']['login']; ?>
">直接登录</a>
						</span>
				</td>
			</tr>
			</table>
		</fieldset>
		</form>
	</div><!-- main End -->
</div>

<script type="text/javascript">
<?php echo '
function Register(){
	var errNum=0;
	var checkItems=[\'key\',\'user\',\'email\',\'pwd\',\'pwd2\'];
	for(var i=0;i<checkItems.length;i++){
		if($("#"+checkItems[i]).val()==""){
			errNum++;
			$("#"+checkItems[i]+"_check").addClass("error");
			$("#"+checkItems[i]+"_check").html("不能为空");
		}else{
			$("#"+checkItems[i]+"_check").addClass("correct");
			$("#"+checkItems[i]+"_check").html("√");
		}
	}
	/* 特殊判断 */
	//用户格式
	var user=$("#user").val();
	if(user!="")
	{
		if(!/^[\\w\\u4E00-\\u9FA5]{4,20}$/.test(user)){
			errNum++;
			$("#user_check").removeClass("correct");
			$("#user_check").addClass("error");
			$("#user_check").html("4-20个字符(字母、汉字、数字、下划线)");
		}else{
			$("#user_check").removeClass("error");
			$("#user_check").addClass("correct");
			$("#user_check").html("√");
		}
	}
	//邮箱格式
	var email=$("#email").val();
	if(email!=""){
		if(!/^(\\w+\\.)*?\\w+@(\\w+\\.)+\\w+$/.test(email)){
			errNum++;
			$("#email_check").removeClass("correct");
			$("#email_check").addClass("error");
			$("#email_check").html("邮箱格式不正确");
		}else{
			$("#email_check").removeClass("error");
			$("#email_check").addClass("correct");
			$("#email_check").html("√");
		}
	}
	//密码
	var pwd=$("#pwd").val();
	if(pwd!="")
	{
		if(!/^\\w{6,20}$/.test(pwd)){
			errNum++;
			$("#pwd_check").removeClass("correct");
			$("#pwd_check").addClass("error");
			$("#pwd_check").html("6-20个字符");
		}else{
			$("#pwd_check").removeClass("error");
			$("#pwd_check").addClass("correct");
			$("#pwd_check").html("√");
		}
	}
	//确认密码
	var pwd2=$("#pwd2").val();
	if(pwd2!="")
	{
		if(pwd2!=pwd){
			errNum++;
			$("#pwd2_check").removeClass("correct");
			$("#pwd2_check").addClass("error");
			$("#pwd2_check").html("两次输入密码不相同");
		}else{
			$("#pwd2_check").removeClass("error");
			$("#pwd2_check").addClass("correct");
			$("#pwd2_check").html("√");
		}
	}
	//提交注册
	if(errNum<=0){
		var key=$("#key").val();
		//判断用户/邮箱/key
		$.post("/index.php?do=register&act=checkue&r="+Math.random(),{"user":user,"email":email,"key":key},function(re){
			var reArr=re.split("|");
			if(reArr[0]==0 && reArr[1]==0 && reArr[2]==0){
				$("#formRegister").submit();
			}else{
				if(reArr[0]>0){
					$("#user_check").removeClass("correct");
					$("#user_check").addClass("error");
					$("#user_check").html("用户已存在");
				}
				if(reArr[1]>0){
					$("#email_check").removeClass("correct");
					$("#email_check").addClass("error");
					$("#email_check").html("邮箱已存在");
				}
				if(reArr[2]>0){
					$("#key_check").removeClass("correct");
					$("#key_check").addClass("error");
					$("#key_check").html("邀请码输入错误");
				}
			}
		});
	}
}
'; ?>

</script>
</body>
</html>