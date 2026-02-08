<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 10px 0; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        table td, table th { padding: 8px; border: 1px solid #ddd; text-align: left; }
        table th { background: #333; color: white; }
    </style>
</head>
<body>

<h1>🔍 Bus Ticket System Diagnostic</h1>

<!-- FILE STRUCTURE CHECK -->
<div class="section">
    <h2>1. File Structure Check</h2>
    <?php
    $required_files = [
        'inc/database.php',
        'inc/ajax.php',
        'inc/basic_template.php',
        'buy_ticket.php',
        'history.php',
        'index.php'
    ];
    
    echo '<table>';
    echo '<tr><th>File</th><th>Status</th><th>Path</th></tr>';
    foreach ($required_files as $file) {
        $exists = file_exists($file);
        $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>';
        $fullPath = $exists ? realpath($file) : 'N/A';
        echo "<tr><td>$file</td><td>$status</td><td>$fullPath</td></tr>";
    }
    echo '</table>';
    ?>
</div>

<!-- DATABASE CONNECTION CHECK -->
<div class="section">
    <h2>2. Database Connection</h2>
    <?php
    if (file_exists('inc/database.php')) {
        require_once 'inc/database.php';
        try {
            $conn = initDB();
            if ($conn) {
                echo '<p class="success">✓ Database connection successful!</p>';
                
                // Check tables
                $tables = ['users', 'buses', 'tickets', 'locations'];
                echo '<h3>Tables Check:</h3><table>';
                echo '<tr><th>Table</th><th>Status</th><th>Row Count</th></tr>';
                
                foreach ($tables as $table) {
                    $result = $conn->query("SELECT COUNT(*) as cnt FROM $table");
                    if ($result) {
                        $row = $result->fetch_assoc();
                        echo "<tr><td>$table</td><td class='success'>✓ EXISTS</td><td>{$row['cnt']} rows</td></tr>";
                    } else {
                        echo "<tr><td>$table</td><td class='error'>✗ NOT FOUND</td><td>-</td></tr>";
                    }
                }
                echo '</table>';
                
                $conn->close();
            }
        } catch (Exception $e) {
            echo '<p class="error">✗ Database connection failed: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<p class="error">✗ inc/database.php not found!</p>';
    }
    ?>
</div>

<!-- SESSION CHECK -->
<div class="section">
    <h2>3. Session Information</h2>
    <?php
    if (isset($_SESSION['user'])) {
        echo '<p class="success">✓ User is logged in</p>';
        echo '<pre>';
        print_r($_SESSION['user']);
        echo '</pre>';
    } else {
        echo '<p class="warning">⚠ No user logged in</p>';
        echo '<p><a href="index.php">Go to Login Page</a></p>';
    }
    ?>
</div>

<!-- AJAX ENDPOINT TEST -->
<div class="section">
    <h2>4. AJAX Endpoint Tests</h2>
    <?php
    $ajax_tests = [
        'locations' => 'inc/ajax.php?type=locations',
        'showseats' => 'inc/ajax.php?type=showseats&bus=1&date=01/02/2026'
    ];
    
    echo '<table>';
    echo '<tr><th>Test</th><th>URL</th><th>Status</th></tr>';
    
    foreach ($ajax_tests as $test => $url) {
        $full_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/' . $url;
        echo "<tr><td>$test</td><td><a href='$url' target='_blank'>$url</a></td>";
        
        if (file_exists('inc/ajax.php')) {
            echo "<td class='success'>✓ File exists - <a href='$url' target='_blank'>Test it</a></td>";
        } else {
            echo "<td class='error'>✗ ajax.php not found</td>";
        }
        echo '</tr>';
    }
    echo '</table>';
</div>

<!-- PHP INFO -->
<div class="section">
    <h2>5. PHP Configuration</h2>
    <table>
        <tr><th>Setting</th><th>Value</th></tr>
        <tr><td>PHP Version</td><td><?php echo phpversion(); ?></td></tr>
        <tr><td>Display Errors</td><td><?php echo ini_get('display_errors') ? 'ON' : 'OFF'; ?></td></tr>
        <tr><td>Error Reporting</td><td><?php echo error_reporting(); ?></td></tr>
        <tr><td>Max Execution Time</td><td><?php echo ini_get('max_execution_time'); ?> seconds</td></tr>
        <tr><td>Memory Limit</td><td><?php echo ini_get('memory_limit'); ?></td></tr>
        <tr><td>Document Root</td><td><?php echo $_SERVER['DOCUMENT_ROOT']; ?></td></tr>
        <tr><td>Current Directory</td><td><?php echo getcwd(); ?></td></tr>
    </table>
</div>

<!-- SAMPLE DATA CHECK -->
<div class="section">
    <h2>6. Sample Data</h2>
    <?php
    if (file_exists('inc/database.php')) {
        require_once 'inc/database.php';
        $conn = initDB();
        
        // Check buses
        echo '<h3>Sample Buses:</h3>';
        $result = $conn->query("SELECT * FROM buses LIMIT 3");
        if ($result && $result->num_rows > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Name</th><th>Bus No</th><th>From</th><th>To</th><th>Fare</th></tr>';
            while ($row = $result->fetch_assoc()) {
                echo "<tr><td>{$row['id']}</td><td>{$row['bname']}</td><td>{$row['bus_no']}</td><td>{$row['from_loc']}</td><td>{$row['to_loc']}</td><td>{$row['fare']}</td></tr>";
            }
            echo '</table>';
        } else {
            echo '<p class="warning">⚠ No buses found in database</p>';
        }
        
        // Check tickets
        echo '<h3>Recent Tickets:</h3>';
        $result = $conn->query("SELECT * FROM tickets ORDER BY id DESC LIMIT 5");
        if ($result && $result->num_rows > 0) {
            echo '<table>';
            echo '<tr><th>ID</th><th>Passenger ID</th><th>Bus ID</th><th>Date</th><th>Fare</th><th>Seats</th></tr>';
            while ($row = $result->fetch_assoc()) {
                $seats = unserialize($row['seats']);
                $seatsList = is_array($seats) ? implode(', ', $seats) : 'Error';
                echo "<tr><td>{$row['id']}</td><td>{$row['passenger_id']}</td><td>{$row['bus_id']}</td><td>{$row['jdate']}</td><td>{$row['fare']}</td><td>$seatsList</td></tr>";
            }
            echo '</table>';
        } else {
            echo '<p class="warning">⚠ No tickets found in database</p>';
        }
        
        $conn->close();
    }
    ?>
</div>

<!-- RECOMMENDATIONS -->
<div class="section">
    <h2>7. Quick Fixes</h2>
    <h3>If ajax.php is not found:</h3>
    <ol>
        <li>Verify the file exists at: <code><?php echo realpath('.'); ?>/inc/ajax.php</code></li>
        <li>Check file permissions (should be 644)</li>
        <li>Check spelling: AJAX vs ajax (case-sensitive on Linux)</li>
    </ol>
    
    <h3>If database connection fails:</h3>
    <ol>
        <li>Check MySQL is running</li>
        <li>Verify credentials in inc/database.php</li>
        <li>Check database name is correct</li>
    </ol>
    
    <h3>If no user is logged in:</h3>
    <ol>
        <li><a href="index.php">Go to Login Page</a></li>
        <li>Use default credentials: admin/admin or create new account</li>
    </ol>
</div>

<div class="section">
    <h2>8. Test AJAX Directly</h2>
    <p>Click these links to test AJAX endpoints:</p>
    <ul>
        <li><a href="inc/ajax.php?type=locations" target="_blank">Test Locations Endpoint</a></li>
        <li><a href="inc/ajax.php?type=showseats&bus=1&date=01/02/2026" target="_blank">Test Showseats Endpoint</a></li>
    </ul>
    <p><strong>Expected Result:</strong> You should see JSON data or HTML code, not a 404 error.</p>
</div>

    <?php
    echo '<hr>';
    echo '<p style="text-align: center; color: #666;">';
    echo '<a href="index.php">← Back to Home</a> | ';
    echo '<a href="buy_ticket.php">Buy Tickets</a> | ';
    echo '<a href="history.php">History</a>';
    echo '</p>';
    ?>

</body>
</html>