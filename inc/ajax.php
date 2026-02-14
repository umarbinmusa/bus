<?php
session_start();
require_once 'database.php';

if (!isset($_GET['type'])) exit();

switch ($_GET['type']) {

    // ── Locations autocomplete ───────────────────────────────────────
    case 'locations':
        $conn = initDB();
        $res  = $conn->query("SELECT name FROM locations ORDER BY name");
        $out  = [];
        while ($row = $res->fetch_assoc()) $out[] = $row['name'];
        echo json_encode($out);
        $conn->close();
        break;

    // ── Username availability check ──────────────────────────────────
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

    // ── Email availability check ─────────────────────────────────────
    case 'email':
        if (!filter_var($_GET['q'] ?? '', FILTER_VALIDATE_EMAIL)) { echo '<span class="text-danger">Invalid Email</span>'; break; }
        $conn = initDB();
        $q    = $conn->real_escape_string($_GET['q']);
        $res  = $conn->query("SELECT id FROM users WHERE email='$q'");
        echo $res->num_rows == 0 ? '' : '<span class="text-danger">Email Already Exists</span>';
        $conn->close();
        break;

    // ── Show seat map ────────────────────────────────────────────────
    case 'showseats':
        $con   = initDB();
        $busid = 0;
        $date  = '';
        $fare  = 0;

        if (isset($_GET['ticket'])) {
            // Viewing an existing ticket (read-only)
            $tid   = (int)$_GET['ticket'];
            $query = "SELECT bus_id, jdate, seats, fare FROM tickets WHERE id=$tid";
        } else {
            // New booking
            $busid = (int)($_GET['bus']  ?? 0);
            $date  = $con->real_escape_string($_GET['date'] ?? '');

            // Only count confirmed bookings
            $has_col = $con->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
            $clause  = ($has_col && $has_col->num_rows > 0) ? " AND booking_confirmed=1" : "";
            $query   = "SELECT seats FROM tickets WHERE bus_id=$busid AND jdate='$date'$clause";
        }

        // ── Build reserved list (safe unserialize) ───────────────────
        $reserved = [];
        $res = $con->query($query);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $d = @unserialize($row['seats']);
                if ($d !== false && is_array($d)) {
                    $reserved = array_merge($reserved, $d);
                }
                if (isset($_GET['ticket'])) {
                    $busid = (int)$row['bus_id'];
                    $date  = $row['jdate'];
                    $fare  = (int)$row['fare'];
                }
            }
        }
        $con->close();

        // ── Load bus info ────────────────────────────────────────────
        $con     = initDB();
        $res     = $con->query("SELECT * FROM buses WHERE id=" . (int)$busid);
        $businfo = $res->fetch_assoc();
        $con->close();

        if (!$businfo) {
            echo '<div class="alert alert-danger">Error: Bus not found.</div>';
            break;
        }

        // ── Seat grid ────────────────────────────────────────────────
        // Use bus total_seats if present, else default 40
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

        // ── Discount ─────────────────────────────────────────────────
        $userType = $_SESSION['user']['utype'] ?? 'Passenger';
        $baseFare = (int)$businfo['fare'];
        $discount = 0;
        if ($userType === 'Student') $discount = 0.10;
        elseif ($userType === 'Staff') $discount = 0.05;

        $viewing = isset($_GET['ticket']);  // true = read-only view

        // ── Output modal HTML ────────────────────────────────────────
        ?>
        <div class="modal" tabindex="-1" role="dialog" style="display:block; background:rgba(0,0,0,.5);">
        <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <?= htmlspecialchars($businfo['bname']) ?> — <?= htmlspecialchars($businfo['bus_no']) ?>
                </h5>
                <button type="button" class="close" onclick="$('#seatViewer').empty().hide();">
                    <span>&times;</span>
                </button>
            </div>

            <?php if (!$viewing): ?>
            <!-- ▼▼▼  THE FORM — action points to book.php  ▼▼▼ -->
            <form method="POST" action="book.php" id="bookingForm">
                <input type="hidden" name="bus_id" value="<?= (int)$busid ?>"/>
                <input type="hidden" name="jdate"  value="<?= htmlspecialchars($date) ?>"/>
                <input type="hidden" name="fare"   id="hiddenFare" value="0"/>
            <?php endif; ?>

            <div class="modal-body">
                <div class="row">

                    <!-- Seat grid -->
                    <div class="col-md-6 text-center">
                        <h6>Click seats to select</h6>
                        <p class="small">
                            <span style="display:inline-block;width:14px;height:14px;background:#28a745;border:1px solid #999;vertical-align:middle;"></span> Available &nbsp;
                            <span style="display:inline-block;width:14px;height:14px;background:#dc3545;border:1px solid #999;vertical-align:middle;"></span> Booked &nbsp;
                            <span style="display:inline-block;width:14px;height:14px;background:#007bff;border:1px solid #999;vertical-align:middle;"></span> Selected
                        </p>
                        <div>
                        <?php
                        for ($i = 0; $i < count($seats); $i++) {
                            $s          = $seats[$i];
                            $isReserved = in_array($s, $reserved);

                            echo '<input type="checkbox" class="seat" name="seats[]" value="' . $s . '" title="' . $s . '"';
                            if ($isReserved || $viewing) echo ' disabled';
                            if ($isReserved && $viewing) echo ' checked';
                            echo '/>';

                            if (($i + 1) % $cols == 0)      echo '<br/>';
                            elseif (($i + 1) % 2 == 0)      echo '<span style="margin-left:20px"></span>';
                        }
                        ?>
                        </div>
                    </div>

                    <!-- Info panel -->
                    <div class="col-md-6">
                        <strong>Bus Info</strong><br/>
                        Route: <?= htmlspecialchars($businfo['from_loc']) ?> → <?= htmlspecialchars($businfo['to_loc']) ?><br/>
                        Departure: <?= htmlspecialchars($businfo['from_time']) ?><br/>
                        Arrival:   <?= htmlspecialchars($businfo['to_time'])   ?><br/>
                        Date: <?= htmlspecialchars($date) ?><br/>
                        <hr/>
                        <strong>Pricing</strong><br/>
                        Base fare / seat: ৳<?= $baseFare ?><br/>
                        <?php if ($discount > 0 && !$viewing): ?>
                            <span class="badge badge-success"><?= ($discount*100) ?>% <?= $userType ?> discount</span><br/>
                        <?php endif; ?>
                        Seats selected: <span id="seatCount">0</span><br/>
                        Seat total:  ৳<span id="fareDisplay">0</span><br/>
                        Service charge: ৳50<br/>
                        <strong>Grand total: ৳<span id="totalDisplay">50</span></strong>
                    </div>

                </div><!-- /.row -->
            </div><!-- /.modal-body -->

            <div class="modal-footer">
                <?php if (!$viewing): ?>
                    <!-- Submit button INSIDE the form -->
                    <button type="submit" class="btn btn-primary" id="confirmBtn">
                        ✅ Confirm Booking
                    </button>
                </form><!-- ▲▲▲ form closes here, button was inside it ▲▲▲ -->
                <?php endif; ?>
                <button type="button" class="btn btn-secondary"
                        onclick="$('#seatViewer').empty().hide();">Close</button>
            </div>

        </div><!-- /.modal-content -->
        </div><!-- /.modal-dialog -->
        </div><!-- /.modal -->

        <script>
        (function(){
            var base     = <?= $baseFare ?>;
            var discount = <?= $discount ?>;

            function recalc() {
                var checked = $("input.seat:checked").length;
                var fare    = Math.round(checked * base * (1 - discount));
                var total   = fare + 50;
                $("#seatCount").text(checked);
                $("#fareDisplay").text(fare);
                $("#totalDisplay").text(total);
                $("#hiddenFare").val(total);   // what book.php will read
            }

            $(document).on("change", "input.seat", recalc);

            $(document).on("submit", "#bookingForm", function() {
                var checked = $("input.seat:checked").length;
                if (checked === 0) {
                    alert("Please select at least one seat.");
                    return false;
                }
                var fare = parseInt($("#hiddenFare").val(), 10);
                if (!fare || fare <= 0) {
                    alert("Fare calculation error. Please reselect your seats.");
                    return false;
                }
                $("#confirmBtn").prop("disabled", true).text("Saving...");
                return true;   // let the form submit normally to book.php
            });
        })();
        </script>
        <?php
        break;

    default:
        break;
}
?>