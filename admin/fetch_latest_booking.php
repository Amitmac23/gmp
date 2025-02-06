<?php
require_once '../config/config.php';

// Check if table_id is provided and is numeric
if (isset($_GET['table_id']) && is_numeric($_GET['table_id'])) {
    $tableId = intval($_GET['table_id']);

    // Prepare the SQL query to fetch the latest booking for the given table
    $stmt = $pdo->prepare("
        SELECT 
            b.*, 
            c.name AS customer_name, 
            g.name AS game_name
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.id
        LEFT JOIN games g ON b.game_id = g.id
        WHERE b.table_id = ? AND b.canceled = 0
        ORDER BY b.start_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$tableId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($booking) {
        // If a booking is found, return it in JSON format
        echo json_encode(['success' => true, 'booking' => $booking]);
    } else {
        // If no booking is found, return an empty response
        echo json_encode(['success' => false]);
    }
} else {
    // If table_id is not provided or is not numeric, return an error message
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>