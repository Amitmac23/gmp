<?php
session_start();
require_once '../config/config.php';  // Include your database connection

// Get the booking details from the AJAX request (decoded JSON)
$data = json_decode(file_get_contents("php://input"), true);

// Check if the required fields are present
if (!isset($data['customer_name'], $data['customer_phone'], $data['start_time'], $data['player_count'], $data['game_id'], $data['table_id'], $data['total_price'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Check if customer already exists
$query = "SELECT id FROM customers WHERE name = :name AND phone = :phone";
$stmt = $pdo->prepare($query);
$stmt->execute(['name' => $data['customer_name'], 'phone' => $data['customer_phone']]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if ($customer) {
    // Customer exists, get customer_id
    $customer_id = $customer['id'];
} else {
    // Customer does not exist, insert into customers table
    $query = "INSERT INTO customers (name, phone) VALUES (:name, :phone)";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['name' => $data['customer_name'], 'phone' => $data['customer_phone']]);
    $customer_id = $pdo->lastInsertId(); // Get the last inserted customer ID
}

// Set time zone to IST and format start time to 'Y-m-d H:i:s'
$ist_timezone = new DateTimeZone('Asia/Kolkata');
$start_time = DateTime::createFromFormat('Y-m-d H:i:s', $data['start_time'], new DateTimeZone('UTC'));

if (!$start_time) {
    echo json_encode(['success' => false, 'message' => 'Invalid start time format.']);
    exit;
}

// Convert start time to IST
$start_time->setTimezone($ist_timezone);
$start_time_formatted = $start_time->format('Y-m-d H:i:s');

// Initialize variables for duration and end time
$duration = null;
$end_time_formatted = null;
$frame = 0; // Default frame value

// Check if the duration is "Frame"
if ($data['duration'] === "frame") {
    $frame = 1; // Set frame value to 1
} else {
    // Calculate end time based on duration in minutes and convert it to IST
    $duration = new DateInterval('PT' . ($data['duration'] * 60) . 'M'); // duration in minutes
    $end_time = clone $start_time;
    $end_time->add($duration);
    $end_time_formatted = $end_time->format('Y-m-d H:i:s');
}

// Calculate total price
$total_price = $data['total_price'];

// Prepare the SQL query for inserting the booking
$query = "INSERT INTO bookings (customer_id, game_id, table_id, start_time, player_count, total_price, frame" . 
         ($data['duration'] !== "frame" ? ", duration, end_time" : "") . 
         ") VALUES (:customer_id, :game_id, :table_id, :start_time, :player_count, :total_price, :frame" . 
         ($data['duration'] !== "frame" ? ", :duration, :end_time" : "") . 
         ")";

// Prepare the statement
$stmt = $pdo->prepare($query);

// Bind parameters
$params = [
    'customer_id' => $customer_id,
    'game_id' => $data['game_id'],
    'table_id' => $data['table_id'],
    'start_time' => $start_time_formatted,
    'player_count' => $data['player_count'],
    'total_price' => $total_price,
    'frame' => $frame, // Bind the frame value
];

if ($data['duration'] !== "frame") {
    $params['duration'] = $data['duration'];
    $params['end_time'] = $end_time_formatted;
}

// Execute the prepared statement
$stmt->execute($params);

// Update table status to "booked"
$query = "UPDATE tables SET status = 'booked' WHERE id = :table_id";
$stmt = $pdo->prepare($query);
$stmt->execute(['table_id' => $data['table_id']]);

// Return success response
echo json_encode(['success' => true, 'message' => 'Booking confirmed and table status updated with IST timings.']);
?>