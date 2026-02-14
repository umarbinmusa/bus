<?php
session_start();

// Allow Passenger, Student, AND Staff
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['utype'], ['Passenger','Student','Staff'])) {
    header("Location: index.php");
    exit();
}

include 'inc/basic_template.php';
t_header("Bus Ticket Booking — History");
t_login_nav();
t_sidebar();

require_once 'inc/database.php';
$conn    = initDB();
$user_id = (int)$_SESSION['user']['id'];
?>

<div class="container">
    <div id="seatViewer" class="popup"></div>
    <div class="loader text-center" id="wait" style="display:none;">
        <img src="img/bus-loader.gif" alt="Loading..."/>
    </div>

    <?php if (!empty($_GET['booked'])): ?>
    <div class="alert alert-success">
        ✅ <strong>Booking confirmed!</strong>
        Ticket #<?= (int)$_GET['booked'] ?> has been saved.
        <a href="history.php">Refresh</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['error'])): ?>
    <div class="alert alert-danger">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <h4>My Booking History</h4>

    <div class="table-con">
    <div class="row head">
        <div class="col-md-1">ID</div>
        <div class="col-md-2">Bus Name</div>
        <div class="col-md-1">From</div>
        <div class="col-md-1">To</div>
        <div class="col-md-2">Dep. Time</div>
        <div class="col-md-2">Arr. Time</div>
        <div class="col-md-1">Date</div>
        <div class="col-md-1">Fare (৳)</div>
        <div class="col-md-1">Seats</div>
    </div>

    <?php
    $res = $conn->query(
        "SELECT t.id AS t_id, t.jdate, t.fare, t.seats,
                b.bname AS bus_name, b.from_loc, b.to_loc, b.from_time, b.to_time
         FROM   tickets t
         LEFT JOIN buses b ON t.bus_id = b.id
         WHERE  t.passenger_id = $user_id
         ORDER  BY t.id DESC"
    );

    if (!$res) {
        echo '<div class="row content"><div class="col-12">Query error: ' . $conn->error . '</div></div>';
    } elseif ($res->num_rows === 0) {
        echo '<div class="row content"><div class="col-12 text-center py-3">
                No bookings yet. <a href="buy_ticket.php">Book a ticket now!</a>
              </div></div>';
    } else {
        while ($row = $res->fetch_assoc()) {
            $seats_data  = @unserialize($row['seats']);
            $seats_count = is_array($seats_data) ? count($seats_data) : '?';
            echo '
            <div class="content row" data-ticket="' . $row['t_id'] . '" style="cursor:pointer;">
                <div class="col-md-1">'  . $row['t_id']                              . '</div>
                <div class="col-md-2">'  . htmlspecialchars($row['bus_name'] ?? '—') . '</div>
                <div class="col-md-1">'  . htmlspecialchars($row['from_loc'] ?? '—') . '</div>
                <div class="col-md-1">'  . htmlspecialchars($row['to_loc']   ?? '—') . '</div>
                <div class="col-md-2">'  . htmlspecialchars($row['from_time']?? '—') . '</div>
                <div class="col-md-2">'  . htmlspecialchars($row['to_time']  ?? '—') . '</div>
                <div class="col-md-1">'  . htmlspecialchars($row['jdate'])            . '</div>
                <div class="col-md-1">'  . (int)$row['fare']                         . '</div>
                <div class="col-md-1">'  . $seats_count                              . '</div>
            </div>';
        }
    }
    $conn->close();
    ?>
    </div><!-- /.table-con -->
</div>

<script>
// Click a row to view the seat map for that ticket
$(document).on("click", ".content[data-ticket]", function() {
    var ticketId = $(this).data("ticket");
    $("#wait").show();
    $.ajax({
        url: "inc/ajax.php?type=showseats&ticket=" + ticketId,
        success: function(html) {
            $("#wait").hide();
            $("#seatViewer").html(html).show();
        },
        error: function() {
            $("#wait").hide();
            alert("Could not load seat details.");
        }
    });
});
</script>

<?php t_footer(); ?>