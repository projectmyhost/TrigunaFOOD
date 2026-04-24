<?php
require_once 'config.php';

// Update admin_test role from admin to user
$update1 = mysqli_query($conn, "UPDATE users SET role = 'user' WHERE username = 'admin_test' AND email = 'admin@test.com'");
$rows1 = mysqli_affected_rows($conn);

// Update TrigunaDEV role to admin
$update2 = mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE username = 'TrigunaDEV'");
$rows2 = mysqli_affected_rows($conn);

echo "=== Update Role User ===" . PHP_EOL;
echo "✓ admin_test diubah ke role 'user': " . $rows1 . " baris diubah" . PHP_EOL;
echo "✓ TrigunaDEV diubah ke role 'admin': " . $rows2 . " baris diubah" . PHP_EOL;

// Verify changes
$result = mysqli_query($conn, "SELECT username, email, role FROM users WHERE username IN ('admin_test', 'TrigunaDEV') ORDER BY username");

echo "\n=== Verifikasi Data Terbaru ===" . PHP_EOL;
while ($row = mysqli_fetch_assoc($result)) {
    echo "Username: " . $row['username'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . PHP_EOL;
}

mysqli_close($conn);
?>
