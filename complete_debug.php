<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user'])) {
    die('Please login first: <a href="index.php">Login</a>');
}

require_once 'inc/database.php';
$conn = initDB();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Complete Debug</title>
    <style>
        body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
        .section { background: #2d2d30; padding: 15px; margin: 15px 0; border-left: 4px solid #007acc; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #ce9178; }
        table { width: 100%; border-collapse: collapse; background: #252526; margin: 10px 0; }
        th, td { border: 1px solid #3e3e42; padding: 8px; text-align: left; }
        th { background: #007acc; color: white; }
        h2 { color: #4ec9b0; border-bottom: 2px solid #007acc; padding-bottom: 5px; }
        code { background: #1e1e1e; padding: 2px 5px; color: #ce9178; }
    </style>
</head>
<body>

<h1>🔍 COMPLETE DEBUG TOOL</h1>

<?php
$user_id = intval($_SESSION['user']['id']);
$user_name = $_SESSION['user']['uname'];
$user_type = $_SESSION['user']['utype'];

echo "<div class='section'>";
echo "<h2>1. SESSION INFORMATION</h2>";
echo "<table>";
echo "<tr><th>Key</th><th>Value</th></tr>";
echo "<tr><td>User ID</td><td class='success'><strong>$user_id</strong></td></tr>";
echo "<tr><td>Username</td><td>$user_name</td></tr>";
echo "<tr><td>User Type</td><td>$user_type</td></tr>";
echo "<tr><td>Passenger Category</td><td>" . (isset($_SESSION['user']['passenger_category']) ? $_SESSION['user']['passenger_category'] : '<span class="warning">Not Set</span>') . "</td></tr>";
echo "</table>";
echo "</div>";

// Test 1: Count tickets in database
echo "<div class='section'>";
echo "<h2>2. TICKETS IN DATABASE</h2>";

$count_query = "SELECT COUNT(*) as total FROM tickets WHERE passenger_id = $user_id";
$count_result = $conn->query($count_query);
$count_row = $count_result->fetch_assoc();

echo "<p><strong>Total tickets for user ID $user_id:</strong> <span class='success'>" . $count_row['total'] . "</span></p>";

if ($count_row['total'] > 0) {
    echo "<p class='success'>✓ Tickets exist in database!</p>";
} else {
    echo "<p class='error'>✗ No tickets found for your user ID</p>";
    echo "<p>Try booking a ticket first, then refresh this page.</p>";
}
echo "</div>";

// Test 2: Show raw ticket data
echo "<div class='section'>";
echo "<h2>3. RAW TICKET DATA</h2>";
$raw_query = "SELECT * FROM tickets WHERE passenger_id = $user_id ORDER BY id DESC LIMIT 5";
$raw_result = $conn->query($raw_query);

if ($raw_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Passenger ID</th><th>Bus ID</th><th>Date</th><th>Fare</th><th>Seats (serialized)</th></tr>";
    while ($raw = $raw_result->fetch_assoc()) {
        $seats_preview = htmlspecialchars(substr($raw['seats'], 0, 80));
        echo "<tr>";
        echo "<td>{$raw['id']}</td>";
        echo "<td>{$raw['passenger_id']}</td>";
        echo "<td>{$raw['bus_id']}</td>";
        echo "<td>{$raw['jdate']}</td>";
        echo "<td>{$raw['fare']}</td>";
        echo "<td><code>$seats_preview...</code></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>No tickets found</p>";
}
echo "</div>";

// Test 3: Test the JOIN query
echo "<div class='section'>";
echo "<h2>4. JOIN QUERY TEST</h2>";

$join_query = "SELECT 
    t.id as ticket_id,
    t.passenger_id,
    t.bus_id,
    t.jdate,
    t.fare,
    t.seats,
    b.id as bus_table_id,
    b.bname,
    b.from_loc,
    b.to_loc
FROM tickets t
LEFT JOIN buses b ON t.bus_id = b.id
WHERE t.passenger_id = $user_id
ORDER BY t.id DESC";

echo "<p><strong>Query:</strong></p>";
echo "<pre style='background: #1e1e1e; padding: 10px; overflow-x: auto;'>" . htmlspecialchars($join_query) . "</pre>";

$join_result = $conn->query($join_query);

if (!$join_result) {
    echo "<p class='error'>✗ Query failed: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p class='success'>✓ Query executed successfully</p>";
    echo "<p><strong>Rows returned:</strong> " . $join_result->num_rows . "</p>";
    
    if ($join_result->num_rows > 0) {
        echo "<table>";
        echo "<tr><th>Ticket ID</th><th>Bus ID</th><th>Bus Name</th><th>Route</th><th>Date</th><th>Fare</th><th>Status</th></tr>";
        
        while ($ticket = $join_result->fetch_assoc()) {
            $status = $ticket['bus_table_id'] ? '<span class="success">✓ OK</span>' : '<span class="error">✗ Bus Missing</span>';
            $route = $ticket['from_loc'] ? "{$ticket['from_loc']} → {$ticket['to_loc']}" : '<span class="error">N/A</span>';
            
            echo "<tr>";
            echo "<td>{$ticket['ticket_id']}</td>";
            echo "<td>{$ticket['bus_id']}</td>";
            echo "<td>" . ($ticket['bname'] ?: '<span class="error">NULL</span>') . "</td>";
            echo "<td>$route</td>";
            echo "<td>{$ticket['jdate']}</td>";
            echo "<td>{$ticket['fare']}</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
}
echo "</div>";

// Test 4: Check buses table
echo "<div class='section'>";
echo "<h2>5. BUSES TABLE</h2>";

$buses_query = "SELECT * FROM buses ORDER BY id";
$buses_result = $conn->query($buses_query);

echo "<p><strong>Total buses:</strong> " . $buses_result->num_rows . "</p>";
echo "<table>";
echo "<tr><th>ID</th><th>Name</th><th>Number</th><th>Route</th><th>Approved</th></tr>";

while ($bus = $buses_result->fetch_assoc()) {
    $approved = $bus['approved'] ? '<span class="success">✓ Yes</span>' : '<span class="error">✗ No</span>';
    echo "<tr>";
    echo "<td>{$bus['id']}</td>";
    echo "<td>{$bus['bname']}</td>";
    echo "<td>{$bus['bus_no']}</td>";
    echo "<td>{$bus['from_loc']} → {$bus['to_loc']}</td>";
    echo "<td>$approved</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// Test 5: Test unserialize
echo "<div class='section'>";
echo "<h2>6. SEATS UNSERIALIZE TEST</h2>";

$seats_query = "SELECT id, seats FROM tickets WHERE passenger_id = $user_id ORDER BY id DESC LIMIT 5";
$seats_result = $conn->query($seats_query);

if ($seats_result->num_rows > 0) {
    echo "<table>";
    echo "<tr><th>Ticket ID</th><th>Serialized Data</th><th>Unserialized</th><th>Status</th></tr>";
    
    while ($seat = $seats_result->fetch_assoc()) {
        $serialized = $seat['seats'];
        $unserialized = @unserialize($serialized);
        
        $status = '';
        $display = '';
        
        if ($unserialized === false && $serialized !== 'b:0;') {
            $status = '<span class="error">✗ FAILED</span>';
            $display = '<span class="error">Corrupt data</span>';
        } elseif (is_array($unserialized)) {
            $status = '<span class="success">✓ OK</span>';
            $display = implode(', ', $unserialized) . ' (' . count($unserialized) . ' seats)';
        } else {
            $status = '<span class="warning">⚠ Not array</span>';
            $display = gettype($unserialized);
        }
        
        echo "<tr>";
        echo "<td>{$seat['id']}</td>";
        echo "<td><code>" . htmlspecialchars(substr($serialized, 0, 50)) . "...</code></td>";
        echo "<td>$display</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='warning'>No tickets to test</p>";
}
echo "</div>";

// Test 6: Simulate history.php query
echo "<div class='section'>";
echo "<h2>7. HISTORY.PHP SIMULATION</h2>";

$history_query = "SELECT t.id as t_id, t.jdate, t.fare, t.seats, b.id as bus_id, b.bname as bus_name,
b.from_loc, b.to_loc, b.from_time, b.to_time 
FROM tickets t, buses b 
WHERE t.bus_id = b.id AND t.passenger_id = $user_id 
ORDER BY t.id DESC";

echo "<p><strong>Query (old JOIN style):</strong></p>";
echo "<pre style='background: #1e1e1e; padding: 10px;'>" . htmlspecialchars($history_query) . "</pre>";

$history_result = $conn->query($history_query);

if (!$history_result) {
    echo "<p class='error'>✗ Query Error: " . htmlspecialchars($conn->error) . "</p>";
} else {
    echo "<p class='success'>✓ Query successful</p>";
    echo "<p><strong>Rows returned:</strong> {$history_result->num_rows}</p>";
    
    if ($history_result->num_rows == 0) {
        echo "<p class='error'>✗ This is why history is empty!</p>";
        echo "<p><strong>Possible reasons:</strong></p>";
        echo "<ul>";
        echo "<li>Bus IDs in tickets don't match buses table</li>";
        echo "<li>Buses are not approved (if there's a filter)</li>";
        echo "<li>Wrong passenger_id being used</li>";
        echo "</ul>";
    } else {
        echo "<p class='success'>✓ Tickets found! History should work.</p>";
    }
}
echo "</div>";

// Summary
echo "<div class='section'>";
echo "<h2>8. DIAGNOSIS SUMMARY</h2>";

$issues = [];
$fixes = [];

if ($count_row['total'] == 0) {
    $issues[] = "No tickets in database for user ID $user_id";
    $fixes[] = "Book a ticket first";
}

if ($join_result && $join_result->num_rows == 0 && $count_row['total'] > 0) {
    $issues[] = "Tickets exist but JOIN fails";
    $fixes[] = "Check if bus_id in tickets matches buses.id";
}

if ($history_result && $history_result->num_rows == 0 && $count_row['total'] > 0) {
    $issues[] = "Old-style JOIN (FROM tickets, buses) failing";
    $fixes[] = "Update history.php to use LEFT JOIN instead";
}

if (empty($issues)) {
    echo "<p class='success' style='font-size: 18px;'>✓ NO ISSUES DETECTED - History should be working!</p>";
    echo "<p>If history.php is still empty, the problem is in the PHP file itself.</p>";
    echo "<p><a href='ultra_simple_history.php' style='color: #4ec9b0;'>Try Ultra Simple History →</a></p>";
} else {
    echo "<p class='error' style='font-size: 18px;'>✗ ISSUES FOUND:</p>";
    echo "<ol>";
    foreach ($issues as $i => $issue) {
        echo "<li class='error'>$issue</li>";
        echo "<p class='warning'>Fix: {$fixes[$i]}</p>";
    }
    echo "</ol>";
}

echo "</div>";

$conn->close();
?>

<hr style="border-color: #007acc;">
<p>
    <a href="buy_ticket.php" style="color: #4ec9b0;">← Buy Tickets</a> | 
    <a href="ultra_simple_history.php" style="color: #4ec9b0;">Ultra Simple History</a> | 
    <a href="history.php" style="color: #4ec9b0;">Regular History</a> | 
    <a href="?" style="color: #4ec9b0;">Refresh</a>
</p>

</body>
</html>