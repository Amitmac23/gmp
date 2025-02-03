<?php
require_once '../config/config.php';
date_default_timezone_set('Asia/Kolkata');

// Get the raw POST data
$data = json_decode(file_get_contents("php://input"), true);

// Log the received data for debugging
error_log(print_r($data, true)); // Log the data to the error log

if (isset($data['table_id'], $data['duration'], $data['total_price'], $data['end_time'], $data['booking_id'])) {
    $tableId = $data['table_id'];
    $duration = $data['duration'];
    $totalPrice = $data['total_price'];
    $endTime = $data['end_time'];
    $bookingId = $data['booking_id'];

    // Update the booking in the database
    $stmt = $pdo->prepare("UPDATE bookings SET duration = ?, total_price = ?, end_time = ? WHERE id = ?");
    if ($stmt->execute([$duration, $totalPrice, $endTime, $bookingId])) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update booking.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
}
?>