<?php
session_start();
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get table_id, payment_method, and booking_id from the POST request
    $tableId = $_POST['table_id'];
    $paymentMethod = $_POST['payment_method'];
    $bookingId = $_POST['booking_id']; // Get the booking ID

    // Log the input values for debugging
    error_log("Table ID: $tableId, Payment Method: $paymentMethod, Booking ID: $bookingId");

    // Check if the booking exists and is pending
    $checkStmt = $pdo->prepare("
        SELECT * FROM bookings 
        WHERE id = ? AND payment_status = 'pending' AND canceled = 0
    ");
    $checkStmt->execute([$bookingId]);
    $booking = $checkStmt->fetch();

    if ($booking) {
        // Process the payment (update the payment status and method)
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET payment_status = 'paid', 
                payment_method = ? 
            WHERE id = ? 
        ");
        $stmt->execute([$paymentMethod, $bookingId]); // Use booking ID to target specific booking

        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Payment processed successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update payment status.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'No pending payment found for this booking.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>