<?php
session_start();
include 'inc/basic_template.php';
t_header("Bus Ticket Booking");

$ok = "";
require_once 'inc/database.php';


if (isset($_POST['edit'])) {
	$conn = initDB();
	
	// Prepare passenger category update
	$passenger_category_sql = '';
	if($_SESSION['user']['utype'] == 'Passenger' && isset($_POST['passenger_category'])) {
		$passenger_category_sql = ", passenger_category='".$_POST['passenger_category']."'";
	}
	
	$sql = "update users set name='".$_POST['name']."', email='".$_POST['email']."', gender='".$_POST['gender']."', address='".$_POST['address'];
	$sql .= "', mobile='".$_POST['mobile']."'".((!isset($_POST['password']) || $_POST['password'] == "") ? " " : ", password='".$_POST['password']."' ");
	$sql .= $passenger_category_sql;
	$sql .= " where id=".$_SESSION['user']['id'];
	
	if ($conn->query($sql)) {
		$ok = "ok";
		// Update session if passenger category changed
		if($_SESSION['user']['utype'] == 'Passenger' && isset($_POST['passenger_category'])) {
			$_SESSION['user']['passenger_category'] = $_POST['passenger_category'];
		}
	}
	else {
		$ok = $sql . "<br/>" .$conn->error;
	}
	$conn->close();
}

$conn = initDB();
$res = $conn->query("select * from users where id=".$_SESSION['user']['id']);
$userinfo = $res->fetch_assoc();
$conn->close();

t_login_nav();
if ($_SESSION['user']['utype'] == "Admin")
	t_admin_sidebar();
elseif ($_SESSION['user']['utype'] == "Owner")
	t_owner_sidebar();
else
	t_sidebar();
?>
<div class="modal" id="editModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Edit Profile</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="post" action="profile.php" id="editForm">
      <div class="modal-body">
        <p>
        <div class="form-group row">
	      <label for="name" class="col-sm-2 col-form-label">Name</label>
	      <div class="col-sm-7">
	        <input name="name" type="text" class="form-control" id="inputName" value="<?php echo $userinfo['name'];?>" />
	      </div>
		  <div class="col-sm-2" id="infoName"></div>
	    </div>
	    <div class="form-group row">
	      <label for="email" class="col-sm-2 col-form-label">Email</label>
	      <div class="col-sm-7">
	        <input name="email" type="text" class="form-control" id="inputEmail" value="<?php echo $userinfo['email'];?>"/>
	      </div>
		  <div class="col-sm-2" id="infoEmail"></div>
	    </div>
	    <div class="form-group row">
	      <label for="upass" class="col-sm-2 col-form-label">New Password</label>
	      <div class="col-sm-7">
	        <input name="password" type="password" class="form-control" id="inputPassword" placeholder="Keep Empty for Old Password">
	      </div>
		  <div class="col-sm-2" id="infoPass"></div>
	    </div>
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-2" for="gender">Gender</label>
	      <div class="col-sm-7 px-5">
	        <input class="form-check-input" type="radio" name="gender" id="radioMale" value="Male" <?php echo ($userinfo['gender'] == 'Male') ? 'checked' : '';?>> Male <br/>
            <input class="form-check-input" type="radio" name="gender" id="radioFemale" value="Female"<?php echo ($userinfo['gender'] == 'Female') ? 'checked' : '';?>> Female
	      </div>
	    </div>
	    
	    <?php if($_SESSION['user']['utype'] == 'Passenger'): ?>
	    <!-- NEW: Passenger Category in Edit Form -->
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-2" for="passenger_cat">Passenger Type</label>
	      <div class="col-sm-7 px-5">
	        <input class="form-check-input" type="radio" name="passenger_category" value="Student" 
	          <?php echo ($userinfo['passenger_category'] == 'Student') ? 'checked' : '';?>> Student <br/>
            <input class="form-check-input" type="radio" name="passenger_category" value="Staff" 
              <?php echo ($userinfo['passenger_category'] == 'Staff') ? 'checked' : '';?>> Staff
	      </div>
	    </div>
	    <?php endif; ?>
	    
	    <div class="form-group row">
	      <label for="address" class="col-sm-2 col-form-label">Address</label>
	      <div class="col-sm-7">
	        <input name="address" type="text" class="form-control" id="inputAddress" value="<?php echo $userinfo['address'];?>" />
	      </div>
		  <div class="col-sm-2" id="infoAddress"></div>
	    </div>

	    <script type="text/javascript">
		var autocomplete;
		function initialize() {
			autocomplete = new google.maps.places.Autocomplete(document.getElementById("inputAddress"));
			autocomplete.setComponentRestrictions({'country': 'bd'});
			google.maps.event.addListener(autocomplete, 'place_changed', function() {});
		}
	    </script>
	    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDT6virNnyW0tcOqRcxKpdKENTESrp4ojE&libraries=places&callback=initialize" async defer></script>
		<div class="form-group row">
	      <label for="mobile" class="col-sm-2 col-form-label">Mobile</label>
	      <div class="col-sm-7 input-group">
	      	<span class="input-group-addon">+880</span>
	        <input name="mobile" type="text" class="form-control" id="inputMobile" value="<?php echo $userinfo['mobile'];?>"/>
	      </div>
		  <div class="col-sm-2" id="infoMobile"></div>
	    </div>

        </p>
      </div>
      <div class="modal-footer">
        <input type="submit" id="submitButton" class="btn btn-primary" value="Submit" name="edit"/>
       		<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
       		<script async>
			$("#inputName").keyup(function() {
				if ( $(this).val().match('^[a-zA-Z ]{3,16}$') ) {
					$("#infoName").html(' ');
				}
				else {
					$("#infoName").html('<span class="text-danger">Invalid Name</span>');
				}
			});
			$("#inputEmail").keyup(function() {
				var pattern = /^\b[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}\b$/i;
				if (pattern.test($(this).val())) {
					$("#infoEmail").html(' ');
				}
				else {
					$("#infoEmail").html('<span class="text-danger">Invalid Email</span>');
				}
			});
			$("#inputPassword").keyup(function() {
				if ($(this).val().length >= 6 || $(this).val().length == 0) {
					$("#infoPass").html(' ');
				}
				else {
					$("#infoPass").html('<span class="text-danger">Weak Password</span>');
				}
			});
			$("#inputAddress").keyup(function() {
				if ( $(this).val().match('^[a-zA-Z0-9 ,-]{5,120}$') ) {
					$("#infoAddress").html(' ');
				}
				else {
					$("#infoAddress").html('<span class="text-danger">Invalid Address</span>');
				}
			});
			$("#inputMobile").keyup(function() {
				if ( $(this).val().match('^[0-9]{10,10}$') ) {
					$("#infoMobile").html(' ');
				}
				else {
					$("#infoMobile").html('<span class="text-danger">Invalid Number</span>');
				}
			});
			$("#editButton").click(function() {
				$("#editModal").show();
			});
			$("button").click(function() {
				$("#editModal").hide();
			});
		</script>
      </div>
      </form>
    </div>
  </div>
</div>
<div class="container">
	<?php
		if ($ok!="") {
			if ($ok == "ok") {
				echo '<div class="alert alert-success">Account <strong>Success</strong>fully Modified!</div>';
			}
			else {
				echo '<div class="alert alert-danger"><strong>Error: </strong>'.$ok.'</div>';
			}
		}
	?>
</div>
<div class="form-group row">
	      <label for="uname" class="col-sm-2 col-form-label">Username</label>
	      <div class="col-sm-9">
	        <?php echo $_SESSION['user']['uname'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <label for="name" class="col-sm-2 col-form-label">Name</label>
	      <div class="col-sm-9">
		  <?php echo $userinfo['name'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <label for="email" class="col-sm-2 col-form-label">Email</label>
	      <div class="col-sm-9">
		  <?php echo $userinfo['email'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-2" for="gender">Gender</label>
	      <div class="col-sm-9">
		  <?php echo $userinfo['gender'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-2" for="utype">User Type</label>
	      <div class="col-sm-9">
		  <?php echo $_SESSION['user']['utype'];?>
	      </div>
	    </div>
	    
	    <?php if($_SESSION['user']['utype'] == 'Passenger'): ?>
	    <!-- NEW: Display Passenger Category -->
	    <div class="form-group row">
	      <label class="col-form-legend col-sm-2" for="passenger_cat">Passenger Type</label>
	      <div class="col-sm-9">
		  <?php echo $userinfo['passenger_category'] ? $userinfo['passenger_category'] : 'Not Set'; ?>
	      </div>
	    </div>
	    <?php endif; ?>
	    
	    <div class="form-group row">
	      <label for="address" class="col-sm-2 col-form-label">Address</label>
	      <div class="col-sm-9">
		  <?php echo $userinfo['address'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <label for="mobile" class="col-sm-2 col-form-label">Mobile</label>
	      <div class="col-sm-9 input-group">
		  +234<?php echo $userinfo['mobile'];?>
	      </div>
	    </div>
	    <div class="form-group row">
	      <div class="offset-sm-2 col-sm-10">
	        <button id="editButton" class="btn btn-primary" onclick="$('#editModal').show()">Edit Profile</button>
	      </div>
	    </div>
<br>
<?php
t_footer();
?>