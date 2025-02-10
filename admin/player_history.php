<?php
session_start();

// Ensure the user is logged in as an admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to admin login if not authenticated
    exit;
}

require_once '../config/config.php';

// Set default timezone to IST (Indian Standard Time)
date_default_timezone_set('Asia/Kolkata');

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
        b.canceled,
        b.frame, -- Assuming `frame` is a column that indicates active bookings
        b.payment_status, -- Added payment status
        b.payment_method -- Added payment method
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
    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0">Player History</h4>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover" id="playerHistoryTable">
                <thead class="table-primary">
                    <tr>
                        <th>No.</th>
                        <th>Player Name</th>
                        <th>Mobile Number</th>
                        <th>Game</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Duration (minutes)</th>
                        <th>Total Price</th>
                        <th>Player Count</th>
                        <th>Payment Status</th>
                        <th>Payment Method</th>
                    </tr>
                </thead>
                <tbody>
    <?php if ($playerHistory): ?>
        <?php foreach ($playerHistory as $index => $history): ?>
            <tr class="<?php echo $history['canceled'] ? 'table-danger' : ''; ?>">
                <td><?php echo $index + 1; ?></td>
                <td><?php echo htmlspecialchars($history['customer_name']); ?></td>
                <td><?php echo htmlspecialchars($history['phone']); ?></td>
                <td><?php echo htmlspecialchars($history['game_name']); ?></td>
                <td><?php echo date('d-m-Y h:i A', strtotime($history['start_time'])); ?></td>
                <td>
                    <?php echo $history['end_time'] ? date('d-m-Y h:i A', strtotime($history['end_time'])) : 'N/A'; ?>
                </td>
                <td>
                    <?php echo isset($history['end_time']) ? round((strtotime($history['end_time']) - strtotime($history['start_time'])) / 60) : 'N/A'; ?>
                </td>
                <td><?php echo number_format($history['total_price'], 2); ?></td>
                <td><?php echo htmlspecialchars($history['player_count']); ?></td>
                <td>
                    <?php 
                    if ($history['canceled']) {
                        echo "Canceled";
                    } else {
                        echo htmlspecialchars($history['payment_status']);
                    }
                    ?>
                </td>
                <td>
                    <?php 
                    if ($history['canceled']) {
                        echo "Canceled";
                    } else {
                        echo htmlspecialchars($history['payment_method']);
                    }
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="11" class="text-center">No player history available.</td>
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