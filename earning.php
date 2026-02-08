<?php
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['utype'] != "Owner")
    header("Location: index.php");

include 'inc/basic_template.php';
t_header("Bus Ticket Booking &mdash; Earnings & Analytics");
t_login_nav();
t_owner_sidebar();
?>

<div class="container">
    <h4><i class="fa fa-money"></i> My Earnings & Analytics</h4>
    
    <!-- Date Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="get" action="" class="form-inline">
                <label class="mr-2">View Earnings:</label>
                <select name="bus" class="form-control mr-2">
                    <option value="">All Buses</option>
                    <?php
                    require_once 'inc/database.php';
                    $conn = initDB();
                    $ownerId = $_SESSION['user']['id'];
                    $busRes = $conn->query("SELECT id, bname, bus_no FROM buses WHERE owner_id=$ownerId");
                    while ($bus = $busRes->fetch_assoc()) {
                        $selected = (isset($_GET['bus']) && $_GET['bus']==$bus['id']) ? 'selected' : '';
                        echo '<option value="'.$bus['id'].'" '.$selected.'>'.$bus['bname'].' ('.$bus['bus_no'].')</option>';
                    }
                    ?>
                </select>
                
                <select name="period" class="form-control mr-2">
                    <option value="all">All Time</option>
                    <option value="today" <?php echo (isset($_GET['period']) && $_GET['period']=='today') ? 'selected' : ''; ?>>Today</option>
                    <option value="week" <?php echo (isset($_GET['period']) && $_GET['period']=='week') ? 'selected' : ''; ?>>This Week</option>
                    <option value="month" <?php echo (isset($_GET['period']) && $_GET['period']=='month') ? 'selected' : ''; ?>>This Month</option>
                </select>
                
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Apply</button>
                <a href="driver_earnings.php" class="btn btn-secondary btn-sm ml-2"><i class="fa fa-refresh"></i> Reset</a>
            </form>
        </div>
    </div>
    
    <!-- Summary Statistics -->
    <div class="row mb-3">
        <?php
        // Build query for statistics
        $statsQuery = "SELECT 
            SUM(total_seats_sold) as total_seats,
            SUM(student_seats_sold) as student_seats,
            SUM(staff_seats_sold) as staff_seats,
            SUM(general_seats_sold) as general_seats,
            SUM(total_revenue) as gross_revenue,
            SUM(commission_amount) as total_commission,
            SUM(net_earnings) as net_earnings,
            COUNT(DISTINCT jdate) as trip_days
            FROM driver_earnings 
            WHERE owner_id=$ownerId";
        
        if (isset($_GET['bus']) && $_GET['bus'] != '') {
            $statsQuery .= " AND bus_id=".$_GET['bus'];
        }
        
        if (isset($_GET['period'])) {
            switch ($_GET['period']) {
                case 'today':
                    $statsQuery .= " AND jdate = '".date('d/m/Y')."'";
                    break;
                case 'week':
                    $statsQuery .= " AND STR_TO_DATE(jdate, '%d/%m/%Y') >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $statsQuery .= " AND STR_TO_DATE(jdate, '%d/%m/%Y') >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        $stats = $conn->query($statsQuery)->fetch_assoc();
        ?>
        
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3><?php echo $stats['total_seats'] ?? 0; ?></h3>
                    <p>Total Seats Sold</p>
                    <small>
                        Students: <?php echo $stats['student_seats'] ?? 0; ?> | 
                        Staff: <?php echo $stats['staff_seats'] ?? 0; ?> | 
                        General: <?php echo $stats['general_seats'] ?? 0; ?>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3>৳<?php echo number_format($stats['gross_revenue'] ?? 0, 0); ?></h3>
                    <p>Gross Revenue</p>
                    <small><?php echo $stats['trip_days'] ?? 0; ?> trip days</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h3>৳<?php echo number_format($stats['total_commission'] ?? 0, 0); ?></h3>
                    <p>Platform Commission (10%)</p>
                    <small>Service fee deducted</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3>৳<?php echo number_format($stats['net_earnings'] ?? 0, 0); ?></h3>
                    <p>Net Earnings</p>
                    <small>Your profit</small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Detailed Earnings Table -->
    <h5><i class="fa fa-table"></i> Detailed Breakdown</h5>
    <div class="table-con">
        <div class="row head">
            <div class="col-md-1">Date</div>
            <div class="col-md-2">Bus</div>
            <div class="col-md-1">Total Seats</div>
            <div class="col-md-2">Seats by Category</div>
            <div class="col-md-2">Revenue</div>
            <div class="col-md-2">Commission</div>
            <div class="col-md-2">Net Earnings</div>
        </div>
        
        <?php
        // Build detailed query
        $detailQuery = "SELECT de.*, b.bname, b.bus_no 
                        FROM driver_earnings de
                        JOIN buses b ON de.bus_id = b.id
                        WHERE de.owner_id=$ownerId";
        
        if (isset($_GET['bus']) && $_GET['bus'] != '') {
            $detailQuery .= " AND de.bus_id=".$_GET['bus'];
        }
        
        if (isset($_GET['period'])) {
            switch ($_GET['period']) {
                case 'today':
                    $detailQuery .= " AND de.jdate = '".date('d/m/Y')."'";
                    break;
                case 'week':
                    $detailQuery .= " AND STR_TO_DATE(de.jdate, '%d/%m/%Y') >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $detailQuery .= " AND STR_TO_DATE(de.jdate, '%d/%m/%Y') >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                    break;
            }
        }
        
        $detailQuery .= " ORDER BY de.last_updated DESC LIMIT 50";
        
        $detailRes = $conn->query($detailQuery);
        
        if ($detailRes->num_rows == 0) {
            echo '<div class="row">
                <div class="col-md-12 text-center p-4">
                    <i class="fa fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                    <p>No earnings data found</p>
                </div>
            </div>';
        } else {
            while ($row = $detailRes->fetch_assoc()) {
                echo '
                <div class="row content">
                    <div class="col-md-1">
                        <strong>'.$row['jdate'].'</strong>
                    </div>
                    <div class="col-md-2">
                        '.$row['bname'].'<br/>
                        <small>'.$row['bus_no'].'</small>
                    </div>
                    <div class="col-md-1 text-center">
                        <strong>'.$row['total_seats_sold'].'</strong>
                    </div>
                    <div class="col-md-2">
                        <small>
                            <span class="badge badge-primary" title="Student Seats">'.$row['student_seats_sold'].'</span>
                            <span class="badge badge-success" title="Staff Seats">'.$row['staff_seats_sold'].'</span>
                            <span class="badge badge-secondary" title="General Seats">'.$row['general_seats_sold'].'</span>
                        </small>
                    </div>
                    <div class="col-md-2">
                        <strong>৳'.number_format($row['total_revenue'], 0).'</strong><br/>
                        <small class="text-muted">
                            S: ৳'.number_format($row['student_revenue'], 0).' | 
                            St: ৳'.number_format($row['staff_revenue'], 0).' | 
                            G: ৳'.number_format($row['general_revenue'], 0).'
                        </small>
                    </div>
                    <div class="col-md-2 text-warning">
                        <strong>৳'.number_format($row['commission_amount'], 0).'</strong><br/>
                        <small>('.$row['commission_rate'].'%)</small>
                    </div>
                    <div class="col-md-2 text-success">
                        <strong>৳'.number_format($row['net_earnings'], 0).'</strong>
                    </div>
                </div>';
            }
        }
        
        $conn->close();
        ?>
    </div>
    
    <!-- Actions -->
    <div class="mt-3">
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">
            <i class="fa fa-print"></i> Print Report
        </button>
        <a href="export_earnings.php?format=csv<?php echo isset($_GET['bus']) ? '&bus='.$_GET['bus'] : ''; ?><?php echo isset($_GET['period']) ? '&period='.$_GET['period'] : ''; ?>" class="btn btn-sm btn-outline-success">
            <i class="fa fa-download"></i> Export to CSV
        </a>
    </div>
    
    <!-- Refresh Earnings Button -->
    <div class="alert alert-info mt-3">
        <strong>Note:</strong> Earnings are automatically calculated when bookings are made. 
        <a href="refresh_earnings.php" class="btn btn-sm btn-primary">
            <i class="fa fa-refresh"></i> Refresh All Earnings
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