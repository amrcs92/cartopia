<?php 

	$user = new User();

	$products = new Products();
	$getAllProducts = $products->getAllProducts();

	if(isset($_POST['rating']))
	{
		$rate = $_POST['rate'];
	}

?>
