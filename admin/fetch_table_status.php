<?php
session_start();
require_once '../config/config.php';

// Fetch all tables and their current status
$stmt = $pdo->query("
    SELECT tables.id AS table_id, tables.table_number, tables.status, bookings.start_time, bookings.end_time, customers.name AS customer_name, games.name AS game_name
    FROM tables
    LEFT JOIN bookings ON tables.id = bookings.table_id AND bookings.canceled = 0
    LEFT JOIN customers ON bookings.customer_id = customers.id
    LEFT JOIN games ON bookings.game_id = games.id
");

$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($tables);
?>