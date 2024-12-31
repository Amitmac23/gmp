<?php
require_once '../config/config.php'; // Database connection using PDO

// Start the session
session_start();

// Check if the customer ID is set in the session
if (!isset($_SESSION['customer_id'])) {
    // If not, redirect to login page
    header("Location: select_table.php");
    exit();
}

// Fetch game details from the database
$gameStmt = $pdo->prepare("SELECT * FROM games WHERE id = ?");
$gameStmt->execute([$_GET['id']]);  // Assuming the game ID is passed in the query string
$game = $gameStmt->fetch(PDO::FETCH_ASSOC);

// If no game is found, show an error
if (!$game) {
    die("Game not found.");
}

// Add the price_per_half_hour from the games table to display later
$game_price_per_half_hour = $game['price_per_half_hour'];

// Fetch tables associated with the selected game
$stmt = $pdo->prepare("SELECT * FROM tables WHERE game_id = ?");
$stmt->execute([$_GET['id']]);
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch customer name based on session customer_id
$customerStmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
$customerStmt->execute([$_SESSION['customer_id']]);
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

// Fetch the game ID from the query string
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid game selection.");
}

$game_id = intval($_GET['id']);

try {
    // Fetch game details from the table_game table (only game_id mapping with tables)
    $stmt = $pdo->prepare("SELECT tg.table_id, t.table_number, t.status, t.price_per_half_hour, t.min_capacity, t.max_capacity, t.extra_charge
                           FROM table_game tg
                           JOIN tables t ON tg.table_id = t.id
                           WHERE tg.game_id = ?");
    $stmt->execute([$game_id]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch admin-defined start and end times from time_slots table
    $timeSlotStmt = $pdo->query("SELECT starting_time, ending_time FROM time_slots LIMIT 1");
    $timeSlot = $timeSlotStmt->fetch(PDO::FETCH_ASSOC);

    if (!$timeSlot) {
        die("Time slots are not configured by the admin.");
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Admin-defined start and end times
$start_time = $timeSlot['starting_time'];
$end_time = $timeSlot['ending_time'];

// Handle form submission for booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $table_id = $_POST['table_id']; // Get table ID from the form
    $start_time = $_POST['start_time'];  // Start time from the form
    $duration = $_POST['duration'];  // Duration in minutes
    $players_count = $_POST['players_count']; // Number of players from the form

    // Convert start time to DateTime object
    $startTime = new DateTime($start_time, new DateTimeZone('Asia/Kolkata'));
    $endTime = clone $startTime;
    $endTime->modify("+$duration minutes");

    // Format start and end times for the database
    $start_time_formatted = $startTime->format('Y-m-d H:i:s');
    $end_time_formatted = $endTime->format('Y-m-d H:i:s');

    // Get table details including min_cap, max_cap, and extra charge
    $selectedTable = array_filter($tables, function($table) use ($table_id) {
        return $table['table_id'] == $table_id;
    });

    if (empty($selectedTable)) {
        die("Table not found.");
    }

    $selectedTable = array_shift($selectedTable);
    $min_cap = $selectedTable['min_cap'];
    $extra_charge = $selectedTable['extra_charge'];
    $table_price_per_half_hour = $selectedTable['price_per_half_hour'];

    // Calculate the total price
    $total_price = $table_price_per_half_hour * $duration * $players_count;

    // Apply extra charge if the number of players exceeds min_cap
    if ($players_count > $min_cap) {
        $extra_players = $players_count - $min_cap;
        $total_price += $extra_players * $extra_charge; // Apply extra charge for exceeding players
    }

    // Insert booking into the bookings table (including game_id)
    try {
        $stmt = $pdo->prepare("INSERT INTO bookings (table_id, customer_id, game_id, start_time, end_time, duration, total_price, players_count)
                               VALUES (:table_id, :customer_id, :game_id, :start_time, :end_time, :duration, :total_price, :players_count)");
        $stmt->execute([
            'table_id' => $table_id,
            'customer_id' => $_SESSION['customer_id'],  // Using session customer ID
            'game_id' => $game_id,  // Insert the selected game ID
            'start_time' => $start_time_formatted,
            'end_time' => $end_time_formatted,
            'duration' => $duration,
            'total_price' => $total_price,
            'players_count' => $players_count
        ]);

        // Redirect to success page after booking
        header("Location: select_table.php");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}

// Get the bookings for each table
$query = "
    SELECT b.start_time, b.end_time, c.name AS customer_name 
    FROM bookings b
    JOIN customers c ON b.customer_id = c.id
    WHERE b.table_id = :table_id
    ORDER BY b.start_time DESC
    LIMIT 1
";
$stmt = $pdo->prepare($query);

// Fetch the bookings for each table in the loop
foreach ($tables as $table) {
    $stmt->execute(['table_id' => $table['table_id']]);
    $booking = $stmt->fetch();
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Table Booking</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body { background-color: #f8f9fa; font-family: Arial, sans-serif; }
        .game-header img { width: 100%; height: auto; border-radius: 8px; margin-bottom: 20px; }
        .table-card { background-color: #fff; border: 1px solid #e9ecef; border-radius: 8px; padding: 20px; text-align: center; position: relative; }
        .table-card:hover { transform: scale(1.03); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); }
        .table-card.booked { background-color: #f8d7da; } /* Red background for booked tables */
        .table-card.available { background-color: #d4edda; } /* Green background for available tables */
        .time-left { font-size: 14px; color: #e65100; position: absolute; bottom: 10px; left: 10px; }
    </style>
</head>
<body>
    <div class="container py-5">
        <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back
    </a>
    <div class="row">
        <div class="col-md-6">
            <div class="game-header">
                <img src="../assets/images/<?= htmlspecialchars($game['game_image']) ?>" alt="<?= htmlspecialchars($game['name']) ?>">
            </div>
        </div>
        <div class="col-md-6">
            <h1><?= htmlspecialchars($game['name']) ?></h1>
            <p><?= htmlspecialchars($game['game_description']) ?></p>
        </div>
    </div>

    <div class="mt-5">
    <h3 class="text-center mb-4">Available Tables</h3>
    <div class="row g-3">
        <?php foreach ($tables as $table): ?>
            <div class="col-md-4">
                <div class="table-card <?= $table['status'] === 'available' ? 'available' : 'booked' ?>" 
                     id="table-card-<?= $table['table_id'] ?>" 
                     data-table-id="<?= $table['table_id'] ?>" 
                     data-table-price="<?= $game['price_per_half_hour'] ?>">

                    <h4>Table <?= htmlspecialchars($table['table_number']) ?></h4>
                    <p>Status: 
                        <?= $table['status'] === 'available' ? 
                            '<span class="text-success">Available</span>' : 
                            '<span class="text-danger">Booked</span>' ?>
                    </p>

                    <?php if ($table['status'] === 'available'): ?>
                        <p><strong>Price:</strong> ₹<?= htmlspecialchars($game['price_per_half_hour']) ?> Per Hour</p>
                        <button class="btn btn-success btn-book" 
                                data-bs-toggle="modal" 
                                data-bs-target="#bookTableModal" 
                                data-table-id="<?= $table['table_id'] ?>" 
                                data-table-price="<?= $game['price_per_half_hour'] ?>" 
                                data-table-number="<?= $table['table_number'] ?>" 
                                data-game-id="<?= $game_id ?>">Book Now</button>
                    <?php else: ?>
                        <?php 
                        // Fetch booking details for this table if booked
                        $stmt = $pdo->prepare("
                            SELECT bookings.*, customers.name AS customer_name 
                            FROM bookings
                            JOIN customers ON bookings.customer_id = customers.id
                            WHERE bookings.table_id = :table_id 
                            ORDER BY bookings.start_time DESC 
                            LIMIT 1
                        ");
                        $stmt->execute(['table_id' => $table['table_id']]);
                        $booking = $stmt->fetch();

                        if ($booking) {
                            $start_time = $booking['start_time'];
                            $end_time = $booking['end_time'];
                            $customer_name = $booking['customer_name'];
                            $total_price = $booking['total_price']; // Fetch total price from the booking table
                        } else {
                            $start_time = $end_time = $customer_name = $total_price = 'N/A';
                        }
                        ?>
                        <p><strong>Booking By:</strong> <?= htmlspecialchars($customer_name) ?></p>
                        <p><strong>Start Time:</strong> <?= date('h:i A', strtotime($start_time)) ?></p>
                        <p><strong>End Time:</strong> <?= date('h:i A', strtotime($end_time)) ?></p>
                        <p><strong>Time Remaining:</strong> 
                            <span id="timer-<?= $table['table_id'] ?>" 
                                  data-end-time="<?= date('Y-m-d H:i:s', strtotime($end_time)) ?>">Waiting to start</span>
                        </p>
                        <p><strong>Total Price:</strong> 
                            <span id="total-price-<?= $table['table_id'] ?>"><?= htmlspecialchars($total_price) ?></span>
                        </p>
                        <button class="btn btn-secondary" disabled>Booked</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal for Table Booking -->
<div class="modal fade" id="bookTableModal" tabindex="-1" aria-labelledby="bookTableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bookTableModalLabel">Book Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Table:</strong> <span id="selectedTable"></span></p>
                <label for="start_time">Start Time:</label>
                <select id="start_time" class="form-control">
                    <!-- Populate time slots dynamically -->
                </select>
                <label for="duration" class="mt-3">Duration:</label>
                <select id="duration" class="form-control">
                    <option value="0.5">30 Minutes</option>
                    <option value="1">1 Hour</option>
                    <option value="1.5">1.5 Hours</option>
                    <option value="2">2 Hours</option>
                </select>
                <div class="mt-3">
                    <label for="player_count" class="form-label">Player Count:</label>
                    <div class="input-group">
                        <button class="btn btn-outline-secondary" type="button" id="decreasePlayer">-</button>
                        <input type="text" id="player_count" class="form-control text-center" value="1" readonly>
                        <button class="btn btn-outline-secondary" type="button" id="increasePlayer">+</button>
                    </div>
                </div>
                <p class="mt-3">Total Price: ₹
                    <input type="text" id="totalPrice" class="form-control" value="0" readonly>
                </p>
                <p><strong>Exit Time:</strong>
                    <input type="text" id="exitTime" class="form-control" value="" readonly>
                </p>

                <!-- Hidden Inputs -->
                <input type="hidden" id="hiddenTableId" value=""/>
                <input type="hidden" id="hiddenTablePrice" value=""/>
                <input type="hidden" id="hiddenMinCapacity" value=""/>
                <input type="hidden" id="hiddenExtraCharge" value=""/>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-success" id="confirmBtn">Confirm Booking</button>
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const timeSelect = document.getElementById('start_time');
    const durationElement = document.getElementById('duration');
    const totalPriceElement = document.getElementById('totalPrice');
    const exitTimeElement = document.getElementById('exitTime');
    const customerSelect = document.getElementById('customer_id');
    const playerCountInput = document.getElementById('player_count');
    const decreasePlayerButton = document.getElementById('decreasePlayer');
    const increasePlayerButton = document.getElementById('increasePlayer');

    let tablePricePerHalfHour = 50;
    let minPlayers = 2; // Default minimum players (you can fetch this from the database)
    let maxPlayers = 6; // Default maximum players (you can fetch this from the database)
    let extraChargePerPlayer = 10; // Default extra charge per player (you can fetch this from the database)

    // Utility function to format time in HH:mm AM/PM format
    function formatTime(date) {
        let hours = date.getHours();
        let minutes = date.getMinutes().toString().padStart(2, '0');
        let ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12;
        return `${hours.toString().padStart(2, '0')}:${minutes} ${ampm}`;
    }

    // Fetch table details (min/max players, extra charge) based on selected table ID
    function fetchTableDetails(tableId) {
        fetch('get_table_details.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ table_id: tableId, cache_buster: new Date().getTime() }), // Cache buster to avoid caching issues
        })
            .then(response => response.json())
            .then(data => {
                console.log('Fetched Table Details:', data); // Debugging: Log fetched data
                if (data.success) {
                    minPlayers = data.min_players;
                    maxPlayers = data.max_players;
                    extraChargePerPlayer = data.extra_charge_per_player;
                    updateTotalPrice(); // Recalculate price after fetching data
                } else {
                    alert('Error: ' + data.error); // Show error message if data fetch failed
                }
            })
            .catch(error => console.error('Error fetching table details:', error));
    }

    // Function to handle table selection
    function handleTableSelection(event) {
        const tableId = event.target.getAttribute('data-table-id'); // Get the selected table ID
        if (tableId) {
            fetchTableDetails(tableId); // Fetch table details based on selected table
        }
    }

    // Add event listeners to table selection buttons
    const tableSelectionButtons = document.querySelectorAll('.btn-book'); // Assuming these are your table selection buttons
    tableSelectionButtons.forEach(button => {
        button.addEventListener('click', handleTableSelection);
    });

    // Update total price based on number of players and duration
    function updateTotalPrice() {
        const selectedStartTime = timeSelect.value;
        const selectedDuration = parseFloat(durationElement.value || 0);
        const selectedPlayers = parseInt(playerCountInput.value);

        if (selectedDuration <= 0 || isNaN(selectedDuration)) {
            totalPriceElement.value = '0.00';
            exitTimeElement.value = 'Invalid Duration';
            return;
        }

        let totalPrice = tablePricePerHalfHour * selectedDuration;

        // Apply extra charge if players exceed the minimum capacity
        if (selectedPlayers > minPlayers) {
            const extraPlayers = selectedPlayers - minPlayers;
            totalPrice += extraPlayers * extraChargePerPlayer;
        }

        totalPriceElement.value = totalPrice.toFixed(2);
    }

    // Decrease player count
    decreasePlayerButton.addEventListener('click', function () {
        let currentCount = parseInt(playerCountInput.value);
        if (currentCount > 1) {
            currentCount--;
            playerCountInput.value = currentCount;
            updateTotalPrice();
        }
    });

    // Increase player count
    increasePlayerButton.addEventListener('click', function () {
        let currentCount = parseInt(playerCountInput.value);
        if (currentCount < maxPlayers) {
            currentCount++;
            playerCountInput.value = currentCount;
            updateTotalPrice();
        } else {
            alert(`Maximum capacity of ${maxPlayers} players reached.`);
        }
    });


    // Function to update the total price based on the number of players and duration
    function updateTotalPrice() {
        const selectedStartTime = timeSelect.value;
        const selectedDuration = parseFloat(durationElement.value || 0);
        const selectedPlayers = parseInt(playerCountInput.value);

        if (selectedDuration <= 0 || isNaN(selectedDuration)) {
            totalPriceElement.value = '0.00';
            exitTimeElement.value = 'Invalid Duration';
            return;
        }

        let totalPrice = tablePricePerHalfHour * selectedDuration;

        // Apply extra charge if players exceed the minimum capacity
        if (selectedPlayers > minPlayers) {
            const extraPlayers = selectedPlayers - minPlayers;
            totalPrice += extraPlayers * extraChargePerPlayer;
        }

        totalPriceElement.value = totalPrice.toFixed(2);

        // Calculate exit time
        const [time, ampm] = selectedStartTime.split(' ');
        const [hours, minutes] = time.split(':').map(Number);

        let startDate = new Date();
        startDate.setHours(ampm === 'PM' && hours !== 12 ? hours + 12 : hours);
        startDate.setMinutes(minutes);
        startDate.setMinutes(startDate.getMinutes() + (selectedDuration * 60));

        exitTimeElement.value = formatTime(startDate);
    }

    // Attach event listeners for price updates
    durationElement.addEventListener('change', updateTotalPrice);
    timeSelect.addEventListener('change', updateTotalPrice);

    // Update total price and exit time based on duration
    function updateCountdown() {
        const timers = document.querySelectorAll('[id^="timer-"]'); // Select all elements with ids starting with "timer-"
        
        timers.forEach(function (timer) {
            const tableId = timer.closest('[data-table-id]') ? timer.closest('[data-table-id]').getAttribute('data-table-id') : null;

            // Check if tableId is null
            if (!tableId) {
                console.error("Table ID is missing!");
                return;
            }

            const endTimeString = timer.getAttribute('data-end-time');
            if (!endTimeString) {
                console.error("End time is missing for table ID:", tableId);
                return;
            }

            // Convert the end time string to Unix timestamp
            const endTime = Date.parse(endTimeString) / 1000;
            const now = Math.floor(new Date().getTime() / 1000); // Current time in Unix timestamp (seconds)

            if (now < endTime) {
                // If current time is before end time, show the remaining time
                const remainingTime = endTime - now;

                const hours = Math.floor(remainingTime / (60 * 60));
                const minutes = Math.floor((remainingTime % (60 * 60)) / 60);
                const seconds = remainingTime % 60;

                // Display the countdown in HH:mm:ss format
                timer.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            } else {
                // If the time has passed, show "00:00:00" and update the table status
                timer.textContent = '00:00:00';

                // Send an AJAX request to update the status in the database
                fetch('update_table_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ table_id: tableId }) // Ensure tableId is passed here
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update the UI to reflect the new status
                        const tableCard = document.getElementById(`table-card-${tableId}`);
                        if (tableCard) {
                            tableCard.classList.remove('booked');
                            tableCard.classList.add('available');
                            
                            const statusText = tableCard.querySelector('p');
                            if (statusText) {
                                statusText.innerHTML = 'Status: <span class="text-success">Available</span>';
                            }
                            
                            // Enable the "Book Now" button
                            const bookButton = tableCard.querySelector('.btn-book');
                            if (bookButton) {
                                bookButton.disabled = false;
                            }
                        }

                        // Optionally reload the page to refresh all states
                        location.reload();
                    } else {
                        console.error("Failed to update table status");
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                });
            }
        });
    }

    // Call updateCountdown every second
    setInterval(updateCountdown, 1000);

    // Initialize countdown on page load
    updateCountdown();

    // Populate the time dropdown with available times
    const currentTime = new Date();
    currentTime.setMinutes(currentTime.getMinutes() + 5);
    const endTime = new Date();
    endTime.setHours(19, 0, 0);  // 6:00 PM

    let tempTime = new Date(currentTime);
    while (tempTime <= endTime) {
        const option = document.createElement('option');
        option.value = formatTime(tempTime);
        option.textContent = formatTime(tempTime);
        timeSelect.appendChild(option);
        tempTime.setMinutes(tempTime.getMinutes() + 5); // increment by 5 minutes
    }

    timeSelect.value = timeSelect.options[0].value;  // Set default value

    // Handle table booking
    document.querySelectorAll('.btn-book').forEach(btn => {
        btn.addEventListener('click', function () {
            const tableId = btn.getAttribute('data-table-id');
            const tablePrice = btn.getAttribute('data-table-price');
            const gameId = btn.getAttribute('data-game-id');  // Capture game_id

            tablePricePerHalfHour = parseFloat(tablePrice) || 50;
            updateTotalPrice();

            document.getElementById('confirmBtn').setAttribute('data-table-id', tableId);
            document.getElementById('confirmBtn').setAttribute('data-game-id', gameId);  // Pass game_id to the confirm button
        });
    });

    // Confirm booking functionality
    document.getElementById('confirmBtn').addEventListener('click', function () {
        const tableId = this.getAttribute('data-table-id');
        const gameId = this.getAttribute('data-game-id');  // Retrieve the game_id
        const startTime = timeSelect.value;
        const duration = durationElement.value;
        const totalPrice = totalPriceElement.value;
        const exitTime = exitTimeElement.value;

        fetch('book_table.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `table_id=${tableId}&game_id=${gameId}&start_time=${startTime}&duration=${duration}&total_price=${totalPrice}&end_time=${exitTime}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert('Table booked successfully!');
                location.reload();

                const modal = document.getElementById('bookingModal');
                modal.style.display = 'none';  // Or use a modal close function if you're using a framework
            } else {
                alert('Booking failed. Try again.');
            }
        });
    });

    // Initialize table details fetch
    fetchTableDetails();
});


</script>

</body>
</html>
