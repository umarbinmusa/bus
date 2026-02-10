<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['utype'] != "Passenger") {
    header("Location: index.php");
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/database.php';
$conn = initDB();
$user_id = intval($_SESSION['user']['id']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Booking History</title>
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css"/>
    <style>
        body { padding: 20px; font-family: Arial; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #333; color: white; }
        tr:hover { background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info-box { background: #e7f3ff; border-left: 4px solid #2196F3; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>

<h1>📋 Your Booking History</h1>

<div class="info-box">
    <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['user']['uname']); ?> (User ID: <?php echo $user_id; ?>)
</div>

<?php
// Query 1: Direct count
$count_query = "SELECT COUNT(*) as total FROM tickets WHERE passenger_id = $user_id";
$count_result = $conn->query($count_query);
$count_row = $count_result->fetch_assoc();
$total_tickets = $count_row['total'];

echo '<div class="info-box">';
echo '<strong>Total Bookings:</strong> ' . $total_tickets;
echo '</div>';

if ($total_tickets == 0) {
    echo '<div class="alert alert-warning">';
    echo '<h3>No Bookings Found</h3>';
    echo '<p>You have not made any bookings yet.</p>';
    echo '<a href="buy_ticket.php" class="btn btn-primary">Book a Ticket Now</a>';
    echo '</div>';
} else {
    // Query 2: Get all tickets with bus info
    $tickets_query = "SELECT 
        t.id as ticket_id,
        t.bus_id,
        t.jdate,
        t.fare,
        t.seats,
        b.bname,
        b.bus_no,
        b.from_loc,
        b.to_loc,
        b.from_time,
        b.to_time
    FROM tickets t
    LEFT JOIN buses b ON t.bus_id = b.id
    WHERE t.passenger_id = $user_id
    ORDER BY t.id DESC";
    
    $tickets_result = $conn->query($tickets_query);
    
    if (!$tickets_result) {
        echo '<div class="error">SQL Error: ' . htmlspecialchars($conn->error) . '</div>';
    } else {
        echo '<h2>Your Bookings (' . $tickets_result->num_rows . ' found)</h2>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Ticket ID</th>';
        echo '<th>Bus Name</th>';
        echo '<th>Bus Number</th>';
        echo '<th>Route</th>';
        echo '<th>Departure</th>';
        echo '<th>Arrival</th>';
        echo '<th>Journey Date</th>';
        echo '<th>Seats</th>';
        echo '<th>Fare</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        while ($ticket = $tickets_result->fetch_assoc()) {
            // Unserialize seats
            $seats_data = @unserialize($ticket['seats']);
            $seats_display = '';
            
            if (is_array($seats_data)) {
                $seats_display = implode(', ', $seats_data) . ' (' . count($seats_data) . ' seats)';
            } else {
                $seats_display = 'Error reading seats';
            }
            
            // Check if bus info is available
            $bus_name = $ticket['bname'] ?: '<span class="error">Bus Not Found (ID: ' . $ticket['bus_id'] . ')</span>';
            $bus_no = $ticket['bus_no'] ?: 'N/A';
            $route = $ticket['from_loc'] && $ticket['to_loc'] ? 
                     $ticket['from_loc'] . ' → ' . $ticket['to_loc'] : 
                     'N/A';
            $departure = $ticket['from_time'] ?: 'N/A';
            $arrival = $ticket['to_time'] ?: 'N/A';
            
            echo '<tr>';
            echo '<td><strong>#' . $ticket['ticket_id'] . '</strong></td>';
            echo '<td>' . $bus_name . '</td>';
            echo '<td>' . htmlspecialchars($bus_no) . '</td>';
            echo '<td>' . htmlspecialchars($route) . '</td>';
            echo '<td>' . htmlspecialchars($departure) . '</td>';
            echo '<td>' . htmlspecialchars($arrival) . '</td>';
            echo '<td>' . htmlspecialchars($ticket['jdate']) . '</td>';
            echo '<td>' . $seats_display . '</td>';
            echo '<td><strong>৳' . number_format($ticket['fare'], 2) . '</strong></td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
}

// Debug section
echo '<hr>';
echo '<h2>🔍 Debug Information</h2>';

// Show raw tickets
echo '<h3>Raw Tickets Table</h3>';
$raw_query = "SELECT * FROM tickets WHERE passenger_id = $user_id";
$raw_result = $conn->query($raw_query);

echo '<table>';
echo '<tr><th>ID</th><th>Passenger ID</th><th>Bus ID</th><th>Date</th><th>Seats (Raw)</th><th>Fare</th></tr>';
if ($raw_result->num_rows > 0) {
    while ($raw = $raw_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $raw['id'] . '</td>';
        echo '<td>' . $raw['passenger_id'] . '</td>';
        echo '<td>' . $raw['bus_id'] . '</td>';
        echo '<td>' . $raw['jdate'] . '</td>';
        echo '<td><small>' . htmlspecialchars(substr($raw['seats'], 0, 100)) . '</small></td>';
        echo '<td>' . $raw['fare'] . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="6">No tickets found</td></tr>';
}
echo '</table>';

// Show buses
echo '<h3>Available Buses</h3>';
$buses_query = "SELECT * FROM buses";
$buses_result = $conn->query($buses_query);

echo '<table>';
echo '<tr><th>ID</th><th>Name</th><th>Number</th><th>Route</th><th>Approved</th></tr>';
while ($bus = $buses_result->fetch_assoc()) {
    echo '<tr>';
    echo '<td>' . $bus['id'] . '</td>';
    echo '<td>' . $bus['bname'] . '</td>';
    echo '<td>' . $bus['bus_no'] . '</td>';
    echo '<td>' . $bus['from_loc'] . ' → ' . $bus['to_loc'] . '</td>';
    echo '<td>' . ($bus['approved'] ? '✅ Yes' : '❌ No') . '</td>';
    echo '</tr>';
}
echo '</table>';

$conn->close();
?>

<hr>
<p>
    <a href="buy_ticket.php" class="btn btn-primary">← Back to Buy Tickets</a>
    <a href="index.php" class="btn btn-secondary">Home</a>
</p>

</body>
</html>