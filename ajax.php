<?php 
	session_start();

	include('products.php');
	include('user.php');

	$user = new User();

	$status = null;

	// $userBalance = $user->getMyBalance($_SESSION['user']['id']);
	if(isset($_POST['action']) && $_POST['action'] == 'checkout')
	{
		if(isset($_SESSION['user']))
		{
			$userId = $_SESSION['user']['id'];
			$status = "loggedin";
			$myBalance = $user->getMyBalance($_SESSION['user']['id']);
			if($myBalance >= $_POST['total_price'])
			{
				$shippingId = $_POST['shipping'];
				$totalCost = $_POST['total_price'];
				$products = $_POST['products'];
				
				$currentBalance = $myBalance - $totalCost;
				$user->checkout($shippingId, $totalCost, $products);
				$user->updateMyBalance($currentBalance, $userId);				
				
			} 
			else
			{
				$status =  "low balance";
			}
		} 
		else{
			$status = "notLogged";
		}

		$_POST = array();
		echo $status;
	}

	if(isset($_POST['rateProduct']) && $_POST['rateProduct'] == 'rating')
	{
		if(isset($_SESSION['user']))
		{
			$userId = $_SESSION['user']['id'];
			$status = "loggedin";
			$productId = $_POST['productId'];
			$rate = $_POST['rate'];


			$user->rateProduct($userId, $productId, $rate);
		}
		else 
		{
			$status = "notLogged";
		}
		
		$_POST = array();
		echo $status;
	}