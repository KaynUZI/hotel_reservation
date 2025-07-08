<?php
require_once 'includes/auth.php';
$auth->requireAdmin();
require_once 'config/database.php';

$conn = getDB();

// Fetch all users
$stmt = $conn->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - Hotel Reservation System</title>
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
        .container {
            margin-top: 2rem;
            background: var(--secondary-color);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            border: 1px solid #444;
            color: var(--silver-color);
            padding: 2rem;
        }
        h2 {
            color: var(--accent-color);
        }
        .table {
            background: var(--primary-color);
            color: var(--silver-color);
            border-radius: 10px;
        }
        .table thead {
            background: var(--primary-color);
            color: var(--accent-color);
            border-bottom: 2px solid var(--accent-color);
        }
        .table thead th {
            color: var(--accent-color);
            font-weight: bold;
        }
        .table-hover tbody tr:hover {
            background: #23272b;
        }
        .badge.bg-danger, .badge.bg-warning, .badge.bg-info, .badge.bg-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
        }
        .btn-custom {
            border-radius: 25px;
            font-weight: 600;
            background: var(--accent-color);
            color: #181818;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background: #d4b24c;
            color: #181818;
            box-shadow: 0 5px 15px rgba(191,160,70,0.2);
        }
        .btn-primary, .btn-info, .btn-danger {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-info:hover, .btn-danger:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-cog me-2"></i>Users</h2>
        <a href="user_form.php?action=new" class="btn btn-primary btn-custom">
            <i class="fas fa-plus me-1"></i> Add User
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['phone']); ?></td>
                    <td>
                        <?php
                        $role = $user['role'];
                        $badge = 'secondary';
                        if ($role === 'admin') $badge = 'danger';
                        elseif ($role === 'staff') $badge = 'warning';
                        elseif ($role === 'guest') $badge = 'info';
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>"> <?php echo ucfirst($role); ?> </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <?php if ($user['id'] != $currentUser['id']): ?>
                        <a href="user_form.php?action=delete&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-danger btn-custom" onclick="return confirm('Are you sure you want to delete this user?');">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 