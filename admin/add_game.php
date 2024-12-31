<?php
// Database connection
require_once '../config/config.php';

// Check if form is submitted for adding a game
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_game'])) {
    // Get the form data
    $game_name = $_POST['game_name'];
    $game_description = $_POST['game_description'];
    $game_image = $_FILES['game_image']['name'];

    // Image upload path
    $upload_dir = 'assets/images/';
    $upload_file = $upload_dir . basename($game_image);

    // Move the uploaded image to the directory
    if (move_uploaded_file($_FILES['game_image']['tmp_name'], $upload_file)) {
        // Insert the game data into the database
        $stmt = $pdo->prepare("INSERT INTO games (game_name, game_description, game_image) VALUES (?, ?, ?)");
        $stmt->execute([$game_name, $game_description, $game_image]);

        // Redirect to manage games page or display success message
        header('Location: manage_games.php');
        exit;
    } else {
        echo '<div class="alert alert-danger">Failed to upload image!</div>';
    }
}
?>
