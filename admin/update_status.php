<?php
// Include your database connection
require_once '../config/config.php'; // Update this path if necessary

// Retrieve data from the POST request
$data = json_decode(file_get_contents('php://input'), true);
$tableId = $data['tableId'] ?? null; // Ensure the key matches the frontend's JSON

// Debugging
file_put_contents('debug.log', print_r($data, true), FILE_APPEND);

if ($tableId && is_numeric($tableId)) {
    try {

        // Prepare the SQL statement to update the table status
        $stmt = $pdo->prepare("UPDATE tables SET status = 'available' WHERE id = :table_id");
        $stmt->execute(['table_id' => $tableId]);

        // Return success response
        echo json_encode(['success' => true, 'message' => 'Table status updated successfully']);
    } catch (PDOException $e) {
        // Handle database errors
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
} else {
    // Handle invalid or missing table ID
    echo json_encode([
        'success' => false,
        'message' => 'Invalid or missing table ID'
    ]);
}

?>
