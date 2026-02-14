<?php
session_start();

// CRITICAL: Check if user is logged in and is Passenger/Student/Staff
if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

// Check user type - MUST be Passenger, Student, or Staff
if (!in_array($_SESSION['user']['utype'], ["Passenger", "Student", "Staff"])) {
    if ($_SESSION['user']['utype'] == "Owner") {
        header("Location: my_buses.php");
        exit();
    } elseif ($_SESSION['user']['utype'] == "Admin") {
        header("Location: users.php");
        exit();
    } else {
        session_destroy();
        header("Location: index.php");
        exit();
    }
}

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
	$fare  = (int)$_POST['fare'];
	$seats = serialize($_POST['seats']);
	
	$sql  = "INSERT INTO tickets (passenger_id, bus_id, jdate, seats, fare, booking_confirmed) VALUES (";
	$sql .= $_SESSION['user']['id'] . "," . $busid . ",'" . $jdate . "','" . $seats . "'," . $fare . ", 1)";
	
	if ($conn->query($sql)) {
		echo '<script>window.location.href="history.php";</script>';
		exit();
	} else {
		echo '<script>alert("Booking Error: ' . addslashes($conn->error) . '");</script>';
	}
	$conn->close();
}
?>

<div class="container">
	<h4>Buy Ticket</h4>

	<div class="alert alert-info">
		<strong>Welcome, <?php echo htmlspecialchars($_SESSION['user']['uname']); ?>!</strong>
		&nbsp; Account Type: <span class="badge badge-primary"><?php echo $_SESSION['user']['utype']; ?></span>
		<?php
		if ($_SESSION['user']['utype'] == 'Student') {
			echo ' &nbsp; <small>You get <strong>10% discount</strong>!</small>';
		} elseif ($_SESSION['user']['utype'] == 'Staff') {
			echo ' &nbsp; <small>You get <strong>5% discount</strong>!</small>';
		}
		?>
	</div>

	<!-- Search Form -->
	<form method="get" action="buy_ticket.php">
	<table class="table-con">
	<tr class="head">
		<th width="15%">From</th>
		<th width="15%">To</th>
		<th width="15%">Journey Date</th>
		<th></th>
	</tr>
	<tr class="content">
		<td><input type="text" name="from"  class="form-control" id="inputFrom"  required/></td>
		<td><input type="text" name="to"    class="form-control" id="inputTo"    required/></td>
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
		list: { match: { enabled: true } }
	};
	$("#inputFrom").easyAutocomplete(opt);
	$("#inputTo").easyAutocomplete(opt);
	$("#inputJdate").datepicker({ minDate: 0, dateFormat: "dd/mm/yy" });
	</script>
	</form>

	<?php
	if (isset($_GET['from']) && isset($_GET['to']) && isset($_GET['jdate'])) {
		require_once 'inc/database.php';
		$conn = initDB();

		$from  = $conn->real_escape_string($_GET['from']);
		$to    = $conn->real_escape_string($_GET['to']);
		$jdate = $conn->real_escape_string($_GET['jdate']);

		$res = $conn->query(
			"SELECT * FROM buses WHERE from_loc='$from' AND to_loc='$to' AND approved=1"
		);

		if (!$res || $res->num_rows == 0) {
			echo '<div class="alert alert-warning mt-3">No buses found for this route.</div>';
		} else {
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

				// ----------------------------------------------------------
				// THE FIX: safely unserialize each row — skip corrupt data
				// ----------------------------------------------------------
				$total_seats = isset($row['total_seats']) ? (int)$row['total_seats'] : 40;

				$booked_res = $conn->query(
					"SELECT seats FROM tickets
					 WHERE bus_id=" . (int)$row['id'] . "
					 AND jdate='$jdate'
					 AND booking_confirmed=1"
				);

				$booked_seats = array();
				if ($booked_res && $booked_res->num_rows > 0) {
					while ($b_row = $booked_res->fetch_assoc()) {
						$data = @unserialize($b_row['seats']);      // @ suppresses the notice
						if ($data !== false && is_array($data)) {  // only merge valid arrays
							$booked_seats = array_merge($booked_seats, $data);
						}
						// corrupted rows are silently skipped
					}
				}
				// ----------------------------------------------------------

				$available = max(0, $total_seats - count($booked_seats));

				echo '<tr class="content">
					<td>' . htmlspecialchars($row['bname'])     . '</td>
					<td>' . htmlspecialchars($row['bus_no'])    . '</td>
					<td>' . htmlspecialchars($row['from_time']) . '</td>
					<td>' . htmlspecialchars($row['to_time'])   . '</td>
					<td>৳' . (int)$row['fare']                  . '</td>
					<td>' . $total_seats                        . '</td>
					<td><strong>' . $available . '</strong></td>
					<td>';

				if ($available > 0) {
					echo '<a onclick="showSeats(' . (int)$row['id'] . ',\'' . htmlspecialchars($jdate, ENT_QUOTES) . '\')">
						<button type="button" class="btn btn-sm btn-success">Book Now</button>
					</a>';
				} else {
					echo '<button type="button" class="btn btn-sm btn-secondary" disabled>Full</button>';
				}

				echo '</td></tr>';
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
			$("#seatViewer").html(result).show();
		},
		error: function() {
			alert("Error loading seats. Please try again.");
		}
	});
}
</script>

<?php
t_footer();
?>