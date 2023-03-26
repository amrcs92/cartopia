<?php 

class Database
{

	private static $instance = null;
	private $db;

	private function __construct(){
		$this->connectDb();
	}

	private function __clone(){
		throw new Exception("Can't clone a Database Connection");
	}

	public function __wakeup(){
	}

	public static function getInstance(){
		if(!self::$instance){
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function connectDb()
	{
		$hostname = "localhost";
		$username = "root";
		$password = "";
		$dbname = "cartopia";
		$conn_string = "mysql:host=$hostname;dbname=$dbname";
		try
		{
			$this->db = new PDO($conn_string, $username, $password);
			$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} 
		catch(PDOException $e)
		{
			throw new Exception("Connection failed: " . $e->getMessage());
		}
		return $this->db;
	}

	public function getConnection(){
		return $this->db;
	}

	public function closeConnection(){
		$this->db = null;
	}
}
?>