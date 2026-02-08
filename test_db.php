<?php
// Simple database test file
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>Database Connection Test</h3>";

require_once 'inc/database.php';

try {
    $conn = initDB();
    
    if ($conn) {
        echo "✓ Database connected successfully!<br><br>";
        
        // Test buses table
        $res = $conn->query("SELECT COUNT(*) as count FROM buses");
        if ($res) {
            $row = $res->fetch_assoc();
            echo "✓ Buses table accessible - Found " . $row['count'] . " buses<br>";
        } else {
            echo "✗ Could not query buses table: " . $conn->error . "<br>";
        }
        
        // Check for new columns
        echo "<br><strong>Checking database structure:</strong><br>";
        
        $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'total_seats'");
        if ($res && $res->num_rows > 0) {
            echo "✓ buses.total_seats column exists<br>";
        } else {
            echo "✗ buses.total_seats column MISSING!<br>";
        }
        
        $res = $conn->query("SHOW COLUMNS FROM buses LIKE 'seat_layout'");
        if ($res && $res->num_rows > 0) {
            echo "✓ buses.seat_layout column exists<br>";
        } else {
            echo "✗ buses.seat_layout column MISSING!<br>";
        }
        
        $res = $conn->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
        if ($res && $res->num_rows > 0) {
            echo "✓ tickets.booking_confirmed column exists<br>";
        } else {
            echo "✗ tickets.booking_confirmed column MISSING!<br>";
        }
        
        // Check a sample bus
        echo "<br><strong>Sample Bus Data:</strong><br>";
        $res = $conn->query("SELECT id, bname, bus_no, total_seats, seat_layout, approved FROM buses LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $bus = $res->fetch_assoc();
            echo "<pre>";
            print_r($bus);
            echo "</pre>";
        } else {
            echo "No buses found in database<br>";
        }
        
        $conn->close();
    } else {
        echo "✗ Database connection FAILED!<br>";
        echo "Check your inc/database.php file";
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage();
}
?>