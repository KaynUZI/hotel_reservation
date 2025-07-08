<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isStaff = $auth->isStaff();

if ($isStaff) {
    // Staff/admin see all guests
    $stmt = $conn->prepare("SELECT * FROM guests ORDER BY created_at DESC");
    $stmt->execute();
    $guests = $stmt->fetchAll();
} else {
    // Regular users see only their own guest profile
    $stmt = $conn->prepare("SELECT * FROM guests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$currentUser['id']]);
    $guests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guests - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #181818; /* Black */
            --secondary-color: #23272b; /* Dark Gray */
            --accent-color: #bfa046; /* Gold */
            --silver-color: #e5e5e5; /* Silver */
            --card-bg: #fff;
            --card-border: #444;
            --text-color: #181818;
        }
        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            margin-top: 2rem;
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            border: 1px solid var(--card-border);
            color: var(--text-color);
            padding: 2rem;
        }
        h2 {
            color: var(--accent-color);
        }
        .table {
            background: #fff;
            color: #181818;
            border-radius: 10px;
        }
        .table thead {
            background: #fff;
            color: var(--accent-color);
            border-bottom: 2px solid var(--accent-color);
        }
        .table thead th {
            color: #181818;
            font-weight: bold;
        }
        .table-hover tbody tr:hover {
            background: #f5f5f5;
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-danger {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-users me-2"></i>Guests</h2>
        <a href="guest_form.php?action=new&from_reservation=1" class="btn btn-primary btn-custom">
            <i class="fas fa-plus me-1"></i> Add Guest
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>ID Type</th>
                    <th>ID Number</th>
                    <th>Date of Birth</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($guests as $guest): ?>
                <tr>
                    <td><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($guest['email']); ?></td>
                    <td><?php echo htmlspecialchars($guest['phone']); ?></td>
                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $guest['id_type']))); ?></td>
                    <td><?php echo htmlspecialchars($guest['id_number']); ?></td>
                    <td><?php echo htmlspecialchars($guest['date_of_birth']); ?></td>
                    <td>
                        <a href="guest_form.php?action=edit&id=<?php echo $guest['id']; ?>" class="btn btn-sm btn-info btn-custom me-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="guest_form.php?action=delete&id=<?php echo $guest['id']; ?>" class="btn btn-sm btn-danger btn-custom" onclick="return confirm('Are you sure you want to delete this guest?');">
                            <i class="fas fa-trash"></i> Delete
                        </a>
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