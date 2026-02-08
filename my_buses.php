<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['utype'] != "Owner")
    header("Location: index.php");
$add = "";
if (isset($_POST['add'])) {
    require_once 'inc/database.php';
	$conn = initDB();
    
    // Auto-categorize seats based on total seats
    $totalSeats = $_POST['total_seats'];
    $layout = $_POST['seat_layout'];
    
    // Generate all seats
    function generateAllSeats($total, $layout) {
        $seats = array();
        $parts = explode('x', $layout);
        $rows = (int)$parts[0];
        $cols = (int)$parts[1];
        $rowLabels = range('A', 'Z');
        
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 1; $col <= $cols; $col++) {
                $seats[] = $rowLabels[$row] . $col;
                if (count($seats) >= $total) {
                    return $seats;
                }
            }
        }
        return $seats;
    }
    
    $allSeats = generateAllSeats($totalSeats, $layout);
    
    // Categorize: 50% student, 25% staff, 25% general
    $studentCount = ceil($totalSeats * 0.5);
    $staffCount = ceil($totalSeats * 0.25);
    
    $studentSeats = array_slice($allSeats, 0, $studentCount);
    $staffSeats = array_slice($allSeats, $studentCount, $staffCount);
    $generalSeats = array_slice($allSeats, $studentCount + $staffCount);
    
	$sql = "insert into buses (bname, bus_no, from_loc, from_time, to_loc, to_time, fare, total_seats, seat_layout, student_seats, staff_seats, general_seats, owner_id) values ('";
	$sql .= $_POST['bname']."','".$_POST['bus_no']."','".$_POST['from_loc']."','".$_POST['from_time']."','";
	$sql .= $_POST['to_loc']."','".$_POST['to_time']."','".$_POST['fare']."','".$_POST['total_seats']."','".$_POST['seat_layout']."','";
    $sql .= implode(',', $studentSeats)."','".implode(',', $staffSeats)."','".implode(',', $generalSeats)."','".$_SESSION['user']['id']."')";
	
    if ($conn->query($sql)) {
		$add = "ok";
	}
	else {
		$add = $sql . "<br/>" .$conn->error;
	}
	$conn->close();
}
include 'inc/basic_template.php';
t_header("Bus Ticket Booking");
t_login_nav();
t_owner_sidebar();
?>
<div class="modal" tabindex="-1" role="dialog" style="display: <?php echo (isset($_GET['act']) && $_GET['act'] == 'add') ? 'block' : 'none';?>">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Bus with Seat Categories</h5>
        <a href="my_buses.php"><button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button></a>
      </div>
      <form method="post" action="my_buses.php">
      <div class="modal-body">
        <div class="form-group row">
            <div class="col-sm-3">Bus Name</div>
                <div class="col-sm-8">
                    <input type="text" name="bname" class="form-control" required/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-3">Bus No.</div>
                <div class="col-sm-8">
                    <input type="text" name="bus_no" class="form-control" required/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-3">From</div>
                <div class="col-sm-8">
                    <input type="text" name="from_loc" class="form-control" id="inputFrom" required/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-3">Departure Time</div>
                <div class="col-sm-8">
                    <input type="text" name="from_time" class="form-control" placeholder="e.g., 10:30 AM" required/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-3">To</div>
                <div class="col-sm-8">
                    <input type="text" name="to_loc" class="form-control" id="inputTo" required/>
            </div>
        </div>
        <link rel="stylesheet" href="css/easy-autocomplete.min.css"/>
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
            <div class="col-sm-3">Arrival Time</div>
                <div class="col-sm-8">
                    <input type="text" name="to_time" class="form-control" placeholder="e.g., 04:30 PM" required/>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-3">Fare (৳)</div>
                <div class="col-sm-8">
                    <input type="number" name="fare" class="form-control" min="0" required/>
            </div>
        </div>
        
        <hr/>
        <h6><i class="fa fa-th"></i> Seat Configuration</h6>
        
        <div class="form-group row">
            <div class="col-sm-3">Seat Template</div>
                <div class="col-sm-8">
                    <select class="form-control" id="seatTemplate" onchange="updateSeatConfig()">
                        <option value="">Select Template</option>
                        <option value="20|5x4">Small Bus (20 seats)</option>
                        <option value="30|8x4">Luxury Coach (30 seats)</option>
                        <option value="40|10x4" selected>Standard Bus (40 seats)</option>
                        <option value="50|13x4">Large Bus (50 seats)</option>
                        <option value="custom">Custom Configuration</option>
                    </select>
            </div>
        </div>
        
        <div class="form-group row">
            <div class="col-sm-3">Total Seats</div>
                <div class="col-sm-8">
                    <input type="number" name="total_seats" id="totalSeats" class="form-control" min="10" max="100" value="40" required onchange="updateCategorization()"/>
                    <small class="form-text text-muted">Number of seats available in this bus</small>
            </div>
        </div>
        
        <div class="form-group row">
            <div class="col-sm-3">Seat Layout</div>
                <div class="col-sm-8">
                    <input type="text" name="seat_layout" id="seatLayout" class="form-control" value="10x4" required onchange="updateCategorization()"/>
                    <small class="form-text text-muted">Format: ROWSxCOLUMNS (e.g., 10x4 for 10 rows, 4 columns)</small>
            </div>
        </div>
        
        <div class="alert alert-info">
            <strong><i class="fa fa-info-circle"></i> Automatic Seat Categorization:</strong><br/>
            <div id="categorization">
                <span id="studentCategory">Student Seats: 20 (50%)</span> - 10% discount<br/>
                <span id="staffCategory">Staff Seats: 10 (25%)</span> - 5% discount<br/>
                <span id="generalCategory">General Seats: 10 (25%)</span> - Regular fare<br/>
            </div>
            <small class="text-muted">
                Students can book staff seats in the last 2 minutes before journey time.<br/>
                Staff members cannot book student seats.
            </small>
        </div>
        
      </div>
      <div class="modal-footer">
        <input type="submit" class="btn btn-primary" value="Add Bus" name="add"/>
        <a href="my_buses.php"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></a>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
function updateSeatConfig() {
    var template = document.getElementById('seatTemplate').value;
    if (template && template !== 'custom') {
        var parts = template.split('|');
        var seats = parts[0];
        var layout = parts[1];
        
        document.getElementById('totalSeats').value = seats;
        document.getElementById('seatLayout').value = layout;
        
        updateCategorization();
    }
}

function updateCategorization() {
    var totalSeats = parseInt(document.getElementById('totalSeats').value) || 0;
    
    var studentSeats = Math.ceil(totalSeats * 0.5);
    var staffSeats = Math.ceil(totalSeats * 0.25);
    var generalSeats = totalSeats - studentSeats - staffSeats;
    
    document.getElementById('studentCategory').innerHTML = 
        '<strong>Student Seats: ' + studentSeats + ' (50%)</strong>';
    document.getElementById('staffCategory').innerHTML = 
        '<strong>Staff Seats: ' + staffSeats + ' (25%)</strong>';
    document.getElementById('generalCategory').innerHTML = 
        '<strong>General Seats: ' + generalSeats + ' (25%)</strong>';
}

// Initialize
updateCategorization();
</script>

<div class="container">
<?php
    if ($add!="") {
        if ($add == "ok") {
            echo '<div class="alert alert-success">Bus Added <strong>Success</strong>fully with seat categories! Waiting for admin approval.</div>';
        }
        else {
            echo '<div class="alert alert-danger"><strong>Error: </strong>'.$add.'</div>';
        }
    }
?>
<div class="row mb-2">
    <h4 class="col-md-3">My Buses</h4>
    <div class="col-md-8 text-right ml-4">
        <a href="my_buses.php?act=add"><button type="button" class="btn btn-sm btn-primary"><i class="fa fa-plus"></i> Add Bus</button></a>
    </div>
</div>
<table width="100%" class="table-con">
<tr class="head">
    <th>ID</th>
    <th>Bus Name</th>
    <th>Bus No.</th>
    <th>Route</th>
    <th>Fare</th>
    <th>Total Seats</th>
    <th>Seat Categories</th>
    <th>Status</th>
</tr>
<?php
require_once 'inc/database.php';
$conn = initDB();
$res = $conn->query("select * from buses where owner_id=" . $_SESSION['user']['id']);
if ($res->num_rows == 0) {
    echo '
    <tr class="row">
        <td colspan="8" class="text-center">No Bus Added Yet</td>
    </tr>';
}
else {
    while ($row = $res->fetch_assoc()) {
        $studentCount = !empty($row['student_seats']) ? count(explode(',', $row['student_seats'])) : 0;
        $staffCount = !empty($row['staff_seats']) ? count(explode(',', $row['staff_seats'])) : 0;
        $generalCount = !empty($row['general_seats']) ? count(explode(',', $row['general_seats'])) : 0;
        
        echo '
        <tr class="content">
            <td>' . $row["id"] . '</td>
            <td>' . $row["bname"] . '</td>
            <td>' . $row["bus_no"] . '</td>
            <td>' . $row["from_loc"] . ' → ' . $row["to_loc"] . '<br/><small>' . $row["from_time"] . ' - ' . $row["to_time"] . '</small></td>
            <td>৳' . $row["fare"] . '</td>
            <td><strong>' . $row["total_seats"] . '</strong><br/><small>' . $row["seat_layout"] . '</small></td>
            <td>
                <span class="badge badge-primary" title="Student Seats">' . $studentCount . '</span>
                <span class="badge badge-success" title="Staff Seats">' . $staffCount . '</span>
                <span class="badge badge-secondary" title="General Seats">' . $generalCount . '</span>
            </td>
            <td>' . (($row["approved"]) ? '<span class="badge badge-success"><i class="fa fa-check"></i> Approved</span>' : '<span class="badge badge-warning"><i class="fa fa-clock-o"></i> Pending</span>' ) . '</td>
        </tr>';
    }
}
$conn->close();
?>
</table>
</div>
<?php
t_footer();
?>