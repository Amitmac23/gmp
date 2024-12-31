<?php

session_start();

// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

// Include database connection
require_once '../config/config.php';
require_once '../libs/phpqrcode-master/phpqrcode-master/qrlib.php';

try {
    // Fetch all games and their corresponding tables from the table_game table
    $stmt = $pdo->query("
        SELECT g.id AS game_id, g.name AS game_name, g.game_image, g.price_per_half_hour AS game_price_per_half_hour,
               t.id AS table_id, t.table_number, t.min_capacity, t.max_capacity, t.extra_charge, t.status, t.price_per_half_hour AS table_price_per_half_hour
        FROM games g
        LEFT JOIN table_game tg ON g.id = tg.game_id
        LEFT JOIN tables t ON tg.table_id = t.id
        ORDER BY g.name, t.table_number
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group the tables by game_id
    $games_with_tables = [];
    foreach ($rows as $row) {
        $game_id = $row['game_id'];
        if (!isset($games_with_tables[$game_id])) {
            $games_with_tables[$game_id] = [
                'game_id' => $row['game_id'],
                'game_name' => $row['game_name'],
                'game_image' => $row['game_image'],
                'game_price_per_half_hour' => $row['game_price_per_half_hour'],
                'tables' => []
            ];
        }
                if ($row['table_id']) {
            $games_with_tables[$game_id]['tables'][] = [
                'table_id' => $row['table_id'],
                'table_number' => $row['table_number'],
                'status' => $row['status'],
                'table_price_per_half_hour' => $row['table_price_per_half_hour'],
                'min_capacity' => $row['min_capacity'],
                'max_capacity' => $row['max_capacity'],
                'extra_charge' => $row['extra_charge']
            ];
        }
    }
} catch (PDOException $e) {
    die("Error fetching games and tables: " . htmlspecialchars($e->getMessage()));
}

// Handle Add Table Request
if (isset($_POST['add_table'])) {
    $game_id = $_POST['game_id'];
    $table_number = $_POST['table_number'];
    $status = 'available'; // Default status
    $price_per_half_hour = $_POST['price_per_half_hour'];
    $min_capacity = $_POST['min_capacity'];
    $max_capacity = $_POST['max_capacity'];
    $extra_charge = $_POST['extra_charge'];

    try {
        // Check if the table already exists for the given game_id
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE game_id = ? AND table_number = ?");
        $checkStmt->execute([$game_id, $table_number]);
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            echo "<div style='color: red; font-weight: bold;'>Error: Table with the same number already exists for this game.</div>";
        } else {
            // Insert the new table with min_capacity, max_capacity, and extra_charge
            $stmt = $pdo->prepare("
                INSERT INTO tables (game_id, table_number, status, price_per_half_hour, min_capacity, max_capacity, extra_charge)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$game_id, $table_number, $status, $price_per_half_hour, $min_capacity, $max_capacity, $extra_charge]);
            echo "<div style='color: green; font-weight: bold;'>Success: Table added successfully.</div>";
        }
    } catch (PDOException $e) {
        echo "<div style='color: red; font-weight: bold;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

if (isset($_POST['edit_table'])) {
    // Ensure the input matches the field names
    $table_id = $_POST['table_id']; // Changed from $_POST['id']
    $table_number = $_POST['table_number'];
    $min_capacity = $_POST['min_capacity'];
    $max_capacity = $_POST['max_capacity'];
    $extra_charge = $_POST['extra_charge'];

    // Prepare and execute the update query
    $stmt = $pdo->prepare("UPDATE tables SET 
        table_number = :table_number,
        min_capacity = :min_capacity,
        max_capacity = :max_capacity,
        extra_charge = :extra_charge
        WHERE id = :id");

    $stmt->execute([
        'table_number' => $table_number,
        'min_capacity' => $min_capacity,
        'max_capacity' => $max_capacity,
        'extra_charge' => $extra_charge,
        'id' => $table_id
    ]);

    // Optionally redirect or show a success message
    header("Location: manage_tables.php");
    exit;
}


// Handle Delete Table request
if (isset($_GET['delete_table_id'])) {
    $table_id = $_GET['delete_table_id'];
    $stmt = $pdo->prepare("DELETE FROM tables WHERE id = ?");
    $stmt->execute([$table_id]);
    header("Location: manage_tables.php");
    exit;
}

// Handle QR Code Generation Request
if (isset($_GET['generate_qr_code']) && isset($_GET['table_id'])) {
    $table_id = $_GET['table_id'];
    $base_url = "http://cz.osportal.in/customer/register_table.php";
    $qr_data = $base_url . "?id=" . $table_id;

    $local_qr_dir = __DIR__ . '/../assets/qrcodes/';
    $qr_code_file = $local_qr_dir . "table_$table_id.png";

    if (!is_dir($local_qr_dir)) {
        mkdir($local_qr_dir, 0777, true);
    }

    QRcode::png($qr_data, $qr_code_file);

    $qr_code_url = "../assets/qrcodes/table_$table_id.png";
    echo "<img src='$qr_code_url' alt='QR Code for Table $table_id' />";
    exit;
}

?>

<!-- HTML and Bootstrap for Display -->
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'sidebar.php'; ?>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Manage Tables</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .btn-add {
            background-color: #28a745;
            color: white;
        }
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        .modal-content {
            border-radius: 10px;
        }
        .card {
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
<div class="container mt-5">
    <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <?php foreach ($games_with_tables as $game): ?>
        <div class="mb-5">
            <div class="card">
                <div class="card-header">
                    <img src="../assets/images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" class="me-3" style="width: 50px; height: 50px; border-radius: 5px;">
                    <?php echo htmlspecialchars($game['game_name']); ?> - Manage Tables
                </div>
                <div class="card-body">
                    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#addTableModal_<?php echo $game['game_id']; ?>">
                        <i class="fas fa-plus"></i> Add Table
                    </button>

                    <h6 class="mt-4">Existing Tables</h6>
                    <div class="row">
                        <?php if (!empty($game['tables'])): ?>
                            <?php foreach ($game['tables'] as $table): ?>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            Table #<?php echo $table['table_number']; ?>
                                        </div>
                                        <div class="card-body">
                                            <p><strong>Status:</strong> <?php echo ucfirst($table['status']); ?></p>
                                            <p><strong>Price:</strong> <?php echo $game['game_price_per_half_hour']; ?></p>
                                            <button class="btn btn-primary my-2" data-bs-toggle="modal" data-bs-target="#editTableModal_<?php echo $table['table_id']; ?>">
                                                <i class="fas fa-edit"></i> Edit Table
                                            </button>
                                            <a href="#" class="btn btn-delete" onclick="confirmDelete(<?php echo $table['table_id']; ?>)">
                                                <i class="fas fa-trash-alt"></i> Delete Table
                                            </a>
                                            <a href="?generate_qr_code=true&table_id=<?php echo $table['table_id']; ?>" class="btn btn-success">
                                                <i class="fas fa-qrcode"></i> Generate QR Code
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                No tables available for this game.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Add Table Modal -->
        <div class="modal fade" id="addTableModal_<?php echo $game['game_id']; ?>" tabindex="-1" aria-labelledby="addTableModalLabel_<?php echo $game['game_id']; ?>" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addTableModalLabel_<?php echo $game['game_id']; ?>">Add Table for <?php echo htmlspecialchars($game['game_name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="POST">
                            <input type="hidden" name="game_id" value="<?php echo $game['game_id']; ?>">
                    
                    <!-- Table Number -->
                    <div class="mb-3">
                        <label for="table_number_<?php echo $game['game_id']; ?>" class="form-label">Table Number</label>
                        <input type="number" class="form-control" id="table_number_<?php echo $game['game_id']; ?>" name="table_number" required>
                    </div>
                    
                    
                    <!-- Minimum Capacity -->
                    <div class="mb-3">
                        <label for="min_capacity_<?php echo $game['game_id']; ?>" class="form-label">Minimum Capacity</label>
                        <input type="number" class="form-control" id="min_capacity_<?php echo $game['game_id']; ?>" name="min_capacity" value="2" min="1" required>
                    </div>
                    
                    <!-- Maximum Capacity -->
                    <div class="mb-3">
                        <label for="max_capacity_<?php echo $game['game_id']; ?>" class="form-label">Maximum Capacity</label>
                        <input type="number" class="form-control" id="max_capacity_<?php echo $game['game_id']; ?>" name="max_capacity" value="10" min="1" required>
                    </div>
                    
                    <!-- Extra Charge -->
                    <div class="mb-3">
                        <label for="extra_charge_<?php echo $game['game_id']; ?>" class="form-label">Extra Charge Per Player</label>
                        <input type="number" class="form-control" id="extra_charge_<?php echo $game['game_id']; ?>" name="extra_charge" value="10.00" step="0.01" required>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" name="add_table" class="btn btn-add">
                                <i class="fas fa-plus"></i> Add Table
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php foreach ($game['tables'] as $table): ?>
    <!-- Edit Table Modal -->
    <div class="modal fade" id="editTableModal_<?php echo $table['table_id']; ?>" tabindex="-1" aria-labelledby="editTableModalLabel_<?php echo $table['table_id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editTableModalLabel_<?php echo $table['table_id']; ?>">Edit Table #<?php echo $table['table_number']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <!-- Hidden field for table_id -->
                        <input type="hidden" name="table_id" value="<?php echo $table['table_id']; ?>">

                        <!-- Table Number -->
                        <div class="mb-3">
                            <label for="edit_table_number_<?php echo $table['table_id']; ?>" class="form-label">Table Number</label>
                            <input type="number" class="form-control" id="edit_table_number_<?php echo $table['table_id']; ?>" name="table_number" value="<?php echo $table['table_number']; ?>" required>
                        </div>

                        <!-- Minimum Capacity -->
                        <div class="mb-3">
                            <label for="edit_min_capacity_<?php echo $table['table_id']; ?>" class="form-label">Minimum Capacity</label>
                            <input type="number" class="form-control" id="edit_min_capacity_<?php echo $table['table_id']; ?>" name="min_capacity" value="<?php echo $table['min_capacity']; ?>" required>
                        </div>

                        <!-- Maximum Capacity -->
                        <div class="mb-3">
                            <label for="edit_max_capacity_<?php echo $table['table_id']; ?>" class="form-label">Maximum Capacity</label>
                            <input type="number" class="form-control" id="edit_max_capacity_<?php echo $table['table_id']; ?>" name="max_capacity" value="<?php echo $table['max_capacity']; ?>" required>
                        </div>

                        <!-- Extra Charge -->
                        <div class="mb-3">
                            <label for="edit_extra_charge_<?php echo $table['table_id']; ?>" class="form-label">Extra Charge Per Player</label>
                            <input type="number" class="form-control" id="edit_extra_charge_<?php echo $table['table_id']; ?>" name="extra_charge" value="<?php echo $table['extra_charge']; ?>" required>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" name="edit_table" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

    <?php endforeach; ?>
</div>

<!-- Include Bootstrap JS and Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
<script>
    // JavaScript function to show the confirmation dialog
    function confirmDelete(tableId) {
        // Confirm delete action
        const isConfirmed = confirm("Are you sure you want to delete this table?");
        if (isConfirmed) {
            // Redirect to the delete action
            window.location.href = "?delete_table_id=" + tableId;
        }
    }
</script>
</body>
</html>