<?php 
	session_start();
	include_once('cache_remover.php');
	include_once('products.php');
	include_once('user.php');
	include_once('home_controller.php');

?>
<?php include_once('include/header.php'); ?>

	<?php include_once('include/navbar.php'); ?>
	<div class="container-fluid">
		<br/>
		<div class="row">
			<div class="col-sm-9">
				<div class="row">
					<?php foreach($getAllProducts as $product): ?>
						<?php $productImage = $products->getProductImage($product['id']); ?>
						<div class="col-sm-3 card-product-column">
				        	<div class="card card-product">
								<a href="product/?<?php echo "prod_id=".$product['id']?>">
									<img class="card-img-top card-img-product" src="<?php echo $productImage['name']; ?>" alt="Card image cap">
								</a>						  	
							  	<div class="card-body text-center" data-product_id="<?php echo $product['id'];?>" data-product_name="<?php echo $product['name']; ?>" data-product_desc="<?php echo $product['description']; ?>" data-product_price="<?php echo "$".$product['price']; ?>" data-product_img="<?php echo $productImage['name']; ?>">
							    	<h5 class="card-title"><a href="product/?<?php echo "prod_id=".$product['id']?>" class="text-info"><?php echo $product['name']; ?></a></h5>
								    <h6 class="card-text">
								    	<strong><?php echo "$".$product['price']; ?></strong>
								    </h6>
							    	<?php 
							    		$rate_formula = $user->getAverageRatedProduct($product['id']);
										$ratedProduct = $user->getRatedProduct($product['id']);
									?>
						    		<div class="rating mt-3">
							    		<?php if($ratedProduct['product_id']): ?>
				            				<?php for($i = 0; $i < round($rate_formula['average_rate']); $i++): ?>
				            					<i class="fa icon-star mt-3 mb-4"></i>
				            				<?php endfor; ?>	                				
				                			<?php if($rate_formula['average_rate'] < 5): ?>
				        						<?php $rest_star = 5 - round($rate_formula['average_rate']); ?>
				        						<?php for($i = 0; $i < $rest_star; $i++): ?>
				        							<i class="fa icon-star-empty mt-3 mb-4"></i>
				        						<?php endfor; ?>	            					
				                			<?php endif; ?>	
				                			<h6 class="mt-auto mb-auto mr-2">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
				                			<h6 class="mt-auto mb-auto">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
				                			<?php else: ?>
									    	<?php for($i = 0; $i < $rate_formula['average_rate']; $i++): ?>
				            					<i class="fa icon-star mt-3 mb-4"></i>
				            				<?php endfor; ?>	                				
				                			<?php if($rate_formula['average_rate'] < 5): ?>
				        						<?php $rest_star = 5 - round($rate_formula['average_rate']); ?>
				        						<?php for($i = 0; $i < $rest_star; $i++): ?>
				        							<i class="fa icon-star-empty mt-3 mb-4"></i>
				        						<?php endfor; ?>	            					
				                			<?php endif; ?>	
					      					<h6 class="mt-auto mb-auto mr-3">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
                							<h6 class="mt-auto mb-auto mr-1">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
	  									<?php endif; ?>	
	    							</div>
	  								<div>
							    		<button class="btn btn-success addToCartBtn card-link float-left" name="add_to_cart" type="submit"><i class="fa icon-shopping-cart"></i> Add to cart</button>
							  	
							    		<a href="product/?<?php echo "prod_id=".$product['id']?>" class="btn btn-secondary card-link float-right">Details</a>
							    	</div>	
							  	</div>
							</div>
				        </div>		    
				    <?php endforeach; ?>
				</div>    
			</div>
			<div class="col-sm-3">
		    	<!-- Sidebar -->
		    	<?php include_once('include/sidebar.php');?>
		    </div>	
	    </div>
	</div>
<?php include_once('include/footer-bar.php'); ?>
<?php include_once('include/footer.php'); ?>