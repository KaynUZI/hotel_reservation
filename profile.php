<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($email)) {
        $message = 'First name, last name, and email are required.';
        $messageType = 'danger';
    } else {
        try {
            // Check if email is already taken by another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $currentUser['id']]);
            if ($stmt->rowCount() > 0) {
                $message = 'Email is already taken by another user.';
                $messageType = 'danger';
            } else {
                // Update basic information
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
                $stmt->execute([$first_name, $last_name, $email, $phone, $currentUser['id']]);

                // Update session variables
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;

                // Handle password change if provided
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                    $stmt->execute([$currentUser['id']]);
                    $user = $stmt->fetch();

                    if (password_verify($current_password, $user['password'])) {
                        if ($new_password === $confirm_password) {
                            if (strlen($new_password) >= 6) {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                                $stmt->execute([$hashed_password, $currentUser['id']]);
                                $message = 'Profile and password updated successfully!';
                                $messageType = 'success';
                            } else {
                                $message = 'New password must be at least 6 characters long.';
                                $messageType = 'danger';
                            }
                        } else {
                            $message = 'New password and confirm password do not match.';
                            $messageType = 'danger';
                        }
                    } else {
                        $message = 'Current password is incorrect.';
                        $messageType = 'danger';
                    }
                } else {
                    $message = 'Profile updated successfully!';
                    $messageType = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Get updated user data
$currentUser = $auth->getCurrentUser();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #181818; /* Black */
            --secondary-color: #23272b; /* Dark Gray */
            --accent-color: #bfa046; /* Gold */
            --silver-color: #e5e5e5; /* Silver */
        }
        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--silver-color);
        }
        .profile-card {
            background: var(--secondary-color);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            color: var(--silver-color);
        }
        .btn-primary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
        h2, h3, h4, .fw-bold {
            color: var(--accent-color) !important;
        }
        .form-label {
            color: var(--accent-color);
        }
        .navbar {
            background: rgba(44, 62, 80, 0.95) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }

        .navbar-brand {
            font-weight: bold;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: white !important;
            transform: translateY(-2px);
        }

        .main-content {
            padding: 2rem 0;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 3rem;
            color: white;
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.3);
        }

        .profile-name {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
        }

        .profile-role {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }

        .btn-custom {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #5a6268);
        }

        .section-title {
            color: var(--primary-color);
            font-weight: bold;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }

        .info-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .info-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 0.25rem;
        }

        .info-value {
            color: #6c757d;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
        }

        @media (max-width: 768px) {
            .profile-card {
                padding: 1.5rem;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 2.5rem;
            }
            
            .profile-name {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <h1 class="profile-name"><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></h1>
                        <span class="profile-role"><?php echo ucfirst($currentUser['role']); ?></span>
                    </div>

                    <form method="POST" action="">
                        <div class="row">
                            <div class="col-md-6">
                                <h3 class="section-title">
                                    <i class="fas fa-user-edit me-2"></i>Personal Information
                                </h3>
                                
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">First Name</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo htmlspecialchars($currentUser['first_name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Last Name</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo htmlspecialchars($currentUser['last_name']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                                </div>

                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h3 class="section-title">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h3>
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                    <small class="text-muted">Leave blank if you don't want to change password</small>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>

                                <div class="info-item">
                                    <div class="info-label">Account Information</div>
                                    <div class="info-value">
                                        <strong>Username:</strong> <?php echo htmlspecialchars($currentUser['username']); ?><br>
                                        <strong>Role:</strong> <?php echo ucfirst($currentUser['role']); ?><br>
                                        <strong>Member Since:</strong> 
                                        <?php 
                                        $stmt = $conn->prepare("SELECT created_at FROM users WHERE id = ?");
                                        $stmt->execute([$currentUser['id']]);
                                        $userData = $stmt->fetch();
                                        echo date('F j, Y', strtotime($userData['created_at']));
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="index.php" class="btn btn-secondary btn-custom">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-custom">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 