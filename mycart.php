<?php 
	session_start();
	include_once('cache_remover.php');
	include_once('include/header.php'); 
	include_once('include/navbar.php');
?>

<div class="container mycart-container">
	<div class="row">
		
		<table id="cart" class="table table-hover table-condensed">
			<thead>
				<tr>
					<th style="width:10%">Product</th>
					<th style="width:45%">Description</th>
					<th style="width:10%">Price</th>
					<th style="width:5%">Quantity</th>
					<th style="width:20%" class="text-center">Subtotal</th>
					<th style="width:10%" class="text-center">Action</th>
				</tr>
			</thead>
			<tbody>							
			</tbody>
			<tfoot>
			</tfoot>
		</table>
	</div>	
</div>

<?php 
	include_once('include/footer-bar.php');
	include_once('include/footer.php');
?>