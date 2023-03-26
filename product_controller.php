<?php 
	
	$products = new Products();
	$user = new User();

	$product = $products->getProductById($_REQUEST['prod_id']);
	
	$_REQUEST = array();
	
	$productImage = $products->getProductImage($product['id']);

	if(isset($_POST['rating']))
	{
		$rate = $_POST['rate'];
		$_POST = array();
	}
	
	$productId = $product['id'];

	$ratedProduct = $user->getRatedProduct($productId);

	$rate_formula = $user->getAverageRatedProduct($productId);