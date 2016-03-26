<?php /* Smarty version 2.6.26, created on 2013-12-07 23:27:59
         compiled from index.html */ ?>
<?php require_once(SMARTY_CORE_DIR . 'core.load_plugins.php');
smarty_core_load_plugins(array('plugins' => array(array('modifier', 'date_format', 'index.html', 34, false),)), $this); ?>
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
</head>
<body>
<div class="container">
<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => "header.html", 'smarty_include_vars' => array()));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>
<?php $_smarty_tpl_vars = $this->_tpl_vars;
$this->_smarty_include(array('smarty_include_tpl_file' => "menus.html", 'smarty_include_vars' => array()));
$this->_tpl_vars = $_smarty_tpl_vars;
unset($_smarty_tpl_vars);
 ?>
<div class="span-19 right">
	<table border="0" cellspacing="0" cellpadding="0">
		<caption><b>我的项目</b> <a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=project&act=create" class="right">创建项目</a></caption>
		<thead>
			<tr>
				<th width="200">项目名称</th>
				<th>项目描述</th>
				<th width="100">内容数</th>
				<th width="100">创建时间</th>
				<th width="50">操作</th>
			</tr>
		</thead>
		<tbody>
			<?php $_from = $this->_tpl_vars['projects']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['v']):
?>
			<tr>
				<td><a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=project&act=view&id=<?php echo $this->_tpl_vars['v']['id']; ?>
"><?php echo $this->_tpl_vars['v']['title']; ?>
</a></td>
				<td><?php echo $this->_tpl_vars['v']['description']; ?>
</td>
				<td><?php echo $this->_tpl_vars['v']['contentNum']; ?>
</td>
				<td><?php echo ((is_array($_tmp=$this->_tpl_vars['v']['addTime'])) ? $this->_run_mod_handler('date_format', true, $_tmp, '%Y-%m-%d') : smarty_modifier_date_format($_tmp, '%Y-%m-%d')); ?>
</td>
				<td>
				<a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=project&act=delete&id=<?php echo $this->_tpl_vars['v']['id']; ?>
&token=<?php echo $this->_tpl_vars['show']['user']['token']; ?>
" onclick="return confirm('确定删除吗?');">删除</a>
				</td>
			</tr>
			<?php endforeach; endif; unset($_from); ?>
		</tbody>
	</table>
</div>
</div>
</body>
</html>