<?php
	require_once('config/database.php');

	class Products
	{
		private $conn;
		
		public function __construct()
		{
			$instance = Database::getInstance();
			$this->conn = $instance->connectDb();
		}

		public function getAllProducts()
		{
			try
			{
				$sql = "SELECT * FROM products";
				$query = $this->conn->prepare($sql);
				$query->execute();
				$result = $query->setFetchMode(PDO::FETCH_ASSOC); 
				$products = $query->fetchAll();
								
			} 
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
			return $products;
		}

		public function getProductImage($product_id)
		{
			try
			{
				$sql = "SELECT * FROM product_images WHERE product_id = ? LIMIT 1";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $product_id);
				$query->execute();
				$result = $query->fetch(PDO::FETCH_ASSOC);
			} 
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
			return $result;
		}

		public function getRelatedProducts()
		{
			try
			{
				$sql = "SELECT * FROM products ORDER BY id DESC LIMIT 3";
				$query = $this->conn->prepare($sql);
				$query->execute();
				$result = $query->setFetchMode(PDO::FETCH_ASSOC);
				$relatedProducts = $query->fetchAll();
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
			return $relatedProducts;
		}

		public function getProductById($product_id)
		{
			try
			{
				$sql = "SELECT * FROM products WHERE id = ? LIMIT 1";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $product_id);
				$query->execute();
				$result = $query->setFetchMode(PDO::FETCH_ASSOC);
				// print_r($result);
				// exit();
				$product = $query->fetch(PDO::FETCH_ASSOC);
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: ". $e->getMessage());
			}
			return $product;

		}

		public function getShippingMethods()
		{
			try
			{
				$sql = "SELECT * FROM shipping";
				$query = $this->conn->prepare($sql);
				$query->execute();
				$result = $query->setFetchMode(PDO::FETCH_ASSOC);
				$shipingMethods = $query->fetchAll();
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
			return $shipingMethods;
		}

	}