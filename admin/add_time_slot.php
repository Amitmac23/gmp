<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $game_id = $_POST['game_id'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    // Insert the time slot
    include 'config.php'; // Your database connection file
    $stmt = $pdo->prepare("INSERT INTO time_slots (game_id, start_time, end_time) VALUES (?, ?, ?)");
    $stmt->execute([$game_id, $start_time, $end_time]);

    echo "Time slot added successfully!";
}
?>
<h2>Manage Time Slots</h2>
<form action="add_time_slot.php" method="POST">
    <label for="game_id">Game:</label>
    <select name="game_id">
        <!-- Fetch games from the database -->
        <?php
        $games = $pdo->query("SELECT id, name FROM games")->fetchAll();
        foreach ($games as $game) {
            echo "<option value='{$game['id']}'>{$game['name']}</option>";
        }
        ?>
    </select><br><br>

    <label for="start_time">Start Time:</label>
    <input type="time" name="start_time" required><br><br>
    
    <label for="end_time">End Time:</label>
    <input type="time" name="end_time" required><br><br>
    
    <button type="submit">Add Time Slot</button>
</form>
