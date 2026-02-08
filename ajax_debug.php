<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

// Log the request
error_log("AJAX Request Type: " . (isset($_GET['type']) ? $_GET['type'] : 'NONE'));

if (!isset($_GET['type'])) {
    die("Error: No type specified");
}

switch ($_GET['type']) {

    case 'locations':
        $conn = initDB();
        $res = $conn->query("select name from locations");
        $jarr = array();
        while ($row = $res->fetch_assoc()) {
            $jarr[] = $row['name'];
        }
        echo json_encode($jarr);
        $conn->close();
        break;


    case 'username':
        if (strlen($_GET['q']) < 3)
            die('<span class="text-danger">Invalid Username</span>');
        $conn = initDB();
        $res = $conn->query("select id from users where uname='" . $_GET['q'] . "'");
        if ($res->num_rows == 0)
            echo '<span class="text-success">Username Available</span>';
        else
            echo '<span class="text-danger">Username Unavailable</span>';
        $conn->close();
        break;


    case 'email':
        if (!filter_var($_GET['q'], FILTER_VALIDATE_EMAIL))
            die('<span class="text-danger">Invalid Email</span>');
        $conn = initDB();
        $res = $conn->query("select id from users where email='" . $_GET['q'] . "'");
        if ($res->num_rows == 0)
            echo '';
        else
            echo '<span class="text-danger">Email Already Exist</span>';
        $conn->close();
        break;

    case 'showseats':
        error_log("Showseats called - Bus: " . (isset($_GET['bus']) ? $_GET['bus'] : 'NONE') . ", Date: " . (isset($_GET['date']) ? $_GET['date'] : 'NONE'));
        
        try {
            $con = initDB();
            
            if (!$con) {
                die("Error: Database connection failed");
            }
            
            $query = "";
            $busid = "";
            $date = "";
            $fare = 0;
            $totalSeats = 40;
            $seatLayout = "10x4";
            
            if (isset($_GET['ticket'])) {
                $ticketId = intval($_GET['ticket']);
                $query = "select bus_id, jdate, seats, fare from tickets where id=" . $ticketId;
            }
            else {
                if (!isset($_GET['bus']) || !isset($_GET['date'])) {
                    die("Error: Missing bus or date parameter");
                }
                
                $busid = intval($_GET['bus']);
                $date = $_GET['date'];
                $query = "select seats from tickets where bus_id='" . $busid . "' and jdate='" . $date . "' and booking_confirmed=1";
            }
            
            // Get bus information including seat configuration
            $busInfoQuery = "select * from buses where id=" . ($busid ? $busid : "(select bus_id from tickets where id=" . intval($_GET['ticket']) . " LIMIT 1)");
            error_log("Bus info query: " . $busInfoQuery);
            
            $busInfo = $con->query($busInfoQuery);
            
            if (!$busInfo) {
                die("Error: Failed to get bus info - " . $con->error);
            }
            
            $busData = $busInfo->fetch_assoc();
            
            if (!$busData) {
                die("Error: Bus not found");
            }
            
            if ($busData) {
                $totalSeats = isset($busData['total_seats']) ? $busData['total_seats'] : 40;
                $seatLayout = isset($busData['seat_layout']) ? $busData['seat_layout'] : '10x4';
                $fare = $busData['fare'];
            }
            
            error_log("Total seats: $totalSeats, Layout: $seatLayout");
            
            // Generate seats dynamically based on layout
            $seats = generateSeats($totalSeats, $seatLayout);
            error_log("Generated " . count($seats) . " seats");
            
            // Get reserved seats
            $reserved = array();
            $res = $con->query($query);
            
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $reserved = array_merge($reserved, unserialize($row['seats']));
                    if (isset($_GET['ticket'])) {
                        $busid = "".$row['bus_id'];
                        $date = $row['jdate'];
                        $fare = $row['fare'];
                    }
                }
            }
            
            error_log("Reserved seats: " . count($reserved));
            
            // Get bus info for display
            $res = $con->query("select * from buses where id=" . $busid);
            $businfo = $res->fetch_assoc();
            
            if (!$businfo) {
                die("Error: Could not load bus information");
            }
            
            $con->close();
            
            // Calculate timer for booking
            $bookingTimeLimit = 5; // 5 minutes
            
            echo '<div class="modal modal-lg" tabindex="-1" role="dialog" style="display: block">
            <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                <h5 class="modal-title">'.$businfo['bname'].' - '.$businfo['bus_no'].'</h5>
                <a onclick="$(\'#seatViewer\').hide(); if(typeof clearBookingTimer === \'function\') clearBookingTimer();"><button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button></a>
                </div>';
                
                if (!isset($_GET['ticket']))
                    echo '<form method="post" action="buy_ticket.php?from='.$businfo['from_loc'].'&to='.$businfo['to_loc'].'&jdate='.$date.'" id="bookingForm">';
                    
                echo '<div class="modal-body">
                <div class="row">
                <div class="col-md-6 bus-model text-center">
                <h6>Select Your Seats</h6>
                <p><small>Green = Available | Grey = Booked</small></p>';
                
                // Display seats dynamically
                $layout = explode('x', $seatLayout);
                $rows = (int)$layout[0];
                $cols = (int)$layout[1];
                
                echo '<div style="display: inline-block; text-align: left;">';
                
                for ($i=0; $i < count($seats); $i++) { 
                    $isReserved = in_array($seats[$i], $reserved);
                    $isSelected = (isset($_GET['ticket']) && $isReserved);
                    
                    echo '<input type="checkbox" class="seat" name="seats[]" value="'.$seats[$i].'" title="Seat '.$seats[$i].'" ';
                    
                    if ($isReserved) {
                        echo isset($_GET['ticket']) ? 'checked disabled' : 'disabled';
                    } elseif (isset($_GET['ticket'])) {
                        echo 'disabled';
                    }
                    
                    echo '/>';
                    
                    if (($i+1) % $cols == 0) {
                        echo '<br/>';
                    } elseif (($i+1) % 2 == 0) {
                        echo '<span style="margin-left: 25px"></span>';
                    }
                }
                
                echo '</div>';
        
                echo '</div>
                <div class="col-md-6">
                <h6>Bus Information</h6>
                <table class="table table-sm">
                <tr><td>Bus No:</td><td><strong>'.$businfo['bus_no'].'</strong></td></tr>
                <tr><td>Route:</td><td>'.$businfo['from_loc'].' → '.$businfo['to_loc'].'</td></tr>
                <tr><td>Journey Date:</td><td>'.$date.'</td></tr>
                <tr><td>Departure:</td><td>'.$businfo['from_time'].'</td></tr>
                <tr><td>Arrival:</td><td>'.$businfo['to_time'].'</td></tr>
                <tr><td>Total Seats:</td><td>'.$totalSeats.' ('.$seatLayout.')</td></tr>
                </table>
                <hr/>
                <h6>Fare Calculation</h6>
                <table class="table table-sm">
                <tr><td>Seat Fare:</td><td>৳<span id="fare">0</span></td></tr>
                <tr><td>Service Charge:</td><td>৳50</td></tr>
                <tr><td><strong>Total:</strong></td><td><strong>৳<span id="total">50</span></strong></td></tr>
                </table>';
                
                if (!isset($_GET['ticket'])) {
                    echo '<hr/>
                    <div class="alert alert-warning" id="timerAlert">
                        <strong><i class="fa fa-clock-o"></i> Time Remaining:</strong><br/>
                        <span id="bookingTimer" style="font-size: 24px; font-weight: bold;">5:00</span><br/>
                        <small>Complete your booking within 5 minutes</small>
                    </div>';
                }
                
                echo '</div>
                </div>
                </div>
                <div class="modal-footer">';
                
                if (!isset($_GET['ticket'])) {
                    echo '<input type="hidden" name="bus_id" value="'.$busid.'"/>
                    <input type="hidden" name="jdate" value="'.$date.'"/>
                    <input type="hidden" name="fare" id="ifare" value="0"/>
                    <input type="submit" class="btn btn-primary" value="Confirm Booking" name="buy" id="confirmBooking"/>
                    </form>';
                }
                
                echo '<a onclick="$(\'#seatViewer\').hide(); if(typeof clearBookingTimer === \'function\') clearBookingTimer();"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></a>
                </div>
                
                <script>
                var bookingTimerInterval;
                var timeLeft = ' . ($bookingTimeLimit * 60) . '; // 5 minutes in seconds
                
                function startBookingTimer() {
                    bookingTimerInterval = setInterval(function() {
                        timeLeft--;
                        
                        var minutes = Math.floor(timeLeft / 60);
                        var seconds = timeLeft % 60;
                        
                        $("#bookingTimer").text(minutes + ":" + (seconds < 10 ? "0" : "") + seconds);
                        
                        if (timeLeft <= 60) {
                            $("#timerAlert").removeClass("alert-warning").addClass("alert-danger");
                        }
                        
                        if (timeLeft <= 0) {
                            clearInterval(bookingTimerInterval);
                            alert("Booking time expired! Please select seats again.");
                            $("#seatViewer").hide();
                            location.reload();
                        }
                    }, 1000);
                }
                
                function clearBookingTimer() {
                    if (bookingTimerInterval) {
                        clearInterval(bookingTimerInterval);
                    }
                }
                
                // Start timer only for new bookings
                '.(!isset($_GET['ticket']) ? 'startBookingTimer();' : '').'
                
                $(".seat[type=checkbox]").click(function() {
                    let sts = $("input[type=checkbox]:checked:not(:disabled)").length;
                    let seatFare = sts * '.$businfo['fare'].';
                    let totalFare = seatFare + 50;
                    
                    $("#fare").html(seatFare);
                    $("#total").html(totalFare);
                    $("#ifare").val(totalFare);
                });
                </script>
            </div>
            </div>
            </div>';
            
        } catch (Exception $e) {
            error_log("Error in showseats: " . $e->getMessage());
            die("Error: " . $e->getMessage());
        }
        break;

    default:
        die("Error: Unknown type - " . $_GET['type']);
        break;
}

// Helper function to generate seats dynamically
function generateSeats($totalSeats, $layout) {
    $seats = array();
    $parts = explode('x', $layout);
    
    if (count($parts) != 2) {
        error_log("Invalid layout format: $layout");
        $parts = array(10, 4); // Default
    }
    
    $rows = (int)$parts[0];
    $cols = (int)$parts[1];
    
    error_log("Generating seats: $rows rows x $cols cols");
    
    $rowLabels = range('A', 'Z');
    
    for ($row = 0; $row < $rows && $row < 26; $row++) {
        for ($col = 1; $col <= $cols; $col++) {
            $seats[] = $rowLabels[$row] . $col;
            if (count($seats) >= $totalSeats) {
                return $seats;
            }
        }
    }
    
    return $seats;
}
?>