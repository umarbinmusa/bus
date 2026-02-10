<?php
session_start();

// CRITICAL: Check if user is logged in and is Passenger/Student/Staff
if (!isset($_SESSION['user'])) {
    // Not logged in at all - redirect to index
    header("Location: index.php");
    exit();
}

// Check user type - MUST be Passenger, Student, or Staff
if (!in_array($_SESSION['user']['utype'], ["Passenger", "Student", "Staff"])) {
    // Wrong user type (Owner or Admin) - redirect them
    if ($_SESSION['user']['utype'] == "Owner") {
        header("Location: my_buses.php");
        exit();
    } elseif ($_SESSION['user']['utype'] == "Admin") {
        header("Location: users.php");
        exit();
    } else {
        // Unknown user type - logout and redirect
        session_destroy();
        header("Location: index.php");
        exit();
    }
}

// If we reach here, user is logged in as Passenger/Student/Staff - good!

include 'inc/basic_template.php';
t_header("Bus Ticket Booking &mdash; Buy Ticket");
t_login_nav();
t_sidebar();

// Handle ticket purchase
if (isset($_POST['buy'])) {
	require_once 'inc/database.php';
	$conn = initDB();
	
	$busid = (int)$_POST['bus_id'];
	$jdate = $conn->real_escape_string($_POST['jdate']);
	$fare = (int)$_POST['fare'];
	$seats = serialize($_POST['seats']);
	$seat_category = isset($_POST['seat_category']) ? $_POST['seat_category'] : 'general';
	
	$sql = "insert into tickets (passenger_id, bus_id, jdate, seats, fare, seat_category, booking_confirmed) values (";
	$sql .= $_SESSION['user']['id'] . "," . $busid . ",'" . $jdate . "','" . $seats . "'," . $fare . ",'" . $seat_category . "', 1)";
	
	if ($conn->query($sql)) {
		$ticket_id = $conn->insert_id;
		
		// Calculate earnings for driver
		$earnings_sql = "CALL calculate_driver_earnings(" . $busid . ", '" . $jdate . "')";
		$conn->query($earnings_sql);
		
		echo '<script>window.location.href="ticket.php?ticket=' . $ticket_id . '";</script>';
		exit();
	}
	else {
		echo '<script>alert("Error: ' . $conn->error . '");</script>';
	}
	$conn->close();
}
?>

<div class="container">
	<h4>Buy Ticket</h4>
	
	<!-- User Info Display -->
	<div class="alert alert-info">
		<strong>Welcome, <?php echo $_SESSION['user']['uname']; ?>!</strong><br/>
		Account Type: <span class="badge badge-primary"><?php echo $_SESSION['user']['utype']; ?></span>
		<?php
		if ($_SESSION['user']['utype'] == 'Student') {
			echo '<br/><small>You get <strong>10% discount</strong> on student seats!</small>';
		} elseif ($_SESSION['user']['utype'] == 'Staff') {
			echo '<br/><small>You get <strong>5% discount</strong> on staff seats!</small>';
		}
		?>
	</div>
	
	<form method="get" action="buy_ticket.php">
	<table class="table-con">
	<tr class="head">
		<th width="15%">From</th>
		<th width="15%">To</th>
		<th width="15%">Journey Date</th>
		<th></th>
	</tr>
	<tr class="content">
		<td><input type="text" name="from" class="form-control" id="inputFrom" required/></td>
		<td><input type="text" name="to" class="form-control" id="inputTo" required/></td>
		<td><input type="text" name="jdate" class="form-control" id="inputJdate" required/></td>
		<td><input type="submit" class="btn btn-primary" value="Search"/></td>
	</tr>
	</table>
	<link rel="stylesheet" href="css/easy-autocomplete.min.css"/>
	<link rel="stylesheet" href="css/jquery-ui.css"/>
	<script src="js/jquery.easy-autocomplete.min.js"></script>
	<script src="js/jquery-ui.js"></script>
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
	
	$("#inputJdate").datepicker({
		minDate: 0,
		dateFormat: "dd/mm/yy"
	});
	</script>
	</form>
	
	<?php
	if (isset($_GET['from']) && isset($_GET['to']) && isset($_GET['jdate'])) {
		require_once 'inc/database.php';
		$conn = initDB();
		
		$from = $conn->real_escape_string($_GET['from']);
		$to = $conn->real_escape_string($_GET['to']);
		$jdate = $conn->real_escape_string($_GET['jdate']);
		
		$res = $conn->query("select * from buses where from_loc='" . $from . "' and to_loc='" . $to . "' and approved=1");
		
		if ($res->num_rows == 0) {
			echo '<div class="alert alert-warning mt-3">No buses found for this route.</div>';
		}
		else {
			echo '<h5 class="mt-4">Available Buses</h5>';
			echo '<table class="table-con">
			<tr class="head">
				<th>Bus Name</th>
				<th>Bus No.</th>
				<th>Departure</th>
				<th>Arrival</th>
				<th>Fare</th>
				<th>Total Seats</th>
				<th>Available</th>
				<th>Action</th>
			</tr>';
			
			while ($row = $res->fetch_assoc()) {
				// Calculate available seats
				$total_seats = $row['total_seats'];
				$booked_res = $conn->query("select seats from tickets where bus_id=" . $row['id'] . " and jdate='" . $jdate . "' and booking_confirmed=1");
				
				$booked_seats = array();
				while ($b_row = $booked_res->fetch_assoc()) {
					$booked_seats = array_merge($booked_seats, unserialize($b_row['seats']));
				}
				$available = $total_seats - count($booked_seats);
				
				echo '<tr class="content">
					<td>' . $row['bname'] . '</td>
					<td>' . $row['bus_no'] . '</td>
					<td>' . $row['from_time'] . '</td>
					<td>' . $row['to_time'] . '</td>
					<td>৳' . $row['fare'] . '</td>
					<td>' . $total_seats . '</td>
					<td><strong>' . $available . '</strong></td>
					<td>';
				
				if ($available > 0) {
					echo '<a onclick="showSeats(' . $row['id'] . ',\'' . $jdate . '\')"><button type="button" class="btn btn-sm btn-success">Book Now</button></a>';
				} else {
					echo '<button type="button" class="btn btn-sm btn-secondary" disabled>Full</button>';
				}
				
				echo '</td>
				</tr>';
			}
			
			echo '</table>';
		}
		
		$conn->close();
	}
	?>
</div>

<div id="seatViewer"></div>

<script>
function showSeats(busid, jdate) {
	$.ajax({
		url: "inc/ajax.php?type=showseats&bus=" + busid + "&date=" + jdate,
		success: function(result) {
			$("#seatViewer").html(result);
		},
		error: function(xhr, status, error) {
			console.error("AJAX Error:", error);
			console.log("Status:", status);
			console.log("Response:", xhr.responseText);
			alert("Error loading seats. Please check console for details.");
		}
	});
}
</script>

<?php
t_footer();
?>