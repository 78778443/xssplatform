<?php /* Smarty version 2.6.26, created on 2013-12-08 00:48:59
         compiled from menus.html */ ?>
<div class="left menus">
	<div class="menutitle">
		<a class="left" href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php">我的项目</a>
		<a class="right" href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=project&act=create">创建</a>
	</div>
	
	<ul>
		<?php $_from = $this->_tpl_vars['projects']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['v']):
?>
		<li><a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=project&act=view&id=<?php echo $this->_tpl_vars['v']['id']; ?>
"><?php echo $this->_tpl_vars['v']['title']; ?>
</a></li>
		<?php endforeach; endif; unset($_from); ?>
	</ul>
	<div class="menutitle">
		<a class="left" href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=module">我的模块</a>
		<a class="right" href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=module&act=create">创建</a>
	</div>
	<ul>
		<?php $_from = $this->_tpl_vars['modules']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['v']):
?>
		<?php if ($this->_tpl_vars['v']['isOpen'] == 0 || ( $this->_tpl_vars['v']['isOpen'] == 1 && $this->_tpl_vars['v']['isAudit'] == 0 )): ?>
		<li><a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=module&act=set&id=<?php echo $this->_tpl_vars['v']['id']; ?>
"><?php echo $this->_tpl_vars['v']['title']; ?>
</a></li>
		<?php endif; ?>
		<?php endforeach; endif; unset($_from); ?>
	</ul>
	<div class="menutitle">
		<a class="left" href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=module">公共模块</a>
	</div>
	<ul>
		<?php $_from = $this->_tpl_vars['modules']; if (!is_array($_from) && !is_object($_from)) { settype($_from, 'array'); }if (count($_from)):
    foreach ($_from as $this->_tpl_vars['v']):
?>
		<?php if ($this->_tpl_vars['v']['isOpen'] == 1 && $this->_tpl_vars['v']['isAudit'] == 1): ?>
		<li><a href="<?php echo $this->_tpl_vars['url']['root']; ?>
/index.php?do=module&act=view&id=<?php echo $this->_tpl_vars['v']['id']; ?>
"><?php echo $this->_tpl_vars['v']['title']; ?>
</a></li>
		<?php endif; ?>
		<?php endforeach; endif; unset($_from); ?>
	</ul>
</div>