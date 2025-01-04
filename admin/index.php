<?php

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to login if not authenticated
    exit;
}
date_default_timezone_set('Asia/Kolkata'); // Set the time zone to IST (Indian Standard Time)

// Include database connection
require_once '../config/config.php';

// Fetch all games and their associated tables using the table_game relationship table
$stmt = $pdo->query("
    SELECT games.*, tables.*, table_game.*
    FROM games
    LEFT JOIN table_game ON games.id = table_game.game_id
    LEFT JOIN tables ON tables.id = table_game.table_id
");
$gameTables = $stmt->fetchAll();

// Organize the fetched data into an associative array for easy access
$games = [];
foreach ($gameTables as $row) {
    $games[$row['game_id']]['game_name'] = $row['name'];
    $games[$row['game_id']]['game_image'] = $row['game_image'];
    $games[$row['game_id']]['tables'][] = [
        'table_id' => $row['table_id'],
        'table_number' => $row['table_number'],
        'status' => $row['status'],
        'price_per_half_hour' => $row['price_per_half_hour']
    ];
}

function calculateTimeLeft($start_time, $end_time) {
    $current_time = new DateTime();
    $start_time = new DateTime($start_time);
    $end_time = new DateTime($end_time);

    if ($current_time < $start_time) {
        return "Waiting to start"; // If current time is before booking start time
    }

    $interval = $current_time->diff($end_time);
    if ($current_time > $end_time) {
        return "Booking Ended"; // If current time is after booking end time
    }

    return $interval->format('%h:%i:%s'); // Format as hours:minutes:seconds
}
?>

<!-- HTML and Bootstrap for Dashboard -->
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'sidebar.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Dashboard - Manage Tables</title>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            font-weight: bold;
        }
        .card-body {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .table-card {
            margin-bottom: 20px;
        }
        .table-image {
            width: 60px;
            height: 60px;
            border-radius: 5px;
        }
        .info-box {
            margin-top: 20px;
            padding: 15px;
            background-color: #f0f0f0;
            border-radius: 10px;
        }
        .badge {
            font-size: 14px;
        }
        .row>*{
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h3 class="text-center mb-4">Game Table Dashboard</h3>

   <div class="row">
    <?php foreach ($games as $game_id => $game): ?>
        <div class="col-md-6">
            <div class="card table-card">
                <div class="card-header">
                    <img src="http://192.168.29.236/gmp/assets/images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" class="me-3 table-image">
                    <?php echo htmlspecialchars($game['game_name']); ?> - Tables
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Existing Tables</h6>
                    <div class="row">
                        <?php if (!empty($game['tables'])): ?>
                            <?php foreach ($game['tables'] as $table): ?>
                                <div class="col-md-4">
                                    <div class="card">
                                        <div class="card-header bg-info text-white">
                                            Table #<?php echo $table['table_number']; ?>
                                        </div>
                                        <div class="card-body" id="table-<?php echo $table['table_id']; ?>">
                                            <?php
                                                // Fetch the latest booking and player name for this table
                                                $stmt = $pdo->prepare("
                                                    SELECT b.*, c.name AS customer_name 
                                                    FROM bookings b
                                                    LEFT JOIN customers c ON b.customer_id = c.id
                                                    WHERE b.table_id = ? AND b.canceled = 0
                                                    ORDER BY b.start_time DESC LIMIT 1
                                                ");
                                                $stmt->execute([$table['table_id']]);
                                                $booking = $stmt->fetch();

                                                if ($booking) {
                                                    $start_time = $booking['start_time']; // Start time
                                                    $end_time = $booking['end_time']; // End time
                                                    
                                                    // Convert to JavaScript-compatible format (ISO 8601)
                                                    $start_time_js = date('c', strtotime($start_time));
                                                    $end_time_js = date('c', strtotime($end_time));
                                                    
                                                    // Calculate the time left using PHP
                                                    $time_left = calculateTimeLeft($booking['start_time'], $booking['end_time']);
                                                    
                                                    if ($time_left === "Booking Ended") {
                                                        echo "<p id='booking-details-{$table['table_id']}'>No current booking.</p>";
                                                    } else {
                                                        echo "<p id='booking-details-{$table['table_id']}'>
                                                                <strong>Player:</strong> " . htmlspecialchars($booking['customer_name']) . "<br>
                                                                <strong>Booking Time:</strong> " . date("h:i A", strtotime($booking['start_time'])) . " to " . date("h:i A", strtotime($booking['end_time'])) . "<br>
                                                                <strong>Time Left:</strong> <span id='timer-{$table['table_id']}' class='badge bg-warning'>$time_left</span>
                                                              </p>";
                                                        // Pass start and end times to JavaScript for each table
                                                        echo "<script>
                                                            var startTimeTable{$table['table_id']} = '{$start_time_js}';
                                                            var endTimeTable{$table['table_id']} = '{$end_time_js}';
                                                        </script>";
                                                    }
                                                } else {
                                                    echo "<p id='booking-details-{$table['table_id']}'>No current booking.</p>";
                                                }
                                            ?>
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
    <?php endforeach; ?>
</div>


<script>
    // Function to update countdown timer and remove past booking details
    function updateTimer(startTime, endTime, timerId, tableId) {
        const startTimeObj = new Date(startTime);  // Convert startTime to JavaScript Date object
        const endTimeObj = new Date(endTime);  // Convert endTime to JavaScript Date object
        const timerElement = document.getElementById(timerId);
        const bookingDetailsElement = document.getElementById('booking-details-' + tableId);

        // Declare timerInterval in the outer scope, so it's accessible for clearing
        let timerInterval;

        function updateCountdown() {
            const currentTime = new Date();  // Get the current time
            
            // Check if the current time is before the start time, if so, display waiting message
            if (currentTime < startTimeObj) {
                timerElement.textContent = 'Waiting to start';
                return; // Do not update the countdown if time has not started yet
            }

            // Once current time is equal or greater than start time, start countdown
            const timeRemaining = endTimeObj - currentTime;  // Calculate remaining time
            
            if (timeRemaining <= 0) {
                // If time is up, update the timer and remove booking details
                bookingDetailsElement.innerHTML = '<p>No current booking.</p>';  // Display no current booking
                clearPastBookingDetails(tableId); // Clear all the past booking details
                clearInterval(timerInterval);  // Clear the interval
            } else {
                // Calculate hours, minutes, and seconds remaining
                const hours = Math.floor(timeRemaining / (1000 * 60 * 60));  // Hours
                const minutes = Math.floor((timeRemaining / (1000 * 60)) % 60);  // Minutes
                const seconds = Math.floor((timeRemaining / 1000) % 60);  // Seconds

                // Display the timer in hh:mm:ss format
                timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        }

        updateCountdown();  // Initial call to set the time
        // Set the interval to update the countdown every second
        timerInterval = setInterval(updateCountdown, 1000);  
    }

    function clearPastBookingDetails(tableId) {
        const table = document.getElementById('table-' + tableId);
        if (table) {
            const bookingDetails = table.querySelector('.card-body');
            if (bookingDetails) {
                bookingDetails.innerHTML = ''; // Clear all the past booking details
            }
        }
    }

    // Start countdown for each table with an active booking
    document.addEventListener('DOMContentLoaded', function() {
        // Ensure that JavaScript variables are set for each table dynamically
        <?php foreach ($games as $game_id => $game): ?>
            <?php foreach ($game['tables'] as $table): ?>
                <?php
                // Fetch the latest booking for each table
                $stmt = $pdo->prepare("
                    SELECT b.*, c.name AS customer_name 
                    FROM bookings b
                    LEFT JOIN customers c ON b.customer_id = c.id
                    WHERE b.table_id = ? 
                    ORDER BY b.start_time DESC LIMIT 1
                ");
                $stmt->execute([$table['table_id']]);
                $booking = $stmt->fetch();
                if ($booking) {
                    $start_time = $booking['start_time'];
                    $end_time = $booking['end_time'];
                ?>
                    // Ensure each table has a unique timer and booking details ID
                    // Pass the start and end times dynamically for each table
                    updateTimer('<?php echo $start_time; ?>', '<?php echo $end_time; ?>', 'timer-<?php echo $table['table_id']; ?>', '<?php echo $table['table_id']; ?>');
                <?php } ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
