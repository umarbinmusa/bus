<?php
$acc = "";
session_start();

// Handle logout
if (isset($_GET['logout'])) {
	session_destroy();
	header("Location: index.php");
	exit();
}

// If user is already logged in, redirect to appropriate page
if (isset($_SESSION['user'])) {
	$userType = $_SESSION['user']['utype'];
	
	// Redirect based on user type
	if (in_array($userType, ["Passenger", "Student", "Staff"])) {
		header("Location: buy_ticket.php");
		exit();
	}
	elseif ($userType == "Owner") {
		header("Location: my_buses.php");
		exit();
	}
	elseif ($userType == "Admin") {
		header("Location: users.php");
		exit();
	}
	else {
		// Unknown user type, log out
		session_destroy();
		header("Location: index.php");
		exit();
	}
}

// Handle signup
if (isset($_POST['signup'])) {
	require_once 'inc/database.php';
	$conn = initDB();
	
	// Sanitize inputs (basic)
	$name = $conn->real_escape_string($_POST['name']);
	$uname = $conn->real_escape_string($_POST['uname']);
	$email = $conn->real_escape_string($_POST['email']);
	$password = $conn->real_escape_string($_POST['password']);
	$gender = $conn->real_escape_string($_POST['gender']);
	$utype = $conn->real_escape_string($_POST['utype']);
	$address = $conn->real_escape_string($_POST['address']);
	$mobile = $conn->real_escape_string($_POST['mobile']);
	
	$sql = "insert into users (name, uname, email, password, gender, utype, address, mobile) values ('";
	$sql .= $name."','".$uname."','".$email."','".$password."','";
	$sql .= $gender."','".$utype."','".$address."','".$mobile."')";
	
	if ($conn->query($sql)) {
		$acc = "ok";
	}
	else {
		$acc = "Error: " . $conn->error;
	}
	$conn->close();
}

// Handle login
if (isset($_POST['login'])) {
	require_once 'inc/database.php';
	$conn = initDB();
	
	// Sanitize inputs
	$uname = $conn->real_escape_string($_POST['uname']);
	$upass = $conn->real_escape_string($_POST['upass']);
	
	$res = $conn->query("select id, utype, uname from users where uname='" . $uname . "' and password='" . $upass . "'");
	
	if ($res->num_rows == 0) {
		$acc = "Invalid Username or Password.";
	}
	else {
		$data = $res->fetch_assoc();
		$_SESSION['user'] = array(
			'id' => $data['id'], 
			'uname' => $data['uname'], 
			'utype' => $data['utype']
		);
		
		$conn->close();
		
		// Redirect based on user type
		if (in_array($data['utype'], ["Passenger", "Student", "Staff"])) {
			header("Location: buy_ticket.php");
			exit();
		}
		elseif ($data['utype'] == "Owner") {
			header("Location: my_buses.php");
			exit();
		}
		elseif ($data['utype'] == "Admin") {
			header("Location: users.php");
			exit();
		}
		else {
			$acc = "Unknown user type.";
		}
	}
	$conn->close();
}

include 'inc/basic_template.php';
t_header("Bus Ticket Booking");
t_navbar();
?>

<table width="100%">
<tr>
	<td width="70%">
		<img src="img/cover_bus.jpg" alt="Bus" height="100%" style="max-height: 600px; object-fit: cover;"/>
	</td>
	<td width="30%" style="padding: 20px;">
		<?php
			if ($acc!="") {
				if ($acc == "ok") {
					echo '<div class="alert alert-success">Account <strong>Success</strong>fully Created!<br/>Please login to continue.</div>';
				}
				else {
					echo '<div class="alert alert-danger"><strong>Error: </strong>'.$acc.'</div>';
				}
			}
		?>
		<h4 class="my-3">Create an Account</h4>
		<form action="index.php" method="post">
	    <div class="form-group row">
	      <label for="uname" class="col-sm-3 col-form-label">Username</label>
	      <div class="col-sm-9">
	        <input name="uname" type="text" class="form-control" id="inputUname" placeholder="Username" required/>
	      </div>
		  <div class="col-sm-3"></div>
		  <div class="col-sm-9" id="infoUname"></div>
	    </div>
	    
	    <div class="form-group row">
	      <label for="name" class="col-sm-3 col-form-label">Name</label>
	      <div class="col-sm-9">
	        <input name="name" type="text" class="form-control" id="inputName" placeholder="Full Name" required/>
	      </div>
		  <div class="col-sm-3"></div>
		  <div class="col-sm-9" id="infoName"></div>
	    </div>
	    
	    <div class="form-group row">
	      <label for="email" class="col-sm-3 col-form-label">Email</label>
	      <div class="col-sm-9">
	        <input name="email" type="email" class="form-control" id="inputEmail" placeholder="Email" required/>
	      </div>
		  <div class="col-sm-3"></div>
		  <div class="col-sm-9" id="infoEmail"></div>
	    </div>
	    
	    <div class="form-group row">
	      <label for="upass" class="col-sm-3 col-form-label">Password</label>
	      <div class="col-sm-9">
	        <input name="password" type="password" class="form-control" id="inputPassword" placeholder="Password" required>
	      </div>
		  <div class="col-sm-3"></div>
		  <div class="col-sm-9" id="infoPass"></div>
	    </div>
	    
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-3" for="gender">Gender</label>
	      <div class="col-sm-9">
	        <input class="form-check-input" type="radio" name="gender" id="radioMale" value="Male" checked> Male &nbsp;
            <input class="form-check-input" type="radio" name="gender" id="radioFemale" value="Female"> Female
	      </div>
	    </div>
	    
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-3" for="utype">User Type</label>
	      <div class="col-sm-9">
	        <input class="form-check-input" type="radio" name="utype" id="radioPass" value="Passenger" checked> Passenger<br/>
            <input class="form-check-input" type="radio" name="utype" id="radioStudent" value="Student"> Student (10% Discount)<br/>
            <input class="form-check-input" type="radio" name="utype" id="radioStaff" value="Staff"> Staff (5% Discount)<br/>
            <input class="form-check-input" type="radio" name="utype" id="radioBO" value="Owner"> Bus Owner
	      </div>
	    </div>
	    
	    <div class="form-group row">
	      <label for="address" class="col-sm-3 col-form-label">Address</label>
	      <div class="col-sm-9">
	        <input name="address" type="text" class="form-control" id="inputAddress" placeholder="Address" />
	      </div>
	    </div>

		<div class="form-group row">
	      <label for="mobile" class="col-sm-3 col-form-label">Mobile</label>
	      <div class="col-sm-9 input-group">
	      	<span class="input-group-addon">+880</span>
	        <input name="mobile" type="text" class="form-control" id="inputMobile" placeholder="Mobile No." required/>
	      </div>
		  <div class="col-sm-3"></div>
		  <div class="col-sm-9" id="infoMobile"></div>
	    </div>
	    
	    <div class="form-group row">
	      <div class="col-sm-3"></div>
	      <div class="col-sm-9">
	        <button type="submit" class="btn btn-primary" name="signup">Sign Up</button>
	      </div>
	    </div>
	    
		<script>
		$(document).ready(function() {
			$("#inputUname").keyup(function() {
				if ($(this).val().length >= 3) {
					$.ajax({
						url: "inc/ajax.php?type=username&q=" + $(this).val(),
						success: function(result) {
							$("#infoUname").html(result);
						}
					});
				}
			});
			
			$("#inputName").keyup(function() {
				if ( $(this).val().match('^[a-zA-Z ]{3,50}$') ) {
					$("#infoName").html('<small class="text-success">✓</small>');
				}
				else {
					$("#infoName").html('<small class="text-danger">Invalid Name</small>');
				}
			});
			
			$("#inputEmail").keyup(function() {
				if ($(this).val().length >= 5) {
					$.ajax({
						url: "inc/ajax.php?type=email&q=" + $(this).val(),
						success: function(result) {
							$("#infoEmail").html(result);
						}
					});
				}
			});
			
			$("#inputPassword").keyup(function() {
				if ($(this).val().length >= 6) {
					$("#infoPass").html('<small class="text-success">✓ Strong</small>');
				}
				else {
					$("#infoPass").html('<small class="text-danger">Weak Password (min 6 chars)</small>');
				}
			});
			
			$("#inputMobile").keyup(function() {
				if ( $(this).val().match('^[0-9]{10}$') ) {
					$("#infoMobile").html('<small class="text-success">✓</small>');
				}
				else {
					$("#infoMobile").html('<small class="text-danger">Must be 10 digits</small>');
				}
			});
		});
		</script>
	  </form>
	</td>
</tr>
</table>

<?php
t_footer();
?>