<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

// Log the request for debugging
error_log("AJAX Request: " . (isset($_GET['type']) ? $_GET['type'] : 'NONE'));

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
        session_start();
        
        error_log("Showseats - Bus: " . (isset($_GET['bus']) ? $_GET['bus'] : 'NONE') . ", Date: " . (isset($_GET['date']) ? $_GET['date'] : 'NONE'));
        
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
            
            // Get user type - handle all passenger types
            $userType = 'Passenger'; // Default
            if (isset($_SESSION['user'])) {
                $userType = $_SESSION['user']['utype'];
            }
            
            if (isset($_GET['ticket'])) {
                $ticketId = intval($_GET['ticket']);
                $query = "select bus_id, jdate, seats, fare from tickets where id=" . $ticketId;
            } else {
                if (!isset($_GET['bus']) || !isset($_GET['date'])) {
                    die("Error: Missing bus or date parameter");
                }
                
                $busid = intval($_GET['bus']);
                $date = $con->real_escape_string($_GET['date']);
                $query = "select seats from tickets where bus_id='" . $busid . "' and jdate='" . $date . "' and booking_confirmed=1";
            }
            
            // Get bus information
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
            
            // Get seat configuration
            $totalSeats = isset($busData['total_seats']) ? $busData['total_seats'] : 40;
            $seatLayout = isset($busData['seat_layout']) ? $busData['seat_layout'] : '10x4';
            $fare = $busData['fare'];
            
            // Get categorized seats
            $studentSeats = !empty($busData['student_seats']) ? explode(',', $busData['student_seats']) : [];
            $staffSeats = !empty($busData['staff_seats']) ? explode(',', $busData['staff_seats']) : [];
            $generalSeats = !empty($busData['general_seats']) ? explode(',', $busData['general_seats']) : [];
            
            error_log("Total seats: $totalSeats, Layout: $seatLayout");
            
            // Generate seats dynamically
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
            
            // Calculate timer
            $bookingTimeLimit = 5; // 5 minutes
            $overrideTime = 2; // Last 2 minutes
            
            // Build the modal HTML
            echo '<div class="modal modal-lg" tabindex="-1" role="dialog" style="display: block">
            <div class="modal-dialog modal-lg" role="document">
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
                
                <!-- Seat Legend -->
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <strong>Seat Categories:</strong><br/>
                            <span class="badge badge-primary">Blue</span> Student Seats (10% discount) |
                            <span class="badge badge-success">Green</span> Staff Seats (5% discount) |
                            <span class="badge badge-secondary">Grey</span> General Seats |
                            <span class="badge badge-danger">Red</span> Booked
                        </div>
                    </div>
                </div>
                
                <div class="row">
                <div class="col-md-7 bus-model text-center">';
                
                // Display seats
                $layout = explode('x', $seatLayout);
                $rows = (int)$layout[0];
                $cols = (int)$layout[1];
                
                echo '<style>
                .seat { 
                    width: 40px; 
                    height: 40px; 
                    margin: 3px;
                    cursor: pointer;
                }
                .seat.student-seat:not(:disabled):not(:checked) { 
                    accent-color: #007bff;
                }
                .seat.staff-seat:not(:disabled):not(:checked) { 
                    accent-color: #28a745;
                }
                .seat.general-seat:not(:disabled):not(:checked) { 
                    accent-color: #6c757d;
                }
                .seat:disabled {
                    cursor: not-allowed;
                    opacity: 0.3;
                }
                </style>';
                
                echo '<div style="display: inline-block; text-align: left;">';
                
                for ($i=0; $i < count($seats); $i++) { 
                    $seatNum = $seats[$i];
                    $isReserved = in_array($seatNum, $reserved);
                    
                    // Determine seat category
                    $seatClass = 'general-seat';
                    $seatCategory = 'general';
                    if (in_array($seatNum, $studentSeats)) {
                        $seatClass = 'student-seat';
                        $seatCategory = 'student';
                    } elseif (in_array($seatNum, $staffSeats)) {
                        $seatClass = 'staff-seat';
                        $seatCategory = 'staff';
                    }
                    
                    // Check if user can select this seat
                    $canSelect = true;
                    $disableReason = '';
                    
                    if (!isset($_GET['ticket'])) {
                        if ($userType == 'Student' && $seatCategory == 'staff') {
                            $canSelect = false;
                            $disableReason = 'Staff seats (available in last 2 min)';
                        } elseif ($userType == 'Staff' && $seatCategory == 'student') {
                            $canSelect = false;
                            $disableReason = 'Student seats only';
                        }
                    }
                    
                    echo '<div style="display: inline-block; text-align: center; margin: 2px;">';
                    echo '<input type="checkbox" 
                            class="seat '.$seatClass.'" 
                            name="seats[]" 
                            value="'.$seatNum.'" 
                            title="'.$seatNum.' - '.ucfirst($seatCategory).' seat'.($disableReason ? ' ('.$disableReason.')' : '').'" 
                            data-category="'.$seatCategory.'"
                            data-can-select="'.($canSelect ? '1' : '0').'" ';
                    
                    if ($isReserved) {
                        echo isset($_GET['ticket']) ? 'checked disabled' : 'disabled';
                    } elseif (isset($_GET['ticket'])) {
                        echo 'disabled';
                    } elseif (!$canSelect) {
                        echo 'disabled class="seat '.$seatClass.' category-restricted"';
                    }
                    
                    echo '/>';
                    echo '<div style="font-size: 10px;">'.$seatNum.'</div>';
                    echo '</div>';
                    
                    if (($i+1) % $cols == 0) {
                        echo '<br/>';
                    } elseif (($i+1) % 2 == 0) {
                        echo '<span style="margin-left: 15px"></span>';
                    }
                }
                
                echo '</div></div>
                <div class="col-md-5">
                <strong>Bus Information:</strong><br/>
                Bus No.: '.$businfo['bus_no'].'<br/>
                Route: '.$businfo['from_loc'].' → '.$businfo['to_loc'].'<br/>
                Journey Date: '.$date.'<br/>
                Departure: '.$businfo['from_time'].'<br/>
                Arrival: '.$businfo['to_time'].'<br/>
                Total Seats: '.$totalSeats.' ('.$seatLayout.')<br/>
                <hr/>
                
                <strong>Your Account:</strong><br/>
                User Type: <span class="badge badge-info">'.$userType.'</span><br/>';
                
                if ($userType == 'Student') {
                    echo 'Discount: <strong>10%</strong> on student seats<br/>';
                } elseif ($userType == 'Staff') {
                    echo 'Discount: <strong>5%</strong> on staff seats<br/>';
                }
                
                echo '<hr/>
                <strong>Booking Details:</strong><br/>
                Selected Seats: <span id="seatList">None</span><br/>
                Base Fare: ৳<span id="fare">0</span><br/>
                Service Charge: ৳50<br/>
                <strong>Total: ৳<span id="total">50</span></strong><br/>';
                
                if (!isset($_GET['ticket'])) {
                    echo '<hr/>
                    <div class="alert alert-warning" id="timerAlert">
                        <strong><i class="fa fa-clock-o"></i> Time Remaining:</strong><br/>
                        <span id="bookingTimer" style="font-size: 24px; font-weight: bold;">5:00</span><br/>
                        <small id="timerMessage">Complete booking within 5 minutes</small>
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
                var timeLeft = ' . ($bookingTimeLimit * 60) . ';
                var overrideTime = ' . ($overrideTime * 60) . ';
                var overrideActivated = false;
                var userType = "'.$userType.'";
                var baseFare = '.$businfo['fare'].';
                
                function startBookingTimer() {
                    bookingTimerInterval = setInterval(function() {
                        timeLeft--;
                        
                        var minutes = Math.floor(timeLeft / 60);
                        var seconds = timeLeft % 60;
                        
                        $("#bookingTimer").text(minutes + ":" + (seconds < 10 ? "0" : "") + seconds);
                        
                        if (timeLeft <= 60) {
                            $("#timerAlert").removeClass("alert-warning").addClass("alert-danger");
                        }
                        
                        // Enable staff seats for students in last 2 minutes
                        if (timeLeft <= overrideTime && !overrideActivated && userType === "Student") {
                            overrideActivated = true;
                            $(".category-restricted").prop("disabled", false).removeClass("category-restricted");
                            $("#timerMessage").html("<strong>Staff seats now available!</strong>");
                        }
                        
                        if (timeLeft <= 0) {
                            clearInterval(bookingTimerInterval);
                            alert("Booking time expired!");
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
                
                '.(!isset($_GET['ticket']) ? 'startBookingTimer();' : '').'
                
                $(".seat[type=checkbox]").click(function() {
                    updateBookingDetails();
                });
                
                function updateBookingDetails() {
                    let selectedSeats = [];
                    let totalFare = 0;
                    
                    $("input[type=checkbox]:checked:not(:disabled)").each(function() {
                        let seatNum = $(this).val();
                        let category = $(this).data("category");
                        selectedSeats.push(seatNum);
                        
                        let seatFare = baseFare;
                        
                        if (userType === "Student" && category === "student") {
                            seatFare = baseFare * 0.9;
                        } else if (userType === "Staff" && category === "staff") {
                            seatFare = baseFare * 0.95;
                        }
                        
                        totalFare += seatFare;
                    });
                    
                    $("#seatList").text(selectedSeats.length > 0 ? selectedSeats.join(", ") : "None");
                    $("#fare").text(Math.round(totalFare));
                    $("#total").text(Math.round(totalFare) + 50);
                    $("#ifare").val(Math.round(totalFare) + 50);
                }
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

// Helper function to generate seats
function generateSeats($totalSeats, $layout) {
    $seats = array();
    $parts = explode('x', $layout);
    
    if (count($parts) != 2) {
        error_log("Invalid layout format: $layout");
        $parts = array(10, 4);
    }
    
    $rows = (int)$parts[0];
    $cols = (int)$parts[1];
    
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