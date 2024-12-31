<?php
// Include database configuration
require_once '../config/config.php';// Make sure this file contains your database connection setup using PDO

// Start the session
session_start();

// Check if the customer ID is set in the session
if (!isset($_SESSION['customer_id'])) {
    // If not, redirect to login page
    header("Location: register.php");
    exit();
}


try {
    // Fetch games from the database
    $stmt = $pdo->query("SELECT id, name, game_description, game_image FROM games");
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Games</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <style>
        body {
            background-color: #f7f7f7;
            font-family: 'Press Start 2P',;
        }
        .game-card {
            position: relative;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease-in-out;
        }
        .game-card:hover {
            transform: scale(1.05);
        }
        .game-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            transition: all 0.3s ease-in-out;
        }
        .game-card-body {
            padding: 15px;
            background: #fff;
        }
        .game-card-body h5 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #333;
        }
        .game-card-body p {
            font-size: 1rem;
            color: #777;
        }
        .game-card-body .btn {
            background: linear-gradient(90deg, #ff8a00, #e52e71);
            border: none;
            padding: 12px 25px;
            font-size: 1.2rem;
            border-radius: 8px;
            box-shadow: 0px 0px 20px #e52e71;
            color: white;
            transition: background-color 0.3s ease;
        }
        .game-card-body .btn:hover {
            background-color: #ff8a00;
        }
        .container {
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <div class="container" data-aos="fade-up">
        <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back
    </a>
        <h1 class="text-center mb-5">Our Games</h1>

        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4">
    <?php foreach ($games as $game): ?>
        <div class="col">
            <div class="game-card" data-aos="zoom-in" data-aos-delay="<?= $game['id'] * 100 ?>">
                <img src="../assets/images/<?= htmlspecialchars($game['game_image']) ?>" 
                     alt="<?= htmlspecialchars($game['name']) ?>" class="game-image">
                <div class="game-card-body">
                    <h5><?= htmlspecialchars($game['name']) ?></h5>
                    <p><?= htmlspecialchars($game['game_description']) ?></p>
                    <a href="select_table.php?id=<?= $game['id'] ?>" class="btn">Play Now</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

    <!-- Initialize AOS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
    </script>
</body>
</html>
