<?php
/**
 * book.php — Booking handler
 * Enforces:
 *   - 5-minute booking window (checked server-side via session timestamp)
 *   - Seat category rules (Student/Staff/General)
 *   - Student can take Staff seats only in last 2 minutes of window
 */
session_start();

if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['utype'], ['Passenger','Student','Staff'])) {
    header('Location: index.php');
    exit();
}

require_once 'inc/database.php';
$conn = initDB();

// ── Read POST ──────────────────────────────────────────────────
$busid     = (int)($_POST['bus_id'] ?? 0);
$jdate     = trim($_POST['jdate']   ?? '');
$fare      = (int)($_POST['fare']   ?? 0);
$passenger = (int)$_SESSION['user']['id'];
$usertype  = $_SESSION['user']['utype'];
$seats_raw = $_POST['seats'] ?? [];

// ── Basic validation ───────────────────────────────────────────
$errors = [];
if ($busid   <= 0)      $errors[] = "Invalid bus.";
if ($jdate  === '')     $errors[] = "Missing journey date.";
if ($fare    <= 0)      $errors[] = "Invalid fare — did you select seats?";
if (empty($seats_raw)) $errors[] = "No seats selected.";

if (!empty($errors)) {
    $msg = urlencode(implode(' ', $errors));
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

// ── 5-MINUTE BOOKING WINDOW ────────────────────────────────────
// We store the first time the user opened the seat modal in their session.
// The key is based on bus+date so each search gets its own fresh timer.
$timerKey = 'booking_start_' . $busid . '_' . md5($jdate);

if (!isset($_SESSION[$timerKey])) {
    // First visit — start the clock now
    // (The modal was just opened; we set this on the first POST)
    // But if we get here it means the modal was never properly started — reject.
    $msg = urlencode("Booking session not found. Please search again.");
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

$elapsed  = time() - (int)$_SESSION[$timerKey];
$TOTAL    = 5 * 60;      // 300 seconds
$UNLOCK   = $TOTAL - (2 * 60);  // 180 seconds elapsed = last 2 min

if ($elapsed > $TOTAL) {
    unset($_SESSION[$timerKey]);
    $msg = urlencode("Booking window expired (5 minutes). Please search again.");
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

// ── Load bus seat categories ───────────────────────────────────
$busRes  = $conn->query("SELECT * FROM buses WHERE id=$busid AND approved=1");
$businfo = $busRes ? $busRes->fetch_assoc() : null;

if (!$businfo) {
    $msg = urlencode("Bus not found or not approved.");
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

$studentSeats = !empty($businfo['student_seats'])
    ? array_map('trim', explode(',', $businfo['student_seats'])) : [];
$staffSeats   = !empty($businfo['staff_seats'])
    ? array_map('trim', explode(',', $businfo['staff_seats']))   : [];
$generalSeats = !empty($businfo['general_seats'])
    ? array_map('trim', explode(',', $businfo['general_seats'])) : [];

// ── Validate each requested seat ──────────────────────────────
$badSeats = [];
foreach ($seats_raw as $seat) {
    $seat = trim($seat);
    $cat  = 'general';
    if (in_array($seat, $studentSeats))      $cat = 'student';
    elseif (in_array($seat, $staffSeats))    $cat = 'staff';

    $allowed = false;

    if ($usertype === 'Student') {
        if ($cat === 'student') {
            $allowed = true;
        } elseif ($cat === 'staff') {
            // Only allowed in the last 2 minutes (elapsed >= UNLOCK threshold)
            $allowed = ($elapsed >= $UNLOCK);
        } elseif ($cat === 'general') {
            $allowed = true;
        }
    } elseif ($usertype === 'Staff') {
        $allowed = ($cat === 'staff' || $cat === 'general');
    } elseif ($usertype === 'Passenger') {
        $allowed = ($cat === 'general');
    }

    if (!$allowed) $badSeats[] = "$seat ($cat)";
}

if (!empty($badSeats)) {
    $msg = urlencode("You are not allowed to book: " . implode(', ', $badSeats));
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

// ── Apply discount ─────────────────────────────────────────────
if ($usertype === 'Student') $fare = (int)round($fare * 0.90);
elseif ($usertype === 'Staff') $fare = (int)round($fare * 0.95);

// ── Insert ticket ──────────────────────────────────────────────
$seats_db  = $conn->real_escape_string(serialize(array_values($seats_raw)));
$jdate_db  = $conn->real_escape_string($jdate);

$has_confirmed = $conn->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
if ($has_confirmed && $has_confirmed->num_rows > 0) {
    $sql = "INSERT INTO tickets (passenger_id, bus_id, jdate, seats, fare, booking_confirmed)
            VALUES ($passenger, $busid, '$jdate_db', '$seats_db', $fare, 1)";
} else {
    $sql = "INSERT INTO tickets (passenger_id, bus_id, jdate, seats, fare)
            VALUES ($passenger, $busid, '$jdate_db', '$seats_db', $fare)";
}

if ($conn->query($sql)) {
    $ticket_id = $conn->insert_id;
    // Clear timer
    unset($_SESSION[$timerKey]);
    $conn->close();
    header("Location: history.php?booked=$ticket_id");
    exit();
} else {
    $err = urlencode("DB error: " . $conn->error);
    $conn->close();
    header("Location: buy_ticket.php?error=$err");
    exit();
}
?>