<?php
/**
 * book.php - Dedicated booking handler
 * Accepts POST from the seat selection modal form
 * Works for Passenger, Student, and Staff user types
 */
session_start();

// Must be logged in as a passenger-type user
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['utype'], ['Passenger','Student','Staff'])) {
    header('Location: index.php');
    exit();
}

require_once 'inc/database.php';
$conn = initDB();

// ── Read POST fields ──────────────────────────────────────────
$busid     = (int)($_POST['bus_id'] ?? 0);
$jdate     = trim($_POST['jdate']   ?? '');
$fare      = (int)($_POST['fare']   ?? 0);
$passenger = (int)$_SESSION['user']['id'];
$usertype  = $_SESSION['user']['utype'];

// seats[] comes as a normal PHP array from checkbox[] inputs
$seats_raw = $_POST['seats'] ?? [];

// ── Validate ──────────────────────────────────────────────────
$errors = [];
if ($busid   <= 0)          $errors[] = "Invalid bus.";
if ($jdate   === '')        $errors[] = "Missing journey date.";
if ($fare    <= 0)          $errors[] = "Invalid fare (did you select seats?).";
if (empty($seats_raw))      $errors[] = "No seats selected.";

if (!empty($errors)) {
    // Go back with error message
    $msg = urlencode(implode(' ', $errors));
    header("Location: buy_ticket.php?error=$msg");
    exit();
}

// ── Apply discount ────────────────────────────────────────────
if ($usertype === 'Student') {
    $fare = (int)round($fare * 0.90);   // 10 % off
} elseif ($usertype === 'Staff') {
    $fare = (int)round($fare * 0.95);   // 5 % off
}

// ── Serialize the seat array ──────────────────────────────────
if (is_array($seats_raw)) {
    $seats_serialized = serialize(array_values($seats_raw));
} else {
    // Fallback: single seat sent as a string
    $seats_serialized = serialize([$seats_raw]);
}

$seats_db  = $conn->real_escape_string($seats_serialized);
$jdate_db  = $conn->real_escape_string($jdate);

// ── Check whether booking_confirmed column exists ─────────────
$has_confirmed_col = false;
$chk = $conn->query("SHOW COLUMNS FROM tickets LIKE 'booking_confirmed'");
if ($chk && $chk->num_rows > 0) {
    $has_confirmed_col = true;
}

// ── INSERT ────────────────────────────────────────────────────
if ($has_confirmed_col) {
    $sql = "INSERT INTO tickets
                (passenger_id, bus_id, jdate, seats, fare, booking_confirmed)
            VALUES
                ($passenger, $busid, '$jdate_db', '$seats_db', $fare, 1)";
} else {
    $sql = "INSERT INTO tickets
                (passenger_id, bus_id, jdate, seats, fare)
            VALUES
                ($passenger, $busid, '$jdate_db', '$seats_db', $fare)";
}

if ($conn->query($sql)) {
    $ticket_id = $conn->insert_id;
    $conn->close();
    // Redirect to history with success flag
    header("Location: history.php?booked=$ticket_id");
    exit();
} else {
    $err = urlencode("DB error: " . $conn->error);
    $conn->close();
    header("Location: buy_ticket.php?error=$err");
    exit();
}
?>