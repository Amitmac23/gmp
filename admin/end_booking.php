<?php
require_once '../config/config.php';

date_default_timezone_set('Asia/Kolkata');

if (isset($_POST['table_id'])) {
    $tableId = $_POST['table_id'];

    try {
        // Step 1: Check the frame value in the bookings table
        $stmt = $pdo->prepare("SELECT frame, start_time FROM bookings WHERE table_id = :table_id AND canceled = 0 ORDER BY start_time DESC LIMIT 1");
        $stmt->bindParam(':table_id', $tableId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Fetch the booking details
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($booking) {
            // Step 2: Check if the frame value is 1
            if ($booking['frame'] == 1) {
                // Calculate the duration (in minutes)
                $startTime = new DateTime($booking['start_time']);
                $currentTime = new DateTime(); // Current time

                // Calculate the difference in minutes, including both hours and minutes
                $interval = $startTime->diff($currentTime);
                $durationInMinutes = ($interval->h * 60) + $interval->i; // Total duration in minutes

                // Update the booking with the end_time and duration
                $updateBookingStmt = $pdo->prepare("UPDATE bookings SET end_time = :end_time, duration = :duration WHERE table_id = :table_id AND canceled = 0");
                $endTime = $currentTime->format('Y-m-d H:i:s'); // Store formatted end time in a variable
                $updateBookingStmt->bindParam(':end_time', $endTime);
                $updateBookingStmt->bindParam(':duration', $durationInMinutes); // Insert duration in minutes
                $updateBookingStmt->bindParam(':table_id', $tableId, PDO::PARAM_INT);
                $updateBookingStmt->execute();
            }

            // Step 3: Update the status of the table to 'available'
            $updateTableStmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = :table_id");
            $updateTableStmt->bindParam(':table_id', $tableId, PDO::PARAM_INT);
            $updateTableStmt->execute();

            // Send success response back to the frontend
            echo json_encode(['success' => true, 'message' => 'Booking ended successfully.']);
        } else {
            // No booking found for the table
            echo json_encode(['success' => false, 'message' => 'No active booking found for this table.']);
        }
    } catch (Exception $e) {
        // Handle any errors
        echo json_encode(['success' => false, 'message' => 'Failed to end booking: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Table ID not provided.']);
}
?>