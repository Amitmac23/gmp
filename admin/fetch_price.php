<?php
// Database connection
require_once '../config/config.php';

if (isset($_GET['game_id']) && is_numeric($_GET['game_id'])) {
    $gameId = intval($_GET['game_id']);

    // Updated query to fetch price and additional fields
    $query = "
        SELECT g.price_per_half_hour, t.min_capacity, t.max_capacity, t.extra_charge 
        FROM games g
        LEFT JOIN table_game tg ON g.id = tg.game_id
        LEFT JOIN tables t ON tg.table_id = t.id
        WHERE g.id = :game_id
    ";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':game_id', $gameId, PDO::PARAM_INT);
    $stmt->execute();

    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($game) {
        echo json_encode([
            'success' => true,
            'price' => (float) $game['price_per_half_hour'],
            'min_capacity' => (int) $game['min_capacity'],
            'max_capacity' => (int) $game['max_capacity'],
            'extra_charge' => (float) $game['extra_charge']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Game not found']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>