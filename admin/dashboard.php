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
    $games[$row['game_image']] = $row['game_image'];
    $games[$row['game_id']]['tables'][] = [
        'table_id' => $row['table_id'],
        'table_number' => $row['table_number'],
        'status' => $row['status'],
        'price_per_half_hour' => $row['price_per_half_hour']
    ];
}

// Function to fetch the latest booking for a given table
function fetchLatestBooking($tableId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            b.*, 
            c.name AS customer_name, 
            g.name AS game_name
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.id
        LEFT JOIN games g ON b.game_id = g.id
        WHERE b.table_id = ? AND b.canceled = 0
        ORDER BY b.start_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$tableId]);
    return $stmt->fetch();
}

// Fetch pending payments for tables from the bookings table
function fetchPendingPayments() {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,  -- Include booking ID
            b.table_id, 
            b.total_price, 
            c.name AS customer_name, 
            b.payment_status, 
            b.payment_method,
            t.table_number AS table_number,  -- Fetch table number
            b.start_time,  -- Fetch start time to determine the latest booking
            b.end_time,  -- Fetch end time to determine the latest booking
            g.name AS game_name  -- Fetch game name
        FROM bookings b
        LEFT JOIN customers c ON b.customer_id = c.id
        LEFT JOIN tables t ON b.table_id = t.id  -- Join with tables to get table details
        LEFT JOIN games g ON b.game_id = g.id  -- Join with games to get game details
        WHERE b.payment_status = 'pending' AND b.canceled = 0
        ORDER BY b.start_time DESC  -- Order by start time to get the latest booking
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC); // Fetch as associative array
}

// Fetch the pending payments
$pendingPayments = fetchPendingPayments();

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
        .table-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .table-card {
            flex: 1 1 calc(20% - 15px);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .table-card-header {
            background-color: #007bff;
            color: white;
            padding: 10px;
            font-weight: bold;
        }
        .table-card-body {
            padding: 15px;
            background-color: #ffffff;
        }
        .badge {
            font-size: 14px;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <h3 class="text-center mb-4">Game Table Dashboard</h3>
    <div class="table-row">
        <?php 
        usort($gameTables, function ($a, $b) {
            return $a['table_number'] <=> $b['table_number'];
        });

        $processedTables = [];
        foreach ($gameTables as $table): 
            if (in_array($table['table_id'], $processedTables)) {
                continue; // Skip duplicate table
            }
            $processedTables[] = $table['table_id'];

            // Check table status
            $isAvailable = $table['status'] === 'available';
        ?>
            <div class="table-card" id="table-<?php echo htmlspecialchars($table['table_id']); ?>" 
                <?php if ($isAvailable): ?>
                    data-bs-toggle="modal" data-bs-target="#bookingModal-<?php echo htmlspecialchars($table['table_id']); ?>"
                <?php else: ?>
                    data-bs-toggle="modal" data-bs-target="#tableDetailsModal-<?php echo htmlspecialchars($table['table_id']); ?>"
                <?php endif; ?>
            >
                <div class="table-card-header <?php echo $isAvailable ? 'bg-success' : 'bg-danger'; ?>">
                    Table #<?php echo htmlspecialchars($table['table_number']); ?> - 
                    <?php echo $isAvailable ? 'Available' : 'Booked'; ?>
                </div>
                <div class="table-card-body" id="booking-details-<?php echo htmlspecialchars($table['table_id']); ?>">
                    <?php if (!$isAvailable): 
                        $booking = fetchLatestBooking($table['table_id']);
                        if ($booking): 
                            $start_time = $booking['start_time'];
                            $end_time = $booking['end_time'];
                            $frame = $booking['frame'];
                            $game_name = $booking['game_name']; // Retrieved from games table
                            $total_price = $booking['total_price'];
                        ?>
                            <p>
                                <strong>Player:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                                <strong>Game:</strong> <?php echo htmlspecialchars($game_name); ?><br>
                                <strong>Booking Time:</strong> <?php echo date("h:i A", strtotime($start_time)); ?>
                                <?php if ($frame != 1): ?>
                                    To <?php echo date("h:i A", strtotime($end_time)); ?>
                                <?php endif; ?>
                                <br><strong>Total Price:</strong> ₹<?php echo number_format($total_price, 2); ?>
                                <?php if ($frame == 1): ?>
                                    <br><strong>Live Timer:</strong> <span class="badge bg-success" id="timer-<?php echo $table['table_id']; ?>">Running...</span>
                                <?php else: ?>
                                    <br><strong>Countdown:</strong> 
                                    <span class="badge bg-warning" id="timer-<?php echo $table['table_id']; ?>">
                                        <?php echo date("h:i A", strtotime($end_time)); ?>
                                    </span>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p>No current booking.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p>Available for booking</p>
                    <?php endif; ?>
                </div>
            </div>

<!-- Booking Modal for available table -->
<div class="modal fade" id="bookingModal-<?php echo htmlspecialchars($table['table_id']); ?>" tabindex="-1" aria-labelledby="bookingModalLabel-<?php echo htmlspecialchars($table['table_id']); ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookTableModalLabel">Book Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Games Section -->
                <div class="mt-3">
                    <label class="form-label"><strong>Available Games:</strong></label>
                    <div id="gamesList-<?php echo htmlspecialchars($table['table_id']); ?>">
    <?php
    // Fetch games for the current table
    $query = "SELECT g.id, g.name, g.has_frame, g.frame_price, t.min_capacity, t.max_capacity, t.extra_charge 
              FROM table_game tg
              INNER JOIN games g ON tg.game_id = g.id
              INNER JOIN tables t ON tg.table_id = t.id
              WHERE tg.table_id = :table_id";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['table_id' => $table['table_id']]);
    $games = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($games)):
        foreach ($games as $game): ?>
            <div class="form-check mb-2">
                <input type="radio" class="form-check-input" name="selectedGame-<?php echo htmlspecialchars($table['table_id']); ?>" id="game-<?php echo htmlspecialchars($game['id']); ?>" value="<?php echo htmlspecialchars($game['id']); ?>" data-has-frame="<?php echo htmlspecialchars($game['has_frame']); ?>" data-frame-price="<?php echo htmlspecialchars($game['frame_price']); ?>" data-min-capacity="<?php echo htmlspecialchars($game['min_capacity']); ?>" data-max-capacity="<?php echo htmlspecialchars($game['max_capacity']); ?>" data-extra-charge="<?php echo htmlspecialchars($game['extra_charge']); ?>">
                <label class="form-check-label" for="game-<?php echo htmlspecialchars($game['id']); ?>">
                    <?php echo htmlspecialchars($game['name']); ?>
                </label>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No games attached to this table.</p>
    <?php endif; ?>
</div>
                </div>

                <!-- Customer Information -->
                <div class="mb-3">
                    <label for="name-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-label">Customer Name:</label>
                    <input type="text" id="name-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control" placeholder="Enter customer name" required>
                </div>
                <div class="mb-3">
                    <label for="customer_phone-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-label">Customer Phone:</label>
                    <input type="text" id="customer_phone-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control" placeholder="Enter customer phone" required>
                </div>

                <!-- Start Time -->
                <label for="start_time">Start Time:</label>
                <select id="start_time-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control"></select>

                <!-- Duration -->
                <label for="duration" class="mt-3">Duration:</label>
                <select id="duration-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control">
                    <option value="0.5">30 Minutes</option>
                    <option value="1">1 Hour</option>
                    <option value="1.5">1.5 Hours</option>
                    <option value="2">2 Hours</option>
                    <option value="frame">Frame</option> <!-- Added Frame Option -->
                </select>

                <div class="mt-3">
                    <label for="player_count-<?php echo $table['table_id']; ?>" class="form-label">Player Count:</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" id="decreasePlayer-<?php echo $table['table_id']; ?>">-</button>
                        <input type="text" id="player_count-<?php echo $table['table_id']; ?>" name="player_count" class="form-control text-center" value="1" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="increasePlayer-<?php echo $table['table_id']; ?>">+</button>
                    </div>
                </div>

                <!-- Total Price -->
                <p class="mt-3">Total Price: ₹
                    <input type="text" id="totalPrice-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control" value="0" readonly>
                </p>

                <!-- Exit Time -->
                <p><strong>Exit Time:</strong>
                    <input type="text" id="exitTime-<?php echo htmlspecialchars($table['table_id']); ?>" class="form-control" value="" readonly>
                </p>

                <!-- Hidden Inputs -->
                <input type="hidden" id="hiddenTableId" value="<?php echo htmlspecialchars($table['table_id']); ?>"/>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="confirmBtn-<?php echo htmlspecialchars($table['table_id']); ?>">Confirm Booking</button>
            </div>
        </div>
    </div>
</div>

            <!-- Modal for table details -->
            <div class="modal fade" id="tableDetailsModal-<?php echo htmlspecialchars($table['table_id']); ?>" tabindex="-1" aria-labelledby="tableDetailsModalLabel-<?php echo htmlspecialchars($table['table_id']); ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="tableDetailsModalLabel-<?php echo htmlspecialchars($table['table_id']); ?>">Table #<?php echo htmlspecialchars($table['table_number']); ?> Details</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="modalBookingDetails-<?php echo htmlspecialchars($table['table_id']); ?>">
                            <?php
                            if ($booking): 
                                $start_time = $booking['start_time'];
                                $end_time = $booking['end_time'];
                                $frame = $booking['frame'];
                                $game_name = $booking['game_name']; // Retrieved from games table
                                $total_price = $booking['total_price'];
                                $player_count = $booking['player_count']; // Assuming this column exists in the booking data
                                $duration = (strtotime($end_time) - strtotime($start_time)) / 60; // Duration in minutes
                                $payment_status = $booking['payment_status'];
                            ?>
                                <p>
                                    <strong>Player:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?><br>
                                    <strong>Game:</strong> <?php echo htmlspecialchars($game_name); ?><br>
                                    <strong>Booking Time:</strong> <?php echo date("h:i A", strtotime($start_time)); ?>
                                    <?php if ($frame != 1): ?>
                                        To <?php echo date("h:i A", strtotime($end_time)); ?><br>
                                        <strong>Duration:</strong> <?php echo $duration; ?> minutes<br>
                                    <strong>Player Count:</strong> <?php echo htmlspecialchars($player_count); ?>
                                    <?php endif; ?>
                                    <br><strong>Total Price:</strong> ₹<?php echo number_format($total_price, 2); ?>
                                </p>

                                <!-- Action Buttons -->
                                <button class="btn btn-danger" onclick="cancelBooking(<?php echo $table['table_id']; ?>)">Cancel</button>
                                <button class="btn btn-warning" onclick="endBooking(<?php echo $table['table_id']; ?>)">End Game</button>
                                <?php if ($frame != 1): // Only show the button if frame is not 1 ?>
                        <button class="btn btn-primary" onclick="addExtraTime(<?php echo $table['table_id']; ?>, <?php echo $booking['id']; ?>)">Add Extra Time</button>
                    <?php endif; ?>
                            <?php else: ?>
                                <p>No current booking.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<div class="container mt-5">
    <h5>Pending Payments by amit</h5>
    <div class="row">
        <?php foreach ($pendingPayments as $payment): ?>
            <div class="col-md-3 mb-3">
                <div class="card text-dark" data-bs-toggle="modal" data-bs-target="#pendingPaymentModal-<?php echo htmlspecialchars($payment['booking_id']); ?>">
                    <div class="card-header bg-primary text-white">
                        Table #<?php echo htmlspecialchars($payment['table_number']); ?> - Pending Payment
                    </div>
                    <div class="card-body">
                        <p><strong>Player:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                        <p><strong>Total Price:</strong> ₹<?php echo number_format($payment['total_price'], 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Modal for Pending Payment -->
            <div class="modal fade" id="pendingPaymentModal-<?php echo htmlspecialchars($payment['booking_id']); ?>" tabindex="-1" aria-labelledby="pendingPaymentLabel-<?php echo htmlspecialchars($payment['booking_id']); ?>" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="pendingPaymentLabel-<?php echo htmlspecialchars($payment['booking_id']); ?>">Pending Payment for Table #<?php echo htmlspecialchars($payment['table_number']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Player:</strong> <?php echo htmlspecialchars($payment['customer_name']); ?></p>
                            <p><strong>Game:</strong> <?php echo htmlspecialchars($payment['game_name']); ?></p>
                            <p><strong>Total Price:</strong> ₹<?php echo number_format($payment['total_price'], 2); ?></p>
                            <p><strong>End Time:</strong> <?php echo date("h:i A", strtotime($payment['end_time'])); ?></p>
                            <p>Select Payment Method:</p>
                            <button class="btn btn-success" onclick="processPayment('<?php echo $payment['table_number']; ?>', 'UPI', '<?php echo $payment['booking_id']; ?>')">UPI</button>
                            <button class="btn btn-primary" onclick="processPayment('<?php echo $payment['table_number']; ?>', 'Cash', '<?php echo $payment['booking_id']; ?>')">Cash</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal for Adding Extra Time -->
<div class="modal fade" id="addExtraTimeModal" tabindex="-1" aria-labelledby="addExtraTimeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addExtraTimeModalLabel">Add Extra Time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label for="extraTimeDuration" class="form-label">Select Extra Time:</label>
                <select id="extraTimeDuration" class="form-select">
                    <option value="0.5">30 Minutes</option>
                    <option value="1">1 Hour</option>
                    <option value="1.5">1.5 Hours</option>
                    <option value="2">2 Hours</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="confirmExtraTimeBtn">Add Extra Time</button>
            </div>
        </div>
    </div>
</div>

<script>
function cancelBooking(tableId) {
    console.log("Cancelling booking for table #" + tableId); // Log tableId

    const confirmation = confirm("Are you sure you want to cancel the booking for Table #" + tableId + "?");

    if (confirmation) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "cancel_booking.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4 && xhr.status == 200) {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert("Booking for table #" + tableId + " has been canceled.");
                    location.reload();
                } else {
                    alert("Failed to cancel booking: " + response.message);
                }
            }
        };

        // Ensure table_id is being sent
        xhr.send("table_id=" + tableId);
    }
}

function endBooking(tableId) {
    console.log("End booking function called for table #" + tableId); // Debug log

    if (confirm("Are you sure you want to end the booking for table #" + tableId + "?")) {
        console.log("User  confirmed ending the booking."); // Debug log

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "end_booking.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                console.log("Response received from server:", xhr.responseText); // Log the response
                if (xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert("Booking for table #" + tableId + " has been ended.");
                        location.reload(); // Optionally reload the page or update the UI
                    } else {
                        alert("Failed to end booking: " + response.message);
                    }
                } else {
                    alert("Error: " + xhr.status);
                }
            }
        };

        xhr.send("table_id=" + tableId); // Send table ID to backend
    } else {
        console.log("User  canceled the action."); // Debug log
    }
}

// function addExtraTime(tableId) {
//     const extraTime = prompt("Enter extra time in minutes:");
//     if (extraTime) {
//         alert("Added " + extraTime + " minutes of extra time to table #" + tableId);
//         // Add your logic to update the booking with extra time in the backend here
//     }
// }

// Function to handle the payment process
function processPayment(tableId, paymentMethod, bookingId) {
    var xhr = new XMLHttpRequest();
    xhr.open("POST", "process_payment.php", true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onreadystatechange = function() {
        if (xhr.readyState === XMLHttpRequest.DONE && xhr.status === 200) {
            var response = JSON.parse(xhr.responseText);
            if (response.success) {
                alert("Payment processed successfully as " + paymentMethod);
                
                // Optionally, you can refresh the pending payments or update the UI
                location.reload(); // Reload the page to reflect changes
            } else {
                alert("Failed to process payment: " + response.message);
            }
        }
        console.log('tableId', bookingId);
    };
    xhr.send("table_id=" + tableId + "&payment_method=" + paymentMethod + "&booking_id=" + bookingId);
}

document.addEventListener('DOMContentLoaded', function() {
    const initializedTimers = {}; // Track initialized timers

    <?php foreach ($gameTables as $table): ?>
        <?php
        $booking = fetchLatestBooking($table['table_id']);
        if ($booking) {
            $start_time = $booking['start_time'];
            $end_time = $booking['end_time'];
            $frame = $booking['frame'];
        ?>
            if (!initializedTimers['<?php echo $table['table_id']; ?>']) {
                updateTimer(
                    '<?php echo $start_time; ?>',
                    '<?php echo $end_time; ?>',
                    <?php echo $frame; ?>,
                    'timer-<?php echo $table['table_id']; ?>',
                    '<?php echo $table['table_id']; ?>'
                );
                initializedTimers['<?php echo $table['table_id']; ?>'] = true;
            }
        <?php } ?>
    <?php endforeach; ?>
});

function updateTimer(startTime, endTime, frame, timerId, tableId) {
    const timerElement = document.getElementById(timerId);
    const bookingDetailsElement = document.getElementById('booking-details-' + tableId);

    if (!timerElement || !bookingDetailsElement) return;

    // Get the current time
    const currentTime = new Date();

    // Parse the start time correctly
    const parsedStartTime = new Date(startTime);
    const parsedEndTime = new Date(endTime);

    // Check if the current time is less than the start time
    if (currentTime < parsedStartTime) {
        timerElement.textContent = "Waiting to Start"; // Show waiting message
        return; // Exit the function
    }

    // If the current time is past the start time, start the timer
    if (frame === 1) {
        updateLiveTimer(startTime, timerId);
    } else {
        updateCountdown(endTime, timerId, tableId);
    }
}

function updateLiveTimer(startTime, timerId) {
    const timerElement = document.getElementById(timerId);

    if (!timerElement) return;

    function update() {
        const currentTime = new Date(); // Get the current time each time we update
        const elapsedTime = currentTime - new Date(startTime);

        const hours = Math.floor(elapsedTime / (1000 * 60 * 60));
        const minutes = Math.floor((elapsedTime / (1000 * 60)) % 60);
        const seconds = Math.floor((elapsedTime / 1000) % 60);

        timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    update(); // Initial call to set the timer immediately
    return setInterval(update, 1000); // Update every second
}

function updateCountdown(endTime, timerId, tableId) {
    const timerElement = document.getElementById(timerId);
    const bookingDetailsElement = document.getElementById('booking-details-' + tableId);

    if (!timerElement || !bookingDetailsElement) return;

    let timerInterval; // Declare timerInterval here

    function update() {
        const currentTime = new Date(); // Get the current time each time we update
        const timeRemaining = new Date(endTime) - currentTime;

        if (timeRemaining <= 0) {
            // Clear booking details and show available message
            bookingDetailsElement.innerHTML = '<p>Available for booking.</p>';
            timerElement.textContent = ''; // Clear the timer display
            clearInterval(timerInterval); // Stop the timer

            // Only call this function when the countdown reaches zero
            updateTableStatusToAvailable(tableId);
        } else {
            const hours = Math.floor(timeRemaining / (1000 * 60 * 60));
            const minutes = Math.floor((timeRemaining / (1000 * 60)) % 60);
            const seconds = Math.floor((timeRemaining / 1000) % 60);

            timerElement.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        }
    }

    update(); // Initial call to set the timer immediately
    timerInterval = setInterval(update, 1000); // Update every second
}

// Function to send AJAX request to update table status
function updateTableStatusToAvailable(tableIdd) {
    const tableId = tableIdd;
    console.log('tableId', tableIdd);

    // Use fetch or XMLHttpRequest to send the data to the server
    fetch('update_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ tableId: tableId, status: 'available' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Table status updated to available');
            location.reload();
        } else {
            console.log('Error updating table status');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function calculateBookingDetails(tableId) {
    const startTimeDropdown = document.getElementById(`start_time-${tableId}`);
    const durationDropdown = document.getElementById(`duration-${tableId}`);
    const totalPriceInput = document.getElementById(`totalPrice-${tableId}`);
    const exitTimeInput = document.getElementById(`exitTime-${tableId}`);
    const selectedGame = document.querySelector(`input[name='selectedGame-${tableId}']:checked`);
    const playerCountInput = document.querySelector(`#player_count-${tableId}`);

    if (!startTimeDropdown || !durationDropdown || !totalPriceInput || !exitTimeInput || !selectedGame || !playerCountInput) {
        console.error("One or more elements missing for table:", tableId);
        return;
    }

    const selectedDuration = parseFloat(durationDropdown.value); // Duration in hours
    let selectedStartTime = new Date(startTimeDropdown.value); // Get time from input

    // Adjust for IST manually
    let istOffset = 5.5 * 60 * 60 * 1000; // IST Offset in milliseconds
    let localTime = selectedStartTime.getTime() + istOffset; // Convert to IST
    selectedStartTime = new Date(localTime);

    const hasFrame = selectedGame.getAttribute('data-has-frame') === 'yes';
    const framePrice = parseFloat(selectedGame.getAttribute('data-frame-price'));
    let pricePerHour = parseFloat(selectedGame.getAttribute('data-price-per-hour')) || 0;
    const extraCharge = parseFloat(selectedGame.getAttribute('data-extra-charge')) || 0; // Fetch extra charge
    const minCapacity = parseInt(selectedGame.getAttribute('data-min-capacity'), 10);
    const currentPlayerCount = parseInt(playerCountInput.value, 10) || 1;

    if (hasFrame && durationDropdown.value === 'frame') {
        // Calculate total price for frame
        let totalPrice = framePrice;

        // Add extra charge for each player above the minimum capacity
        if (currentPlayerCount > minCapacity) {
            const additionalPlayers = currentPlayerCount - minCapacity; // Calculate how many players are above the minimum
            totalPrice += additionalPlayers * extraCharge; // Add extra charge for each additional player
        }

        totalPriceInput.value = totalPrice.toFixed(2);
        exitTimeInput.value = "Full Game"; // Set exit time to full game
    } else {
        // Calculate exit time and total price based on duration
        const exitDate = new Date(selectedStartTime.getTime() + selectedDuration * 60 * 60 * 1000); // Add duration in milliseconds
        exitTimeInput.value = exitDate.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });

        // Calculate total price
        let totalPrice = selectedDuration * pricePerHour;

        // Add extra charge for each player above the minimum capacity
        if (currentPlayerCount > minCapacity) {
            const additionalPlayers = currentPlayerCount - minCapacity; // Calculate how many players are above the minimum
            totalPrice += additionalPlayers * extraCharge; // Add extra charge for each additional player
        }

        totalPriceInput.value = totalPrice.toFixed(2);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("[id^='bookingModal-']").forEach(modal => {
        const tableId = modal.id.split("-")[1];
        const startTimeDropdown = document.getElementById(`start_time-${tableId}`);
        const durationDropdown = document.getElementById(`duration-${tableId}`);
        const gameRadios = document.querySelectorAll(`input[name='selectedGame-${tableId}']`);

        // Function to format time in AM/PM
        function formatTime(date) {
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const formattedHours = hours % 12 || 12;
            const formattedMinutes = String(minutes).padStart(2, '0');
            return `${formattedHours}:${formattedMinutes} ${ampm}`;
        }

        function populateStartTime() {
            const now = new Date();
            now.setMinutes(now.getMinutes() + 5);
            const option = document.createElement("option");
            option.value = now.toISOString().slice(0, 16);
            option.textContent = formatTime(now);
            startTimeDropdown.appendChild(option);
        }

        async function fetchPricePerHour(gameId) {
            try {
                const response = await fetch(`fetch_price.php?game_id=${gameId}`);
                const data = await response.json();
                if (data.success) {
                    const selectedGame = document.querySelector(`input[name='selectedGame-${tableId}']:checked`);
                    if (selectedGame) {
                        selectedGame.setAttribute('data-price-per-hour', data.price);
                        selectedGame.setAttribute('data-min-capacity', data.min_capacity);
                        selectedGame.setAttribute('data-max-capacity', data.max_capacity);
                        selectedGame.setAttribute('data-extra-charge', data.extra_charge);
                    }
                    calculateBookingDetails(tableId);
                } else {
                    console.error("Failed to fetch price:", data.message);
                }
            } catch (error) {
                console.error("Error fetching price:", error);
            }
        }

        function updateDurationOptions(hasFrame) {
            durationDropdown.innerHTML = '';
            const durations = [
                { value: "0.5", text: "30 Minutes" },
                { value: "1", text: "1 Hour" },
                { value: "1.5", text: "1.5 Hours" },
                { value: "2", text: "2 Hours" }
            ];

            durations.forEach(duration => {
                const option = document.createElement("option");
                option.value = duration.value;
                option.textContent = duration.text;
                durationDropdown.appendChild(option);
            });

            if (hasFrame === "yes") {
                const frameOption = document.createElement("option");
                frameOption.value = "frame";
                frameOption.textContent = "Frame";
                durationDropdown.appendChild(frameOption);
            }
        }

        gameRadios.forEach(radio => {
            radio.addEventListener("change", function () {
                if (this.checked) {
                    const hasFrame = this.getAttribute('data-has-frame');
                    fetchPricePerHour(this.value);
                    updateDurationOptions(hasFrame);
                }
            });
        });

        durationDropdown.addEventListener("change", () => calculateBookingDetails(tableId));
        startTimeDropdown.addEventListener("change", () => calculateBookingDetails(tableId));

        populateStartTime();
    });
});

document.body.addEventListener("click", (event) => {
    if (event.target && event.target.id.startsWith("decreasePlayer-")) {
        const tableId = event.target.id.split("-")[1];
        const playerCountInput = document.querySelector(`#player_count-${tableId}`);
        const selectedGame = document.querySelector(`input[name='selectedGame-${tableId}']:checked`);

        if (playerCountInput) {
            let currentCount = parseInt(playerCountInput.value, 10) || 1;
            // Allow decreasing to at least 1
            if (currentCount > 1) {
                playerCountInput.value = currentCount - 1;
                calculateBookingDetails(tableId);
            }
        }

        // Check if selectedGame is not null before accessing its attributes
        if (selectedGame) {
            const minCapacity = parseInt(selectedGame.getAttribute('data-min-capacity'), 10);
            // Additional logic can go here if needed
        } else {
            console.error(`No selected game found for table ID: ${tableId}`);
        }
    }

    if (event.target && event.target.id.startsWith("increasePlayer-")) {
        const tableId = event.target.id.split("-")[1];
        const playerCountInput = document.querySelector(`#player_count-${tableId}`);
        const selectedGame = document.querySelector(`input[name='selectedGame-${tableId}']:checked`);

        if (playerCountInput) {
            let currentCount = parseInt(playerCountInput.value, 10) || 1;
            const maxCapacity = selectedGame ? parseInt(selectedGame.getAttribute('data-max-capacity'), 10) : 0; // Default to 0 if selectedGame is null
            if (currentCount < maxCapacity) {
                playerCountInput.value = currentCount + 1;
                calculateBookingDetails(tableId);
            } else {
                alert("Cannot exceed maximum capacity.");
            }
        }

        // Check if selectedGame is not null before accessing its attributes
        if (selectedGame) {
            // Additional logic can go here if needed
        } else {
            console.error(`No selected game found for table ID: ${tableId}`);
        }
    }
});


document.querySelectorAll("[id^='confirmBtn-']").forEach(button => {
    button.addEventListener("click", function() {
        const tableId = this.id.split("-")[1]; // Extract table ID from button ID
        
        const totalPriceElement = document.getElementById(`totalPrice-${tableId}`);
        let totalPrice = 0;
        if (!totalPriceElement) {
            console.error("Total price element not found for table ID " + tableId);
        } else {
            totalPrice = totalPriceElement.value;
            console.log('tt ',totalPrice);
        }
        console.log('dd ',tableId);

        const selectedGame = document.querySelector(`input[name='selectedGame-${tableId}']:checked`);
        const customerName = document.getElementById(`name-${tableId}`).value.trim(); // Updated
        const customerPhone = document.getElementById(`customer_phone-${tableId}`).value.trim(); // Updated
        const startTime = document.getElementById(`start_time-${tableId}`).value; // ISO format
        const duration = document.getElementById(`duration-${tableId}`).value; // Ensure this is populated correctly
        const playerCountElement = document.getElementById(`player_count-${tableId}`);
        let playerCount = 1; // Default value
        if (playerCountElement) {
            playerCount = playerCountElement.value; // Assign value if the element is found
        }

        if (!selectedGame) {
            alert("Please select a game.");
            return;
        }

        // Check if customer name or phone is empty
        if (!customerName || !customerPhone) {
            alert("Please enter both customer name and phone.");
            return;
        }

        // Convert to Indian Standard Time (IST) by adding 5 hours 30 minutes
        const startDate = new Date(startTime); // Parse the ISO format
        const istOffset = 5.5 * 60 * 60 * 1000; // IST offset in milliseconds
        startDate.setTime(startDate.getTime() + istOffset); // Adjust time to IST

        // Format the start time to 'Y-m-d H:i:s'
        const formattedStartTime = startDate.toISOString().slice(0, 19).replace('T', ' '); // Format to 'Y-m-d H:i:s'

        // Prepare data object
        const data = {
            table_id: tableId,
            game_id: selectedGame.value,
            customer_name: customerName,
            customer_phone: customerPhone,
            start_time: formattedStartTime,
            duration: duration,
            total_price: totalPrice,
            player_count: playerCount,
        };

        // Only add duration and end_time if duration is not "Frame"
        if (duration !== "frame") {
            const exitTimeInput = document.getElementById(`exitTime-${tableId}`);
            const exitTime = exitTimeInput.value; // Get exit time if applicable
            const durationValue = duration; // Get duration value

            data.duration = durationValue; // Add duration to data
            data.end_time = exitTime; // Add end time to data
        }

        var xhr = new XMLHttpRequest();
        xhr.open("POST", "booked_table.php", true);
        xhr.setRequestHeader("Content-Type", "application/json");
        xhr.onreadystatechange = function () {
            if (xhr.readyState == 4) {
                if (xhr.status == 200) {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        alert("Booking confirmed for table #" + tableId);
                        location.reload();
                    } else {
                        alert("Failed to confirm booking: " + response.message);
                    }
                } else {
                    alert("Error: " + xhr.status);
                }
            }
        };
        xhr.send(JSON.stringify(data)); // Send the data as JSON
    });
});



let currentTableId; // To store the current table ID
let currentBookingId; // To store the current booking ID

// Function to show the extra time modal
function addExtraTime(tableId, bookingId) {
    currentTableId = tableId; // Store the current table ID
    currentBookingId = bookingId; // Store the current booking ID
    const extraTimeModal = new bootstrap.Modal(document.getElementById('addExtraTimeModal'));
    extraTimeModal.show(); // Show the modal
}

document.getElementById('confirmExtraTimeBtn').addEventListener('click', function() {
    const extraTimeDuration = parseFloat(document.getElementById('extraTimeDuration').value);
    const totalPriceInput = document.getElementById(`totalPrice-${currentTableId}`);
    const endTimeInput = document.getElementById(`exitTime-${currentTableId}`);
    const durationInput = document.getElementById(`duration-${currentTableId}`);

    // Check if the elements exist
    if (!totalPriceInput || !endTimeInput || !durationInput) {
        console.error("One or more elements are missing for table ID:", currentTableId);
        return; // Exit the function if any element is missing
    }

    // Log the current end time value
    console.log("Current end time input:", endTimeInput); // Check if the element is found
    console.log("Current end time value:", endTimeInput.value); // Log the value

    // Check if endTimeInput is empty
    if (!endTimeInput.value) {
        console.warn("End time input is empty. Setting a default value.");
        endTimeInput.value = new Date().toISOString().slice(0, 19).replace('T', ' '); // Set to current time
    }

    // Calculate new end time
    const currentEndTime = new Date(endTimeInput.value); // Get the current end time
    if (isNaN(currentEndTime.getTime())) {
        console.error("Current end time is invalid:", endTimeInput.value);
        return; // Exit if the current end time is invalid
    }

    // Update the total price and end time
    let currentTotalPrice = parseFloat(totalPriceInput.value);
    let currentDuration = parseFloat(durationInput.value);

    // Assuming price per hour is constant, calculate the new total price
    const pricePerHour = currentTotalPrice / currentDuration; // Calculate price per hour
    currentDuration += extraTimeDuration; // Update duration
    currentTotalPrice += (extraTimeDuration * pricePerHour); // Update total price

    // Update the inputs
    totalPriceInput.value = currentTotalPrice.toFixed(2);
    durationInput.value = currentDuration.toFixed(1); // Update duration

    // Calculate new end time
    const newEndTime = new Date(currentEndTime.getTime() + (extraTimeDuration * 60 * 60 * 1000)); // Add extra time

    // Convert to IST (UTC+5:30)
    const istOffset = 5.5 * 60 * 60 * 1000; // IST offset in milliseconds
    const istEndTime = new Date(newEndTime.getTime() + istOffset); // Adjust to IST

    // Format the date to 'YYYY-MM-DD HH:MM:SS'
    const formattedEndTime = istEndTime.toISOString().slice(0, 19).replace('T', ' '); // Format to 'YYYY-MM-DD HH:MM:SS'

    // Update the input field
    endTimeInput.value = formattedEndTime; // Set the formatted end time

    // Close the modal
    const extraTimeModal = bootstrap.Modal.getInstance(document.getElementById('addExtraTimeModal'));
    extraTimeModal.hide();

    // Send the updated details to the server
    updateBookingDetails(currentTableId, currentDuration, currentTotalPrice, formattedEndTime, currentBookingId);
});
function updateBookingDetails(tableId, duration, totalPrice, endTime, bookingId) {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "update_booking.php", true);
    xhr.setRequestHeader("Content-Type", "application/json");
    xhr.onreadystatechange = function () {
        if (xhr.readyState == 4) {
            if (xhr.status == 200) {
                const response = JSON.parse(xhr.responseText);
                if (response.success) {
                    alert("Booking updated successfully.");
                } else {
                    alert("Failed to update booking: " + response.message);
                }
            } else {
                alert("Error: " + xhr.status);
            }
        }
    };

    const data = {
        table_id: tableId,
        duration: duration,
        total_price: totalPrice,
        end_time: endTime, // Use the formatted end time
        booking_id: bookingId // Ensure this is defined
    };

    console.log("Sending data to server:", data); // Log the data being sent

    xhr.send(JSON.stringify(data)); // Send the data as JSON
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>