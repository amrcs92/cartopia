$(document).ready(function(){

    // product details page increment/decrement quantity input onclick +/- ---------
    var count = 1;
    var buttonPlus  = $(".btn-plus");
    var buttonMinus = $(".btn-minus");

    var incrementValue = buttonPlus.click(function() {
        var quantity = $(this).parent(".button-container").find("#product_quantity");
        quantity.val(Number(quantity.val())+1);
        count++;
        var inputvalue = $('#product_quantity').attr("value", count);
    });

    var decrementValue = buttonMinus.click(function() {
        var quantity = $(this).parent(".button-container").find("#product_quantity");
        var amount = Number(quantity.val());
        if (amount > 1) {
            quantity.val(amount-1);
            count--;
            $('#product_quantity').attr("value", count)
        }
    });

    // end product details page increment/decrement -----------------------

    // product details page quantity input (disable keyboard and backspace) ------------

    $('#product_quantity').keypress(function(event) {
        event.preventDefault();    
    });
    $('#product_quantity').keydown(function(e){
        if(e.which === 8){
            e.preventDefault();
            return false;
        }
    });

    // end product details page quantity input -------------------

    if(!JSON.parse(localStorage.getItem('cart'))){
        var cart = {};
        cart.products = [];
        localStorage.setItem('cart', JSON.stringify(cart));        
    }
       
    var productCart = JSON.parse(localStorage.getItem('cart'));
    var subtotal = 0;
    var prods = productCart.products;
    for(var i = 0; i < prods.length; i++){
        var price_no_dollar = String(prods[i]['price']).replace("$","");
        subtotal = prods[i].quantity * parseFloat(price_no_dollar);
        subtotal = "$" + subtotal.toFixed(2);
        var body_tr = `<tr data-tr="product-row" class="productRow" data-product_id='${prods[i]['pid']}' data-product_name='${prods[i]['name']}' 
        data-product_desc='${prods[i]['description']}' data-product_price='${prods[i]['price']}' data-product_img='${prods[i]['image']}' 
        data-product_quantity='${prods[i]['quantity']}'>
            <td data-th="Product">
                
                <div class="col-sm-4 hidden-xs">
                    <img src="${prods[i].image}" width="140" height="140" alt="..." class="img-responsive"/>
                </div>
            </td>    
            <td data-th="Description" id="product_description">    
                <div class="col-sm-8">
                    <h4 class="nomargin">${prods[i].name}</h4>
                    <p>${prods[i].description}</p>
                </div>                
            </td>
            <td data-th="Price"><div class="col-sm-4">${prods[i].price}</div></td>
            <td data-th="Quantity" class="quantity-input">
                <input type="number" class="form-control text-center product_quant" onkeydown="return false" value="${prods[i].quantity}">
            </td>
            <td data-th="Subtotal" class="text-center">${subtotal}</td>
            <td class="actions" data-th="Actions">
                <button class="btn btn-info btn-sm updateProduct" id="updateProduct"><i class="fa icon-refresh"></i></button>
                <button class="btn btn-danger btn-sm removeProduct" id="removeProduct"><i class="fa icon-trash"></i></button>                                
            </td>
            </tr>`;

        $('tbody').append(body_tr);
    }

    // calculate total cost of products in cart

    var prods = productCart.products;
        
    var total = 0;
    var product_count = 0;
    for(var i = 0; i < prods.length; i++){
        var price_no_dollar = String(prods[i]['price']).replace("$","");
        total += prods[i].quantity * parseFloat(price_no_dollar);
        product_count += prods[i].quantity;
    }

    var total_cost = "$" + total.toFixed(2);
        
    var footer_tr = `<tr>
        <td colspan="1" id="continueShopping">
            <a href="/cartopia/home.php" class="btn btn-warning">
            <i class="fa icon-angle-left"></i> Continue Shopping</a>
        </td>
        <td colspan="1">Shipping Method:
        
            <form method="post" enctype="multipart/form-data" class="shipping_method">
                <select name="shipping_method" class="form-control">
                    <option value="">Select Shipping</option>
                    <option value ="1">Pickup $0.00</option>
                    <option value="2">UPS $5.00</option>
                </select>
            </form>    
        </td>
        <td colspan="2"><strong>Total Quantity: ${product_count}</strong></td>
        <td colspan="1" class="text-center"><strong>Total ${total_cost}</strong></td>
        <td colspan="1">
            <button type="button" id="checkoutBtn" class="btn btn-success btn-block" name="checkout">Checkout 
            <i class="fa icon-angle-right"></i></button>
        </td>
    </tr>`;

    $('tfoot').append(footer_tr);

    $('nav #product_in_cart').append(product_count);


    if(product_count.length == 0){
        $('nav #product_in_cart').append('0');
    }

    if((body_tr == null) || (footer_tr == null)){
        $('table#cart').hide();
        $('.mycart-container').html(`
            <table id="cart" class="table borderless">
                <thead>
                    <tr class="text-center">
                        <th style="padding-bottom:0;">
                            <i class="icon-shopping-cart icon-5"></i>
                            <h2>Your shopping cart is empty.</h2>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="text-center">                         
                        <td>
                            <h5>Looks like you have no items in your shopping cart.</h5>
                        
                            <h6>Do you have account ? Login if you want to checkout Or Continue Shopping</h6>
                            <a href="/cartopia/home.php" class="btn btn-success">Continue Shopping</a>
                            <a href="/cartopia/login.php" class="btn btn-primary"><i class="fa icon-signin"></i> Login</a>
                        </td>
                    </tr>    
                </tbody>
                <tfoot>
                </tfoot>
            </table>
        `);
    }

    // add to cart button home page ------------------

    function addToCart(product){
        if(localStorage && localStorage.getItem('cart')){
            var cart = JSON.parse(localStorage.getItem('cart'));
            var flag = 1;
            for(var i = 0; i < cart.products.length; i++){
                if(cart.products[i]['pid'] == product['pid']){
                    flag = 0;
                    cart.products[i]['quantity']++;
                }
            }
            if(flag){
                cart.products.push(product);
            }

            localStorage.setItem('cart', JSON.stringify(cart));
        }
    }

    $(".addToCartBtn").on('click', function(){

        var cardBody = $(this).parent().parent();
        
        var quantity = 1;

        var product = {};
        product.pid             =  $(cardBody).data('product_id');
        product.name            =  $(cardBody).data('product_name');
        product.description     =  $(cardBody).data('product_desc');
        product.price           =  $(cardBody).data('product_price');
        product.image           =  $(cardBody).data('product_img');
        product.quantity        =  quantity;
        product_count++;
        addToCart(product);
        $('nav #product_in_cart').html(product_count);
    });

    // end add to cart button ----------------------------

    // add product quantity button product details page-------------------

    function addProductQuantityToCart(product){
        if(localStorage && localStorage.getItem('cart')){
            var cart = JSON.parse(localStorage.getItem('cart'));
            var flag = 1;
            for(var i = 0; i < cart.products.length; i++){
                if(cart.products[i]['pid'] == product['pid']){
                    flag = 0;
                    product['quantity'] += cart.products[i]['quantity'];
                    cart.products[i]['quantity'] = product['quantity'];
                    localStorage.setItem('cart', JSON.stringify(cart.products[i]['quantity']));
                }
            }

            if(flag){
                cart.products.push(product);
            }
            localStorage.setItem('cart', JSON.stringify(cart));
        }
    }

     $("#addcart").on('click', function(){
        var cardBody            = $(this).parent();
        var quantity            = cardBody.find('#product_quantity').val();
        console.log('quantity: ', quantity);
        var product             = {};
        product.pid             = $(cardBody).data('product_id');
        product.name            =  $(cardBody).data('product_name');
        product.description     =  $(cardBody).data('product_desc');
        product.price           =  $(cardBody).data('product_price');
        product.image           =  $(cardBody).data('product_img');
        product.quantity        = Number(quantity);
        
        var quantity_no = Number(quantity);
        product_count += quantity_no;
        $('nav #product_in_cart').html(product_count);
        addProductQuantityToCart(product);     
    });

    // end add product quantity button -----------------------

    // checkout button (mycart page)-----------------

    function checkoutEmptyCart(){
        $('table#cart').hide();
        var emptyCart = `
        <table id="cart" class="table borderless">
            <thead>
                <tr class="text-center">
                    <th style="padding-bottom:0;">
                        <i class="icon-shopping-cart icon-5"></i>
                        <h2>Your shopping cart is empty.</h2>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr class="text-center">                         
                    <td>
                        <h5>Looks like you have no items in your shopping cart.</h5>
                    
                        <h6>Do you have account ? Login if you want to checkout Or Continue Shopping</h6>
                        <a href="/cartopia/home.php" class="btn btn-success">Continue Shopping</a>
                        <a href="/cartopia/login.php" class="btn btn-primary"><i class="fa icon-signin"></i> Login</a>
                    </td>
                </tr>    
            </tbody>
            <tfoot>
            </tfoot>
        </table>`;
        $('.mycart-container').html(emptyCart);
        
        $("#product_in_cart").text('0');
        window.localStorage.removeItem('cart');
        window.location.reload();
    }

    $('.mycart-container').on('click', '#checkoutBtn', function(event){
        event.preventDefault();

        var shippingMethod = $('select[name="shipping_method"]').val(),
            products       = JSON.parse(localStorage.getItem('cart')).products,
            totalPrice     = 0;

        if (shippingMethod != 1 && shippingMethod == 2) {
            totalPrice += 5;
        }

        products.forEach(element => {
            var element_no_dollar = String(element['price']).replace('$', '');
            var quantity = element['quantity'];
            var price = parseFloat(element_no_dollar);
            totalPrice += price * quantity;
        });
        
        if(shippingMethod < 0 || shippingMethod == ''){
            alert('Please Select shipping method !!');
        } else{
            var checkoutSuccess = `<div class="col-md-12">
            <div class="alert alert-success alert-dismissible fade show mt-2" role="alert">
              <i class="icon-ok"></i> <strong>Congratulation!!</strong> Your order has been placed
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
        </div>  `;
                       
            $.ajax({
                method : "POST",
                url: "http://localhost/cartopia/ajax.php",
                data: {
                    shipping: shippingMethod,
                    products: products,
                    total_price: totalPrice,
                    action: "checkout"
                },

                success: function(response) {
                    if(response == 'loggedin'){
                        console.log(response);
                        
                        $(".mycart-container .row").prepend(checkoutSuccess);
                        setTimeout(checkoutEmptyCart, 5000);                        

                    } else if(response == 'low balance'){
                        alert('add more credit to buy');
                    }
                    else{
                        window.location.href = 'http://localhost/cartopia/login.php';
                    }                
                },
                error: function(errors) {
                    console.log('errors', errors);
                }
            });                        
        }

    });

    // end checkout button ---------------------

    // update cart button mycart page ----------------------

    function updateProductCart(product){
        if(localStorage && localStorage.getItem('cart')){
            var cart = JSON.parse(localStorage.getItem('cart'));
            for(var i = 0; i < cart.products.length; i++){
                if(cart.products[i]['pid'] == product['pid']){
                    cart.products[i]['quantity'] = product['quantity'];
                    localStorage.setItem('cart', JSON.stringify(cart.products[i]['quantity']));
                }
            }
        }    
        
        localStorage.setItem('cart', JSON.stringify(cart));
    }

    $('.mycart-container').on('click', '.updateProduct', function(event){
        var closest_quantity    = $(this).parents('.productRow').find('.product_quant').val();
        var closest_pid         = $(this).parents('.productRow').data('product_id');
        var product             = {};
        product.pid             = closest_pid;
        product.quantity        = Number(closest_quantity);

        var total_quantity = 0;
        $('input').each(function(index){ 
            var product_quantities = $(this).parents('.productRow').find('.product_quant').val();
            var product_quantities_no = Number(product_quantities);
            total_quantity += product_quantities_no;
        });

        $('nav #product_in_cart').html(total_quantity);
        updateProductCart(product);
        window.location.reload();
        event.preventDefault();
    });

    // end update cart -------------------------

    // remove product from cart (mycart page)------------

    function removeProduct(mycart) {
        if(localStorage && localStorage.getItem('cart')){
            var cart = JSON.parse(localStorage.getItem('cart'));
            // console.log(mycart.product);
           
            for(var i = 0; i < cart.products.length; i++){
             
                if(cart.products[i]['pid'] == mycart.product['pid']){

                    cart.products.splice(i, 1);
                    localStorage.setItem('cart', JSON.stringify(cart));
                }
            }
        }
    }

    $('.mycart-container').on('click', '.removeProduct', function(event){
        var product_row = $(this).parents('.productRow');
        var mycart = {
            "product": {
                pid: product_row.data('product_id')
            }              
        };

        removeProduct(mycart);
        product_row.remove();
        window.location.reload();
    });

    // end remove product from cart ----------------------------------

    // $('.viewRatingBtn').attr('disabled', true);

    // rate product product details page --------------------

    $(".ratingBtn").on('change', function(event){
        debugger;
        event.preventDefault();
        var rating = $("input[name='rate']:checked").val();
        var product_id = $(this).parents('.card-body').data('product_id');
        $.ajax({
            type: "POST",
            url: "http://localhost/cartopia/ajax.php",
            data: {
                productId: product_id,
                rate: rating,
                rateProduct: "rating"
            },
        
            success: function(response){
                if(response == "loggedin"){
                    console.log(response);
                    window.location.reload();
                }
                // console.log(rating);
            }, error:function(error){
                console.log(error);
            }
        });
    });

    // end rate product product details page --------------------

});