<?php
session_start();
require_once 'database.php';

if (!isset($_GET['type'])) exit();

switch ($_GET['type']) {

    case 'locations':
        $conn = initDB();
        $res  = $conn->query("SELECT name FROM locations ORDER BY name");
        $out  = [];
        while ($row = $res->fetch_assoc()) $out[] = $row['name'];
        echo json_encode($out);
        $conn->close();
        break;

    case 'username':
        if (strlen($_GET['q'] ?? '') < 3) { echo '<span class="text-danger">Too short</span>'; break; }
        $conn = initDB();
        $q    = $conn->real_escape_string($_GET['q']);
        $res  = $conn->query("SELECT id FROM users WHERE uname='$q'");
        echo $res->num_rows == 0
            ? '<span class="text-success">Username Available</span>'
            : '<span class="text-danger">Username Unavailable</span>';
        $conn->close();
        break;

    case 'email':
        if (!filter_var($_GET['q'] ?? '', FILTER_VALIDATE_EMAIL)) { echo '<span class="text-danger">Invalid Email</span>'; break; }
        $conn = initDB();
        $q    = $conn->real_escape_string($_GET['q']);
        $res  = $conn->query("SELECT id FROM users WHERE email='$q'");
        echo $res->num_rows == 0 ? '' : '<span class="text-danger">Email Already Exists</span>';
        $conn->close();
        break;

    case 'showseats':
        $con    = initDB();
        $busid  = 0;
        $date   = '';
        $fare   = 0;

        if (isset($_GET['ticket'])) {
            $tid     = (int)$_GET['ticket'];
            $query   = "SELECT bus_id, jdate, seats, fare FROM tickets WHERE id=$tid";
            $viewing = true;
        } else {
            $busid   = (int)($_GET['bus']  ?? 0);
            $date    = $con->real_escape_string($_GET['date'] ?? '');
            $has_col = $con->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
            $clause  = ($has_col && $has_col->num_rows > 0) ? " AND booking_confirmed=1" : "";
            $query   = "SELECT seats FROM tickets WHERE bus_id=$busid AND jdate='$date'$clause";
            $viewing = false;
        }

        $reserved = [];
        $res = $con->query($query);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $d = @unserialize($row['seats']);
                if ($d !== false && is_array($d)) $reserved = array_merge($reserved, $d);
                if ($viewing) {
                    $busid = (int)$row['bus_id'];
                    $date  = $row['jdate'];
                    $fare  = (int)$row['fare'];
                }
            }
        }
        $con->close();

        $con     = initDB();
        $res     = $con->query("SELECT * FROM buses WHERE id=" . (int)$busid);
        $businfo = $res->fetch_assoc();
        $con->close();

        if (!$businfo) { echo '<div class="alert alert-danger">Bus not found.</div>'; break; }

        // Build seat list
        $totalSeats = isset($businfo['total_seats']) ? (int)$businfo['total_seats'] : 40;
        $seats      = [];
        $rowLabels  = range('A','Z');
        $cols       = 4;
        $rows       = (int)ceil($totalSeats / $cols);
        for ($r = 0; $r < $rows; $r++) {
            for ($c = 1; $c <= $cols; $c++) {
                $seats[] = $rowLabels[$r] . $c;
                if (count($seats) >= $totalSeats) break 2;
            }
        }

        // Seat categories from DB
        $studentSeats = !empty($businfo['student_seats'])
            ? array_map('trim', explode(',', $businfo['student_seats'])) : [];
        $staffSeats   = !empty($businfo['staff_seats'])
            ? array_map('trim', explode(',', $businfo['staff_seats']))   : [];
        $generalSeats = !empty($businfo['general_seats'])
            ? array_map('trim', explode(',', $businfo['general_seats'])) : [];

        // Fallback: no categories defined — all general
        if (empty($studentSeats) && empty($staffSeats) && empty($generalSeats)) {
            $generalSeats = $seats;
        }

        $userType = $_SESSION['user']['utype'] ?? 'Passenger';
        $baseFare = (int)$businfo['fare'];
        $discount = 0;
        if ($userType === 'Student') $discount = 0.10;
        elseif ($userType === 'Staff') $discount = 0.05;

        $TOTAL_SECONDS     = 5 * 60;   // 300 s
        $UNLOCK_AT_SECONDS = 2 * 60;   // 120 s — student can book staff seats in last 2 min

        // Pre-compute counts for info panel
        $bookedStudent = count(array_intersect($reserved, $studentSeats));
        $bookedStaff   = count(array_intersect($reserved, $staffSeats));
        $bookedGeneral = count(array_intersect($reserved, $generalSeats));
        ?>

<div class="modal" tabindex="-1" role="dialog"
     style="display:block;background:rgba(0,0,0,.55);position:fixed;top:0;left:0;width:100%;height:100%;z-index:1050;">
<div class="modal-dialog modal-lg" role="document" style="margin:40px auto;">
<div class="modal-content">

    <div class="modal-header">
        <h5 class="modal-title">
            <?= htmlspecialchars($businfo['bname']) ?> &mdash; <?= htmlspecialchars($businfo['bus_no']) ?>
        </h5>
        <button type="button" class="close" id="btnClose"><span>&times;</span></button>
    </div>

    <?php if (!$viewing): ?>
    <form method="POST" action="book.php" id="bookingForm">
        <input type="hidden" name="bus_id" value="<?= (int)$busid ?>"/>
        <input type="hidden" name="jdate"  value="<?= htmlspecialchars($date) ?>"/>
        <input type="hidden" name="fare"   id="hiddenFare" value="0"/>
    <?php endif; ?>

    <div class="modal-body">

        <?php if (!$viewing): ?>
        <!-- 5-MINUTE COUNTDOWN -->
        <div id="timerBox" class="alert alert-warning text-center mb-3">
            <strong>&#x23F1; Booking window:</strong>
            <span id="timerDisplay" style="font-size:1.5em;font-weight:bold;margin-left:8px;">5:00</span>
            <div id="timerMsg" style="font-size:.83em;margin-top:3px;">
                Select your seats and confirm within <strong>5 minutes</strong>.
            </div>
        </div>
        <?php endif; ?>

        <div class="row">

            <!-- SEAT GRID -->
            <div class="col-md-6 text-center">
                <h6>Select Seats</h6>

                <!-- Colour legend -->
                <p class="small mb-1">
                    <span style="display:inline-block;width:13px;height:13px;background:#28a745;border:1px solid #aaa;vertical-align:middle;"></span> Free &nbsp;
                    <span style="display:inline-block;width:13px;height:13px;background:#dc3545;border:1px solid #aaa;vertical-align:middle;"></span> Booked &nbsp;
                    <span style="display:inline-block;width:13px;height:13px;background:#007bff;border:1px solid #aaa;vertical-align:middle;"></span> Selected
                </p>
                <?php if (!$viewing): ?>
                <p class="small mb-2">
                    <span style="color:#6f42c1;font-weight:bold;">&#9632; S</span> Student seat &nbsp;
                    <span style="color:#fd7e14;font-weight:bold;">&#9632; St</span> Staff seat &nbsp;
                    <span style="color:#6c757d;">&#9632; G</span> General
                </p>
                <?php endif; ?>

                <div>
                <?php
                for ($i = 0; $i < count($seats); $i++) {
                    $s   = $seats[$i];
                    $isR = in_array($s, $reserved);

                    if (in_array($s, $studentSeats))   $cat = 'student';
                    elseif (in_array($s, $staffSeats)) $cat = 'staff';
                    else                                $cat = 'general';

                    // Seat access rules
                    $blocked = false;
                    if (!$viewing) {
                        if ($userType === 'Student'   && $cat === 'staff')    $blocked = true; // unlocks at 2-min mark
                        if ($userType === 'Staff'     && $cat === 'student')  $blocked = true; // never
                        if ($userType === 'Passenger' && $cat !== 'general')  $blocked = true; // never
                    }

                    $attrs  = 'type="checkbox" class="seat seat-' . $cat . '" ';
                    $attrs .= 'name="seats[]" value="' . $s . '" title="' . $s . ' (' . ucfirst($cat) . ')" ';
                    $attrs .= 'data-cat="' . $cat . '" ';

                    if ($isR || ($blocked && !$viewing)) $attrs .= 'disabled ';
                    if ($isR && $viewing)                $attrs .= 'checked ';
                    if ($blocked && !$viewing && !$isR)  $attrs .= 'data-blocked="1" ';

                    echo '<input ' . $attrs . '/>';

                    if (($i + 1) % $cols == 0) echo '<br/>';
                    elseif (($i + 1) % 2 == 0) echo '<span style="margin-left:16px"></span>';
                }
                ?>
                </div>

                <?php if (!$viewing):
                    if ($userType === 'Student'): ?>
                <div id="studentNotice" class="alert alert-info mt-2" style="font-size:.82em;">
                    &#128274; Staff seats will <strong>unlock in the last 2&nbsp;minutes</strong> if still available.
                </div>
                    <?php elseif ($userType === 'Staff'): ?>
                <div class="alert alert-secondary mt-2" style="font-size:.82em;">
                    &#8505; You may book <strong>Staff</strong> or <strong>General</strong> seats only.
                </div>
                    <?php elseif ($userType === 'Passenger'): ?>
                <div class="alert alert-secondary mt-2" style="font-size:.82em;">
                    &#8505; You may book <strong>General</strong> seats only.
                </div>
                    <?php endif;
                endif; ?>
            </div><!-- /seat grid -->

            <!-- INFO PANEL -->
            <div class="col-md-6">
                <strong>Bus Info</strong><br/>
                Route: <?= htmlspecialchars($businfo['from_loc']) ?> &rarr; <?= htmlspecialchars($businfo['to_loc']) ?><br/>
                Departure: <?= htmlspecialchars($businfo['from_time']) ?><br/>
                Arrival: <?= htmlspecialchars($businfo['to_time']) ?><br/>
                Date: <?= htmlspecialchars($date) ?><br/>
                <hr/>

                <?php if (!$viewing): ?>
                <strong>Seat Availability</strong><br/>
                <small>
                    <span style="color:#6f42c1;"><strong>Student:</strong></span>
                    <?= (count($studentSeats) - $bookedStudent) ?>/<?= count($studentSeats) ?> free<br/>
                    <span style="color:#fd7e14;"><strong>Staff:</strong></span>
                    <?= (count($staffSeats) - $bookedStaff) ?>/<?= count($staffSeats) ?> free<br/>
                    <span style="color:#6c757d;"><strong>General:</strong></span>
                    <?= (count($generalSeats) - $bookedGeneral) ?>/<?= count($generalSeats) ?> free<br/>
                </small>
                <hr/>
                <?php endif; ?>

                <strong>Pricing</strong><br/>
                Base fare/seat: &#2547;<?= $baseFare ?><br/>
                <?php if ($discount > 0 && !$viewing): ?>
                <span class="badge badge-success"><?= ($discount*100) ?>% <?= $userType ?> discount</span><br/>
                <?php endif; ?>
                <br/>
                Seats selected: <strong><span id="seatCount">0</span></strong><br/>
                Seat fare: &#2547;<span id="fareDisplay">0</span><br/>
                Service charge: &#2547;50<br/>
                <strong>Total: &#2547;<span id="totalDisplay">50</span></strong>
            </div>

        </div><!-- /.row -->
    </div><!-- /.modal-body -->

    <div class="modal-footer">
        <?php if (!$viewing): ?>
        <button type="submit" class="btn btn-primary" id="confirmBtn">&#10003; Confirm Booking</button>
        </form>
        <?php endif; ?>
        <button type="button" class="btn btn-secondary" id="btnClose2">Close</button>
    </div>

</div></div></div>

<style>
.seat { margin:2px; cursor:pointer; }
.seat.seat-student:not(:disabled) { accent-color:#6f42c1; }
.seat.seat-staff:not(:disabled)   { accent-color:#fd7e14; }
.seat.seat-general:not(:disabled) { accent-color:#6c757d; }
.seat:disabled { opacity:.4; cursor:not-allowed; }
</style>

<script>
(function(){
    var TOTAL      = <?= $TOTAL_SECONDS ?>;
    var UNLOCK_AT  = <?= $UNLOCK_AT_SECONDS ?>;
    var baseFare   = <?= $baseFare ?>;
    var discount   = <?= $discount ?>;
    var userType   = <?= json_encode($userType) ?>;
    var viewing    = <?= $viewing ? 'true' : 'false' ?>;
    var timeLeft   = TOTAL;
    var expired    = false;
    var unlocked   = false;

    // Close handlers
    function closeMe() {
        if (window._bookingTimer) clearInterval(window._bookingTimer);
        $('#seatViewer').empty().hide();
    }
    $('#btnClose, #btnClose2').on('click', closeMe);

    // Fare recalc
    function recalc() {
        var n    = $('input.seat:checked').length;
        var fare = Math.round(n * baseFare * (1 - discount));
        var tot  = fare + 50;
        $('#seatCount').text(n);
        $('#fareDisplay').text(fare);
        $('#totalDisplay').text(tot);
        $('#hiddenFare').val(tot);
    }
    $(document).on('change','input.seat', recalc);

    if (viewing) return;

    // Submit guard
    $(document).on('submit','#bookingForm', function(){
        if (expired) { alert('Booking time has expired. Please search again.'); return false; }
        if ($('input.seat:checked').length === 0) { alert('Please select at least one seat.'); return false; }
        var fare = parseInt($('#hiddenFare').val(), 10);
        if (!fare || fare <= 0) { alert('Please select seats first.'); return false; }
        $('#confirmBtn').prop('disabled',true).text('Saving…');
        return true;
    });

    // Unlock staff seats for students at 2-min mark
    function unlockStaff() {
        if (unlocked || userType !== 'Student') return;
        unlocked = true;
        $('input.seat-staff[data-blocked="1"]').each(function(){
            $(this).prop('disabled', false).removeAttr('data-blocked');
        });
        $('#studentNotice')
            .removeClass('alert-info').addClass('alert-success')
            .html('&#128275; <strong>Staff seats are now unlocked</strong> — book quickly!');
    }

    // Countdown
    window._bookingTimer = setInterval(function(){
        timeLeft--;
        var m = Math.floor(timeLeft/60);
        var s = timeLeft % 60;
        $('#timerDisplay').text(m + ':' + (s<10?'0':'')+s);

        // Unlock at 2-min remaining
        if (timeLeft <= UNLOCK_AT && !unlocked) unlockStaff();

        // Colour changes
        if (timeLeft <= 60) {
            $('#timerBox').removeClass('alert-warning alert-info').addClass('alert-danger');
            $('#timerMsg').html('&#9888; <strong>Under 1 minute left!</strong> Confirm now.');
        } else if (timeLeft <= UNLOCK_AT) {
            $('#timerBox').removeClass('alert-warning').addClass('alert-info');
        }

        // Expired
        if (timeLeft <= 0) {
            clearInterval(window._bookingTimer);
            expired = true;
            $('#timerBox').removeClass('alert-warning alert-info alert-danger').addClass('alert-secondary');
            $('#timerDisplay').text('EXPIRED');
            $('#timerMsg').html('&#9200; Time\'s up. Close this and search again.');
            $('input.seat').prop('disabled', true);
            $('#confirmBtn').prop('disabled', true).text('Expired');
        }
    }, 1000);

})();
</script>
        <?php
        break;

    default: break;
}
?>