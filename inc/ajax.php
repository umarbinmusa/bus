<?php
require_once 'database.php';
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
        $con = initDB();
        $query = "";
        $busid = "";
        $date = "";
        $fare = 0;
        $totalSeats = 40;
        $seatLayout = "10x4";
        
        // Get user type
        $userType = isset($_SESSION['user']) ? $_SESSION['user']['utype'] : 'Passenger';
        
        if (isset($_GET['ticket'])) {
            $query = "select bus_id, jdate, seats, fare from tickets where id=".$_GET['ticket'];
        }
        else {
            $query = "select seats from tickets where bus_id='".$_GET['bus']."' and jdate='".$_GET['date']."' and booking_confirmed=1";
            $busid = $_GET['bus'];
            $date = $_GET['date'];
        }
        
        // Get bus information including seat configuration
        $busInfo = $con->query("select * from buses where id=" . ($busid ? $busid : "(select bus_id from tickets where id=".$_GET['ticket']." LIMIT 1)"));
        $busData = $busInfo->fetch_assoc();
        
        if ($busData) {
            $totalSeats = $busData['total_seats'];
            $seatLayout = $busData['seat_layout'];
            $fare = $busData['fare'];
            
            // Get categorized seats
            $studentSeats = !empty($busData['student_seats']) ? explode(',', $busData['student_seats']) : [];
            $staffSeats = !empty($busData['staff_seats']) ? explode(',', $busData['staff_seats']) : [];
            $generalSeats = !empty($busData['general_seats']) ? explode(',', $busData['general_seats']) : [];
        } else {
            $studentSeats = [];
            $staffSeats = [];
            $generalSeats = [];
        }
        
        // Generate all seats dynamically based on layout
        $allSeats = generateSeats($totalSeats, $seatLayout);
        
        // Get reserved seats
        $reserved = array();
        $res = $con->query($query);
        if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $reserved = array_merge($reserved, unserialize($row['seats']));
                if (isset($_GET['ticket'])) {
                    $busid = "".$row['bus_id'];
                    $date = $row['jdate'];
                    $fare = $row['fare'];
                }
            }
        }
        
        $con->close();
        $con = initDB();
        $res = $con->query("select * from buses where id=".$busid);
        $businfo = $res->fetch_assoc();
        $con->close();
        
        // Calculate timer for booking
        $bookingTimeLimit = 5; // 5 minutes total
        $overrideTime = 2; // Last 2 minutes - students can book staff seats
        
        echo '<div class="modal modal-lg" tabindex="-1" role="dialog" style="display: block">
        <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title">'.$businfo['bname'].' - '.$businfo['bus_no'].'</h5>
            <a onclick="$(\'#seatViewer\').hide(); clearBookingTimer();"><button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button></a>
            </div>';
            
            if (!isset($_GET['ticket']))
                echo '<form method="post" action="buy_ticket.php?from='.$businfo['from_loc'].'&to='.$businfo['to_loc'].'&jdate='.$_GET['date'].'" id="bookingForm">';
                
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
            
            // Display seats with categories
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
                filter: hue-rotate(200deg);
            }
            .seat.staff-seat:not(:disabled):not(:checked) { 
                accent-color: #28a745;
                filter: hue-rotate(90deg);
            }
            .seat.general-seat:not(:disabled):not(:checked) { 
                accent-color: #6c757d;
            }
            .seat:disabled {
                cursor: not-allowed;
                opacity: 0.3;
            }
            .seat-label {
                font-size: 10px;
                font-weight: bold;
            }
            </style>';
            
            for ($i=0; $i < count($allSeats); $i++) { 
                $seatNum = $allSeats[$i];
                $isReserved = in_array($seatNum, $reserved);
                $isSelected = (isset($_GET['ticket']) && $isReserved);
                
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
                    // Student trying to book staff seat (not allowed initially)
                    if ($userType == 'Student' && $seatCategory == 'staff') {
                        $canSelect = false;
                        $disableReason = 'Staff seats only (available in last 2 minutes)';
                    }
                    // Staff trying to book student seat (not allowed)
                    elseif ($userType == 'Staff' && $seatCategory == 'student') {
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
                echo '<div class="seat-label">'.$seatNum.'</div>';
                echo '</div>';
                
                if (($i+1) % $cols == 0)
                    echo '<br/>';
                elseif (($i+1) % 2 == 0)
                    echo '<span style="margin-left: 15px"></span>';
            }
    
            echo '</div>
            <div class="col-md-5">
            <strong>Bus Information:</strong><br/>
            Bus No.: '.$businfo['bus_no'].'<br/>
            Route: '.$businfo['from_loc'].' to '.$businfo['to_loc'].'<br/>
            Journey Date: '.$date.'<br/>
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
            Base Fare: <span id="fare">0</span> BDT<br/>
            Service Charge: 50 BDT<br/>
            <strong>Total: <span id="total">50</span> BDT</strong><br/>';
            
            if (!isset($_GET['ticket'])) {
                echo '<hr/>
                <div class="alert alert-warning" id="timerAlert">
                    <strong><i class="fa fa-clock-o"></i> Time Remaining:</strong><br/>
                    <span id="bookingTimer" style="font-size: 24px; font-weight: bold;">5:00</span><br/>
                    <small id="timerMessage">Complete your booking within 5 minutes</small>
                </div>
                
                <div class="alert alert-success" id="overrideAlert" style="display: none;">
                    <strong><i class="fa fa-unlock"></i> Seats Unlocked!</strong><br/>
                    Students can now book staff seats
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
                <input type="hidden" name="seat_category" id="seatCategory" value="general"/>
                <input type="submit" class="btn btn-primary" value="Confirm Booking" name="buy" id="confirmBooking"/>
                </form>';
            }
            
            echo '<a onclick="$(\'#seatViewer\').hide(); clearBookingTimer();"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></a>
            </div>
            
            <script>
            var bookingTimerInterval;
            var timeLeft = ' . ($bookingTimeLimit * 60) . '; // 5 minutes in seconds
            var overrideTime = ' . ($overrideTime * 60) . '; // 2 minutes in seconds
            var overrideActivated = false;
            var userType = "'.$userType.'";
            var baseFare = '.$businfo['fare'].';
            
            function startBookingTimer() {
                bookingTimerInterval = setInterval(function() {
                    timeLeft--;
                    
                    var minutes = Math.floor(timeLeft / 60);
                    var seconds = timeLeft % 60;
                    
                    $("#bookingTimer").text(minutes + ":" + (seconds < 10 ? "0" : "") + seconds);
                    
                    // Warning at 1 minute
                    if (timeLeft <= 60) {
                        $("#timerAlert").removeClass("alert-warning").addClass("alert-danger");
                        $("#timerMessage").text("Hurry! Booking expires soon");
                    }
                    
                    // Enable staff seats for students in last 2 minutes
                    if (timeLeft <= overrideTime && !overrideActivated && userType === "Student") {
                        overrideActivated = true;
                        $(".category-restricted").prop("disabled", false).removeClass("category-restricted");
                        $("#overrideAlert").slideDown();
                        $("#timerAlert").removeClass("alert-danger").addClass("alert-success");
                        $("#timerMessage").html("<strong>Staff seats now available for students!</strong>");
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
                updateBookingDetails();
            });
            
            function updateBookingDetails() {
                let selectedSeats = [];
                let totalFare = 0;
                let categories = [];
                
                $("input[type=checkbox]:checked:not(:disabled)").each(function() {
                    let seatNum = $(this).val();
                    let category = $(this).data("category");
                    selectedSeats.push(seatNum);
                    categories.push(category);
                    
                    // Calculate fare based on category and user type
                    let seatFare = baseFare;
                    
                    if (userType === "Student" && category === "student") {
                        seatFare = baseFare * 0.9; // 10% discount
                    } else if (userType === "Staff" && category === "staff") {
                        seatFare = baseFare * 0.95; // 5% discount
                    }
                    
                    totalFare += seatFare;
                });
                
                $("#seatList").text(selectedSeats.length > 0 ? selectedSeats.join(", ") : "None");
                $("#fare").text(Math.round(totalFare));
                $("#total").text(Math.round(totalFare) + 50);
                $("#ifare").val(Math.round(totalFare) + 50);
                
                // Determine dominant category
                let categoryCount = {};
                categories.forEach(c => categoryCount[c] = (categoryCount[c] || 0) + 1);
                let dominantCategory = Object.keys(categoryCount).reduce((a, b) => 
                    categoryCount[a] > categoryCount[b] ? a : b, "general"
                );
                $("#seatCategory").val(dominantCategory);
            }
            </script>
        </div>
        </div>
        </div>';
        break;

    default:
        break;
}

// Helper function to generate seats dynamically
function generateSeats($totalSeats, $layout) {
    $seats = array();
    $parts = explode('x', $layout);
    $rows = (int)$parts[0];
    $cols = (int)$parts[1];
    
    $rowLabels = range('A', 'Z');
    
    for ($row = 0; $row < $rows; $row++) {
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