<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Your existing code
require_once '../config/config.php';

if (isset($_POST['table_id'])) {
    $tableId = $_POST['table_id'];
    
    try {
        // Begin the transaction
        $pdo->beginTransaction();

        // Update table status to 'available' in the tables table
        $updateTableQuery = "UPDATE tables SET status = 'available' WHERE id = :table_id";
        $stmt1 = $pdo->prepare($updateTableQuery);
        $stmt1->bindParam(':table_id', $tableId, PDO::PARAM_INT);
        $stmt1->execute();

        // Check if the update affected any rows
        if ($stmt1->rowCount() == 0) {
            throw new Exception("No table status updated. Please check the table ID.");
        }

        // Update the canceled column to 1 for the booking related to this table, ensuring no other tables are affected
        $updateBookingQuery = "UPDATE bookings SET canceled = 1 WHERE table_id = :table_id AND canceled = 0 AND end_time > NOW()";
        $stmt2 = $pdo->prepare($updateBookingQuery);
        $stmt2->bindParam(':table_id', $tableId, PDO::PARAM_INT);
        $stmt2->execute();

        // Check if the booking update affected any rows
        if ($stmt2->rowCount() == 0) {
            throw new Exception("No active booking canceled. Please check the table ID and booking status.");
        }

        // Commit the transaction
        $pdo->commit();

        echo json_encode(["success" => true]);
    } catch (Exception $e) {
        // Rollback the transaction if an error occurs
        $pdo->rollBack();
        echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid table ID"]);
}
