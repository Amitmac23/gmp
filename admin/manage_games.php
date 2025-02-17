<?php

session_start();

// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

// Database connection
require_once '../config/config.php';

// Check if the form is submitted for adding a game
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_game'])) {
    $name = $_POST['name'];
    $game_description = $_POST['game_description'];
    $game_image = $_FILES['game_image']['name'];
    $price_per_half_hour = $_POST['price_per_half_hour'];
    $has_frame = $_POST['has_frame'];
    $frame_price = ($has_frame === 'yes') ? floatval($_POST['frame_price']) : null; // Frame price if applicable

    if (empty($name)) {
        echo '<div class="alert alert-danger">Game name is required!</div>';
    } else {
        $upload_dir = '../assets/images/';
        $upload_file = $upload_dir . basename($game_image);
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $image_extension = strtolower(pathinfo($game_image, PATHINFO_EXTENSION));

        if (in_array($image_extension, $allowed_extensions)) {
            if (move_uploaded_file($_FILES['game_image']['tmp_name'], $upload_file)) {
                $stmt = $pdo->prepare("INSERT INTO games (name, game_description, game_image, price_per_half_hour, has_frame, frame_price) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $game_description, $game_image, $price_per_half_hour, $has_frame, $frame_price]);

                echo '<div class="alert alert-success">Game added successfully!</div>';
            } else {
                echo '<div class="alert alert-danger">Failed to upload image!</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Invalid image format. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.</div>';
        }
    }
}

// Check if the form is submitted for editing a game
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_game'])) {
    $game_id = $_POST['game_id'];
    $name = htmlspecialchars($_POST['game_name']);
    $game_description = htmlspecialchars($_POST['game_description']);
    $price_per_half_hour = floatval($_POST['price_per_half_hour']); // Ensure it's a valid number
    $has_frame = $_POST['has_frame'];
    $frame_price = ($has_frame === 'yes') ? floatval($_POST['frame_price']) : null; // Frame price if applicable

    if (empty($name)) {
        echo '<div class="alert alert-danger">Game name is required!</div>';
    } else {
        if ($_FILES['game_image']['name']) {
            $game_image = time() . '_' . basename($_FILES['game_image']['name']); // Prevent overwriting
            $upload_dir = '../assets/images/';
            $upload_file = $upload_dir . $game_image;
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            $image_extension = strtolower(pathinfo($game_image, PATHINFO_EXTENSION));

            if (in_array($image_extension, $allowed_extensions)) {
                if (move_uploaded_file($_FILES['game_image']['tmp_name'], $upload_file)) {
                    $stmt = $pdo->prepare("
                        UPDATE games 
                        SET name = ?, game_description = ?, game_image = ?, price_per_half_hour = ?, has_frame = ?, frame_price = ? 
                        WHERE id = ?");
                    $stmt->execute([$name, $game_description, $game_image, $price_per_half_hour, $has_frame, $frame_price, $game_id]);

                    echo '<div class="alert alert-success">Game updated successfully!</div>';
                    header("Location: manage_games.php"); // Redirect to the game list page
                    exit();
                } else {
                    echo '<div class="alert alert-danger">Failed to upload image!</div>';
                }
            } else {
                echo '<div class="alert alert-danger">Invalid image format. Only JPG, JPEG, PNG, and GIF are allowed.</div>';
            }
        } else {
            // If no image is uploaded, update without changing the image
            $stmt = $pdo->prepare("
                UPDATE games 
                SET name = ?, game_description = ?, price_per_half_hour = ?, has_frame = ?, frame_price = ? 
                WHERE id = ?");
            $stmt->execute([$name, $game_description, $price_per_half_hour, $has_frame, $frame_price, $game_id]);

            echo '<div class="alert alert-success">Game updated successfully!</div>';
            header("Location: manage_games.php"); // Redirect to the game list page
            exit();
        }
    }
}

// Check if the delete_game button is clicked
if (isset($_POST['delete_game'])) {
    // Get the game_id from the form
    $game_id = $_POST['game_id'];

    // Prepare and execute the delete query
    $stmt = $pdo->prepare("DELETE FROM games WHERE id = :game_id");
    $stmt->execute(['game_id' => $game_id]);

    // Redirect back to the current page (or wherever you want)
    header("Location: manage_games.php");
    exit;
}

// Fetch games from the database
$stmt = $pdo->query("SELECT * FROM games");
$games = $stmt->fetchAll();
?>


<!DOCTYPE html>
<html lang="en">
<head>
 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Games</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@2.3.1/dist/aos.css">
    <style>
        body {
            background-color: #f7f7f7;
            font-family: 'Press Start 2P', cursive;
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
            margin-top: 30px;
        }
        .add-game-btn {
            margin-top: 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 8px;
            padding: 10px 20px;
            cursor: pointer;
        }
        .add-game-btn:hover {
            background-color: #45a049;
        }
        /* Style for Edit/Delete buttons */
        .edit-delete-btns {
            margin-top: 10px;
            display: flex;
            gap: 10px;
        }
        .edit-delete-btns .btn {
            background-color: rgba(0, 0, 0, 0.5);
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 1.2rem;
        }
        .edit-delete-btns .btn:hover {
            background-color: rgba(0, 0, 0, 0.7);
        }
    </style>
</head>
<?php include 'sidebar.php'; ?>
<body>

<!-- Main container for displaying games -->
<div class="container" data-aos="fade-up">
    <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-1">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <h1 class="text-center">Manage Games</h1>

    <!-- Button to trigger add game modal -->
    <div class="text-center">
        <button class="add-game-btn" data-bs-toggle="modal" data-bs-target="#addGameModal">
            Add Game
        </button>
    </div>

    <div class="row row-cols-1 row-cols-md-3 row-cols-lg-4 g-4 mt-4">
    <?php
    // Fetch games from the database
    $stmt = $pdo->query("SELECT * FROM games");
    $games = $stmt->fetchAll();

    // Loop through each game and display its details
    foreach ($games as $game) {
        $game_id = $game['id'];
        $game_name = htmlspecialchars($game['name']);
        $game_description = htmlspecialchars($game['game_description']);
        $game_image = htmlspecialchars($game['game_image']);
        $price_per_half_hour = $game['price_per_half_hour'];  // Fetch price per half hour
        $image_path = "../assets/images/" . $game_image;

        echo '
        <div class="col">
            <div class="game-card" data-aos="zoom-in" data-aos-delay="100">
                <img src="' . $image_path . '" alt="' . $game_name . '" class="game-image">
                <div class="game-card-body">
                    <h5>' . $game_name . '</h5>
                    <p>' . $game_description . '</p>
                    <p><strong>Price Per Hour : </strong>' . number_format($price_per_half_hour, 2) . '</p> <!-- Display price -->

                    <!-- Edit/Delete buttons below the description -->
                    <div class="edit-delete-btns">
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#editGameModal' . $game_id . '">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#deleteGameModal' . $game_id . '">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
        </div>
        ';
    }
    ?>
    </div>
</div>


<!-- HTML Form to Add Game -->
<div class="modal fade" id="addGameModal" tabindex="-1" aria-labelledby="addGameModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addGameModalLabel">Add New Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Game Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="game_description" class="form-label">Game Description</label>
                        <textarea class="form-control" name="game_description"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price_per_half_hour" class="form-label">Price Per Hour</label>
                        <input type="number" class="form-control" name="price_per_half_hour" value="" step="1" required>
                    </div>

                    <!-- Frame Option -->
                    <div class="mb-3">
                        <label class="form-label">Does this game have a frame?</label>
                        <div>
                            <input type="radio" name="has_frame" value="yes" id="frameYes" onclick="toggleFrameOption(true)" required>
                            <label for="frameYes">Yes</label>
                            <input type="radio" name="has_frame" value="no" id="frameNo" onclick="toggleFrameOption(false)" required>
                            <label for="frameNo">No</label>
                        </div>
                    </div>

                    <!-- Frame Price (Initially Hidden) -->
                    <div class="mb-3" id="framePriceContainer" style="display: none;">
                        <label for="frame_price" class="form-label">Frame Price</label>
                        <input type="number" class="form-control" name="frame_price" step="1">
                    </div>

                    <div class="mb-3">
                        <label for="game_image" class="form-label">Game Image</label>
                        <input type="file" class="form-control" name="game_image" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" name="add_game">Add Game</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for editing a game (dynamic for each game) -->
<?php foreach ($games as $game): ?>
<!-- Edit Game Modal -->
<div class="modal fade" id="editGameModal<?php echo $game['id']; ?>" tabindex="-1" aria-labelledby="editGameModalLabel<?php echo $game['id']; ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editGameModalLabel<?php echo $game['id']; ?>">Edit Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <!-- Game Name -->
                    <div class="mb-3">
                        <label for="game_name" class="form-label">Game Name</label>
                        <input type="text" class="form-control" name="game_name" value="<?php echo htmlspecialchars($game['name']); ?>" required>
                    </div>
                    
                    <!-- Game Description -->
                    <div class="mb-3">
                        <label for="game_description" class="form-label">Game Description</label>
                        <textarea class="form-control" name="game_description" required><?php echo htmlspecialchars($game['game_description']); ?></textarea>
                    </div>

                    <!-- Edit Half Hour Price -->
                    <div class="mb-3">
                        <label for="price_per_half_hour" class="form-label">Price Per Hour</label>
                        <input type="number" class="form-control" name="price_per_half_hour" value="<?php echo htmlspecialchars($game['price_per_half_hour']); ?>" required>
                    </div>

                    <!-- Game Image -->
                    <div class="mb-3">
                        <label for="game_image" class="form-label">Game Image</label>
                        <input type="file" class="form-control" name="game_image">
                    </div>

                </div>
                <div class="modal-footer">
                    <!-- Hidden Game ID for Editing -->
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" name="edit_game">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>


<!-- Modal for deleting a game (dynamic for each game) -->
<?php foreach ($games as $game): ?>
<div class="modal fade" id="deleteGameModal<?php echo $game['id']; ?>" tabindex="-1" aria-labelledby="deleteGameModalLabel<?php echo $game['id']; ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteGameModalLabel<?php echo $game['id']; ?>">Delete Game</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this game?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <!-- Delete Form -->
                <form action="" method="POST">
                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>"> <!-- Dynamic game_id -->
                    <button type="submit" class="btn btn-danger" name="delete_game">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>


<!-- Initialize AOS for animation -->
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();

    function toggleFrameOption(show) {
        const framePriceContainer = document.getElementById('framePriceContainer');
        if (show) {
            framePriceContainer.style.display = 'block';
        } else {
            framePriceContainer.style.display = 'none';
            framePriceContainer.querySelector('input').value = ''; // Clear the input
        }
    }
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>