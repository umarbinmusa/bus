<?php
session_start();
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
            echo '<span class="text-danger">Username Unvailable</span>';
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
        $con = initDB();
        $query = "";
        $busid = "";
        $date = "";
        $fare = 0;
        
        if (isset($_GET['ticket'])) {
            $query = "select bus_id, jdate, seats, fare from tickets where id=".$_GET['ticket'];
        }
        else {
            $query = "select seats from tickets where bus_id='".$_GET['bus']."' and jdate='".$_GET['date']."'";
            $busid = $_GET['bus'];
            $date = $_GET['date'];
        }
        
        $seats = array("A1","A2","A3","A4","B1","B2","B3","B4","C1","C2","C3","C4","D1","D2","D3","D4","E1","E2","E3","E4","F1","F2","F3","F4","G1","G2","G3","G4","H1","H2","H3","H4","I1","I2","I3","J4","J1","J2","J3","J4");
        $reserved = array();
        
        $res = $con->query($query);
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                // FIXED: Safely unserialize seats data
                $seats_data = @unserialize($row['seats']);
                
                // Only merge if unserialize was successful AND it's an array
                if ($seats_data !== false && is_array($seats_data)) {
                    $reserved = array_merge($reserved, $seats_data);
                }
                
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
        
        // Get user's passenger category
        $userCategory = isset($_SESSION['user']['passenger_category']) ? $_SESSION['user']['passenger_category'] : '';
        
        // Calculate discount for students
        $baseFare = $businfo['fare'];
        $discount = 0;
        $discountText = '';
        
        if($userCategory == 'Student') {
            $discount = 0.20; // 20% discount for students
            $discountText = ' <span class="text-success">(20% Student Discount)</span>';
        }
        
        $finalFare = $baseFare * (1 - $discount);
        
        // Booking window message
        $bookingMessage = '';
        
        if (!isset($_GET['ticket'])) {
            if ($userCategory == 'Student') {
                $bookingMessage = '<div class="alert alert-info">
                    <strong>🎓 Student Priority:</strong> You have 10 minutes to book with 20% discount! 
                    After timer expires, booking closes for everyone.
                </div>';
            } else if ($userCategory == 'Staff') {
                $bookingMessage = '<div class="alert alert-warning">
                    <strong>⚠️ Important:</strong> You have 10 minutes to book your seats. 
                    After 10 minutes, students can book any remaining staff seats at student price, then booking closes.
                </div>';
            }
        }
        
        echo '<div class="modal modal-lg" tabindex="-1" role="dialog" style="display: block">
        <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
            <h5 class="modal-title">'.$businfo['bname'].'</h5>
            <a onclick="$(\'#seatViewer\').hide()"><button type="button" class="close" data-dismiss="modal" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button></a>
            </div>';
            
            if (!isset($_GET['ticket']))
                echo '<form method="post" action="buy_ticket.php" id="bookingForm">';
            
            echo $bookingMessage;
            
            echo '<div class="modal-body">
            <div class="row">
            <div class="col-md-6 bus-model text-center">';

            for ($i=0; $i < count($seats); $i++) { 
                $isReserved = in_array($seats[$i], $reserved);
                $isDisabled = $isReserved ? (isset($_GET['ticket']) ? 'checked' : 'disabled') : '';
                
                if (isset($_GET['ticket'])) {
                    $isDisabled .= ' disabled';
                }
                
                echo '<input type="checkbox" class="seat" name="seats[]" value="'.$seats[$i].'" title="'.$seats[$i].'" '.$isDisabled.'/>';
                
                if (($i+1) % 4 == 0)
                    echo '<br/>';
                elseif (($i+1) % 2 == 0)
                    echo '<span style="margin-left: 25px"></span>';
            }
    
            echo '</div>
            <div class="col-md-6">
            <strong>Bus Information</strong><br/>
            Bus No.: '.$businfo['bus_no'].'<br/>
            Journey Date: '.$date.'<br/>
            <hr>';
            
            // Display pricing information
            if($discount > 0 && !isset($_GET['ticket'])) {
                echo '<strong>Pricing</strong><br/>';
                echo 'Original Fare: <span style="text-decoration: line-through;">'.number_format($baseFare, 2).'</span> BDT<br/>';
                echo 'Discounted Fare: <span id="fare">0</span> BDT'.$discountText.'<br/>';
            } else {
                echo '<strong>Pricing</strong><br/>';
                echo 'Fare: <span id="fare">' . (($fare == 0) ? '0' : ($fare-50)) . '</span> BDT<br/>';
            }
            
            echo 'Booking Charge: 50 BDT<br/>
            <strong>Total: <span id="total">'.$fare.'</span> BDT</strong>
            <hr>
            <div style="margin-top: 15px;">
                <div><span style="display:inline-block; width:15px; height:15px; background:#28a745; border:1px solid #000;"></span> Available</div>
                <div><span style="display:inline-block; width:15px; height:15px; background:#dc3545; border:1px solid #000;"></span> Booked</div>
                <div><span style="display:inline-block; width:15px; height:15px; background:#007bff; border:1px solid #000;"></span> Selected</div>
            </div>
            </div>
            </div>
            </div>
            <div class="modal-footer">';
            
            if (!isset($_GET['ticket'])) {
                echo '<input type="hidden" name="bus_id" value="'.$busid.'"/>
                <input type="hidden" name="jdate" value="'.$date.'"/>
                <input type="hidden" name="fare" id="ifare" value="0"/>
                <button type="submit" class="btn btn-primary" name="buy" id="buyButton">Buy Tickets</button>
                </form>';
            }
            
            echo '<a onclick="$(\'#seatViewer\').hide()"><button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button></a>
            </div>
            
            <script async>
            var userCategory = "'.$userCategory.'";
            var baseFare = '.$baseFare.';
            var discount = '.$discount.';
            
            // Seat selection handler
            $(".seat[type=checkbox]").on("change", function() {
                var selectedSeats = $("input[name=\'seats[]\']:checked").length;
                console.log("Seats selected:", selectedSeats);
                
                var finalFare = baseFare * (1 - discount);
                var totalFare = selectedSeats * finalFare;
                var totalWithCharge = totalFare + 50;
                
                $("#fare").html(totalFare.toFixed(2));
                $("#total").html(totalWithCharge.toFixed(2));
                $("#ifare").val(Math.round(totalWithCharge));
                
                console.log("Total fare:", totalWithCharge);
            });
            
            // Form submission handler
            $("#bookingForm").on("submit", function(e) {
                var selectedSeats = $("input[name=\'seats[]\']:checked").length;
                var fareValue = $("#ifare").val();
                
                console.log("=== FORM SUBMISSION ===");
                console.log("Selected seats:", selectedSeats);
                console.log("Fare value:", fareValue);
                console.log("Bus ID:", $("input[name=bus_id]").val());
                console.log("Journey Date:", $("input[name=jdate]").val());
                
                if (selectedSeats === 0) {
                    e.preventDefault();
                    alert("Please select at least one seat before booking.");
                    return false;
                }
                
                if (!fareValue || parseInt(fareValue) <= 0) {
                    e.preventDefault();
                    alert("Error calculating fare. Please try selecting seats again.");
                    return false;
                }
                
                console.log("Form validation passed. Submitting...");
                return true;
            });
            </script>
        </div>
        </div>
        </div>';
        break;


    default:
        break;
}