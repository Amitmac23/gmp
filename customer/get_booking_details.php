<?php
// get_booking_details.php

// Include database connection
require_once '../config/config.php';

$data = json_decode(file_get_contents("php://input"), true);
$tableId = $data['table_id'];

$response = ['success' => false, 'error' => 'Table ID missing'];

if ($tableId) {
    // Prepare SQL query to get booking details for the given table
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE table_id = :table_id ORDER BY start_time DESC LIMIT 1");
    $stmt->execute(['table_id' => $tableId]);
    $booking = $stmt->fetch();

    if ($booking) {
        // Return booking details including frame value
        $response = [
            'success' => true,
            'frame' => $booking['frame'], // Assuming the column is named 'frame'
            'start_time' => $booking['start_time'],
            'end_time' => $booking['end_time']
        ];
    } else {
        $response['error'] = 'No booking found for this table';
    }
}

echo json_encode($response);
?>
