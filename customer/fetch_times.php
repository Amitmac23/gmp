<?php
require_once '../config/config.php';  // Include your database connection file

// Get available times from the time_slot table
$sql = "SELECT * FROM time_slot";  // Adjust this based on your table structure
$stmt = $pdo->prepare($sql);
$stmt->execute();
$timeSlots = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing bookings to compare
$bookedTimesSql = "SELECT start_time FROM bookings WHERE start_time >= NOW()";  // Adjust as needed
$stmt = $pdo->prepare($bookedTimesSql);
$stmt->execute();
$bookedTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Extract booked times into an array
$bookedTimesArray = array_map(function($row) {
    return $row['start_time'];
}, $bookedTimes);

echo json_encode(['timeSlots' => $timeSlots, 'bookedTimes' => $bookedTimesArray]);
?>
