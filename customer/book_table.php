<?php
// book_table.php
require_once '../config/config.php';  // Include your database connection

// Start the session to access session variables
session_start();  // Ensure session is started to access the customer_id from the session

// Check if required POST data is set
if (isset($_POST['table_id'], $_POST['game_id'], $_POST['start_time'], $_POST['total_price'], $_POST['player_count'], $_POST['frame'])) {
    // Get the POST data
    $table_id = $_POST['table_id'];
    $game_id = $_POST['game_id'];
    $customer_id = $_SESSION['customer_id'];  // Get customer_id from the session
    $start_time = $_POST['start_time'];
    $total_price = $_POST['total_price'];
    $player_count = $_POST['player_count'];  // Capture player count from POST data
    $frame = $_POST['frame'];  // Capture the frame value (1 or 0)

    // Ensure the start_time is in the correct format (YYYY-MM-DD HH:MM:SS)
    $start_time = date('Y-m-d H:i:s', strtotime($start_time)); 

    // Set duration and end_time to null initially
    $duration = $end_time = null;

    // If frame is not selected (i.e., not frame), we should have duration and end_time
    if ($frame == '0' && isset($_POST['duration'], $_POST['end_time'])) {
        // Get the duration and end time if available
        $duration = $_POST['duration'];
        $end_time = $_POST['end_time'];

        // Ensure the end_time is in the correct format (YYYY-MM-DD HH:MM:SS)
        $end_time = date('Y-m-d H:i:s', strtotime($end_time));
    }

    try {
        // Start the transaction
        $pdo->beginTransaction();

        // Step 1: Update the table status to 'booked' in the database
        $stmt = $pdo->prepare("UPDATE tables SET status = 'booked' WHERE id = :table_id");
        $stmt->execute(['table_id' => $table_id]);

        // Step 2: Insert the booking data into the bookings table, including player_count, customer_id, frame, and other fields
        $stmt = $pdo->prepare("INSERT INTO bookings (table_id, game_id, customer_id, start_time, duration, total_price, end_time, player_count, frame) 
                               VALUES (:table_id, :game_id, :customer_id, :start_time, :duration, :total_price, :end_time, :player_count, :frame)");
        $stmt->execute([
            'table_id' => $table_id,
            'game_id' => $game_id,
            'customer_id' => $customer_id,  // Insert customer_id from session
            'start_time' => $start_time,
            'duration' => $duration, // Can be null if frame
            'total_price' => $total_price,
            'end_time' => $end_time, // Can be null if frame
            'player_count' => $player_count,  // Insert player count into the bookings table
            'frame' => $frame  // Insert frame value (1 for frame, 0 for regular)
        ]);

        // Commit the transaction
        $pdo->commit();

        // Return a JSON response indicating success
        echo json_encode(['status' => 'success', 'table_id' => $table_id, 'new_status' => 'booked', 'frame'=> $frame]);
    } catch (PDOException $e) {
        // If there is an error, roll back the transaction and display the error message
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    // If any required POST data is missing, return an error
    echo json_encode(['status' => 'error', 'message' => 'Invalid input data']);
}
?>
