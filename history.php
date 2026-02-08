<?php
session_start();
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['utype'], ["Passenger", "Student", "Staff"]))
    header("Location: index.php");
include 'inc/basic_template.php';
t_header("Bus Ticket Booking &mdash; My Booking History");
t_login_nav();
t_sidebar();
?>

<div class="container">
    <h4><i class="fa fa-history"></i> My Booking History</h4>
    
    <!-- Filter Options -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="" class="form-inline">
                <label class="mr-2">Filter by:</label>
                <select name="status" class="form-control mr-2">
                    <option value="">All Status</option>
                    <option value="confirmed" <?php echo (isset($_GET['status']) && $_GET['status']=='confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="cancelled" <?php echo (isset($_GET['status']) && $_GET['status']=='cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="expired" <?php echo (isset($_GET['status']) && $_GET['status']=='expired') ? 'selected' : ''; ?>>Expired</option>
                </select>
                
                <select name="period" class="form-control mr-2">
                    <option value="">All Time</option>
                    <option value="today" <?php echo (isset($_GET['period']) && $_GET['period']=='today') ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo (isset($_GET['period']) && $_GET['period']=='week') ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo (isset($_GET['period']) && $_GET['period']=='month') ? 'selected' : ''; ?>>This Month</option>
                </select>
                
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
                <a href="booking_history.php" class="btn btn-secondary btn-sm ml-2"><i class="fa fa-refresh"></i> Reset</a>
            </form>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-3">
        <?php
        require_once 'inc/database.php';
        $conn = initDB();
        $userId = $_SESSION['user']['id'];
        
        // Get statistics
        $stats = $conn->query("SELECT 
            COUNT(*) as total_bookings,
            SUM(CASE WHEN booking_status='confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN booking_status='cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(seat_count) as total_seats,
            SUM(final_amount) as total_spent
            FROM booking_history 
            WHERE user_id=$userId")->fetch_assoc();
        ?>
        
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total_bookings']; ?></h3>
                    <p>Total Bookings</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3><?php echo $stats['confirmed']; ?></h3>
                    <p>Confirmed</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total_seats']; ?></h3>
                    <p>Seats Booked</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3>৳<?php echo number_format($stats['total_spent'], 0); ?></h3>
                    <p>Total Spent</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Booking History Table -->
    <div class="table-con">
        <div class="row head">
            <div class="col-md-1">ID</div>
            <div class="col-md-2">Journey Details</div>
            <div class="col-md-2">Bus Info</div>
            <div class="col-md-1">Seats</div>
            <div class="col-md-1">Category</div>
            <div class="col-md-2">Amount</div>
            <div class="col-md-2">Booking Time</div>
            <div class="col-md-1">Status</div>
        </div>
        
        <?php
        // Build query with filters
        $query = "SELECT * FROM booking_history WHERE user_id=$userId";
        
        if (isset($_GET['status']) && $_GET['status'] != '') {
            $query .= " AND booking_status='".$_GET['status']."'";
        }
        
        if (isset($_GET['period'])) {
            switch ($_GET['period']) {
                case 'today':
                    $query .= " AND DATE(booking_time) = CURDATE()";
                    break;
                case 'week':
                    $query .= " AND YEARWEEK(booking_time) = YEARWEEK(NOW())";
                    break;
                case 'month':
                    $query .= " AND MONTH(booking_time) = MONTH(NOW()) AND YEAR(booking_time) = YEAR(NOW())";
                    break;
            }
        }
        
        $query .= " ORDER BY booking_time DESC LIMIT 50";
        
        $res = $conn->query($query);
        
        if ($res->num_rows == 0) {
            echo '<div class="row">
                <div class="col-md-12 text-center p-4">
                    <i class="fa fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                    <p>No booking history found</p>
                </div>
            </div>';
        } else {
            while ($row = $res->fetch_assoc()) {
                $statusClass = '';
                $statusIcon = '';
                
                switch ($row['booking_status']) {
                    case 'confirmed':
                        $statusClass = 'badge-success';
                        $statusIcon = 'fa-check-circle';
                        break;
                    case 'cancelled':
                        $statusClass = 'badge-danger';
                        $statusIcon = 'fa-times-circle';
                        break;
                    case 'expired':
                        $statusClass = 'badge-warning';
                        $statusIcon = 'fa-clock-o';
                        break;
                    default:
                        $statusClass = 'badge-secondary';
                        $statusIcon = 'fa-question-circle';
                }
                
                $categoryBadge = '';
                switch ($row['seat_category']) {
                    case 'student':
                        $categoryBadge = '<span class="badge badge-primary">Student</span>';
                        break;
                    case 'staff':
                        $categoryBadge = '<span class="badge badge-success">Staff</span>';
                        break;
                    case 'general':
                        $categoryBadge = '<span class="badge badge-secondary">General</span>';
                        break;
                    case 'mixed':
                        $categoryBadge = '<span class="badge badge-info">Mixed</span>';
                        break;
                }
                
                // Get seat numbers from serialized data
                $seats = unserialize($row['seats_booked']);
                $seatList = is_array($seats) ? implode(', ', $seats) : $row['seats_booked'];
                
                echo '
                <div class="row content">
                    <div class="col-md-1"><strong>#'.$row['id'].'</strong></div>
                    <div class="col-md-2">
                        <strong>'.$row['from_loc'].' → '.$row['to_loc'].'</strong><br/>
                        <small>'.$row['jdate'].'</small>
                    </div>
                    <div class="col-md-2">
                        '.$row['bus_name'].'<br/>
                        <small>'.$row['bus_no'].'</small>
                    </div>
                    <div class="col-md-1">
                        <strong>'.$row['seat_count'].'</strong><br/>
                        <small title="'.$seatList.'">'.(strlen($seatList) > 15 ? substr($seatList, 0, 15).'...' : $seatList).'</small>
                    </div>
                    <div class="col-md-1">
                        '.$categoryBadge.'
                    </div>
                    <div class="col-md-2">
                        ';
                        
                if ($row['discount_percent'] > 0) {
                    echo '<del>৳'.number_format($row['total_fare'], 0).'</del><br/>';
                }
                
                echo '<strong>৳'.number_format($row['final_amount'], 0).'</strong>';
                
                if ($row['discount_percent'] > 0) {
                    echo '<br/><small class="text-success">'.$row['discount_percent'].'% OFF</small>';
                }
                
                echo '
                    </div>
                    <div class="col-md-2">
                        <small>'.date('d M Y', strtotime($row['booking_time'])).'<br/>'.date('h:i A', strtotime($row['booking_time'])).'</small>
                    </div>
                    <div class="col-md-1">
                        <span class="badge '.$statusClass.'">
                            <i class="fa '.$statusIcon.'"></i> '.ucfirst($row['booking_status']).'
                        </span>
                    </div>
                </div>';
            }
        }
        
        $conn->close();
        ?>
    </div>
    
    <!-- Export Options -->
    <div class="mt-3">
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="fa fa-print"></i> Print History
        </button>
        <a href="export_history.php?format=csv" class="btn btn-sm btn-outline-success">
            <i class="fa fa-download"></i> Export to CSV
        </a>
    </div>
</div>

<style>
.card {
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
.table-con .row.content:hover {
    background-color: #f8f9fa;
}
</style>

<?php
t_footer();
?>