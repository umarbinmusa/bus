<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Navigation Test - Bus Ticket System</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f5f5f5; 
        }
        .box {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        h2 { border-bottom: 2px solid #333; padding-bottom: 10px; }
        .button {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
        .button:hover { background: #0056b3; }
    </style>
</head>
<body>

<h1>🔍 Bus Ticket System - Navigation Diagnostic</h1>

<?php
// Test 1: Check current directory
echo '<div class="box">';
echo '<h2>Test 1: File System Check</h2>';
echo '<p><strong>Current Directory:</strong> ' . getcwd() . '</p>';
echo '<p><strong>Script Location:</strong> ' . __FILE__ . '</p>';
echo '<p><strong>Document Root:</strong> ' . $_SERVER['DOCUMENT_ROOT'] . '</p>';

$requiredFiles = [
    'index.php',
    'buy_ticket.php',
    'history.php',
    'inc/database.php',
    'inc/ajax.php',
    'inc/basic_template.php'
];

echo '<h3>Required Files:</h3><ul>';
foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    $status = $exists ? '<span class="success">✓ EXISTS</span>' : '<span class="error">✗ MISSING</span>';
    echo "<li>$file - $status</li>";
}
echo '</ul>';
echo '</div>';

// Test 2: Check database
echo '<div class="box">';
echo '<h2>Test 2: Database Connection</h2>';
if (file_exists('inc/database.php')) {
    require_once 'inc/database.php';
    try {
        $conn = initDB();
        if ($conn) {
            echo '<p class="success">✓ Database connected successfully!</p>';
            
            // Check users table
            $result = $conn->query("SELECT COUNT(*) as cnt FROM users");
            if ($result) {
                $row = $result->fetch_assoc();
                echo '<p>Users in database: ' . $row['cnt'] . '</p>';
                
                // Check for admin user
                $adminCheck = $conn->query("SELECT * FROM users WHERE uname='admin'");
                if ($adminCheck && $adminCheck->num_rows > 0) {
                    echo '<p class="success">✓ Admin user exists</p>';
                } else {
                    echo '<p class="error">✗ Admin user not found!</p>';
                }
            }
            
            $conn->close();
        } else {
            echo '<p class="error">✗ Database connection failed</p>';
        }
    } catch (Exception $e) {
        echo '<p class="error">✗ Error: ' . $e->getMessage() . '</p>';
    }
} else {
    echo '<p class="error">✗ inc/database.php not found!</p>';
}
echo '</div>';

// Test 3: Session Test
echo '<div class="box">';
echo '<h2>Test 3: Session Test</h2>';
session_start();

if (isset($_GET['test_login'])) {
    $_SESSION['user'] = array(
        'id' => 1,
        'uname' => 'testuser',
        'utype' => $_GET['test_login']
    );
    echo '<p class="success">✓ Session created for user type: ' . $_GET['test_login'] . '</p>';
}

if (isset($_SESSION['user'])) {
    echo '<p class="success">✓ Session is active</p>';
    echo '<pre>';
    print_r($_SESSION['user']);
    echo '</pre>';
    echo '<p><a href="?clear_session=1" class="button">Clear Session</a></p>';
} else {
    echo '<p class="warning">No active session</p>';
    echo '<p>Test session creation:</p>';
    echo '<a href="?test_login=Passenger" class="button">Test as Passenger</a>';
    echo '<a href="?test_login=Student" class="button">Test as Student</a>';
    echo '<a href="?test_login=Staff" class="button">Test as Staff</a>';
    echo '<a href="?test_login=Owner" class="button">Test as Owner</a>';
    echo '<a href="?test_login=Admin" class="button">Test as Admin</a>';
}

if (isset($_GET['clear_session'])) {
    session_destroy();
    echo '<p class="success">✓ Session cleared</p>';
    echo '<meta http-equiv="refresh" content="1">';
}
echo '</div>';

// Test 4: Navigation Test
echo '<div class="box">';
echo '<h2>Test 4: Navigation Logic Test</h2>';

if (isset($_SESSION['user'])) {
    $utype = $_SESSION['user']['utype'];
    echo '<p>Current user type: <strong>' . $utype . '</strong></p>';
    
    $redirectTo = '';
    if (in_array($utype, ["Passenger", "Student", "Staff"])) {
        $redirectTo = "buy_ticket.php";
    } elseif ($utype == "Owner") {
        $redirectTo = "my_buses.php";
    } elseif ($utype == "Admin") {
        $redirectTo = "users.php";
    }
    
    echo '<p>Should redirect to: <strong>' . $redirectTo . '</strong></p>';
    
    if (file_exists($redirectTo)) {
        echo '<p class="success">✓ Target file exists</p>';
        echo '<p><a href="' . $redirectTo . '" class="button">Go to ' . $redirectTo . '</a></p>';
    } else {
        echo '<p class="error">✗ Target file does not exist!</p>';
    }
} else {
    echo '<p class="warning">Create a test session above to test navigation</p>';
}
echo '</div>';

// Test 5: Check index.php
echo '<div class="box">';
echo '<h2>Test 5: index.php Content Check</h2>';
if (file_exists('index.php')) {
    $indexContent = file_get_contents('index.php');
    
    // Check for key components
    $checks = [
        'session_start()' => strpos($indexContent, 'session_start()') !== false,
        'Login form' => strpos($indexContent, 'name="login"') !== false,
        'Signup form' => strpos($indexContent, 'name="signup"') !== false,
        'Student option' => strpos($indexContent, 'Student') !== false,
        'Staff option' => strpos($indexContent, 'Staff') !== false,
        'Header redirect' => strpos($indexContent, 'header("Location:') !== false,
    ];
    
    echo '<ul>';
    foreach ($checks as $check => $result) {
        $status = $result ? '<span class="success">✓</span>' : '<span class="error">✗</span>';
        echo "<li>$status $check</li>";
    }
    echo '</ul>';
} else {
    echo '<p class="error">✗ index.php not found!</p>';
}
echo '</div>';

// Test 6: Direct page access
echo '<div class="box">';
echo '<h2>Test 6: Direct Page Access</h2>';
echo '<p>Try accessing pages directly:</p>';
$pages = [
    'index.php' => 'Home Page',
    'buy_ticket.php' => 'Buy Ticket (Passenger)',
    'my_buses.php' => 'My Buses (Owner)',
    'users.php' => 'Users (Admin)',
    'history.php' => 'History (Passenger)',
];

foreach ($pages as $page => $desc) {
    if (file_exists($page)) {
        echo '<a href="' . $page . '" class="button" target="_blank">' . $desc . '</a>';
    } else {
        echo '<span class="error">✗ ' . $desc . ' (missing)</span><br>';
    }
}
echo '</div>';

// Test 7: Server Info
echo '<div class="box">';
echo '<h2>Test 7: Server Information</h2>';
echo '<ul>';
echo '<li>PHP Version: ' . phpversion() . '</li>';
echo '<li>Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . '</li>';
echo '<li>Request URI: ' . $_SERVER['REQUEST_URI'] . '</li>';
echo '<li>Script Name: ' . $_SERVER['SCRIPT_NAME'] . '</li>';
echo '</ul>';
echo '</div>';

// Test 8: Manual Login Test
echo '<div class="box">';
echo '<h2>Test 8: Manual Login Test</h2>';
echo '<p>Use this form to test login without index.php:</p>';
echo '<form method="post" action="">';
echo '<input type="text" name="test_uname" placeholder="Username" value="admin" />';
echo '<input type="password" name="test_pass" placeholder="Password" value="admin" />';
echo '<button type="submit" name="test_login_submit">Test Login</button>';
echo '</form>';

if (isset($_POST['test_login_submit'])) {
    require_once 'inc/database.php';
    $conn = initDB();
    $uname = $conn->real_escape_string($_POST['test_uname']);
    $pass = $conn->real_escape_string($_POST['test_pass']);
    
    $res = $conn->query("SELECT id, utype FROM users WHERE uname='$uname' AND password='$pass'");
    
    if ($res && $res->num_rows > 0) {
        $data = $res->fetch_assoc();
        $_SESSION['user'] = array(
            'id' => $data['id'],
            'uname' => $uname,
            'utype' => $data['utype']
        );
        echo '<p class="success">✓ Login successful! User type: ' . $data['utype'] . '</p>';
        echo '<p>Redirecting...</p>';
        
        // Determine redirect
        if (in_array($data['utype'], ["Passenger", "Student", "Staff"])) {
            $redirect = "buy_ticket.php";
        } elseif ($data['utype'] == "Owner") {
            $redirect = "my_buses.php";
        } elseif ($data['utype'] == "Admin") {
            $redirect = "users.php";
        }
        
        echo '<meta http-equiv="refresh" content="2;url=' . $redirect . '">';
        echo '<p><a href="' . $redirect . '" class="button">Click here if not redirected</a></p>';
    } else {
        echo '<p class="error">✗ Login failed</p>';
    }
    $conn->close();
}
echo '</div>';

?>

<div class="box">
    <h2>Quick Actions</h2>
    <a href="test_navigation.php" class="button">Refresh This Page</a>
    <a href="index.php" class="button">Go to Index</a>
    <a href="diagnostic.php" class="button">Full Diagnostic</a>
</div>

<div class="box">
    <h2>📝 What to Check</h2>
    <ol>
        <li>All files must be marked as "EXISTS" in Test 1</li>
        <li>Database must connect successfully in Test 2</li>
        <li>Admin user must exist</li>
        <li>Test session creation and navigation in Test 3 & 4</li>
        <li>Try manual login in Test 8</li>
    </ol>
</div>

</body>
</html>