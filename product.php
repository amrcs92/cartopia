<?php
	session_start();
	include_once('cache_remover.php');
	include_once('products.php'); 
	include_once('user.php');

	include_once('product_controller.php');

?>

<?php include_once('include/header.php'); ?>

<?php include_once('include/navbar.php'); ?>


<div class="container">
	<div class="row mt-3 mb-3">
		<div class="col-md-6" style="display: inline-block;">
			<img width="400" height="400" src="../<?php echo $productImage['name']; ?>" alt="">
		</div>
		<div class="col-md-6" style="display: inline-block; vertical-align: top;">
			<div class="card">
			  <div class="card-body" data-product_id="<?php echo $product['id'];?>" data-product_name="<?php echo $product['name']; ?>" data-product_desc="<?php echo $product['description']; ?>" data-product_price="<?php echo $product['price']; ?>" data-product_img="<?php echo $productImage['name']; ?>">
			    <h2><?php echo $product['name']; ?></h2>
			    <p>
			    	<strong>Description</strong><br/>
			    	<?php echo $product['description']; ?>
			    </p>
			    <h5><small>Current price: $<?php echo $product['price'];?></small></h5>
			    <div class="section">
                    <h5 class="title-attr"><small>Quantity</small></h5>                    
                    <div class="button-container">
                        <div class="btn-minus"><i class="fa icon-minus"></i></div>
                        <input type="text" value="1" name="prod_quant" id="product_quantity"/>
                    	<div class="btn-plus"><i class="fa icon-plus"></i></div>
                    </div>
                </div>
                <div class="rating ml-0" style="display: flex; width: 100%">
            		<h5 class="title-attr" style="line-height: 3; margin-right: 5px;"><small>Rating</small></h5>
            		<?php if(isset($_SESSION['user']['id'])): ?> <!------------ if session ---------------->
            			<?php 
            				$userId = $_SESSION['user']['id'];
							$ratedUser = $user->getRatedUser($userId, $productId);
            			?>
	                	<?php if($ratedUser['user_id']): ?> <!------------ if ratedUser ---------------->
            				<?php for($i = 0; $i < round($rate_formula['average_rate']); $i++): ?>
            					<i class="fa icon-star mt-auto mb-auto"></i>
            				<?php endfor; ?>	                				
                			<?php if($rate_formula['average_rate'] < 5): ?> <!------------ if ratedUser rate < 5 ---------------->
        						<?php $rest_star = 5 - round($rate_formula['average_rate']); ?>
        						<?php for($i = 0; $i < $rest_star; $i++): ?>
        							<i class="fa icon-star-empty mt-auto mb-auto"></i>
        						<?php endfor; ?>	            					
                			<?php endif; ?>	<!------------ end if ratedUser rate < 5 ---------------->
	                		<h6 class="mt-auto mb-auto mr-2">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
	                		<h6 class="mt-auto mb-auto">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
	                		<?php else: ?> <!------------ else ratedUser ---------------->
					    	<form method="post" id="ratingProduct">
					    		<input type="hidden" name="productId" value="<?php echo $product['id'];?>">
					    		<input type="radio" class="ratingBtn" id="star5" name="rate" value="5" />
						      	<label for="star5" class="text-center" title="Perfect">5 stars</label>
		  						<input type="radio" class="ratingBtn" id="star4" name="rate" value="4" />
		  						<label for="star4" class="text-center" title="V.Good">4 stars</label>
		  						<input type="radio" class="ratingBtn" id="star3" name="rate" value="3" />
		  						<label for="star3" class="text-center" title="Good">3 stars</label>
		  						<input type="radio" class="ratingBtn" id="star2" name="rate" value="2" />
		  						<label for="star2" class="text-center" title="Fair">2 stars</label>
		  						<input type="radio" class="ratingBtn" id="star1" name="rate" value="1" />
		  						<label for="star1" class="text-center" title="Bad">1 star</label>
		  					</form>
		  					<h6 class="mt-auto mb-auto mr-3">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
	                		<h6 class="mt-auto mb-auto mr-1">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
		  				<?php endif; ?> <!------------ end if ratedUser  ---------------->
		  				<?php else: ?> <!------------ else session ---------------->
		  					<?php if($ratedProduct['product_id']): ?> <!------------ if ratedProduct ---------------->
	            				<?php for($i = 0; $i < round($rate_formula['average_rate']); $i++): ?>
	            					<i class="fa icon-star mt-auto mb-auto"></i>
	            				<?php endfor; ?>	                				
	                			<?php if($rate_formula['average_rate'] < 5): ?> <!------------ if ratedProduct rate < 5 ---------------->
	        						<?php $rest_star = 5 - round($rate_formula['average_rate']); ?>
	        						<?php for($i = 0; $i < $rest_star; $i++): ?>
	        							<i class="fa icon-star-empty mt-auto mb-auto"></i>
	        						<?php endfor; ?>	            					
	                			<?php endif; ?>	<!------------ end if ratedProduct rate < 5 ---------------->
	                			<h6 class="mt-auto mb-auto mr-2">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
	                			<h6 class="mt-auto mb-auto">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
	                			<?php else: ?> <!------------ else ratedProduct ---------------->
							    	<i class="fa icon-star-empty mt-auto mb-auto"></i>
							    	<i class="fa icon-star-empty mt-auto mb-auto"></i>
							    	<i class="fa icon-star-empty mt-auto mb-auto"></i>
							    	<i class="fa icon-star-empty mt-auto mb-auto"></i>
							    	<i class="fa icon-star-empty mt-auto mb-auto"></i>
		  							<h6 class="mt-auto mb-auto mr-3">Average rating: <?php echo isset($rate_formula['average_rate'])?$rate_formula['average_rate']:0.00; ?></h6>
	                				<h6 class="mt-auto mb-auto mr-1">Total rates: <?php echo isset($rate_formula['total_rate'])?$rate_formula['total_rate']:0; ?></h6>
		  					<?php endif; ?> <!------------ end if ratedProduct ---------------->
		  			<?php endif; ?> <!------------ end if session ---------------->
				</div>
                <button type="submit" id="addcart" class="btn btn-success btn-block"><i class="fa icon-shopping-cart"></i> Add to cart</button>        	
			  </div>
			</div>
		</div>
	</div>
</div>

<?php include_once('include/footer-bar.php'); ?>
<?php include_once('include/footer.php'); ?>