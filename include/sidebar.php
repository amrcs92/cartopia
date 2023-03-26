<?php 
	include_once('session.php');
	include_once('products.php');

	$userLogin = new Session();
	$products = new Products();
	$relatedProducts = $products->getRelatedProducts();
?>
<nav id="sidebar">
	<div class="card">
		<div class="card-header bg-dark text-light">My Account</div>
		<div class="card-body">
			<?php 
				$userLogin->showSession();
			?>
		</div> 
	</div>
	<br/>
	<div class="card mb-2">
		<div class="card-header bg-primary text-light">Related Products</div>
		<div class="card-body">
			<?php foreach($relatedProducts as $relatedProduct):?>
				<?php $relatedProductImage = $products->getProductImage($relatedProduct['id']); ?>
				<div class="card related-products">
					<a href="product/?<?php echo "prod_id=".$relatedProduct['id']?>">
			  			<img class="card-img-top card-img-product" src="<?php echo $relatedProductImage['name']; ?>" alt="Card image">
			  		</a>	
					<div class="card-body">
					    <h5 class="card-title"><a href="product.php/?<?php echo "prod_id=".$relatedProduct['id']?>" class="text-info"><?php echo $relatedProduct['name']; ?></a></h5>
					    <p class="card-text"><strong><?php echo "$".$relatedProduct['price']; ?></strong></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div> 
	</div>
</nav>
