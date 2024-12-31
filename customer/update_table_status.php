<?php
// update_table_status.php

// Include your database connection
require_once '../config/config.php'; // Update the path to match your project structure

// Retrieve the POST data (table ID from JSON input)
$data = json_decode(file_get_contents('php://input'), true);
$tableId = $data['table_id'] ?? null;

// Debugging: Log the received table ID
error_log("Received Table ID: " . var_export($tableId, true)); 

if ($tableId) {
    try {
        // Update the table status to 'available' in the database
        $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = :table_id");
        $stmt->execute(['table_id' => $tableId]);

        // Debugging: Log the success
        error_log("Table status updated successfully for table ID: " . $tableId);

        // Return success response as JSON
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        // Handle any database errors
        error_log("Database error: " . $e->getMessage()); // Log the error message

        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    // Handle invalid or missing table ID
    error_log("Invalid table ID: " . var_export($tableId, true));

    echo json_encode([
        'success' => false,
        'message' => 'Invalid table ID'
    ]);
}
?>
