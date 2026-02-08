<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['utype'] != "Passenger")
  header("Location: index.php");

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'inc/basic_template.php';
t_header("Bus Ticket Booking &mdash; Buy Tickets");
t_login_nav();
t_sidebar();

// PROCESS BOOKING
if (isset($_POST['buy'])) {
  require_once 'inc/database.php';
  $conn = initDB();
  
  // Check if seats are selected
  if (!isset($_POST['seats']) || empty($_POST['seats'])) {
    echo '<div class="alert alert-danger"><strong>Error: </strong>No seats selected</div>';
  }
  else {
    // Check all required fields
    $errors = [];
    
    if (!isset($_POST['bus_id']) || empty($_POST['bus_id'])) {
      $errors[] = "Missing bus_id";
    }
    if (!isset($_POST['jdate']) || empty($_POST['jdate'])) {
      $errors[] = "Missing journey date";
    }
    if (!isset($_POST['fare']) || $_POST['fare'] <= 0) {
      $errors[] = "Invalid fare amount";
    }
    
    if (!empty($errors)) {
      echo '<div class="alert alert-danger"><strong>Errors:</strong><ul>';
      foreach($errors as $error) {
        echo '<li>'.$error.'</li>';
      }
      echo '</ul></div>';
    } else {
      // All data is present, validate and insert into database
      $passenger_id = intval($_SESSION['user']['id']);
      $bus_id = intval($_POST['bus_id']);
      $jdate = $conn->real_escape_string($_POST['jdate']);
      $fare = intval($_POST['fare']);
      
      // Validate and serialize seats properly
      if (is_array($_POST['seats']) && count($_POST['seats']) > 0) {
        $seats_serialized = serialize($_POST['seats']);
      } else {
        echo '<div class="alert alert-danger">Invalid seat selection</div>';
        $seats_serialized = null;
      }
      
      if ($seats_serialized) {
        $sql = "INSERT INTO tickets (passenger_id, bus_id, jdate, seats, fare) VALUES (";
        $sql .= "$passenger_id, $bus_id, '$jdate', '$seats_serialized', $fare)";
        
        if ($conn->query($sql)) {
          $ticket_id = $conn->insert_id;
          echo '<div class="alert alert-success">';
          echo '<h4>✅ Booking Successful!</h4>';
          echo 'Ticket ID: '.$ticket_id.'<br>';
          echo 'Seats: '.implode(', ', $_POST['seats']).'<br>';
          echo 'Total Fare: ৳'.$fare.'<br>';
          echo '<a href="history.php" class="btn btn-info mt-2">View Booking History</a> ';
          echo '<a href="print.php?ticket='.$ticket_id.'" class="btn btn-success mt-2">Print Ticket</a>';
          echo '</div>';
        }
        else {
          echo '<div class="alert alert-danger">';
          echo '<strong>Database Error:</strong><br>';
          echo 'Error: '.$conn->error;
          echo '</div>';
        }
      }
    }
  }
  $conn->close();
}
?>

<!-- Select Locations -->
<link rel="stylesheet" href="css/easy-autocomplete.min.css"/>
<link rel="stylesheet" href="css/bootstrap-datepicker.min.css"/>

<h3>Book Your Ticket</h3>

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
  <div class="col-sm-2">Sl.</div>
  <div class="col-sm-4">Bus Name</div>
  <div class="col-sm-2">Dip. Time</div>
  <div class="col-sm-2">Arr. Time</div>
  <div class="col-sm-2">Fare (৳)</div>
</div>
<?php
require_once 'inc/database.php';
$conn = initDB();
$from = isset($_GET['from']) ? $_GET['from'] : "";
$to = isset($_GET['to']) ? $_GET['to'] : "";
$res = $conn->query("select * from buses where from_loc='".$from."' and to_loc='".$to."'");
if ($res->num_rows == 0 || !isset($_GET['jdate']) || $_GET['jdate'] == '') {
  echo '<div class="row">
    <div class="col-sm-12 text-center"><h4>No Bus</h4></div>
  </div>';
}
else {
  while ($row = $res->fetch_assoc()) {
    echo '
    <div class="row content" data-bus-id="'.$row['id'].'" style="cursor: pointer; padding: 10px;">
      <div class="col-sm-2">'.$row['id'].'</div>
      <div class="col-sm-4">'.$row['bname'].'</div>
      <div class="col-sm-2">'.$row['from_time'].'</div>
      <div class="col-sm-2">'.$row['to_time'].'</div>
      <div class="col-sm-2">'.$row['fare'].'</div>
    </div>
    ';
  }
}
$conn->close();
?>
</div>

<script>
console.log("Page loaded. jQuery version:", $.fn.jquery);
console.log("Current location:", window.location.href);

$(".content").click(function() {
    var bus = $(this).find(">:first-child").html();
    var date = "<?php echo isset($_GET['jdate']) ? $_GET['jdate'] : ''; ?>";
    
    console.log("Bus clicked:", bus);
    console.log("Date:", date);
    
    // Use relative path for AJAX
    var ajaxUrl = "inc/ajax.php?type=showseats&bus=" + bus + "&date=" + date;
    console.log("AJAX URL:", ajaxUrl);
    
    $.ajax({
        url: ajaxUrl,
        method: "GET",
        success: function(result) {
            console.log("AJAX success, response length:", result.length);
            setTimeout(function() {
                $("#seatViewer").html(result);
                $("#seatViewer").show();
            }, 500);
        },
        error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error);
            console.error("Status Code:", xhr.status);
            console.error("Response:", xhr.responseText);
            alert("Error loading seats: " + error + "\nStatus: " + xhr.status + "\nCheck console for details");
        },
        beforeSend: function() {
            $("#wait").show();
        },
        complete: function() {
            setTimeout(function() {
                $("#wait").hide();
            }, 500);
        }
    });
});

// Close seat viewer
$(document).on('click', '#seatViewer .close, #seatViewer [data-dismiss="modal"]', function() {
    $('#seatViewer').hide();
});
</script>

<br><br><br><br><br><br>

<?php
t_footer();
?>