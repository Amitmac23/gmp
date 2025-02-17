<?php

session_start();

// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

// Include the database connection
require_once '../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_table'])) {
        // Assign table to games
        $table_id = $_POST['table_id'];
        $game_ids = $_POST['game_ids'] ?? [];

        try {
            // Delete existing assignments for this table
            $stmt = $pdo->prepare("DELETE FROM table_game WHERE table_id = :table_id");
            $stmt->execute(['table_id' => $table_id]);

            // Insert new assignments for multiple games
            foreach ($game_ids as $game_id) {
                $stmt = $pdo->prepare("INSERT INTO table_game (table_id, game_id) VALUES (:table_id, :game_id)");
                $stmt->execute(['table_id' => $table_id, 'game_id' => $game_id]);
            }

            // Success message
            echo "<script>
                Swal.fire({
                    title: 'Success',
                    text: 'Games have been successfully assigned to the table!',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.reload();
                    }
                });
            </script>";
        } catch (Exception $e) {
            // Error handling
            echo "<script>
                Swal.fire({
                    title: 'Error',
                    text: 'Error: " . htmlspecialchars($e->getMessage()) . "',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
        <?php include 'sidebar.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Games to Table</title>
    <!-- SweetAlert CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.9/dist/sweetalert2.min.css">

<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.9/dist/sweetalert2.all.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Roboto', sans-serif;
        }
        .container {
            max-width: 800px;
            padding: 50px 15px;
        }
        h1 {
            text-align: center;
            color: #333;
            font-size: 32px;
            margin-bottom: 30px;
        }
        .form-select, .btn {
            border-radius: 5px;
        }
        .alert {
            margin-bottom: 20px;
        }
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
        }
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
        }
        .form-check {
            margin-bottom: 10px;
        }
        .table th {
            background-color: #007bff;
            color: white;
            text-align: center;
        }
        .table td {
            text-align: center;
        }
        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            width: 100%;
        }
        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #0056b3;
        }
        .table-container {
            margin-top: 50px;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Assign Games to a Table</h1>

    <!-- Success/Error message -->
    <?php if (isset($message)): ?>
        <div class="alert <?php echo $alert_class; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Assign Games to a Table Form -->
    <div class="card p-4">
        <form method="POST" action="">
            <div class="mb-4">
                <label for="table_id" class="form-label">Select Table</label>
                <select name="table_id" id="table_id" class="form-select" required>
                    <option value="" disabled selected>Select a table</option>
                    <?php
                    // Fetch tables
                    $stmt = $pdo->query("SELECT id, table_number FROM tables");
                    while ($table = $stmt->fetch()) {
                        echo "<option value=\"{$table['id']}\">{$table['table_number']}</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-4">
                <label for="game_ids" class="form-label">Select Games</label>
                <div class="checkbox-group">
                    <?php
                    // Fetch games
                    $stmt = $pdo->query("SELECT id, name FROM games");
                    while ($game = $stmt->fetch()) {
                        echo "<div class=\"form-check\">
                                <input class=\"form-check-input\" type=\"checkbox\" name=\"game_ids[]\" value=\"{$game['id']}\" id=\"game_{$game['id']}\">
                                <label class=\"form-check-label\" for=\"game_{$game['id']}\">
                                    {$game['name']}
                                </label>
                              </div>";
                    }
                    ?>
                </div>
            </div>

            <button type="submit" name="assign_table" class="btn btn-primary">Assign Games</button>
        </form>
    </div>

    <!-- Display Assigned Games to Tables -->
    <div class="card table-container p-4 mt-5">
        <h3>Assigned Games to Tables</h3>
        <table class="table table-striped table-bordered mt-3">
            <thead>
                <tr>
                    <th>Table ID</th>
                    <th>Table Number</th>
                    <th>Assigned Games</th>
                </tr>
            </thead>
            <tbody>
            <?php
            // Fetch all tables and their associated games
            $stmt = $pdo->query("
                SELECT t.id AS table_id, t.table_number, 
                       GROUP_CONCAT(g.name SEPARATOR ', ') AS games
                FROM tables t
                LEFT JOIN table_game tg ON t.id = tg.table_id
                LEFT JOIN games g ON tg.game_id = g.id
                GROUP BY t.id
                ORDER BY t.table_number
            ");
            while ($row = $stmt->fetch()) {
                echo "<tr>
                    <td>{$row['table_id']}</td>
                    <td>{$row['table_number']}</td>
                    <td>{$row['games']}</td>
                </tr>";
            }
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
