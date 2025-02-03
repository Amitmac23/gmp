<?php

// get_table_status.php
require_once '../config/config.php'; // Include your DB config

header('Content-Type: application/json');

// Get the table ID
$data = json_decode(file_get_contents('php://input'), true);
$tableId = $data['tableId'] ?? null;

if ($tableId && is_numeric($tableId)) {
    try {
        // Query the database to get the table's current status and end time
        $stmt = $pdo->prepare("SELECT status, end_time FROM tables WHERE id = :tableId");
        $stmt->execute(['tableId' => $tableId]);
        $table = $stmt->fetch();

        if ($table) {
            $status = $table['status'];
            $endTime = $table['end_time'];

            // Return status and end time
            echo json_encode(['status' => $status, 'endTime' => $endTime]);
        } else {
            echo json_encode(['status' => 'available']);
        }
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['error' => 'Invalid table ID']);
}


?>