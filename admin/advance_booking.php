<?php
require_once '../config/config.php';

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Get the current time
$currentTime = date('Y-m-d H:i:s');

// Handle cancel booking
if (isset($_POST['cancel_booking'])) {
    $bookingId = $_POST['booking_id'];

    // SQL query to delete booking
    $deleteQuery = "DELETE FROM bookings WHERE id = :booking_id";
    $stmt = $pdo->prepare($deleteQuery);
    $stmt->execute(['booking_id' => $bookingId]);

    // Redirect to refresh the page after deletion
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch advance bookings
$query = "
    SELECT 
        b.id AS booking_id,
        c.name AS customer_name,
        c.phone AS customer_phone, -- Include phone number
        b.start_time,
        b.end_time,
        g.name AS game_name
    FROM 
        bookings b
    JOIN 
        customers c ON b.customer_id = c.id
    JOIN 
        games g ON b.game_id = g.id
    WHERE 
        b.start_time > :current_time
    ORDER BY 
        b.start_time ASC
";
$stmt = $pdo->prepare($query);
$stmt->execute(['current_time' => $currentTime]);
$advanceBookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'sidebar.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advance Bookings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="container mt-5">
    <!-- Back Button -->
    <a href="javascript:history.back()" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back
    </a>

    <div class="card shadow-lg border-0">
        <div class="card-header bg-primary text-white text-center">
            <h4 class="mb-0">Advance Bookings</h4>
        </div>
        <div class="card-body">
            <table class="table table-striped table-hover">
                <thead class="table-primary">
                    <tr>
                        <th>#</th>
                        <th>Customer Name</th>
                        <th>Phone</th> <!-- Add Phone column -->
                        <th>Game</th>
                        <th>Start Time</th>
                        <th>End Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bookingTableBody">
                    <?php if ($advanceBookings): ?>
                        <?php foreach ($advanceBookings as $index => $booking): ?>
                            <tr id="booking-<?php echo $booking['booking_id']; ?>">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                <td><?php echo htmlspecialchars($booking['customer_phone']); ?></td> <!-- Display phone number -->
                                <td><?php echo htmlspecialchars($booking['game_name']); ?></td>
                                <td><?php echo date('Y-m-d h:i A', strtotime($booking['start_time'])); ?></td>
                                <td><?php echo date('Y-m-d h:i A', strtotime($booking['end_time'])); ?></td>
                                <td>
                                    <form action="" method="POST" style="display: inline;">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['booking_id']; ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" name="cancel_booking" onclick="return confirm('Are you sure you want to cancel this booking?');">
                                            Cancel
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No advance bookings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Function to fetch the latest bookings data via AJAX and update the table
function fetchBookings() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', window.location.href, true); // Request the same page to get fresh data
    xhr.onload = function() {
        if (xhr.status === 200) {
            // Extract the updated bookings table content from the response
            var parser = new DOMParser();
            var doc = parser.parseFromString(xhr.responseText, 'text/html');
            var newBookingTableBody = doc.getElementById('bookingTableBody').innerHTML;
            
            // Replace the old table body with the new one
            document.getElementById('bookingTableBody').innerHTML = newBookingTableBody;
        }
    };
    xhr.send();
}

// Set interval to refresh the bookings every 20 seconds
setInterval(fetchBookings, 20000);
</script>
</body>
</html>
