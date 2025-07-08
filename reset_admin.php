<?php
require_once 'config/database.php';

$conn = getDB();

// Reset admin password to 'admin123'
$newPassword = 'admin123';
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
$result = $stmt->execute([$hashedPassword]);

if ($result) {
    echo "Admin password has been reset successfully!<br>";
    echo "Username: admin<br>";
    echo "Password: admin123<br>";
    echo "<a href='login.php'>Go to Login</a>";
} else {
    echo "Failed to reset password.";
}
?> 