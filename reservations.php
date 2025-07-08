<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();

// Handle success/error messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch reservations (admin: all, user: own)
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT r.*, g.first_name, g.last_name, rm.room_number, rt.name AS room_type FROM reservations r JOIN guests g ON r.guest_id = g.id JOIN rooms rm ON r.room_id = rm.id JOIN room_types rt ON rm.room_type_id = rt.id ORDER BY r.created_at DESC");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT r.*, g.first_name, g.last_name, rm.room_number, rt.name AS room_type FROM reservations r JOIN guests g ON r.guest_id = g.id JOIN rooms rm ON r.room_id = rm.id JOIN room_types rt ON rm.room_type_id = rt.id WHERE g.user_id = ? ORDER BY r.created_at DESC");
    $stmt->execute([$currentUser['id']]);
}
$reservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #181818; /* Black */
            --secondary-color: #23272b; /* Dark Gray */
            --accent-color: #bfa046; /* Gold */
            --silver-color: #e5e5e5; /* Silver */
            --card-bg: #23272b;
            --card-border: #444;
            --text-color: #f5f5f5;
            --muted-text: #b0b0b0;
        }
        body { 
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }
        .container { margin-top: 2rem; }
        .dashboard-card {
            background: #fff;
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            border: 1px solid var(--card-border);
            color: #181818;
        }
        .dashboard-card h2, .dashboard-card h3 { color: var(--accent-color); }
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-danger, .btn-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover, .btn-secondary:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
        .table-responsive, .table {
            background: #fff !important;
            color: #181818 !important;
            border-radius: 12px;
            box-shadow: none;
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
        .table tbody tr {
            border-bottom: 1px solid var(--silver-color);
        }
        .table-hover tbody tr:hover {
            background: #f5f5f5;
        }
        .badge {
            font-size: 0.95em;
            border-radius: 8px;
            padding: 0.5em 1em;
        }
        .badge.bg-success, .badge.bg-warning, .badge.bg-danger, .badge.bg-info, .badge.bg-primary, .badge.bg-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
        }
        .alert-success, .alert-danger {
            background: var(--card-bg);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }
        .section-title { color: var(--accent-color); font-weight: bold; }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="dashboard-card">
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                if ($success === 'payment_completed') {
                    echo 'Payment completed successfully! Your reservation has been confirmed.';
                } elseif ($success === 'cancelled') {
                    echo 'Reservation cancelled successfully.';
                } else {
                    echo htmlspecialchars($success);
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php 
                if ($error === 'invalid_reservation') {
                    echo 'Invalid reservation ID.';
                } elseif ($error === 'reservation_not_found') {
                    echo 'Reservation not found.';
                } elseif ($error === 'access_denied') {
                    echo 'Access denied.';
                } elseif ($error === 'payment_already_made') {
                    echo 'Payment has already been made for this reservation.';
                } elseif ($error === 'unauthorized') {
                    echo 'You are not authorized to perform this action.';
                } elseif ($error === 'already_cancelled') {
                    echo 'This reservation has already been cancelled.';
                } elseif ($error === 'cannot_cancel_checked_out') {
                    echo 'Cannot cancel a reservation that has been checked out.';
                } else {
                    echo htmlspecialchars($error);
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title"><i class="fas fa-calendar-check me-2"></i>Reservations</h2>
            <a href="reservation_form.php?action=new" class="btn btn-primary btn-custom">
                <i class="fas fa-plus me-1"></i> New Reservation
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Guest</th>
                        <th>Room</th>
                        <th>Room Type</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $res): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($res['id']); ?></td>
                        <td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($res['room_number']); ?></td>
                        <td><?php echo htmlspecialchars($res['room_type']); ?></td>
                        <td><?php echo htmlspecialchars($res['check_in_date']); ?></td>
                        <td><?php echo htmlspecialchars($res['check_out_date']); ?></td>
                        <td>$<?php echo number_format($res['total_amount'], 2); ?></td>
                        <td>
                            <?php
                            $status = $res['status'];
                            $badge = 'secondary';
                            if ($status === 'confirmed') $badge = 'success';
                            elseif ($status === 'pending') $badge = 'warning';
                            elseif ($status === 'checked_in') $badge = 'info';
                            elseif ($status === 'checked_out') $badge = 'primary';
                            elseif ($status === 'cancelled') $badge = 'danger';
                            ?>
                            <span class="badge bg-<?php echo $badge; ?>"> <?php echo ucfirst($status); ?> </span>
                        </td>
                        <td>
                            <div class="d-flex flex-wrap gap-2">
                                <?php if ($status === 'pending' && !$isAdmin): ?>
                                <a href="payment.php?id=<?php echo $res['id']; ?>" class="btn btn-sm btn-success btn-custom">
                                    <i class="fas fa-credit-card"></i> Pay Now
                                </a>
                                <?php endif; ?>
                                <?php if ($status !== 'cancelled' && $status !== 'checked_out'): ?>
                                <a href="cancel_reservation.php?id=<?php echo $res['id']; ?>" class="btn btn-sm btn-danger btn-custom me-1">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <?php endif; ?>
                                <a href="change_room.php?id=<?php echo $res['id']; ?>" class="btn btn-sm btn-warning btn-custom">
                                    <i class="fas fa-exchange-alt"></i> Change Room
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 