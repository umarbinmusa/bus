<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['utype'], ["Passenger", "Student", "Staff"]))
  header("Location: index.php");
include 'inc/basic_template.php';
t_header("Bus Ticket Booking &mdash; Buy Tickets");
t_login_nav();
t_sidebar();

if (isset($_POST['buy'])) {
  require_once 'inc/database.php';
  $conn = initDB();
  
  if (!isset($_POST['seats']) || $_POST['seats'] == "") {
    echo '<div class="alert alert-danger"><strong>Error: </strong>No seats selected</div>';
  }
  else {
    // Simple booking - just check if seats are available
    $userId = $_SESSION['user']['id'];
    $busId = $_POST['bus_id'];
    $jdate = $_POST['jdate'];
    
    // Verify seats are still available
    $existingBookings = $conn->query("SELECT seats FROM tickets 
                                      WHERE bus_id='$busId' 
                                      AND jdate='$jdate' 
                                      AND booking_confirmed=1");
    
    $bookedSeats = array();
    while ($row = $existingBookings->fetch_assoc()) {
      $bookedSeats = array_merge($bookedSeats, unserialize($row['seats']));
    }
    
    $conflict = array_intersect($_POST['seats'], $bookedSeats);
    
    if (count($conflict) > 0) {
      echo '<div class="alert alert-danger"><strong>Error: </strong>Some seats are no longer available: ' . implode(', ', $conflict) . '</div>';
    } else {
      // Proceed with booking
      $sql = "INSERT INTO tickets (passenger_id, bus_id, jdate, seats, fare, booking_time, booking_expires, booking_confirmed) VALUES ('";
      $sql .= $_SESSION['user']['id']."','".$_POST['bus_id']."','".$_POST['jdate']."','".serialize($_POST['seats'])."','";
      $sql .= $_POST['fare']."', NOW(), DATE_ADD(NOW(), INTERVAL 5 MINUTE), 1)";

      if ($conn->query($sql)) {
        $userType = $_SESSION['user']['utype'];
        $discount = 0;
        if ($userType == 'Student') {
          $discount = 10; // 10% student discount
        } elseif ($userType == 'Staff') {
          $discount = 5; // 5% staff discount
        }
        
        $discountMessage = $discount > 0 ? " ($discount% $userType discount applied)" : "";
        
        echo '<div class="alert alert-success">
                <strong>Booking Confirmed!</strong>'.$discountMessage.'<br/>
                <a class="text-right" href="print.php?ticket='.$conn->insert_id.'">
                  <button class="btn btn-info mt-2"><i class="fa fa-print"></i> Print Ticket</button>
                </a>
              </div>';
      }
      else {
        echo '<div class="alert alert-danger"><strong>Error: </strong>'.$conn->error.'</div>';
      }
    }
  }
  $conn->close();
}
?>
<!-- Select Locations -->
<link rel="stylesheet" href="css/easy-autocomplete.min.css"/>
<link rel="stylesheet" href="css/bootstrap-datepicker.min.css"/>

<form action="" method="get">
	<div class="form-group row">
      <label for="from" class="col-sm-2 col-form-label">From</label>
      <div class="col-sm-7 well">
      	<input type="text" class="form-control" id="inputFrom" name="from" value="<?php echo (isset($_GET['from'])) ? $_GET['from'] : ''; ?>"/>
      </div>
  </div>
  <div class="form-group row">
    <label for="to" class="col-sm-2 col-form-label">To</label>
    <div class="col-sm-7">
      <input type="text" class="form-control" id="inputTo" name="to" value="<?php echo (isset($_GET['to'])) ? $_GET['to'] : ''; ?>" />
    </div>
  </div>
  <script src="js/jquery.easy-autocomplete.min.js"></script>
  <script>
    var opt = {
      url: "inc/ajax.php?type=locations",
      list: {
        match: {
          enabled: true
        }
      }
    };
    $("#inputFrom").easyAutocomplete(opt);
    $("#inputTo").easyAutocomplete(opt);
  </script>
  <div class="form-group row">
    <label for="jdate" class="col-sm-2 col-form-label">Journey Date</label>
    <div class="col-sm-7 input-group">
      <input name="jdate" class="form-control" id="inputJDate" type="text" value="<?php echo (isset($_GET['jdate'])) ? $_GET['jdate'] : ''; ?>"/>
    </div>
  </div>
  <script src="js/bootstrap-datepicker.min.js"></script>
  <script>
    $('#inputJDate').datepicker({
      format: "dd/mm/yyyy",
      weekStart: 6,
      startDate: "today",
      autoclose: true,
      todayHighlight: true
    });
  </script>
  <div class="form-group row">
    <div class="col-sm-2"></div>
    <div class="col-sm-7">
      <input type="submit" class="btn btn-info" name="submit" value="Search Buses" />
    </div>
  </div>
</form>
<div class="popup" id="seatViewer"></div>
<div class="loader text-center" id="wait"><img src="/img/bus-loader.gif" alt="Wait..."/></div>
<div class="table-con">
<div class="row">
  <div class="col-sm-1">Sl.</div>
  <div class="col-sm-3">Bus Name</div>
  <div class="col-sm-2">Dep. Time</div>
  <div class="col-sm-2">Arr. Time</div>
  <div class="col-sm-2">Available Seats</div>
  <div class="col-sm-2">Fare (৳)</div>
</div>
<?php
require_once 'inc/database.php';
$conn = initDB();
$from = isset($_GET['from']) ? $_GET['from'] : "";
$to = isset($_GET['to']) ? $_GET['to'] : "";
$res = $conn->query("select * from buses where from_loc='".$from."' and to_loc='".$to."' and approved=1");
if ($res->num_rows == 0 || !isset($_GET['jdate']) || $_GET['jdate'] == '') {
  echo '<div class="row">
    <div class="col-sm-12 text-center"><h4>No Bus Found</h4><p>Try searching with different locations or dates</p></div>
  </div>';
}
else {
  while ($row = $res->fetch_assoc()) {
    // Calculate available seats
    $busId = $row['id'];
    $jdate = $_GET['jdate'];
    $totalSeats = $row['total_seats'];
    
    $bookedSeatsQuery = $conn->query("SELECT seats FROM tickets 
                                      WHERE bus_id='$busId' 
                                      AND jdate='$jdate' 
                                      AND booking_confirmed=1");
    
    $bookedCount = 0;
    while ($seatRow = $bookedSeatsQuery->fetch_assoc()) {
      $bookedCount += count(unserialize($seatRow['seats']));
    }
    
    $availableSeats = $totalSeats - $bookedCount;
    
    // Apply user type discount
    $fare = $row['fare'];
    $userType = $_SESSION['user']['utype'];
    $originalFare = $fare;
    
    if ($userType == 'Student') {
      $fare = $fare * 0.9; // 10% discount
    } elseif ($userType == 'Staff') {
      $fare = $fare * 0.95; // 5% discount
    }
    
    echo '
    <div class="row content" data-bus-id="'.$row['id'].'">
      <div class="col-sm-1">'.$row['id'].'</div>
      <div class="col-sm-3">'.$row['bname'].' ('.$row['total_seats'].' seats)</div>
      <div class="col-sm-2">'.$row['from_time'].'</div>
      <div class="col-sm-2">'.$row['to_time'].'</div>
      <div class="col-sm-2"><strong>'.$availableSeats.'</strong> / '.$totalSeats.'</div>
      <div class="col-sm-2">';
    
    if ($userType != 'Passenger') {
      echo '<del>৳'.$originalFare.'</del> ';
    }
    echo '৳'.number_format($fare, 0);
    
    if ($userType == 'Student') {
      echo ' <span class="badge badge-success">10% Off</span>';
    } elseif ($userType == 'Staff') {
      echo ' <span class="badge badge-info">5% Off</span>';
    }
    
    echo '</div>
    </div>';
  }
}
$conn->close();
?>
</div>

<script>
$(".content").click(function() {
    var bus = $(this).find(">:first-child").html();
    var date = "<?php echo isset($_GET['jdate']) ? $_GET['jdate'] : '';?>";
    
    if (!date) {
      alert("Please select a journey date first!");
      return;
    }
    
    $.ajax({
        url: "./inc/ajax.php?type=showseats&bus=" + bus + "&date=" + date,
        success: function(result) {
            setTimeout(function() {
                $("#seatViewer").html(result);
            }, 1000);
        },
        beforeSend: function() {
            $("#wait").show();
        },
        complete: function() {
            setTimeout(function() {
                $("#wait").hide();
            }, 1000);
        }
    });
    setTimeout(function() {
        $("#seatViewer").show();
    }, 1100);
});
</script>
<br>
<br>
<br>
<br>
<br>
<br>
<?php
t_footer();
?>