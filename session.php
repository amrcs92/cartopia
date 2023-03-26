<?php 
	include_once('user.php');

	class Session
	{
		public function sessionExist()
		{
			if(isset($_SESSION['user']))
			{
				header('home.php');
			}
		}

		public function emptySession()
		{
			if(!isset($_SESSION['user']))
			{
				header('login.php');
			}
		}

		public function showSession()
		{
			
			if(isset($_SESSION['user']))
			{
				echo "
				<h5> Welcome, ". $_SESSION['user']['username']."</h5>".
				"<a href='myaccount.php' class='inlineBlock'>Profile</a> | 
				<form role='form' method='post' class='inlineBlock'>
					<input type='hidden' name='logout'>
					<button type='submit' class='btn btn-link'>Logout</button>
				</form>";			
			} 
			else
			{
				echo"
					<h5>Welcome, Guest</h5>
					<a href='register.php'>Register</a> | <a href='login.php'>Login</a>
				";	
			}
		}
	}