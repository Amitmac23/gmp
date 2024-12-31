<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php"); // Redirect to login if not authenticated
    exit;

}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'sidebar.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
        }
        .dashboard-card {
            background-color: #ffffff;
            padding: 20px;
            margin: 10px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .dashboard-card h4 {
            margin-bottom: 15px;
        }
        .btn-custom {
            background-color: #007bff;
            color: white;
        }
        .btn-custom:hover {
            background-color: #0056b3;
        }
        .container {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        
        
        <div class="row">
            <h1 class="text-center mt-5">Welcome to Admin Dashboard</h1>
            <!-- Manage Games -->
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h4>Manage Games</h4>
                    <p>Manage and delete games in the system.</p>
                    <a href="manage_games.php" class="btn btn-custom w-100">Manage Games</a>
                </div>
            </div>
            
            <!-- Manage Tables -->
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h4>Manage Tables</h4>
                    <p>View and manage available tables and bookings.</p>
                    <a href="manage_tables.php" class="btn btn-custom w-100">Manage Tables</a>
                </div>
            </div>
            
            <!-- View Bookings -->
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h4>View Bookings</h4>
                    <p>See current player bookings and game slots.</p>
                    <a href="view_bookings.php" class="btn btn-custom w-100">View Bookings</a>
                </div>
            </div>
            
            <!-- Player History -->
            <div class="col-md-3">
                <div class="dashboard-card">
                    <h4>Player History</h4>
                    <p>Track which games players have played and at what times.</p>
                    <a href="player_history.php" class="btn btn-custom w-100">Player History</a>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <!-- Cancel Booking Slot -->
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h4>Cancel Booking Slot</h4>
                    <p>Cancel booked game slots for players if necessary.</p>
                    <a href="cancel_booking.php" class="btn btn-danger w-100">Cancel Booking</a>
                </div>
            </div>
            
            <!-- View Tables and Booking Times -->
            <div class="col-md-6">
                <div class="dashboard-card">
                    <h4>View Tables & Booking Times</h4>
                    <p>View tables with their respective booking times and available slots.</p>
                    <a href="view_tables.php" class="btn btn-success w-100">View Tables</a>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
