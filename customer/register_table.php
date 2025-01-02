<?php
// Start the session
session_start();
require_once '../config/config.php';

// Get the table_id from the URL (this will be passed after scanning the QR code)
$table_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$table_id) {
    echo "Table ID is required.";
    exit;
}

// Fetch game_id, price_per_half_hour, table_number, and status from the tables table based on the table_id
$stmt = $pdo->prepare("
    SELECT 
        game_id, 
        price_per_half_hour, 
        table_number, 
        status, 
        min_capacity, 
        max_capacity, 
        extra_charge 
    FROM tables 
    WHERE id = :id
");
$stmt->execute(['id' => $table_id]);
$table = $stmt->fetch();

// If no table is found, display an error
if (!$table) {
    echo "Table not found.";
    exit;
}

// Check if the table is already booked
if ($table['status'] === 'booked') {
    echo "<div style='
        display: flex; 
        align-items: center; 
        justify-content: center; 
        height: 100vh; 
        background-color: #f8f9fa; 
        font-family: Arial, sans-serif; 
        text-align: center;
    '>
        <div style='padding: 20px; background: #fff; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border-radius: 8px;'>
            <h2 style='color: #d9534f;'>Table Already Booked</h2>
            <p style='color: #6c757d;'>This table has already been booked. Please select a different table.</p>
            <a href='select_table.php?id=" . $table['game_id'] . "' style='text-decoration: none; padding: 10px 20px; background: #007bff; color: #fff; border-radius: 4px;'>Back to Tables</a>
        </div>
    </div>";
    exit;
}

$game_id = $table['game_id']; // Get the game_id from the table
$price_per_half_hour = $table['price_per_half_hour']; // Get the price per half-hour
$min_capacity = $table['min_capacity']; // Get the min capacity
$max_capacity = $table['max_capacity']; // Get the max capacity
$extra_charge = $table['extra_charge']; // Get the extra charge per player

// Retrieve form data (customer details)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $start_time_input = $_POST['start_time']; // Start time from the form
    $duration = $_POST['duration'];
    $player_count = isset($_POST['player_count']) ? (int)$_POST['player_count'] : $min_capacity;

    // Validate and format the start time
    try {
        // Convert start time to DateTime object
        $start_time = new DateTime($start_time_input, new DateTimeZone('Asia/Kolkata'));
        $start_time_formatted = $start_time->format('Y-m-d H:i:s');

        // Calculate end time
        $end_time = clone $start_time;
        $end_time->modify('+' . $duration . ' minutes');
        $end_time_formatted = $end_time->format('Y-m-d H:i:s');

        // Validate player count
        if ($player_count < $min_capacity || $player_count > $max_capacity) {
            echo "Player count must be between $min_capacity and $max_capacity.";
            exit;
        }

        // Calculate total price based on the duration, price per half-hour, and extra charge
        $total_price = ($price_per_half_hour * ($duration / 60)); // Base price for the duration
        if ($player_count > $min_capacity) {
            // Calculate extra charge for additional players
            $extra_players = $player_count - $min_capacity;
            $total_price += $extra_players * $extra_charge; // Add extra charge for the extra players
        }

        // Check if the customer already exists
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE name = :name AND phone = :phone");
        $stmt->execute([ 'name' => $name, 'phone' => $phone ]);
        $customer = $stmt->fetch();

        if ($customer) {
            // Customer already exists, get the customer ID
            $customer_id = $customer['id'];
        } else {
            // Insert a new customer record
            $stmt = $pdo->prepare("INSERT INTO customers (name, phone) VALUES (:name, :phone)");
            $stmt->execute([ 'name' => $name, 'phone' => $phone ]);
            $customer_id = $pdo->lastInsertId();
        }

        // Insert booking information into the bookings table
        $stmt = $pdo->prepare("INSERT INTO bookings (table_id, game_id, customer_id, start_time, duration, end_time, total_price, player_count)
                               VALUES (:table_id, :game_id, :customer_id, :start_time, :duration, :end_time, :total_price, :player_count)");
        $stmt->execute([
            'table_id' => $table_id,
            'game_id' => $game_id,
            'customer_id' => $customer_id,
            'start_time' => $start_time_formatted,
            'duration' => $duration,
            'end_time' => $end_time_formatted,
            'total_price' => $total_price,
            'player_count' => $player_count
        ]);

        // Update table status to "booked"
        $stmt = $pdo->prepare("UPDATE tables SET status = 'booked' WHERE id = :table_id");
        $stmt->execute(['table_id' => $table_id]);

        // Redirect to the table page after booking
        $_SESSION['customer_id'] = $customer_id;
        header("Location: select_table.php?id=" . $game_id);
        exit;

    } catch (Exception $e) {
        // Error handling
        echo "Error: " . $e->getMessage();
        // Optionally log error for debugging
        // error_log($e->getMessage());
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register for Table</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
   <style>
    body {
        background: url('../assets/images/8 pool.jpg') no-repeat center center fixed;
        background-size: cover;
        font-family: 'Roboto', sans-serif;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        margin: 0;
    }
    .container {
        background: rgba(0, 0, 0, 0.7);
        padding: 20px;
        border-radius: 15px;
        max-width: 600px;
        box-shadow: 0 0 15px rgba(0, 0, 0, 0.8);
        color: #fff;
        width: 90%;
        margin: auto;
    }
    .card-header {
        font-family: 'Press Start 2P', cursive;
        font-size: 2rem;
        text-align: center;
        color: #ff8a00;
        margin-bottom: 20px;
    }
    .form-label, .form-control {
        font-size: 1rem;
        margin-bottom: 15px;
    }
    .form-label {
        color: #f0f0f0;
    }
    .form-control {
        border-radius: 12px;
        padding: 10px;
        border: 2px solid #ff8a00;
        background: #444;
        color: white;
    }
    .form-control:focus {
        border-color: #e52e71;
        box-shadow: 0 0 5px rgba(229, 46, 113, 0.5);
    }
    .btn {
        border-radius: 30px;
        background: #ff8a00;
        color: white;
        font-size: 1.1rem;
        padding: 12px;
        width: 100%;
        margin-top: 20px;
        transition: background 0.3s;
    }
    .btn:hover {
        background: #e52e71;
    }
    .input-group {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .input-group button {
        background: #ff8a00;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: background 0.3s ease;
    }
    .input-group button:hover {
        background: #e52e71;
    }
    .input-group input {
        text-align: center;
        width: 450px;
        border: 2px solid #ff8a00;
        background: #444;
        color: white;
        border-radius: 8px;
        font-size: 1rem;
        padding: 5px;
    }

    /* Media Queries for Small Screens */
    @media (max-width: 600px) {
        .card-header {
            font-size: 1.6rem;
            margin-top: 20px;
        }
        body {
            padding: 10px;
        }
        .container {
            padding: 20px;
            margin-top: 20px;
        }
        .form-label, .form-control {
            font-size: 0.9rem;
        }
        .input-group button {
            width: 35px;
            height: 35px;
            font-size: 1rem;
        }
        .input-group input {
            width: 200px;
        }
        .btn {
            font-size: 1rem;
            padding: 10px;
        }
    }
</style>

<body>
    <div class="container">
        <h3 class="card-header">Register for Table <?= $table['table_number'] ?></h3>
        <div class="form-container">
            <form method="POST" action="">
                <label for="name" class="form-label">Customer Name:</label>
                <input type="text" name="name" id="name" class="form-control" required>

                <label for="phone" class="form-label">Phone Number:</label>
                <input type="text" name="phone" id="phone" class="form-control" required>

                <label for="start_time" class="form-label">Start Time:</label>
                <select name="start_time" id="start_time" class="form-control" required></select>

                <label for="duration" class="form-label">Duration:</label>
                <select name="duration" id="duration" class="form-control" required>
                    <option value="30">30 Minutes</option>
                    <option value="60">1 Hour</option>
                    <option value="90">1.5 Hours</option>
                    <option value="120">2 Hours</option>
                </select>

                <div class="mt-3">
                    <label for="player_count" class="form-label">Player Count:</label>
                    <div class="input-group">
                        <button type="button" id="decreasePlayer">-</button>
                        <input type="text" id="player_count" value="1" readonly>
                        <button type="button" id="increasePlayer">+</button>
                    </div>
                </div>

                <label for="end_time" class="form-label">Exit Time:</label>
                <input type="text" name="end_time" id="end_time" class="form-control" readonly>

                <label for="total_price" class="form-label">Total Price:</label>
                <input type="text" name="total_price" id="total_price" class="form-control" readonly>

                <button type="submit" class="btn">Confirm Booking</button>
            </form>

        </div>
    </div>

  <script>
document.addEventListener('DOMContentLoaded', () => {
    const timeSelect = document.getElementById('start_time');
    const durationInput = document.getElementById('duration');
    const endTimeInput = document.getElementById('end_time');
    const totalPriceInput = document.getElementById('total_price');
    const playerCountInput = document.getElementById('player_count');
    const increasePlayer = document.getElementById('increasePlayer');
    const decreasePlayer = document.getElementById('decreasePlayer');

    // Fetched from PHP dynamically
    const minCapacity = <?php echo $min_capacity; ?>;
    const maxCapacity = <?php echo $max_capacity; ?>;
    const extraChargePerPlayer = <?php echo $extra_charge; ?>;
    const pricePerHalfHour = <?php echo $price_per_half_hour; ?>;

    // Fetch current time and closing time
    const currentTime = new Date();
    currentTime.setMinutes(currentTime.getMinutes() + 5); // Start from current time + 5 minutes
    currentTime.setSeconds(0); // Set seconds to 0 to match the start time format

    const closingTime = new Date();
    closingTime.setHours(19, 0, 0); // 7:00 PM (19:00) or fetch dynamically

    let tempTime = new Date(currentTime);

    // Add options for every 5 minutes starting from 5 minutes ahead
    while (tempTime <= closingTime) {
        const indiaTime = new Date(tempTime.toLocaleString("en-US", { timeZone: "Asia/Kolkata" }));

        const option = document.createElement('option');
        option.value = indiaTime.toLocaleString("en-US", { timeZone: "Asia/Kolkata" });
        option.textContent = formatTime(indiaTime); // Format the time for display

        timeSelect.appendChild(option);
        tempTime.setMinutes(tempTime.getMinutes() + 5); // Increment by 5 minutes
    }

    // Format time to HH:MM AM/PM
    function formatTime(date) {
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const formattedHours = hours % 12 || 12;
        const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
        return `${formattedHours}:${formattedMinutes} ${ampm}`;
    }

    // Update exit time and total price based on start time, duration, and player count
    function updateDetails() {
        const startTime = new Date(timeSelect.value);
        startTime.setSeconds(0); // Set seconds to 0 to match the start time format
        const duration = parseInt(durationInput.value);
        const playerCount = parseInt(playerCountInput.value);

        if (isNaN(startTime) || isNaN(duration) || isNaN(playerCount)) {
            alert('Please ensure all inputs are valid.');
            return;
        }

        if (duration <= 0) {
            alert('Duration must be greater than 0 minutes.');
            return;
        }

        if (playerCount < 1 || playerCount > maxCapacity) {
            alert(`Player count must be between 1 and ${maxCapacity}.`);
            return;
        }

        const exitTime = new Date(startTime);
        exitTime.setMinutes(exitTime.getMinutes() + duration);

        if (exitTime <= closingTime) {
            endTimeInput.value = formatTime(exitTime);

            // Calculate the base price based on duration and price per half hour
            let totalPrice = (duration / 60) * pricePerHalfHour;

            // Apply extra charge for each player exceeding the minimum capacity
            if (playerCount > minCapacity) {
                const extraPlayers = playerCount - minCapacity;
                totalPrice += extraPlayers * extraChargePerPlayer; // Extra charge per exceeding player
            }

            totalPriceInput.value = totalPrice.toFixed(2);
        } else {
            alert('Selected duration exceeds closing time.');
            durationInput.value = '';
        }
    }

    // Increment player count
    increasePlayer.addEventListener('click', () => {
        let currentCount = parseInt(playerCountInput.value);
        if (currentCount < maxCapacity) { // Ensure player count does not exceed maxCapacity
            playerCountInput.value = currentCount + 1;
            updateDetails(); // Update details after increment
        } else {
            alert(`Player count cannot exceed ${maxCapacity}.`);
        }
    });

    // Decrement player count (No minimum capacity restriction)
    decreasePlayer.addEventListener('click', () => {
        let currentCount = parseInt(playerCountInput.value);
        if (currentCount > 0) { // Allow decrement to 0 or below, but not negative
            playerCountInput.value = currentCount - 1;
            updateDetails(); // Update details after decrement
        }
    });

    // Add event listeners for start time and duration input
    timeSelect.addEventListener('change', updateDetails);
    durationInput.addEventListener('input', updateDetails);

    // Initialize values
    updateDetails();
});

</script>
</body>
</html>