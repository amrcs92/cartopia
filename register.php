<?php 
  include_once('include/header.php');
  include_once('user.php'); 
  include_once('user_controller.php');
?>

<div class="bg-default">
  <div class="container">
    <div class="row">
      <div class="mt-5">
        <a href="/cartopia/home" class="btn btn-secondary"><i class="fa icon-double-angle-left"></i> Home</a>
      </div>
      <div class="col-md-8 offset-md-2 mt-5 mb-5">
        <div class="login-panel card card-default">
          <div class="card-header bg-success">
            <h3 class="text-light">Register</h3>
          </div>
          <div class="card-body">
            <?php if(isset($register)): ?>
              <?php foreach($register as $key => $errorMsg):?>
                <?php if($key == 'required_fields'): ?>
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
                <label for="username">Username*</label>
                <input type="text" name="username" id="username" class="form-control">
              </div>
              <div class="form-group">
                <label for="email">email*</label>
                <input type="email" id="email" name="email" class="form-control">
              </div>
              <div class="form-group">
                <label for="password">Password*</label>
                <input type="password" id="password" class="form-control" name="password">
              </div>
              <div class="form-group">
                <label id="phone">Phone</label>
                <input type="number" id="phone" name="phone" class="form-control">
              </div>
              <div class="form-group">
                <label id="address">Address</label>
                <input type="text" id="address" name="address" class="form-control">
              </div>
              <div class="form-group">
                <button type="submit" class="btn btn-block btn-lg btn-success" name="register">Register</button>
              </div>
            </form>
            <p>Already a member? <a href="login.php">Sign in</a></p>
          </div>  
        </div>
      </div>
    </div>
  </div>
</div>
<?php include_once('include/footer.php'); ?>  