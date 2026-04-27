<?php
session_start();

if (!isset($_SESSION['user'])) { header("Location: index.php"); exit(); }

if (!in_array($_SESSION['user']['utype'], ["Passenger","Student","Staff"])) {
    if ($_SESSION['user']['utype'] == "Owner")     { header("Location: my_buses.php"); exit(); }
    elseif ($_SESSION['user']['utype'] == "Admin") { header("Location: users.php");    exit(); }
    else { session_destroy(); header("Location: index.php"); exit(); }
}

require_once 'inc/database.php';

// ── AJAX: start the 5-minute booking timer ────────────────────
// Called by JS when the user clicks "Book Now", before the modal loads.
if (isset($_POST['start_timer'])) {
    header('Content-Type: application/json');
    $busid = (int)($_POST['bus_id'] ?? 0);
    $jdate = trim($_POST['jdate']   ?? '');
    if ($busid > 0 && $jdate !== '') {
        $key = 'booking_start_' . $busid . '_' . md5($jdate);
        $_SESSION[$key] = time();
        echo json_encode(['ok' => true, 'started' => $_SESSION[$key]]);
    } else {
        echo json_encode(['ok' => false]);
    }
    exit();
}

// ── Normal page render ────────────────────────────────────────
include 'inc/basic_template.php';
t_header("Bus Ticket Booking — Buy Ticket");
t_login_nav();
t_sidebar();
?>

<div class="container">
    <h4>Buy Ticket</h4>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Welcome, <strong><?= htmlspecialchars($_SESSION['user']['uname']) ?></strong>!
        &nbsp; Type: <span class="badge badge-primary"><?= $_SESSION['user']['utype'] ?></span>
        <?php
        if ($_SESSION['user']['utype'] === 'Student')
            echo ' &nbsp; <small>&#127891; <strong>10% student discount</strong> on your seats</small>';
        elseif ($_SESSION['user']['utype'] === 'Staff')
            echo ' &nbsp; <small>&#128188; <strong>5% staff discount</strong> on your seats</small>';
        ?>
    </div>

    <!-- Search form -->
    <form method="get" action="buy_ticket.php">
    <table class="table-con">
    <tr class="head">
        <th width="15%">From</th>
        <th width="15%">To</th>
        <th width="15%">Journey Date</th>
        <th></th>
    </tr>
    <tr class="content">
        <td><input type="text" name="from"  class="form-control" id="inputFrom"  value="<?= htmlspecialchars($_GET['from']  ?? '') ?>" required/></td>
        <td><input type="text" name="to"    class="form-control" id="inputTo"    value="<?= htmlspecialchars($_GET['to']    ?? '') ?>" required/></td>
        <td><input type="text" name="jdate" class="form-control" id="inputJdate" value="<?= htmlspecialchars($_GET['jdate'] ?? '') ?>" required/></td>
        <td><input type="submit" class="btn btn-primary" value="Search"/></td>
    </tr>
    </table>
    </form>

    <link rel="stylesheet" href="css/easy-autocomplete.min.css"/>
    <link rel="stylesheet" href="css/jquery-ui.css"/>
    <script src="js/jquery.easy-autocomplete.min.js"></script>
    <script src="js/jquery-ui.js"></script>
    <script>
    var eacOpt = { url: "inc/ajax.php?type=locations", list: { match: { enabled: true } } };
    $("#inputFrom").easyAutocomplete(eacOpt);
    $("#inputTo").easyAutocomplete(eacOpt);
    $("#inputJdate").datepicker({ minDate: 0, dateFormat: "dd/mm/yy" });
    </script>

    <?php
    // ── Search results ──────────────────────────────────────────
    if (!empty($_GET['from']) && !empty($_GET['to']) && !empty($_GET['jdate'])) {
        $conn  = initDB();
        $from  = $conn->real_escape_string($_GET['from']);
        $to    = $conn->real_escape_string($_GET['to']);
        $jdate = $conn->real_escape_string($_GET['jdate']);

        $res = $conn->query("SELECT * FROM buses WHERE from_loc='$from' AND to_loc='$to' AND approved=1");

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
                <th>Fare (৳)</th>
                <th>Available</th>
                <th>Action</th>
            </tr>';

            while ($row = $res->fetch_assoc()) {
                $total   = isset($row['total_seats']) ? (int)$row['total_seats'] : 40;
                $has_col = $conn->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
                $clause  = ($has_col && $has_col->num_rows > 0) ? " AND booking_confirmed=1" : "";

                $bres = $conn->query(
                    "SELECT seats FROM tickets WHERE bus_id=" . (int)$row['id'] .
                    " AND jdate='$jdate'$clause"
                );
                $booked = [];
                if ($bres) {
                    while ($b = $bres->fetch_assoc()) {
                        $d = @unserialize($b['seats']);
                        if ($d !== false && is_array($d)) $booked = array_merge($booked, $d);
                    }
                }
                $available = max(0, $total - count($booked));

                echo '<tr class="content">
                    <td>' . htmlspecialchars($row['bname'])     . '</td>
                    <td>' . htmlspecialchars($row['bus_no'])    . '</td>
                    <td>' . htmlspecialchars($row['from_time']) . '</td>
                    <td>' . htmlspecialchars($row['to_time'])   . '</td>
                    <td>৳' . (int)$row['fare']                  . '</td>
                    <td><strong>' . $available . '</strong></td>
                    <td>';

                if ($available > 0) {
                    // Pass bus id and jdate as data attributes; JS calls start_timer first
                    echo '<button class="btn btn-sm btn-success btn-book"
                            data-bus="'  . (int)$row['id'] . '"
                            data-date="' . htmlspecialchars($jdate, ENT_QUOTES) . '">
                            Book Now
                          </button>';
                } else {
                    echo '<button class="btn btn-sm btn-secondary" disabled>Full</button>';
                }

                echo '</td></tr>';
            }
            echo '</table>';
        }
        $conn->close();
    }
    ?>
</div>

<!-- Modal container -->
<div id="seatViewer"></div>

<script>
// When "Book Now" is clicked:
//  1. POST to buy_ticket.php to start the 5-min timer in the PHP session
//  2. THEN load the seat modal via inc/ajax.php
$(document).on('click', '.btn-book', function(){
    var busId = $(this).data('bus');
    var jdate = $(this).data('date');
    var btn   = $(this);
    btn.prop('disabled', true).text('Loading…');

    $.ajax({
        url    : 'buy_ticket.php',
        method : 'POST',
        data   : { start_timer: 1, bus_id: busId, jdate: jdate },
        success: function(){
            // Now load the seat modal
            $.ajax({
                url    : 'inc/ajax.php?type=showseats&bus=' + busId + '&date=' + jdate,
                success: function(html){
                    $('#seatViewer').html(html).show();
                    btn.prop('disabled', false).text('Book Now');
                },
                error: function(){
                    alert('Error loading seats. Please try again.');
                    btn.prop('disabled', false).text('Book Now');
                }
            });
        },
        error: function(){
            alert('Could not start booking session. Please try again.');
            btn.prop('disabled', false).text('Book Now');
        }
    });
});
</script>

<?php t_footer(); ?>