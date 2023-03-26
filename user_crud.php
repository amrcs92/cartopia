<?php 
	require_once('config/database.php');

	class UserCRUD
	{
		private $conn;

		public function __construct()
		{
			$instance = Database::getInstance();
			$this->conn = $instance->connectDb();
		}

		public function createUser()
		{			
			try
			{
				$sql = "INSERT INTO users (username, phone, address, email, password, balance, user_role) VALUES(:username, :phone, :address, :email, :password, 100.00, :user_role)";
				$query = $this->conn->prepare($sql);
				$query->bindParam(':username', $username);
				$query->bindParam(':phone', $phone);
				$query->bindParam(':address', $address);
				$query->bindParam(':email', $email);
				$query->bindParam(':password', $password);
				$query->bindParam(':user_role', $user_role);

				$username = $_POST['username'];
				$phone = $_POST['phone'];
				$address = $_POST['address'];
				$email = $_POST['email'];
				$password = crypt($_POST['password'], '$6$rounds=5000$usesomesillystringforsalt$');
				$user_role = 0;

				$query->execute();
			} 
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: ". $e->getMessage());
			}
		}

		public function getUserEmailAndPass($email, $password)
		{
			try
			{
				$sql = "SELECT * FROM users WHERE email = ? AND password = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $email);
				$query->bindParam(2, $password);

				$password = crypt($_POST['password'], '$6$rounds=5000$usesomesillystringforsalt$');

				$query->execute();
				if(password_verify($_POST['password'], $password))
				{
					$result = $query->fetch(PDO::FETCH_ASSOC);

					if($query->rowCount() == 1)
					{
						return $result;
					}
				}
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function getUserBalance($userId)
		{
			try
			{
				$sql = "SELECT balance FROM users WHERE id = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $userId);
				
				$query->execute();
				$result = $query->fetchColumn();
				if($query->rowCount() == 1)
				{
					return $result;
				}
			} 
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function updateUserBalance($balance, $userId)
		{
			try
			{
				$sql = "UPDATE users SET balance = ? WHERE id = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $balance);
				$query->bindParam(2, $userId);

				$query->execute();
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function createRating($userId, $productId, $rate)
		{
			try
			{
				$sql = "INSERT INTO user_rating  (user_id, product_id, rate, created_at) VALUES (?, ?, ?, NOW() + INTERVAL 12 HOUR)";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $userId);
				$query->bindParam(2, $productId);
				$query->bindParam(3, $rate);

				$query->execute();
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function getProductRating($productId)
		{
			try
			{
				$sql = "SELECT * FROM user_rating WHERE product_id = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $productId);
				$query->execute();
				$result = $query->fetch(PDO::FETCH_ASSOC);

				if($query->rowCount() >= 1)
				{
					return $result;
				}

			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function getUserRating($userId, $productId)
		{
			try
			{
				$sql = "SELECT * FROM user_rating WHERE user_id = ? AND product_id = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $userId);
				$query->bindParam(2, $productId);
				$query->execute();

				$result = $query->fetch(PDO::FETCH_ASSOC);

				if($query->rowCount() == 1)
				{
					return $result;
				}

			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function getAverageUsersRating($productId)
		{
			try
			{
				$sql = "SELECT ROUND( AVG(rate), 2) as average_rate, COUNT(id) as total_rate FROM user_rating WHERE product_id = ?";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $productId);
				$query->execute();

				$result = $query->fetch(PDO::FETCH_ASSOC);
				if($query->rowCount() == 1)
				{
					return $result;
				}
			} 
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}

		public function checkoutCart($shippingId, $totalCost, $products)
		{
			try
			{
				$sql = "INSERT INTO orders (shipping_id, total_cost, created_at) VALUES (?, ?, NOW() + INTERVAL 12 HOUR )";
				$query = $this->conn->prepare($sql);
				$query->bindParam(1, $shippingId);
				$query->bindParam(2, $totalCost);
				
				$query->execute();

				$orderId = $this->conn->lastInsertId();

				$sql = "INSERT INTO products_order (order_id, product_id, quantity) VALUES ";
				foreach($products as $product)
				{
					$sql .= " (?, ?, ?),";
				}
				$sql = substr($sql, 0, -1);
				
				$query2 = $this->conn->prepare($sql);

				$key = 0;
				foreach($products as &$product)
				{
					$key++;
					$query2->bindParam($key, $orderId);
					$key++;
					$query2->bindParam($key, $product['pid']);
					$key++;
					$query2->bindParam($key, $product['quantity']);
				}
				
				$query2->execute();
			}
			catch(PDOEXCEPTION $e)
			{
				throw new Exception("Error: " . $e->getMessage());
			}
		}
	}