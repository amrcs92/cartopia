<?php
	include_once('user_crud.php');
	
	class User extends UserCRUD
	{
		private $userCRUD;
	

		function __construct()
		{
			$this->userCRUD = new UserCRUD();
		}

		public function register()
		{

			$errorArr = array();

			if(isset($_POST['register']))
			{
				$username = $_POST['username'];
				$phone = $_POST['phone'];
				$address = $_POST['address'];
				// filter email variable with sanitizing input
				$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
				$password = $_POST['password'];
				
				if($username == '' && $email == '' && $password == '')
				{
					$errorArr['required_fields'] = "Please fill the required fields !!";
				}
				if(!isset($email) || trim($email) == '')
				{
					$errorArr['email_empty'] = "Email can't be empty !!";
				}
				if(!isset($password) || trim($password) == '')
				{
					$errorArr['password_empty'] = "Password can't be empty !!";
				}
				if(!filter_var($email, FILTER_SANITIZE_EMAIL))
				{
					$errorArr['invalid_format'] = "Invalid Email format !!";
				}
				if(!empty($errorArr))
				{
					return $errorArr;
				}

				$this->userCRUD->createUser();

				$_POST = array();

				header('Location: /cartopia/login.php');

			}
		}

		public function login()
		{
			$errorArr = array();
			if(isset($_POST['login']))
			{
				$email = $_POST['email'];
				$password = $_POST['password'];
				

				if((!isset($email) || trim($email) =='') && (!isset($password) || trim($password) == ''))
				{
					$errorArr['email_password_invalid'] = "Invalid email or password";
				}
				if(!isset($email) || trim($email) == '')
				{
					$errorArr['email_empty'] = "Email can't be empty";
				} 
				if(!isset($password) || trim($password) == '')
				{
					$errorArr['password_empty'] = "Password can't be empty";
				}
				if(!empty($errorArr))
				{
					return $errorArr;
				} else
				{
					$user = $this->userCRUD->getUserEmailAndPass($email, $password);
					//unset the post after submit
					$_POST = array();

					if($user)
					{
						session_start();
						$_SESSION['user'] = $user;
						header('Location: /cartopia/home.php');
						return $user;
					}
				}
			}
		}

		public function logout()
		{
			if(isset($_POST['logout']))
			{
				session_unset();
				session_destroy();
				header('Location: /cartopia/home.php');
			}
		}

		public function getMyBalance($userId)
		{
			return $this->userCRUD->getUserBalance($userId);
		}

		public function updateMyBalance($balance, $userId)
		{
			return $this->userCRUD->updateUserBalance($balance, $userId);
		}

		public function rateProduct($userId, $productId, $rate)
		{
			return $this->userCRUD->createRating($userId, $productId, $rate);
		}

		public function getRatedProduct($productId)
		{
			return $this->userCRUD->getProductRating($productId);
		}

		public function getRatedUser($userId, $productId)
		{
			return $this->userCRUD->getUserRating($userId, $productId);
		}

		public function getAverageRatedProduct($productId)
		{
			return $this->userCRUD->getAverageUsersRating($productId);
		}

		public function checkout($shippingId, $totalCost, $products){
			return $this->userCRUD->checkoutCart($shippingId, $totalCost, $products);
		}
	}
?>