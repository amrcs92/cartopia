<?php 

$request = trim($_SERVER['REQUEST_URI'], '/');
// echo $request;
if($request == 'cartopia')
{
	require 'front.php';
}
elseif ($request == 'cartopia/home') 
{
	require 'home.php';
}
elseif ($i = strpos($request, '/product/')) 
{
	// echo substr($request,$i+9);
	$_GET['prod_id'] = substr($request,$i+9);
	require 'product.php';
}
elseif ($request == 'cartopia/mycart') 
{
	require 'mycart.php';
}