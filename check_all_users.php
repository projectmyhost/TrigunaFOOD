<?php
require_once 'config.php';

// Get all users
$result = mysqli_query($conn, "SELECT id, username, email, password, role FROM users ORDER BY username");

echo "=== Semua User di Database ===" . PHP_EOL;
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo $count . ". Username: " . $row['username'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . PHP_EOL;
    echo "   ID: " . $row['id'] . " | Pass (first 20 chars): " . substr($row['password'], 0, 20) . "..." . PHP_EOL;
}

echo "\nTotal: " . $count . " user(s)" . PHP_EOL;

mysqli_close($conn);
?>
