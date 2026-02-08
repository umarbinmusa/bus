<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/database.php';
$conn = initDB();

echo '<!DOCTYPE html>
<html>
<head>
    <title>Repair Tickets Database</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; }
        table { border-collapse: collapse; width: 100%; background: white; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>';

echo '<h1>🔧 Ticket Database Repair Tool</h1>';

// Check all tickets
echo '<h2>Step 1: Checking Tickets</h2>';
$result = $conn->query("SELECT * FROM tickets ORDER BY id DESC");

if (!$result) {
    echo '<p class="error">Error querying tickets: ' . $conn->error . '</p>';
} else {
    echo '<table>';
    echo '<tr><th>ID</th><th>Passenger</th><th>Bus</th><th>Date</th><th>Seats Data</th><th>Status</th><th>Action</th></tr>';
    
    $corrupted = [];
    
    while ($row = $result->fetch_assoc()) {
        $seats_valid = true;
        $seats_display = '';
        
        // Try to unserialize
        $seats = @unserialize($row['seats']);
        
        if ($seats === false && $row['seats'] !== 'b:0;') {
            $seats_valid = false;
            $seats_display = '<span class="error">CORRUPTED</span>';
            $corrupted[] = $row['id'];
        } elseif (is_array($seats)) {
            $seats_display = '<span class="success">OK (' . count($seats) . ' seats: ' . implode(', ', $seats) . ')</span>';
        } else {
            $seats_display = '<span class="error">Invalid format</span>';
            $seats_valid = false;
            $corrupted[] = $row['id'];
        }
        
        echo '<tr>';
        echo '<td>' . $row['id'] . '</td>';
        echo '<td>' . $row['passenger_id'] . '</td>';
        echo '<td>' . $row['bus_id'] . '</td>';
        echo '<td>' . $row['jdate'] . '</td>';
        echo '<td><pre>' . htmlspecialchars(substr($row['seats'], 0, 100)) . '</pre></td>';
        echo '<td>' . $seats_display . '</td>';
        echo '<td>' . ($seats_valid ? '✓' : '<a href="?delete=' . $row['id'] . '">Delete</a>') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    if (!empty($corrupted)) {
        echo '<h2>Step 2: Corrupted Tickets Found</h2>';
        echo '<p class="error">Found ' . count($corrupted) . ' corrupted ticket(s): ' . implode(', ', $corrupted) . '</p>';
        echo '<p><a href="?delete_all_corrupted=1" style="background: red; color: white; padding: 10px; text-decoration: none; display: inline-block;">Delete All Corrupted Tickets</a></p>';
    } else {
        echo '<h2>Step 2: Result</h2>';
        echo '<p class="success">✓ All tickets are valid!</p>';
    }
}

// Handle deletions
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($conn->query("DELETE FROM tickets WHERE id = $id")) {
        echo '<p class="success">✓ Deleted ticket #' . $id . '</p>';
        echo '<p><a href="repair_tickets.php">Refresh</a></p>';
    } else {
        echo '<p class="error">Error deleting ticket: ' . $conn->error . '</p>';
    }
}

if (isset($_GET['delete_all_corrupted'])) {
    echo '<h2>Step 3: Cleaning Corrupted Data</h2>';
    
    // Get all tickets
    $result = $conn->query("SELECT * FROM tickets");
    $deleted = 0;
    
    while ($row = $result->fetch_assoc()) {
        $seats = @unserialize($row['seats']);
        if ($seats === false && $row['seats'] !== 'b:0;') {
            if ($conn->query("DELETE FROM tickets WHERE id = " . $row['id'])) {
                echo '<p class="info">Deleted corrupted ticket #' . $row['id'] . '</p>';
                $deleted++;
            }
        } elseif (!is_array($seats)) {
            if ($conn->query("DELETE FROM tickets WHERE id = " . $row['id'])) {
                echo '<p class="info">Deleted invalid ticket #' . $row['id'] . '</p>';
                $deleted++;
            }
        }
    }
    
    echo '<p class="success">✓ Deleted ' . $deleted . ' corrupted ticket(s)</p>';
    echo '<p><a href="repair_tickets.php">Refresh</a></p>';
}

echo '<hr>';
echo '<h2>Quick Actions</h2>';
echo '<ul>';
echo '<li><a href="repair_tickets.php">Refresh This Page</a></li>';
echo '<li><a href="buy_ticket.php">Go to Buy Tickets</a></li>';
echo '<li><a href="history.php">Go to History</a></li>';
echo '<li><a href="diagnostic.php">Run Diagnostic</a></li>';
echo '</ul>';

$conn->close();

echo '</body></html>';
?>