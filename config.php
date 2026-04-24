<?php
// Database Configuration - TRIGUNAV3
$host = "localhost";
$user = "root";
$pass = "";  // Default Laragon password is empty
$db   = "trigunaketiga";

// Create connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($conn, "utf8mb4");

// Return connection for inclusion
?>
