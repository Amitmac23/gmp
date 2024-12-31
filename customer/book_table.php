<?php
// book_table.php
require_once '../config/config.php';  // Include your database connection

// Start the session to access session variables
session_start();  // Ensure session is started to access the customer_id from the session

// Check if required POST data is set
if (isset($_POST['table_id'], $_POST['game_id'], $_POST['start_time'], $_POST['duration'], $_POST['total_price'], $_POST['end_time'])) {
    // Get the POST data
    $table_id = $_POST['table_id'];
    $game_id = $_POST['game_id'];
    $customer_id = $_SESSION['customer_id'];  // Get customer_id from the session
    $start_time = $_POST['start_time'];
    $duration = $_POST['duration'];
    $total_price = $_POST['total_price'];
    $end_time = $_POST['end_time'];

    // Ensure the start_time and end_time are in the correct format (YYYY-MM-DD HH:MM:SS)
    $start_time = date('Y-m-d H:i:s', strtotime($start_time)); 
    $end_time = date('Y-m-d H:i:s', strtotime($end_time));

    try {
        // Start the transaction
        $pdo->beginTransaction();

        // Step 1: Update the table status to 'booked' in the database
        $stmt = $pdo->prepare("UPDATE tables SET status = 'booked' WHERE id = :table_id");
        $stmt->execute(['table_id' => $table_id]);

        // Step 2: Insert the booking data into the bookings table, including customer_id
        $stmt = $pdo->prepare("INSERT INTO bookings (table_id, game_id, customer_id, start_time, duration, total_price, end_time) 
                               VALUES (:table_id, :game_id, :customer_id, :start_time, :duration, :total_price, :end_time)");
        $stmt->execute([
            'table_id' => $table_id,
            'game_id' => $game_id,
            'customer_id' => $customer_id,  // Insert customer_id from session
            'start_time' => $start_time,
            'duration' => $duration,
            'total_price' => $total_price,
            'end_time' => $end_time
        ]);

        // Commit the transaction
        $pdo->commit();

        // Return a JSON response indicating success
        echo json_encode(['status' => 'success', 'table_id' => $table_id, 'new_status' => 'booked']);
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
