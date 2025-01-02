<?php
// Database configuration
$host = 'localhost';       // Database host (usually 'localhost')
$dbname = 'gmp';  // Name of your database
$username = 'root';        // Database username
$password = '';            // Database password (leave empty for XAMPP)
//hello world

// Create a connection using PDO
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    // Set PDO error mode to exception for better debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
