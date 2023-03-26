<?php 
  session_start();
  include_once('include/header.php'); 
  include_once('user.php');
  include_once('user_controller.php');
?>

<div class="bg-default bg-verticaly-height">
  <div class="container">
    <div class="row">
      <div class="mt-5">
        <a href="/cartopia/home" class="btn btn-secondary"><i class="fa icon-double-angle-left"></i> Home</a>
      </div>
      <div class="col-md-6 offset-md-3">
        <div class="card card-signin">
          <div class="card-header bg-primary">
            <h3 class="text-light">Signin</h3>
          </div>
          <div class="card-body">
            <?php if(isset($login)): ?>
              <?php foreach($login as $key => $errorMsg):?>
                <?php if($key == 'email_password_invalid' || $key == 'email_empty' || $key == 'password_empty'): ?>
                  <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="icon-exclamation-sign"></i> <strong><?php echo $errorMsg; ?></strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                <?php endif; ?>
              <?php endforeach; ?>
            <?php endif; ?>
            <form role="form" method="post">
              <div class="form-group">
                <label for="email">email*</label>
                <input type="email" id="email" name="email" class="form-control">
              </div>
              <div class="form-group">
                <label for="password">Password*</label>
                <input type="password" id="password" class="form-control" name="password">
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-block btn-lg btn-primary" name="login">Login</button>
              </div>
            </form>
            <p>Don't have account <a href="register.php" class="text-success">Register</a></p>
          </div>
        </div>
      </div>  
    </div>  
  </div>
</div>    
