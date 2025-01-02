<?php
session_start();
require_once '../config/config.php'; // Include the config file for database connection

if (isset($_POST['login'])) {
    // Get input from the form
    $username = $_POST['username'];
    $password = $_POST['password'];

    try {
        // Prepare the SQL query to fetch the admin user
        $stmt = $pdo->prepare("SELECT username, password FROM admin_users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            // Fetch the admin user record
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Compare passwords directly
            if ($password === $user['password']) {
                // Login success
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                // Invalid password
                echo "<script>alert('Invalid Username or Password');</script>";
            }
        } else {
            // Username not found
            echo "<script>alert('Invalid Username or Password');</script>";
        }
    } catch (PDOException $e) {
        // Handle query error
        echo "<script>alert('Database error: " . $e->getMessage() . "');</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2 class="text-center">Admin Login</h2>
        <form action="" method="POST" class="mt-4">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" id="password" class="form-control" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
