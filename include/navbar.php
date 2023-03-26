<?php include_once('user.php'); ?>
<?php 
  $user = new User();
  $user->logout();
?>
  
<nav class="navbar navbar-icon-top navbar-expand-lg navbar-default bg-default">
  <a class="navbar-brand" href="/cartopia/"><img src="/cartopia/images/cartopia_logo.png" alt="cartopia-logo"></a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
  </button>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav float-left">
        <li class="nav-item active">
          <a class="nav-link" href="/cartopia/home">
            <i class="fa icon-home"></i>
            Home
            <span class="sr-only">(current)</span>
            </a>
        </li>
         <li class="nav-item">
          <a class="nav-link" href="/cartopia/contactus">
            <i class="fa icon-phone-sign"></i>
            Contact us
          </a>
        </li>
         <li class="nav-item">
          <a class="nav-link" href="/cartopia/myaccount">
            <i class="fa icon-user"></i>
            My Account
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/cartopia/mycart">
            <i class="fa icon-shopping-cart"></i>  
            My Cart <p class="badge badge-danger mb-0" id="product_in_cart"></p>          
          </a>
        </li> 
      <div class="float-right login-register-btn">
        <li>
          <?php if(isset($_SESSION['user'])):?>
              <div class="dropdown">
                <button type="button" class="btn btn-primary btn-block dropdown-toggle dropdown-mycart" data-toggle="dropdown">
                  <i class="fa icon-user"></i>
                  <?php echo $_SESSION['user']['username']; ?>
                </button>
                <div class="dropdown-menu">
                  <form method="post" class="dropdown-item">
                    <button class="btn btn-link" type="submit" name="logout">
                      <i class="fa icon-signout"></i> Logout</button>
                  </form>            
                </div>
              </div>            
              <p class="text-success pt-2">
                <span class="text-dark">Your balance:</span>
                $<?php echo $user->getMyBalance($_SESSION['user']['id']);?>
              </p>
            <?php else: ?>
              <a class="btn btn-primary" href="/cartopia/login.php">Login</a> <a class="btn btn-success" href="/cartopia/register.php">Register</a>
          <?php endif; ?>              
        </li>
      </div>  
    </ul>
  </div>
</nav>