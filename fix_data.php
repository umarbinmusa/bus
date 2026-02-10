<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/database.php';
$conn = initDB();

echo '<!DOCTYPE html>
<html>
<head>
    <title>Fix Missing Bus References</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        table { border-collapse: collapse; width: 100%; background: white; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
    </style>
</head>
<body>';

echo '<h1>🔧 Fix Missing Bus References</h1>';

// Step 1: Check buses table
echo '<div class="section">';
echo '<h2>Step 1: Check Buses Table</h2>';
$buses_result = $conn->query("SELECT * FROM buses");
echo '<table>';
echo '<tr><th>ID</th><th>Name</th><th>Bus No</th><th>From</th><th>To</th><th>Approved</th></tr>';
while ($bus = $buses_result->fetch_assoc()) {
    $approved_status = $bus['approved'] ? '<span class="success">Yes</span>' : '<span class="warning">No</span>';
    echo '<tr>';
    echo '<td>' . $bus['id'] . '</td>';
    echo '<td>' . $bus['bname'] . '</td>';
    echo '<td>' . $bus['bus_no'] . '</td>';
    echo '<td>' . $bus['from_loc'] . '</td>';
    echo '<td>' . $bus['to_loc'] . '</td>';
    echo '<td>' . $approved_status . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

// Step 2: Check for orphaned tickets
echo '<div class="section">';
echo '<h2>Step 2: Check Tickets with Missing Bus References</h2>';
$orphan_query = "SELECT t.*, u.uname 
                 FROM tickets t 
                 LEFT JOIN buses b ON t.bus_id = b.id 
                 LEFT JOIN users u ON t.passenger_id = u.id
                 WHERE b.id IS NULL";
$orphan_result = $conn->query($orphan_query);

if ($orphan_result->num_rows > 0) {
    echo '<p class="error">Found ' . $orphan_result->num_rows . ' ticket(s) referencing non-existent buses!</p>';
    echo '<table>';
    echo '<tr><th>Ticket ID</th><th>Passenger</th><th>Bus ID (Missing)</th><th>Date</th><th>Fare</th><th>Action</th></tr>';
    while ($ticket = $orphan_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $ticket['id'] . '</td>';
        echo '<td>' . $ticket['uname'] . '</td>';
        echo '<td class="error">' . $ticket['bus_id'] . ' (Not Found)</td>';
        echo '<td>' . $ticket['jdate'] . '</td>';
        echo '<td>' . $ticket['fare'] . '</td>';
        echo '<td><a href="?delete_ticket=' . $ticket['id'] . '">Delete</a></td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="success">✓ All tickets have valid bus references</p>';
}
echo '</div>';

// Step 3: Check approved vs unapproved buses with tickets
echo '<div class="section">';
echo '<h2>Step 3: Tickets for Unapproved Buses</h2>';
$unapproved_query = "SELECT t.*, b.bname, b.approved, u.uname
                     FROM tickets t
                     INNER JOIN buses b ON t.bus_id = b.id
                     INNER JOIN users u ON t.passenger_id = u.id
                     WHERE b.approved = 0";
$unapproved_result = $conn->query($unapproved_query);

if ($unapproved_result->num_rows > 0) {
    echo '<p class="warning">Found ' . $unapproved_result->num_rows . ' ticket(s) for unapproved buses</p>';
    echo '<table>';
    echo '<tr><th>Ticket ID</th><th>Passenger</th><th>Bus Name</th><th>Date</th><th>Fare</th><th>Action</th></tr>';
    while ($ticket = $unapproved_result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $ticket['id'] . '</td>';
        echo '<td>' . $ticket['uname'] . '</td>';
        echo '<td>' . $ticket['bname'] . ' <span class="warning">(Unapproved)</span></td>';
        echo '<td>' . $ticket['jdate'] . '</td>';
        echo '<td>' . $ticket['fare'] . '</td>';
        echo '<td><a href="?approve_bus=' . $ticket['bus_id'] . '">Approve Bus</a></td>';
        echo '</tr>';
    }
    echo '</table>';
} else {
    echo '<p class="success">✓ All buses with tickets are approved</p>';
}
echo '</div>';

// Step 4: Show all tickets with their bus info
echo '<div class="section">';
echo '<h2>Step 4: All Tickets Overview</h2>';
$all_tickets = "SELECT t.id, t.passenger_id, t.bus_id, t.jdate, t.fare,
                u.uname, b.bname, b.from_loc, b.to_loc, b.approved
                FROM tickets t
                LEFT JOIN users u ON t.passenger_id = u.id
                LEFT JOIN buses b ON t.bus_id = b.id
                ORDER BY t.id DESC";
$all_result = $conn->query($all_tickets);

echo '<table>';
echo '<tr><th>Ticket ID</th><th>Passenger</th><th>Bus</th><th>Route</th><th>Date</th><th>Fare</th><th>Status</th></tr>';
while ($ticket = $all_result->fetch_assoc()) {
    $status = '';
    if (!$ticket['bname']) {
        $status = '<span class="error">Bus Missing</span>';
    } elseif ($ticket['approved'] == 0) {
        $status = '<span class="warning">Bus Unapproved</span>';
    } else {
        $status = '<span class="success">OK</span>';
    }
    
    echo '<tr>';
    echo '<td>' . $ticket['id'] . '</td>';
    echo '<td>' . $ticket['uname'] . '</td>';
    echo '<td>' . ($ticket['bname'] ?: 'N/A') . '</td>';
    echo '<td>' . ($ticket['from_loc'] ?: 'N/A') . ' → ' . ($ticket['to_loc'] ?: 'N/A') . '</td>';
    echo '<td>' . $ticket['jdate'] . '</td>';
    echo '<td>৳' . $ticket['fare'] . '</td>';
    echo '<td>' . $status . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

// Handle actions
if (isset($_GET['delete_ticket'])) {
    $ticket_id = intval($_GET['delete_ticket']);
    if ($conn->query("DELETE FROM tickets WHERE id = $ticket_id")) {
        echo '<div class="section"><p class="success">✓ Deleted ticket #' . $ticket_id . '</p></div>';
        echo '<meta http-equiv="refresh" content="2">';
    }
}

if (isset($_GET['approve_bus'])) {
    $bus_id = intval($_GET['approve_bus']);
    if ($conn->query("UPDATE buses SET approved = 1 WHERE id = $bus_id")) {
        echo '<div class="section"><p class="success">✓ Approved bus #' . $bus_id . '</p></div>';
        echo '<meta http-equiv="refresh" content="2">';
    }
}

// Auto-fix option
echo '<div class="section">';
echo '<h2>Step 5: Auto-Fix Options</h2>';
echo '<p><a href="?auto_approve_all" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Auto-Approve All Buses</a></p>';
echo '<p><a href="?delete_orphaned" style="background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Delete Orphaned Tickets</a></p>';
echo '</div>';

if (isset($_GET['auto_approve_all'])) {
    $conn->query("UPDATE buses SET approved = 1");
    echo '<div class="section"><p class="success">✓ All buses approved!</p></div>';
    echo '<meta http-equiv="refresh" content="2">';
}

if (isset($_GET['delete_orphaned'])) {
    $conn->query("DELETE t FROM tickets t LEFT JOIN buses b ON t.bus_id = b.id WHERE b.id IS NULL");
    echo '<div class="section"><p class="success">✓ Deleted orphaned tickets!</p></div>';
    echo '<meta http-equiv="refresh" content="2">';
}

echo '<hr>';
echo '<p><a href="history.php">← Back to History</a> | <a href="buy_ticket.php">Buy Tickets</a></p>';

$conn->close();
echo '</body></html>';
?>