<?php
session_start();

// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

require_once '../config/config.php';

// Default query to fetch all player history
$query = "
    SELECT 
        b.id, -- Added booking id for canceling
        b.customer_id,
        c.name AS customer_name,
        c.phone, -- Fetch mobile number
        b.start_time,
        b.end_time,
        g.name AS game_name,
        b.total_price, -- Assuming total_price exists in bookings table
        b.player_count, -- Added missing comma
        b.canceled -- Ensure this column exists in the bookings table
    FROM 
        bookings b
    JOIN 
        customers c ON b.customer_id = c.id
    JOIN 
        games g ON b.game_id = g.id
";

// Check if custom date range is provided via GET
$startDate = isset($_GET['startDate']) ? $_GET['startDate'] : null;
$endDate = isset($_GET['endDate']) ? $_GET['endDate'] : null;

if ($startDate && $endDate) {
    // Filter query based on selected date range
    $query .= " WHERE DATE(b.start_time) BETWEEN :start_date AND :end_date";
}

$query .= " ORDER BY b.start_time DESC";

// Prepare and execute the query
$stmt = $pdo->prepare($query);

if ($startDate && $endDate) {
    $stmt->bindParam(':start_date', $startDate);
    $stmt->bindParam(':end_date', $endDate);
}

$stmt->execute();
$playerHistory = $stmt->fetchAll(PDO::FETCH_ASSOC); // Use associative fetch

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bookingId = $_POST['booking_id'];

    // Fetch the table ID associated with the booking
    $fetchTableQuery = "SELECT table_id FROM bookings WHERE id = :booking_id";
    $fetchTableStmt = $pdo->prepare($fetchTableQuery);
    $fetchTableStmt->bindParam(':booking_id', $bookingId);
    $fetchTableStmt->execute();
    $table = $fetchTableStmt->fetch(PDO::FETCH_ASSOC);

    if ($table) {
        $tableId = $table['table_id'];

        // Update the `canceled` column in the bookings table
        $cancelQuery = "UPDATE bookings SET canceled = 1 WHERE id = :booking_id";
        $cancelStmt = $pdo->prepare($cancelQuery);
        $cancelStmt->bindParam(':booking_id', $bookingId);

        if ($cancelStmt->execute()) {
            // Update the table's status to 'available'
            $updateTableQuery = "UPDATE tables SET status = 'available' WHERE id = :table_id";
            $updateTableStmt = $pdo->prepare($updateTableQuery);
            $updateTableStmt->bindParam(':table_id', $tableId);

            if ($updateTableStmt->execute()) {
                // Redirect back to the same page to prevent resubmission
                header("Location: player_history.php");
                exit;
            } else {
                echo "<div class='alert alert-danger'>Failed to update the table status. Please try again.</div>";
            }
        } else {
            echo "<div class='alert alert-danger'>Failed to cancel the booking. Please try again.</div>";
        }
    } else {
        echo "<div class='alert alert-danger'>Table not found for the specified booking.</div>";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <?php include 'sidebar.php'; ?>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Player History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            margin-top: 20px;
        }
        table {
            margin-top: 20px;
        }
        th, td {
            text-align: center;
            vertical-align: middle;
        }
        .game-image {
            height: 50px;
            width: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <!-- Date Filter Section -->
    <form method="GET" action="player_history.php" class="d-flex mb-3">
        <input type="date" name="startDate" id="startDate" class="form-control me-2" value="<?php echo htmlspecialchars($startDate); ?>" />
        <input type="date" name="endDate" id="endDate" class="form-control me-2" value="<?php echo htmlspecialchars($endDate); ?>" />
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>

    <div class="card shadow-lg border-0">
    <div class="card-header bg-primary text-white text-center">
        <h4 class="mb-0">Player History</h4>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover" id="playerHistoryTable">
            <thead class="table-primary">
                <tr>
                    <th>#</th>
                    <th>Player Name</th>
                    <th>Mobile Number</th>
                    <th>Game</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Total Time (minutes)</th>
                    <th>Total Price</th>
                    <th>Player Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($playerHistory): ?>
                    <?php foreach ($playerHistory as $index => $history): ?>
                        <?php 
                        $currentTime = time(); 
                        $endTime = strtotime($history['end_time']);
                        ?>
                        <tr class="<?php echo $history['canceled'] ? 'table-danger' : ''; ?>">
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($history['customer_name']); ?></td>
                            <td><?php echo htmlspecialchars($history['phone']); ?></td>
                            <td><?php echo htmlspecialchars($history['game_name']); ?></td>
                            <td><?php echo date('d-m-Y h:i A', strtotime($history['start_time'])); ?></td>
                            <td><?php echo date('d-m-Y h:i A', $endTime); ?></td>
                            <td>
                                <?php
                                $start = strtotime($history['start_time']);
                                echo round(($endTime - $start) / 60);
                                ?>
                            </td>
                            <td><?php echo number_format($history['total_price'], 2); ?></td>
                            <td><?php echo htmlspecialchars($history['player_count']); ?></td>
                            <td>
                                <?php if (!$history['canceled'] && $currentTime < $endTime): ?>
                                    <form method="POST" action="player_history.php" style="display:inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $history['id']; ?>" /> <!-- Use 'id' here -->
                                        <input type="hidden" name="action" value="cancel" />
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                            Cancel
                                        </button>
                                    </form>
                                <?php elseif ($history['canceled']): ?>
                                    <span class="text-danger">Canceled</span>
                                <?php else: ?>
                                    <span class="text-secondary">Expired</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center">No history found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div>

<!-- jQuery (required for DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<script>
    $(document).ready(function() {
        // Initialize DataTables
        $('#playerHistoryTable').DataTable({
            "paging": true,       // Enable pagination
            "searching": true,    // Enable search functionality
            "ordering": true,     // Enable sorting
            "info": true,         // Show information (like "Showing 1 to 10 of 20 entries")
            "autoWidth": false    // Disable automatic column width adjustment
        });
    });
</script>

</body>
</html>
