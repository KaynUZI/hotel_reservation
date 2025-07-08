<?php
require_once 'includes/auth.php';
$auth->requireAdmin();
require_once 'config/database.php';

$conn = getDB();

// Get action and user ID
$action = $_GET['action'] ?? '';
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($action === 'delete' && $user_id > 0) {
    // Prevent admin from deleting themselves
    $currentUser = $auth->getCurrentUser();
    if ($user_id == $currentUser['id']) {
        header('Location: users.php?error=cannot_delete_self');
        exit();
    }
    // Delete user
    $stmt = $conn->prepare('DELETE FROM users WHERE id = ?');
    if ($stmt->execute([$user_id])) {
        header('Location: users.php?success=deleted');
        exit();
    } else {
        header('Location: users.php?error=delete_failed');
        exit();
    }
} else {
    // Invalid request
    header('Location: users.php?error=invalid_action');
    exit();
} 