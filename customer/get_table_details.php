<?php

require_once '../config/config.php';
// Example PHP endpoint to fetch table details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $tableId = $data['table_id'];

    // Fetch table details from the database
    $stmt = $pdo->prepare("SELECT min_capacity, max_capacity, extra_charge FROM tables WHERE id = :tableId");
    $stmt->bindParam(':tableId', $tableId, PDO::PARAM_INT);
    $stmt->execute();
    $table = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($table) {
    echo json_encode([
        'success' => true,
        'min_players' => $table['min_capacity'],
        'max_players' => $table['max_capacity'],
        'extra_charge_per_player' => $table['extra_charge']
    ]);
} 
}
?>
