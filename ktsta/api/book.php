<?php
require_once __DIR__ . '/../includes/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Method not allowed']); exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    echo json_encode(['error' => 'Invalid request']); exit;
}

$db = getDB();
$tripId = (int)($data['trip_id'] ?? 0);
$seat = (int)($data['seat'] ?? 0);
$name = $db->real_escape_string(trim($data['name'] ?? ''));
$phone = $db->real_escape_string(trim($data['phone'] ?? ''));
$method = $db->real_escape_string($data['method'] ?? 'cash');
$passId = isLoggedIn() ? (int)$_SESSION['user_id'] : 0;

// Validate
if (!$tripId || !$seat || !$name) {
    echo json_encode(['error' => 'Missing required fields']); exit;
}

// Check trip
$trip = $db->query("SELECT t.*, r.origin, r.destination, b.bus_number FROM trips t 
    JOIN routes r ON t.route_id=r.id JOIN buses b ON t.bus_id=b.id WHERE t.id=$tripId")->fetch_assoc();
if (!$trip) { echo json_encode(['error' => 'Trip not found']); exit; }
if ($trip['available_seats'] <= 0) { echo json_encode(['error' => 'No seats available']); exit; }

// Check seat not already taken
$taken = $db->query("SELECT id FROM bookings WHERE trip_id=$tripId AND seat_number=$seat AND booking_status NOT IN ('cancelled')");
if ($taken->num_rows > 0) { echo json_encode(['error' => 'Seat already taken. Please choose another.']); exit; }

// If wallet payment, check balance
if ($method === 'wallet' && $passId) {
    $user = getUserById($passId);
    if ($user['wallet_balance'] < $trip['fare']) {
        echo json_encode(['error' => 'Insufficient wallet balance. Please top up or choose another payment method.']); exit;
    }
}

// Create booking
$ref = generateRef('BKG');
$fare = $trip['fare'];
$payStatus = ($method === 'cash') ? 'pending' : 'paid';
$userId = $passId ?: 1; // default to admin-created if not logged in

$db->query("INSERT INTO bookings (booking_ref, passenger_id, trip_id, seat_number, passenger_name, passenger_phone, fare, payment_method, payment_status, booking_status, booked_by) 
    VALUES ('$ref', $userId, $tripId, $seat, '$name', '$phone', $fare, '$method', '$payStatus', 'confirmed', $userId)");
$bookingId = $db->insert_id;

// Deduct wallet
if ($method === 'wallet' && $passId) {
    $db->query("UPDATE users SET wallet_balance = wallet_balance - $fare WHERE id=$passId");
    $newBal = $db->query("SELECT wallet_balance FROM users WHERE id=$passId")->fetch_assoc()['wallet_balance'];
    $db->query("INSERT INTO wallet_transactions (user_id, amount, type, description, balance_after, reference) 
        VALUES ($passId, $fare, 'debit', 'Bus ticket booking - $ref', $newBal, '$ref')");
}

// Update available seats
$db->query("UPDATE trips SET available_seats = available_seats - 1 WHERE id=$tripId");

// Generate QR code
$qrData = "KTSTA|{$ref}|{$name}|{$trip['origin']}|{$trip['destination']}|SEAT:{$seat}";
$qrUrl = generateQR($qrData);
$db->query("UPDATE bookings SET qr_code='".addslashes($qrUrl)."' WHERE id=$bookingId");

// Notification
if ($passId) {
    addNotification($passId, 'Booking Confirmed', "Your booking $ref has been confirmed. Seat $seat on " . $trip['origin'] . " → " . $trip['destination'], 'booking');
}

// Payment record
$payRef = generateRef('PAY');
$db->query("INSERT INTO payments (payment_ref, booking_id, user_id, amount, payment_method, status, description) 
    VALUES ('$payRef', $bookingId, $userId, $fare, '$method', '$payStatus', 'Ticket booking - $ref')");

echo json_encode([
    'success' => true,
    'booking_ref' => $ref,
    'origin' => $trip['origin'],
    'destination' => $trip['destination'],
    'departure' => date('D, d M Y H:i', strtotime($trip['departure_datetime'])),
    'seat' => $seat,
    'passenger' => $name,
    'fare' => $fare,
    'bus' => $trip['bus_number'],
    'qr' => $qrUrl,
    'payment_status' => $payStatus,
]);
