<?php

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

date_default_timezone_set('Asia/Kolkata');

// Include database connection
require_once '../config/config.php';

// Fetch all games and their associated tables
$stmt = $pdo->query("
    SELECT games.*, tables.*, table_game.*
    FROM games
    LEFT JOIN table_game ON games.id = table_game.game_id
    LEFT JOIN tables ON tables.id = table_game.table_id
    WHERE tables.table_number IS NOT NULL AND tables.table_number != ''
");

$gameTables = $stmt->fetchAll();

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

function fetchLatestBooking($tableId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT b.*, c.name AS customer_name 
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.id
        WHERE b.table_id = ? AND b.canceled = 0
        ORDER BY b.start_time DESC LIMIT 1
    ");
    $stmt->execute([$tableId]);
    return $stmt->fetch();
}
?>
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
                        <img src="../assets/images/<?php echo htmlspecialchars($game['game_image']); ?>" alt="<?php echo htmlspecialchars($game['game_name']); ?>" class="me-3 table-image">
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
                                            <div class="card-body" id="table-<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>">
                                                <?php
                                                    $booking = fetchLatestBooking($table['table_id']);
                                                    if ($booking) {
                                                        $start_time = $booking['start_time'];
                                                        $end_time = $booking['end_time'];
                                                        $frame = $booking['frame'];

                                                        echo "<p id='booking-details-{$game_id}-{$table['table_id']}'>
                                                                <strong>Player:</strong> " . htmlspecialchars($booking['customer_name']) . "<br>
                                                                <strong>Booking Time:</strong> " . date("h:i A", strtotime($start_time));

                                                        if ($frame == 1) {
                                                            echo "<br><strong>Live Timer:</strong> <span id='timer-{$game_id}-{$table['table_id']}' class='badge bg-success'>Running...</span>";
                                                        } else {
                                                            echo " to " . date("h:i A", strtotime($end_time)) . "<br>
                                                                  <strong>Countdown:</strong> <span id='timer-{$game_id}-{$table['table_id']}' class='badge bg-warning'>Calculating...</span>";
                                                        }
                                                        echo "</p>";
                                                    } else {
                                                        echo "<p id='booking-details-{$game_id}-{$table['table_id']}'>No current booking.</p>";
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
</div>


<script>
function updateTimer(startTime, endTime, frame, timerId, tableId) {
    const startTimeObj = new Date(startTime);  // Convert startTime to JavaScript Date object
    const endTimeObj = new Date(endTime);  // Convert endTime to JavaScript Date object
    const timerElement = document.getElementById(timerId);
    const bookingDetailsElement = document.getElementById('booking-details-' + tableId);

    let timerInterval;

    // Function to update the live timer (for frame 1)
    function updateLiveTimer() {
        const currentTime = new Date();  // Get the current time

        // If startTime has not been reached, show "Waiting to start"
        if (currentTime < startTimeObj) {
            timerElement.textContent = 'Waiting to start';
            return;
        }

        const elapsedTime = currentTime - startTimeObj; // Time difference in milliseconds

        const hours = Math.floor(elapsedTime / (1000 * 60 * 60)); // Hours
        const minutes = Math.floor((elapsedTime / (1000 * 60)) % 60); // Minutes
        const seconds = Math.floor((elapsedTime / 1000) % 60); // Seconds

        // Display the live timer in hh:mm:ss format
        timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    // Function to update the countdown timer (for frame 0)
    function updateCountdown() {
        const currentTime = new Date();

        // Check if the current time is before the start time
        if (currentTime < startTimeObj) {
            timerElement.textContent = 'Waiting to start';
            return; // Do not update the countdown if time has not started yet
        }

        // Calculate remaining time
        const timeRemaining = endTimeObj - currentTime;

        if (timeRemaining <= 0) {
            // If time is up, update the timer and remove booking details
            bookingDetailsElement.innerHTML = '<p>No current booking.</p>'; // Display no current booking
            clearPastBookingDetails(tableId); // Clear all the past booking details
            clearInterval(timerInterval); // Clear the interval
        } else {
            // Calculate hours, minutes, and seconds remaining
            const hours = Math.floor(timeRemaining / (1000 * 60 * 60));
            const minutes = Math.floor((timeRemaining / (1000 * 60)) % 60);
            const seconds = Math.floor((timeRemaining / 1000) % 60);

            // Display the timer in hh:mm:ss format
            timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }

    // Start the countdown
    updateCountdown(); // Initial call to set time immediately
    timerInterval = setInterval(updateCountdown, 1000); // Update countdown every second

    // Check the frame value and call the appropriate function (live timer for frame 1, countdown for frame 0)
    if (frame === 1) {
        // Check if the start time has not yet been reached, then show "Waiting to start"
        if (new Date() < startTimeObj) {
            timerElement.textContent = 'Waiting to start';
        } else {
            updateLiveTimer(); // Initial call to set time immediately
            timerInterval = setInterval(updateLiveTimer, 1000); // Update live timer every second
        }
    } else {
        updateCountdown(); // Initial call to set time immediately
        timerInterval = setInterval(updateCountdown, 1000); // Update countdown every second
    }
}

// Function to clear past booking details
function clearPastBookingDetails(tableId) {
    const table = document.getElementById('table-' + tableId);
    if (table) {
        const bookingDetails = table.querySelector('.card-body');
        if (bookingDetails) {
            bookingDetails.innerHTML = ''; // Clear all the past booking details
        }
    }
}

const initializedTimers = {}; // Object to track initialized timers

document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($games as $game_id => $game): ?>
        <?php foreach ($game['tables'] as $table): ?>
            <?php
            // Fetch the latest booking for each table
            $booking = fetchLatestBooking($table['table_id']);
            if ($booking) {
                $start_time = $booking['start_time'];
                $end_time = $booking['end_time'];
                $frame = $booking['frame'];  // Get the frame value
            ?>
                // Initialize the timer if not already initialized
                if (!initializedTimers['<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>']) {
                    console.log("Initializing timer for Table ID: <?php echo $game_id; ?>-<?php echo $table['table_id']; ?>");

                    // Initialize the timer based on frame status (running or countdown)
                    if (<?php echo $frame; ?> === 1) {
                        // Timer is running, set it to "Running..." state
                        updateTimer('<?php echo $start_time; ?>', '<?php echo $end_time; ?>', 1, 'timer-<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>', '<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>');
                    } else {
                        // Timer is calculating, set it to "Calculating..." state
                        updateTimer('<?php echo $start_time; ?>', '<?php echo $end_time; ?>', 0, 'timer-<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>', '<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>');
                    }

                    // Mark the timer as initialized
                    initializedTimers['<?php echo $game_id; ?>-<?php echo $table['table_id']; ?>'] = true;
                }
            <?php } ?>
        <?php endforeach; ?>
    <?php endforeach; ?>
});


</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>