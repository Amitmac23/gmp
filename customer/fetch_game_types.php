<?php
require_once '../config/config.php';

if (isset($_GET['game_id']) && intval($_GET['game_id']) === 19) {
    $stmt = $pdo->prepare("SELECT name, price FROM games WHERE parent_game_id = ?");
    $stmt->execute([19]);
    $gameTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($gameTypes);
} else {
    echo json_encode([]); // Return empty array for other game IDs
}
?>
