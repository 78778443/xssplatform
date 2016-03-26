<?php
/**
 * DB.class.php database operator
 * @author blue,email: blueforyou@163.com
 * $databaseType: mysql,mssql,oracle..
 * ----------------------------------------------------------------
 * OldCMS,site:http://www.oldcms.com
 */
if(!defined('IN_OLDCMS')) die('Access Denied');
 
class BlueDB{
	public static function DB($databaseType='mysql'){
		switch(strtolower($databaseType)){
			case 'mysql':
				return new DB_Mysql;
				break;
			default:
				return false;
				break;
		}
	}
}
interface IDataBase{
	function Connect($dbHost='localhost',$dbUser='root',$dbPwd='',$dbName='',$dbCharset='utf8',$tbPrefix='');
	function Execute($sql);
	function Dataset($sql);
	function FirstRow($sql);
	function FirstColumn($sql);
	function FirstValue($sql);
}
/**
* Mysql
*/
class DB_Mysql implements IDataBase{
	private $host,$username,$password,$database,$charset;
	public $linkId,$queryId,$rows=array(),$rowsNum=0,$tbPrefix;
	function __destructor(){
		Disconnect();
	}
	/* connect to database */
	public function Connect($dbHost='localhost',$dbUser='root',$dbPwd='',$dbName='',$dbCharset='utf8',$tbPrefix=''){
		$this->host=$dbHost;
		$this->username=$dbUser;
		$this->password=$dbPwd;
		$this->database=$dbName;
		$this->charset=$dbCharset;
		$this->tbPrefix=$tbPrefix;
		
		$this->linkId=mysql_connect($this->host,$this->username,$this->password);
		if(!empty($this->linkId)){
			mysql_query("SET NAMES '".$this->charset."'",$this->linkId);
			if(mysql_select_db($this->database,$this->linkId)) return $this->linkId;
		}else{
			return false;
		}
	}
	/* disconnect to database */
	private function Disconnect(){
		if(!empty($this->linkId)){
			if(!empty($this->queryId)) mysql_free_result($this->queryId);
			return mysql_close($this->linkId);
		}
	}
	/* execute without result */
	public function Execute($sql){
		return mysql_query($sql,$this->linkId);
	}
	/* auto execute type=>insert/update */
	public function AutoExecute($table,$array=array(),$type='INSERT',$where=''){
		if(!empty($array) && !empty($table)){
			switch(strtoupper($type)){
				case 'INSERT':
					$sql="INSERT INTO {$table}(".implode(',',array_keys($array)).") VALUES('".implode("','",array_values($array))."')";
					break;
				case 'UPDATE':
					$sql="UPDATE {$table}";
					$updates=array();
					foreach($array as $key=>$value){
						$updates[]="{$key}='{$value}'";
					}
					$sql.=" SET ".implode(',',$updates);
					if(!empty($where)){
						$sql.=" WHERE {$where}";
					}
					break;
				default:break;
			}
			return $this->Execute($sql);
		}else{
			return false;
		}
	}
	/* return dataset of query */
	public function Dataset($sql){
		$this->rows=array();
		$this->queryId=mysql_query($sql,$this->linkId);
		while($row=mysql_fetch_assoc($this->queryId)){
			$this->rows[]=$row;
		}
		$this->rowsNum=count($this->rows);
		return $this->rows;
	}
	/* return first row */
	public function FirstRow($sql){
		$this->queryId=mysql_query($sql,$this->linkId);
		$row=mysql_fetch_assoc($this->queryId);
		if(!empty($row)){
			$this->rowsNum=1;
			return $row;
		}else{
			$this->rowsNum=0;
			return false;
		}
	}
	/* return first column (array) */
	public function FirstColumn($sql){
		$Columns=array();
		$this->queryId=mysql_query($sql,$this->linkId);
		while($row=@mysql_fetch_row($this->queryId)){
			$Columns[]=$row[0];
		}
		$this->rowsNum=count($Columns);
		return $Columns;
	}
	/* return first value */
	public function FirstValue($sql){
		$this->queryId=mysql_query($sql,$this->linkId);
		$row=@mysql_fetch_row($this->queryId);
		if(!empty($row)){
			$this->rowsNum=1;
			return $row[0];
		}else{
			$this->rowsNum=0;
			return false;
		}
	}
	/* last id */
	public function LastId(){
		return mysql_insert_id();
	}
}
?>