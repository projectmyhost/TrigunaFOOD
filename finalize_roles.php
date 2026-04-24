<?php
require_once 'config.php';

echo "=== Membuat/Update User TrigunaDEV ===" . PHP_EOL;

// Check if user exists
$check = mysqli_query($conn, "SELECT id FROM users WHERE username = 'TrigunaDEV'");
$exists = mysqli_num_rows($check) > 0;

if ($exists) {
    // Update existing user to admin
    $update = mysqli_query($conn, "UPDATE users SET role = 'admin' WHERE username = 'TrigunaDEV'");
    $rows = mysqli_affected_rows($conn);
    echo "✓ User TrigunaDEV sudah ada. Diupdate role ke 'admin': " . $rows . " baris diubah" . PHP_EOL;
} else {
    // Create new user
    $hashed_password = password_hash('blueedryt123', PASSWORD_BCRYPT);
    $insert = mysqli_query($conn, "INSERT INTO users (username, email, password, role) VALUES ('TrigunaDEV', 'trigunadev@example.com', '$hashed_password', 'admin')");
    if ($insert) {
        echo "✓ User TrigunaDEV berhasil dibuat dengan role 'admin'" . PHP_EOL;
    } else {
        echo "✗ Gagal membuat user: " . mysqli_error($conn) . PHP_EOL;
    }
}

// Verify final state
echo "\n=== Verifikasi Data Terbaru ===" . PHP_EOL;
$result = mysqli_query($conn, "SELECT username, email, role FROM users ORDER BY username");
$count = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $count++;
    echo $count . ". Username: " . $row['username'] . " | Email: " . $row['email'] . " | Role: " . $row['role'] . PHP_EOL;
}

mysqli_close($conn);
?>
