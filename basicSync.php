<?php
/********************************************************************************
*                             Basic Synchronization Script                      *
*                                   version: beta 1                             *
*                             Written By Abdulaziz Al Rashdi                    *
*                   http://www.alrashdi.co  |  https://github.com/phpawcom      *
*  Note: Make a backup for your databases before starting the synchronization   *
*********************************************************************************/
$db1 = new db('localhost', 'root', 'root', 'test_cs_in', ''); // Main Database
$db2 = new db('localhost', 'root', 'root', 'test_cs_in2', '');  // Another Database
$sync = new sync($db1, $db2);

## Don't change below ##
class sync {
	private $databases = array();
	private $fields = array();
	private $temporary = array();
	private $lastKey = array();
	private $dbTest = array('', '');
	public function __construct($db1, $db2){
		$this->databases[0] = $db1;
		$this->databases[1] = $db2;
		$this->prepare_newer_date();
		if(@count($this->temporary) > 0):
		  $this->execute_queries();
		endif;
		echo '<pre>'.print_r($this->temporary, true).'</pre>';
		echo '<pre>'.print_r($this->lastKey, true).'</pre>';
	}
	private function prepare_newer_date(){
		$this->databases[1]->query('select * from #prefix#synchronization', 'tables');
		while($row = $this->databases[1]->fetch_array('tables')):
		  $this->copyTo($row['table_name'], $row['primarykey'], $row['lastkey_out']);
		  $this->copyFrom($row['table_name'], $row['primarykey'], $row['lastkey_in']);
		endwhile;
	}
	private function copyTo($table, $keyName, $keyValue){
		//echo 'copyTo: '.$table.'.'.$keyName.' = '.$keyValue."<br />\n";
		$this->databases[1]->query('select * from #prefix#'.$table.' where '.$keyName.' > '.$keyValue.' order by '.$keyName.' ASC');
		while($field = $this->databases[1]->fetch_fields()):
		  if($field->name == $keyName) continue;
		  array_push($this->fields, $field->name);
		endwhile;
		while($row = $this->databases[1]->fetch_array()):
		  $temporary = 'insert into '.$this->dbTest[1].'#prefix#'.$table.' set ';
		  foreach($this->fields as $field):
		    $temporary .= $field.' = \''.$row[$field].'\', ';
		  endforeach;
		  $temporary = substr($temporary, 0, -2);
		  array_push($this->temporary, array('db' => '0', 'table' => $this->dbTest[1].$table, 'pk' => $keyName, 'pkv' => $row[$keyName], 'query' => $temporary));
		endwhile;
		$this->fields = array();
	}
	private function copyFrom($table, $keyName, $keyValue){
		//echo 'copyFrom: '.$table.'.'.$keyName.' = '.$keyValue."<br />\n";
		$this->databases[0]->query('select * from #prefix#'.$table.' where '.$keyName.' > '.$keyValue.' order by '.$keyName.' ASC');
		while($field = $this->databases[0]->fetch_fields()):
		  if($field->name == $keyName) continue;
		  array_push($this->fields, $field->name);
		endwhile;
		while($row = $this->databases[0]->fetch_array()):
		  $temporary = 'insert into '.$this->dbTest[0].'#prefix#'.$table.' set ';
		  foreach($this->fields as $field):
		    $temporary .= $field.' = \''.$row[$field].'\', ';
		  endforeach;
		  $temporary = substr($temporary, 0, -2);
		  array_push($this->temporary, array('db' => '1', 'table' => $this->dbTest[0].$table, 'pk' => $keyName, 'pkv' => $row[$keyName], 'query' => $temporary));
		endwhile;
		$this->fields = array();
	}
	private function execute_queries(){
		foreach($this->temporary as $row):
		 $this->databases[$row['db']]->query($row['query']);
		 if($row['db'] == 0):
		   $this->lastKey[$row['table']]['out'] = $this->databases[$row['db']]->insert_id();
		 else:
		   $this->lastKey[$row['table']]['in'] = $this->databases[$row['db']]->insert_id();
		 endif;
		endforeach;
		$this->databases[1]->query('select * from #prefix#synchronization', 'tables');
		while($row = $this->databases[1]->fetch_array('tables')):
		  $this->databases[0]->query('select max('.$row['primarykey'].') as maxpk from #prefix#'.$row['table_name'].' ');
		  $lastId = $this->databases[0]->fetch_array();
		  $this->lastKey[$row['table_name']]['out'] = (int) $lastId[0];
		  $this->databases[1]->query('select max('.$row['primarykey'].') as maxpk from #prefix#'.$row['table_name'].' ');
		  $lastId = $this->databases[1]->fetch_array();
		  $this->lastKey[$row['table_name']]['in'] = (int) $lastId[0];
		  
		  $this->databases[1]->query('update #prefix#synchronization set 
		                              lastkey_out = \''. $this->lastKey[$row['table_name']]['out'].'\', 
									  lastkey_in = \''. $this->lastKey[$row['table_name']]['in'].'\' 
		                              where table_name = \''.$row['table_name'].'\' && primarykey = \''.$row['primarykey'].'\'');
		endwhile;
	}
}
class db extends sync {
	public static $database;
	public $mysqldatabase;
	public $dbconnect;
	public $dbquery;
	public $dbresult;
	public $dbqueryArr = array();
	public function __construct($server, $username, $password, $name, $prefix){
		$this->mysqldatabase = array('server' => $server,
									 'username' => $username,
									 'password' => $password,
									 'name' => $name,
									 'prefix' => $prefix);
	}
	public function connect(){
		$this->dbconnect = @new mysqli($this->mysqldatabase['server'], $this->mysqldatabase['username'], $this->mysqldatabase['password'], $this->mysqldatabase['name']);
		try {
			if (mysqli_connect_error())
			  throw new Exception("Failed to connect to MySQL: (" . mysqli_connect_errno() . ") " . mysqli_connect_error());
		}
		catch(Exception $e1){
			echo $e1->getMessage()."<br />\n";
			echo '<textarea name="MYSQL_ERROR" cols="100" rows="10">'.$e1->getTraceAsString()."</textarea><br />\n";
			exit;
		}
		$this->dbconnect->set_charset('utf8');
		return $this->dbconnect;
	}
	public function disconnect(){
		if($this->dbconnect == TRUE)
		 $this->dbconnect->close();
	}
	public function query($sql='', $qryArr=FALSE){
		$this->dbconnect = !isset($this->dbconnect)? self::connect() : $this->dbconnect;
		try {
			$sql = str_ireplace('#prefix#', $this->mysqldatabase['prefix'], $sql);
			if(($queryid = @$this->dbconnect->query($sql, MYSQLI_STORE_RESULT)) == FALSE)			
			   throw new Exception('Seems there is an error in Structured Query Language');
			if($qryArr != FALSE){
				$this->dbqueryArr["$qryArr"] = $queryid;
			}else{
				 $this->dbquery = $queryid;
			}
		}
		catch(Exception $e3){
			echo $e3->getMessage()."<br />\n";
			echo '<pre>'.$e3->getTraceAsString()."</pre>\n";
			echo 'MySQLi Error: '.$this->dbconnect->error."<br /><br />\n";
			exit;
		}
	}
	public function fetch_array($qryArr=FALSE){
		$queryid = ($qryArr != FALSE)? $this->dbqueryArr["$qryArr"] : $this->dbquery;
		$this->dbresult = mysqli_fetch_array($queryid, MYSQL_BOTH);
		return $this->dbresult;
	}
	public function num_rows($qryArr=FALSE){
		$queryid = ($qryArr != FALSE)? $this->dbqueryArr["$qryArr"] : $this->dbquery;
		$this->dbresult = $queryid->num_rows;
		return $this->dbresult;
	}
	public function insert_id(){
		return $this->dbconnect->insert_id;
	}
	
	public function fetch_fields($qryArr=FALSE){
		$queryid = $qryArr != FALSE? $this->dbqueryArr["$qryArr"] : $this->dbquery;
		return $queryid->fetch_field();
	}
}


?>