<?php
// Database connection
require_once '../config/config.php';

// Debug: Log the request parameters
error_log("Received request with parameters: " . print_r($_GET, true));

if (isset($_GET['game_id']) && is_numeric($_GET['game_id']) && isset($_GET['table_id']) && is_numeric($_GET['table_id'])) {
    $gameId = intval($_GET['game_id']);
    $tableId = intval($_GET['table_id']);

    // Debug: Log the received game_id and table_id
    error_log("Received game_id: " . $gameId);
    error_log("Received table_id: " . $tableId);

    // Query to fetch price from games table and min_capacity from tables table
    $query = "
        SELECT g.price_per_half_hour, t.min_capacity, t.max_capacity, t.extra_charge 
        FROM games g
        JOIN table_game tg ON g.id = tg.game_id
        JOIN tables t ON tg.table_id = t.id
        WHERE g.id = :game_id AND t.id = :table_id
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->bindParam(':table_id', $tableId, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode([
            'success' => true,
            'price' => (float) $result['price_per_half_hour'],
            'min_capacity' => (int) $result['min_capacity'],
            'max_capacity' => (int) $result['max_capacity'],
            'extra_charge' => (float) $result['extra_charge']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Game or table not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>