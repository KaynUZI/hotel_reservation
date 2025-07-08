<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();

// Fetch payment history (admin: all, user: own)
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT p.*, r.check_in_date, r.check_out_date, r.total_amount, r.status as reservation_status,
               g.first_name, g.last_name, g.email, rm.room_number, rt.name AS room_type_name
        FROM payments p 
        JOIN reservations r ON p.reservation_id = r.id 
        JOIN guests g ON r.guest_id = g.id 
        JOIN rooms rm ON r.room_id = rm.id 
        JOIN room_types rt ON rm.room_type_id = rt.id 
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("
        SELECT p.*, r.check_in_date, r.check_out_date, r.total_amount, r.status as reservation_status,
               g.first_name, g.last_name, g.email, rm.room_number, rt.name AS room_type_name
        FROM payments p 
        JOIN reservations r ON p.reservation_id = r.id 
        JOIN guests g ON r.guest_id = g.id 
        JOIN rooms rm ON r.room_id = rm.id 
        JOIN room_types rt ON rm.room_type_id = rt.id 
        WHERE g.user_id = ? 
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$currentUser['id']]);
}
$payments = $stmt->fetchAll();

// Calculate statistics
$totalPayments = count($payments);
$totalAmount = array_sum(array_column($payments, 'amount'));
$completedPayments = count(array_filter($payments, function($p) { return $p['payment_status'] === 'completed'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Hotel Reservation System</title>
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
            --muted-text: #b0b0b0;
        }

        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: var(--primary-color) !important;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }

        .navbar-brand {
            font-weight: bold;
            color: var(--accent-color) !important;
        }

        .nav-link {
            color: var(--silver-color) !important;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            color: var(--accent-color) !important;
        }

        .main-content {
            padding: 2rem 0;
        }

        .dashboard-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        .dashboard-card h2, .dashboard-card h3 { color: var(--accent-color); }

        .stat-card {
            background: var(--secondary-color);
            color: var(--accent-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            text-align: center;
            border: 1px solid var(--card-border);
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--accent-color);
        }
        .stat-label {
            font-size: 1rem;
            color: #fff;
            opacity: 0.9;
        }
        .btn-custom {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            background: var(--accent-color);
            color: #181818;
        }
        .btn-custom:hover {
            background: #d4b24c;
            color: #181818;
            box-shadow: 0 5px 15px rgba(191,160,70,0.2);
        }
        .btn-primary, .btn-success, .btn-info, .btn-warning {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover {
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
        .section-title {
            color: var(--accent-color);
            font-weight: bold;
            margin-bottom: 1.5rem;
        }
        .payment-status {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            background: var(--accent-color);
            color: #181818;
        }
        .payment-method-icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
            color: var(--accent-color) !important;
        }
        @media (max-width: 768px) {
            .dashboard-card {
                padding: 1.5rem;
            }
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container main-content">
        <div class="dashboard-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="section-title">
                    <i class="fas fa-credit-card me-2"></i>Payment History
                </h2>
                <a href="reservations.php" class="btn btn-primary btn-custom">
                    <i class="fas fa-arrow-left me-1"></i> Back to Reservations
                </a>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $totalPayments; ?></div>
                        <div class="stat-label">Total Payments</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card success">
                        <div class="stat-number">$<?php echo number_format($totalAmount, 2); ?></div>
                        <div class="stat-label">Total Amount Paid</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card warning">
                        <div class="stat-number"><?php echo $completedPayments; ?></div>
                        <div class="stat-label">Completed Payments</div>
                    </div>
                </div>
            </div>

            <!-- Payment History Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Transaction ID</th>
                            <th>Guest</th>
                            <th>Room</th>
                            <th>Reservation Dates</th>
                            <th>Amount</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($payments)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="fas fa-credit-card fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No payment history found.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['transaction_id']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($payment['email']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($payment['room_number']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($payment['room_type_name']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo date('M j', strtotime($payment['check_in_date'])); ?> - 
                                        <?php echo date('M j, Y', strtotime($payment['check_out_date'])); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php 
                                            $check_in = new DateTime($payment['check_in_date']);
                                            $check_out = new DateTime($payment['check_out_date']);
                                            $days = $check_out->diff($check_in)->days;
                                            echo $days . ' night' . ($days > 1 ? 's' : '');
                                            ?>
                                        </small>
                                    </td>
                                    <td>
                                        <strong>$<?php echo number_format($payment['amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        $method = $payment['payment_method'];
                                        $icon = 'credit-card';
                                        $color = 'primary';
                                        
                                        if ($method === 'paypal') {
                                            $icon = 'fab fa-paypal';
                                            $color = 'info';
                                        } elseif ($method === 'credit_card') {
                                            $icon = 'fas fa-credit-card';
                                            $color = 'primary';
                                        } elseif ($method === 'cash') {
                                            $icon = 'fas fa-money-bill-wave';
                                            $color = 'success';
                                        }
                                        ?>
                                        <i class="<?php echo $icon; ?> text-<?php echo $color; ?> payment-method-icon"></i>
                                        <?php echo ucfirst(str_replace('_', ' ', $method)); ?>
                                    </td>
                                    <td>
                                        <span class="payment-status <?php echo $payment['payment_status']; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($payment['payment_date'])); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('g:i A', strtotime($payment['payment_date'])); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 