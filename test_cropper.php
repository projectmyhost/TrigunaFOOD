<?php
// Test file untuk memastikan setup sudah benar
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

echo "=== Test Cropper Setup ===\n\n";

// 1. Test database connection
echo "1. Database Connection: ";
if ($conn) {
    echo "✓ OK\n";
} else {
    echo "✗ FAILED: " . mysqli_connect_error() . "\n";
}

// 2. Check users table columns
echo "2. Checking users table columns... ";
$result = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='users' AND COLUMN_NAME IN ('foto_profil', 'nomor_hp', 'alamat')");
if ($result) {
    $count = mysqli_num_rows($result);
    if ($count >= 3) {
        echo "✓ All columns exist\n";
    } else {
        echo "⚠ Missing " . (3 - $count) . " column(s)\n";
        while ($row = mysqli_fetch_assoc($result)) {
            echo "   - Found: " . $row['COLUMN_NAME'] . "\n";
        }
    }
} else {
    echo "✗ Query failed\n";
}

// 3. Check uploads directory
echo "3. Uploads Directory: ";
if (is_dir("uploads/profiles")) {
    echo "✓ Exists\n";
} else {
    echo "✗ Does not exist (will be created on first upload)\n";
}

// 4. Test sample data
echo "4. Sample Users in Database: ";
$users = mysqli_query($conn, "SELECT id, username, foto_profil FROM users LIMIT 3");
if ($users && mysqli_num_rows($users) > 0) {
    echo mysqli_num_rows($users) . " found\n";
    while ($user = mysqli_fetch_assoc($users)) {
        echo "   - ID: " . $user['id'] . ", Username: " . $user['username'] . ", Photo: " . ($user['foto_profil'] ? "Yes" : "No") . "\n";
    }
} else {
    echo "No users found\n";
}

echo "\n=== Setup Check Complete ===\n";
?>
