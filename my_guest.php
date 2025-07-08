<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

if ($auth->isStaff()) {
    header('Location: guests.php');
    exit();
}

$conn = getDB();
$currentUser = $auth->getCurrentUser();

// Fetch the user's guest profile
$stmt = $conn->prepare("SELECT * FROM guests WHERE user_id = ? LIMIT 1");
$stmt->execute([$currentUser['id']]);
$guest = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Guest Profile - Hotel Reservation System</title>
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
            max-width: 700px;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        .card h3 {
            color: var(--accent-color);
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
        .btn-success, .btn-info {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-success:hover, .btn-info:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
        .table {
            background: #fff;
            color: #181818;
            border-radius: 10px;
        }
        .table th {
            background: #fff;
            color: var(--accent-color);
            font-weight: bold;
        }
        .table-bordered th, .table-bordered td {
            border: 1px solid var(--silver-color) !important;
        }
        .alert-info {
            background: var(--secondary-color);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4"><i class="fas fa-user me-2"></i>My Guest Profile</h3>
        <?php if (!$guest): ?>
            <a href="guest_form.php?action=new&from_reservation=1" class="btn btn-success btn-custom mb-3">
                <i class="fas fa-user-plus me-1"></i> Add My Guest Profile
            </a>
        <?php endif; ?>
        <?php if ($guest): ?>
            <table class="table table-bordered">
                <tr><th>Name</th><td><?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name']); ?></td></tr>
                <tr><th>Email</th><td><?php echo htmlspecialchars($guest['email']); ?></td></tr>
                <tr><th>Phone</th><td><?php echo htmlspecialchars($guest['phone']); ?></td></tr>
                <tr><th>Address</th><td><?php echo htmlspecialchars($guest['address']); ?></td></tr>
                <tr><th>ID Type</th><td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $guest['id_type']))); ?></td></tr>
                <tr><th>ID Number</th><td><?php echo htmlspecialchars($guest['id_number']); ?></td></tr>
                <tr><th>Date of Birth</th><td><?php echo htmlspecialchars($guest['date_of_birth']); ?></td></tr>
            </table>
            <a href="guest_form.php?action=edit&id=<?php echo $guest['id']; ?>" class="btn btn-info btn-custom w-100 mt-3">
                <i class="fas fa-edit me-1"></i> Edit My Profile
            </a>
        <?php else: ?>
            <div class="alert alert-info mt-3">You have not created a guest profile yet.</div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 