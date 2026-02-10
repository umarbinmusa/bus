<?php
$acc = "";
session_start();

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}
elseif (isset($_SESSION['user'])) {
    if ($_SESSION['user']['utype'] == "Passenger") {
        header("Location: buy_ticket.php");
        exit();
    }
    elseif ($_SESSION['user']['utype'] == "Owner") {
        header("Location: my_buses.php");
        exit();
    }
    elseif ($_SESSION['user']['utype'] == "Admin") {
        header("Location: users.php");
        exit();
    }
    else {
        session_destroy();
        header("Location: index.php");
        exit();
    }
}
elseif (isset($_POST['signup'])) {
    require_once 'inc/database.php';
    $conn = initDB();

    // Get gender
    $gender = 'Male';
    if (isset($_POST['gender'])) {
        if ($_POST['gender'] == '2') $gender = 'Female';
        else if ($_POST['gender'] == '3') $gender = 'Other';
        else $gender = 'Male';
    }

    // Get utype
    $utype = 'Passenger';
    if (isset($_POST['utype'])) {
        if ($_POST['utype'] == '2') $utype = 'Owner';
        else $utype = 'Passenger';
    }

    // Get passenger category
    $passenger_category = 'NULL';
    if ($utype == 'Passenger' && isset($_POST['passenger_category'])) {
        $passenger_category = "'" . $conn->real_escape_string($_POST['passenger_category']) . "'";
    }

    $name = $conn->real_escape_string($_POST['name']);
    $uname = $conn->real_escape_string($_POST['uname']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = $conn->real_escape_string($_POST['password']);
    $address = $conn->real_escape_string($_POST['address']);
    $mobile = $conn->real_escape_string($_POST['mobile']);

    $sql = "INSERT INTO users (name, uname, email, password, gender, utype, passenger_category, address, mobile) VALUES (";
    $sql .= "'$name','$uname','$email','$password','$gender','$utype',$passenger_category,'$address','$mobile')";

    if ($conn->query($sql)) {
        $acc = "ok";
    }
    else {
        $acc = $conn->error;
    }
    $conn->close();
}
elseif (isset($_POST['login'])) {
    require_once 'inc/database.php';
    $conn = initDB();
    $uname = $conn->real_escape_string($_POST['uname']);
    $upass = $conn->real_escape_string($_POST['upass']);

    // Check if passenger_category column exists, use safe query
    $res = $conn->query("SELECT id, utype FROM users WHERE uname='$uname' AND password='$upass'");

    if (!$res) {
        $acc = "Query Error: " . $conn->error;
    }
    elseif ($res->num_rows == 0) {
        $acc = "Invalid Username or Password.";
    }
    else {
        $data = $res->fetch_assoc();
        $user_id = $data['id'];

        // Get passenger_category separately (in case column doesn't exist)
        $passenger_category = '';
        $cat_res = $conn->query("SELECT passenger_category FROM users WHERE id = $user_id");
        if ($cat_res && $cat_res->num_rows > 0) {
            $cat_data = $cat_res->fetch_assoc();
            $passenger_category = $cat_data['passenger_category'];
        }

        $_SESSION['user'] = array(
            'id' => $user_id,
            'uname' => $_POST['uname'],
            'utype' => $data['utype'],
            'passenger_category' => $passenger_category
        );
        header("Location: index.php");
        exit();
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
        <img src="img/cover_bus.jpg" alt="Bus" height="100%"/>
    </td>
    <td width="30%">
        <?php
        if ($acc != "") {
            if ($acc == "ok") {
                echo '<div class="alert alert-success">Account <strong>Successfully</strong> Created!</div>';
            }
            else {
                echo '<div class="alert alert-danger"><strong>Error: </strong>'.$acc.'</div>';
            }
        }
        ?>
        <h4 class="my-3">Create an Account</h4>
        <form action="index.php" method="post">
        <div class="form-group row">
          <label for="uname" class="col-sm-2 col-form-label">Username</label>
          <div class="col-sm-7">
            <input name="uname" type="text" class="form-control" id="inputUname" placeholder="Username"/>
          </div>
          <div class="col-sm-2" id="infoUname"></div>
        </div>
        <div class="form-group row">
          <label for="name" class="col-sm-2 col-form-label">Name</label>
          <div class="col-sm-7">
            <input name="name" type="text" class="form-control" id="inputName" placeholder="Full Name"/>
          </div>
          <div class="col-sm-2" id="infoName"></div>
        </div>
        <div class="form-group row">
          <label for="email" class="col-sm-2 col-form-label">Email</label>
          <div class="col-sm-7">
            <input name="email" type="text" class="form-control" id="inputEmail" placeholder="Email"/>
          </div>
          <div class="col-sm-2" id="infoEmail"></div>
        </div>
        <div class="form-group row">
          <label for="upass" class="col-sm-2 col-form-label">Password</label>
          <div class="col-sm-7">
            <input name="password" type="password" class="form-control" id="inputPassword" placeholder="Password">
          </div>
          <div class="col-sm-2" id="infoPass"></div>
        </div>
        <div class="form-group row">
          <label class="col-form-legend col-sm-2" for="gender">Gender</label>
          <div class="col-sm-7 px-5">
            <input class="form-check-input" type="radio" name="gender" id="radioMale" value="1" checked> Male <br/>
            <input class="form-check-input" type="radio" name="gender" id="radioFemale" value="2"> Female
          </div>
        </div>
        <div class="form-group row">
          <label class="col-form-legend col-sm-2" for="utype">User Type</label>
          <div class="col-sm-7 px-5">
            <input class="form-check-input" type="radio" name="utype" id="radioPass" value="3" checked> Passenger <br/>
            <input class="form-check-input" type="radio" name="utype" id="radioBO" value="2"> Bus Owner
          </div>
        </div>

        <!-- Passenger Category -->
        <div class="form-group row" id="passengerCategory">
          <label class="col-form-legend col-sm-2" for="passenger_cat">Passenger Type</label>
          <div class="col-sm-7 px-5">
            <input class="form-check-input" type="radio" name="passenger_category" id="radioStudent" value="Student" checked> Student <br/>
            <input class="form-check-input" type="radio" name="passenger_category" id="radioStaff" value="Staff"> Staff
          </div>
        </div>

        <div class="form-group row">
          <label for="address" class="col-sm-2 col-form-label">Address</label>
          <div class="col-sm-7">
            <input name="address" type="text" class="form-control" id="inputAddress" placeholder="Address" />
          </div>
          <div class="col-sm-2" id="infoAddress"></div>
        </div>
        <div class="form-group row">
          <label for="mobile" class="col-sm-2 col-form-label">Mobile</label>
          <div class="col-sm-7 input-group">
            <span class="input-group-addon">+880</span>
            <input name="mobile" type="text" class="form-control" id="inputMobile" placeholder="Mobile No."/>
          </div>
          <div class="col-sm-2" id="infoMobile"></div>
        </div>
        <div class="form-group row">
          <div class="offset-sm-2 col-sm-10">
            <button type="submit" class="btn btn-primary" name="signup">Sign Up</button>
          </div>
        </div>
        <script>
        // Show/hide passenger category
        $('input[name="utype"]').change(function() {
            if ($(this).val() === '3') {
                $('#passengerCategory').show();
            } else {
                $('#passengerCategory').hide();
            }
        });

        $("#inputUname").keyup(function() {
            $.ajax({
                url: "inc/ajax.php?type=username&q=" + $(this).val(),
                success: function(result) {
                    $("#infoUname").html(result);
                }
            });
        });
        $("#inputName").keyup(function() {
            if ($(this).val().match('^[a-zA-Z ]{3,25}$')) {
                $("#infoName").html('');
            } else {
                $("#infoName").html('<span class="text-danger">Invalid Name</span>');
            }
        });
        $("#inputEmail").keyup(function() {
            $.ajax({
                url: "inc/ajax.php?type=email&q=" + $(this).val(),
                success: function(result) {
                    $("#infoEmail").html(result);
                }
            });
        });
        $("#inputPassword").keyup(function() {
            if ($(this).val().length >= 6) {
                $("#infoPass").html('');
            } else {
                $("#infoPass").html('<span class="text-danger">Weak Password</span>');
            }
        });
        $("#inputMobile").keyup(function() {
            if ($(this).val().match('^[0-9]{10,10}$')) {
                $("#infoMobile").html('');
            } else {
                $("#infoMobile").html('<span class="text-danger">Invalid Number</span>');
            }
        });
        </script>
        </form>
    </td>
</tr>
</table>
<?php
t_footer();
?>