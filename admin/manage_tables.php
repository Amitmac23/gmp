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
    $min_capacity = $_POST['min_capacity'];
    $max_capacity = $_POST['max_capacity'];
    $extra_charge = $_POST['extra_charge'];

    try {
        // Check if the table already exists for the given game_id
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tables WHERE game_id = ? AND table_number = ?");
        $checkStmt->execute([$game_id, $table_number]);
        $count = $checkStmt->fetchColumn();

        if ($count > 0) {
            echo "<script>
                Swal.fire({
                    title: 'Error',
                    text: 'Table with the same number already exists for this game.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            </script>";
        } else {
            // Insert the new table without the table_price_per_half_hour
            $stmt = $pdo->prepare("
                INSERT INTO tables (game_id, table_number, status, min_capacity, max_capacity, extra_charge)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$game_id, $table_number, $status, $min_capacity, $max_capacity, $extra_charge]);

            // Retrieve the last inserted table_id
            $table_id = $pdo->lastInsertId();

            // Insert the table_id and game_id into the table_game table
            $stmt = $pdo->prepare("
                INSERT INTO table_game (table_id, game_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$table_id, $game_id]);

            echo "<script>
                Swal.fire({
                    title: 'Success',
                    text: 'Table added successfully.',
                    icon: 'success',
                    confirmButtonText: 'OK'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = window.location.href + '?reload=true'; // Add a cache-busting parameter
                    }
                });
            </script>";
        }
    } catch (PDOException $e) {
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
    $base_url = "http://192.168.29.237/gmp/customer/register_table.php";
    $qr_data = $base_url . "?id=" . $table_id;

    $local_qr_dir = __DIR__ . '/../assets/qrcodes/';
    $qr_code_file = $local_qr_dir . "table_$table_id.png";

    if (!is_dir($local_qr_dir)) {
        mkdir($local_qr_dir, 0777, true);
    }

    // Check if the QR code file already exists
    if (!file_exists($qr_code_file)) {
        QRcode::png($qr_data, $qr_code_file);
    }

    // Redirect back to the main page with a success parameter
    header("Location: manage_tables.php?show_qr_code=1&table_id=$table_id");
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
    <!-- SweetAlert CSS -->
<!-- SweetAlert CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.9/dist/sweetalert2.min.css">

<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.9/dist/sweetalert2.all.min.js"></script>

<!-- SweetAlert JS -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.9/dist/sweetalert2.all.min.js"></script>
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
                                            <a href="#" class="btn btn-success" onclick="showQRCode(<?php echo $table['table_id']; ?>)">
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

<!-- QR Code Modal -->
<div class="modal fade" id="qrCodeModal" tabindex="-1" aria-labelledby="qrCodeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrCodeModalLabel">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p id="tableNumberText" class="mt-3"></p>
                <p><img id="qrCodeImage" src="" alt="QR Code" class="img-fluid" style="max-width: 80%; height: auto;" /></p>
                <button class="btn btn-primary mt-3" onclick="printQRCode()">Print QR Code</button>
                <a id="downloadLink" href="" class="btn btn-success mt-3" download>
                    <i class="fas fa-download"></i> Download QR Code
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Include Bootstrap JS and Popper.js -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
<script>
    // JavaScript function to show the confirmation dialog
    function confirmDelete(tableId) {
    Swal.fire({
        title: 'Are you sure?',
        text: 'You are about to delete this table!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'No, cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "?delete_table_id=" + tableId;
        }
    });
}

function showQRCode(tableId) {
    const qrCodeUrl = "../assets/qrcodes/table_" + tableId + ".png"; // Adjust the path as needed
    const qrCodeImage = document.getElementById('qrCodeImage');
    const tableNumberText = document.getElementById('tableNumberText');
    const downloadLink = document.getElementById('downloadLink'); // Add this line

    // Find the table number from the tables array
    let tableNumber = '';
    <?php foreach ($games_with_tables as $game): ?>
        <?php foreach ($game['tables'] as $table): ?>
            if (tableId == <?php echo $table['table_id']; ?>) {
                tableNumber = <?php echo json_encode($table['table_number']); ?>;
            }
        <?php endforeach; ?>
    <?php endforeach; ?>

    // Check if the QR code image exists
    const img = new Image();
    img.onload = function() {
        qrCodeImage.src = qrCodeUrl;
        tableNumberText.innerText = "Table Number: " + tableNumber;
        downloadLink.href = qrCodeUrl; // Set the download link
        downloadLink.download = "table_" + tableNumber + "_qr.png"; // Set the download file name with table number
        const qrCodeModal = new bootstrap.Modal(document.getElementById('qrCodeModal'));
        qrCodeModal.show();
    };
    img.onerror = function() {
        // If the QR code image does not exist, generate it
        window.location.href = "?generate_qr_code=1&table_id=" + tableId;
    };
    img.src = qrCodeUrl;
}

// Check if the page was redirected with a QR code generation request
if (window.location.href.includes('?show_qr_code=1')) {
    const urlParams = new URLSearchParams(window.location.search);
    const tableId = urlParams.get('table_id');
    if (tableId) {
        showQRCode(tableId);
    }
}


function printQRCode() {
    const qrCodeImage = document.getElementById('qrCodeImage');
    const tableNumberText = document.getElementById('tableNumberText');
    const printWindow = window.open('', '_blank', 'height=600,width=800');

    // Write the HTML content to the new window
    printWindow.document.write('<html><head><title>Print QR Code</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { text-align: center; font-family: Arial, sans-serif; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100vh; margin: 0; }');
    printWindow.document.write('h1 { font-size: 24px; margin-bottom: 20px; }');
    printWindow.document.write('p { font-size: 18px; font-weight: bold; margin-bottom: 20px; }');
    printWindow.document.write('img { max-width: 100%; height: auto; margin: 0 auto; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<p>' + tableNumberText.innerText + '</p>');
    printWindow.document.write('<img src="' + qrCodeImage.src + '" />');
    printWindow.document.write('</body></html>');
    printWindow.document.close();

    // Focus on the new window and print it
    printWindow.focus();
    printWindow.print();
    printWindow.close();
}
</script>
</body>
</html>