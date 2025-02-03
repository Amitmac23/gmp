<?php
// Database Connection (config.php)
require_once '../config/config.php';

session_start(); // Start the session

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get data from form
    $name = $_POST['name'];
    $phone = $_POST['phone'];

    // Check if the name and phone already exist in the database
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE name = ? AND phone = ?");
    $stmt->execute([$name, $phone]);

    // If the combination already exists, skip the insertion
    if ($stmt->rowCount() == 0) {
        // Insert the data into the database if not found
        $stmt = $pdo->prepare("INSERT INTO customers (name, phone) VALUES (?, ?)");
        $stmt->execute([$name, $phone]);

        // Get the last inserted customer id
        $customer_id = $pdo->lastInsertId();

        // Store customer_id in session
        $_SESSION['customer_id'] = $customer_id;
    } else {
        // Fetch customer_id from the result if the customer exists
        $customer = $stmt->fetch();
        $_SESSION['customer_id'] = $customer['id'];
    }

    // Redirect to the next page after successful registration (or if the data already exists)
    header("Location: choose_game.php"); // Redirect to choose_game.php
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        /* Styles */
        body {
            background: url('../assets/images/marvels-spider-man-2.webp') no-repeat center center fixed; 
            background-size: cover;
            font-family: 'Roboto', sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0;
        }

        .container {
            background-color: rgba(0, 0, 0, 0.6);
            padding: 40px;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.8);
            color: #fff;
        }

        .card-header {
            font-family: 'Press Start 2P', cursive;
            font-size: 2rem;
            text-align: center;
            color: #ff8a00;
            margin-bottom: 20px;
        }

        .form-label {
            font-size: 1.1rem;
            color: #f0f0f0;
        }

        .form-control {
            border-radius: 10px;
            padding: 10px;
            border: 2px solid #ff8a00;
            background-color: #333;
            color: white;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: #e52e71;
            box-shadow: 0 0 5px rgba(229, 46, 113, 0.5);
        }

        .btn {
            border-radius: 25px;
            background-color: #ff8a00;
            color: white;
            font-size: 1.2rem;
            padding: 12px;
            margin-top: 20px;
            width: 100%;
        }

        .btn:hover {
            background-color: #e52e71;
        }
    </style>
    <script>
        function validatePhone(input) {
            const phonePattern = /^\d{10}$/; // Regex for 10 digits only
            return phonePattern.test(input.value);
        }

        function onSubmitForm(event) {
            const phoneInput = document.getElementById('phone');
            if (!validatePhone(phoneInput)) {
                event.preventDefault(); // Prevent form submission
                alert('Please enter a valid 10-digit phone number.');
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h3 class="card-header">Register Now</h3>
        <form method="POST" onsubmit="onSubmitForm(event)">
            <div class="mb-3">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" id="name" name="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="phone" class="form-label">Phone Number</label>
                <input type="text" id="phone" name="phone" class="form-control" maxlength="10" required 
                    pattern="\d{10}" title="Please enter exactly 10 digits">
            </div>
            <button type="submit" class="btn">Start Your Game</button>
        </form>
    </div>
</body>
</html>
