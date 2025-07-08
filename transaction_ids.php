<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';
require_once 'includes/transaction_id.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();

// Handle search
$search = $_GET['search'] ?? '';
$type = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query
$whereConditions = [];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(p.transaction_id LIKE ? OR g.first_name LIKE ? OR g.last_name LIKE ? OR g.email LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

if (!empty($type)) {
    $whereConditions[] = "p.payment_method = ?";
    $params[] = $type;
}

if (!empty($date_from)) {
    $whereConditions[] = "DATE(p.payment_date) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(p.payment_date) <= ?";
    $params[] = $date_to;
}

// Limit to user's own transactions if not admin/staff
if (!$isAdmin && !$isStaff) {
    $whereConditions[] = "g.user_id = ?";
    $params[] = $currentUser['id'];
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get transactions
$sql = "
    SELECT p.*, r.check_in_date, r.check_out_date, r.total_amount as reservation_amount,
           g.first_name, g.last_name, g.email, g.phone,
           rt.name as room_type_name, rm.room_number,
           u.username as created_by_username
    FROM payments p
    JOIN reservations r ON p.reservation_id = r.id
    JOIN guests g ON r.guest_id = g.id
    JOIN rooms rm ON r.room_id = rm.id
    JOIN room_types rt ON rm.room_type_id = rt.id
    LEFT JOIN users u ON r.created_by = u.id
    $whereClause
    ORDER BY p.payment_date DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Get payment methods for filter
$paymentMethods = $conn->query("SELECT DISTINCT payment_method FROM payments ORDER BY payment_method")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction IDs - Hotel Reservation System</title>
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-secondary:hover {
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
        .badge, .status-badge {
            font-size: 0.95em;
            border-radius: 8px;
            padding: 0.5em 1em;
            background: var(--accent-color) !important;
            color: #181818 !important;
        }
        .container.main-content {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        .search-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }
        @media (max-width: 768px) {
            .container.main-content {
                padding: 0 0.5rem;
            }
            .search-actions {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container main-content">
        <div class="row">
            <div class="col-12">
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 style="color: var(--primary-color);">
                            <i class="fas fa-receipt me-2"></i>
                            Transaction IDs
                        </h3>
                        <div>
                            <a href="payment_history.php" class="btn btn-secondary btn-custom">
                                <i class="fas fa-history me-2"></i>
                                Payment History
                            </a>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number"><?php echo count($transactions); ?></div>
                                <div class="stats-label">Total Transactions</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number">
                                    $<?php echo number_format(array_sum(array_column($transactions, 'amount')), 2); ?>
                                </div>
                                <div class="stats-label">Total Amount</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number">
                                    <?php echo count(array_filter($transactions, function($t) { return $t['payment_status'] === 'completed'; })); ?>
                                </div>
                                <div class="stats-label">Completed</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card">
                                <div class="stats-number">
                                    <?php echo count(array_filter($transactions, function($t) { return $t['payment_status'] === 'pending'; })); ?>
                                </div>
                                <div class="stats-label">Pending</div>
                            </div>
                        </div>
                    </div>

                    <!-- Search Form -->
                    <div class="search-card">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Transaction ID, Guest Name, Email">
                            </div>
                            <div class="col-md-2">
                                <label for="type" class="form-label">Payment Method</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Methods</option>
                                    <?php foreach ($paymentMethods as $method): ?>
                                        <option value="<?php echo $method['payment_method']; ?>" 
                                                <?php echo $type === $method['payment_method'] ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($method['payment_method']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?php echo htmlspecialchars($date_from); ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?php echo htmlspecialchars($date_to); ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-custom me-2">
                                    <i class="fas fa-search me-2"></i>
                                    Search
                                </button>
                                <a href="transaction_ids.php" class="btn btn-secondary btn-custom">
                                    <i class="fas fa-times me-2"></i>
                                    Clear
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Transactions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($transactions)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No transactions found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <span class="transaction-id">
                                                    <?php echo htmlspecialchars($transaction['transaction_id']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($transaction['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($transaction['room_number']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($transaction['room_type_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <strong>$<?php echo number_format($transaction['amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo ucfirst($transaction['payment_method']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?php echo $transaction['payment_status']; ?>">
                                                    <?php echo ucfirst($transaction['payment_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo date('M d, Y H:i', strtotime($transaction['payment_date'])); ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#transactionModal<?php echo $transaction['id']; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if ($isAdmin || $isStaff): ?>
                                                        <a href="payment_history.php?id=<?php echo $transaction['id']; ?>" 
                                                           class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-history"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Transaction Details Modal -->
                                        <div class="modal fade" id="transactionModal<?php echo $transaction['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">
                                                            <i class="fas fa-receipt me-2"></i>
                                                            Transaction Details
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6>Transaction Information</h6>
                                                                <table class="table table-sm">
                                                                    <tr>
                                                                        <td><strong>Transaction ID:</strong></td>
                                                                        <td><span class="transaction-id"><?php echo htmlspecialchars($transaction['transaction_id']); ?></span></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Payment Date:</strong></td>
                                                                        <td><?php echo date('F d, Y H:i:s', strtotime($transaction['payment_date'])); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Amount:</strong></td>
                                                                        <td><strong>$<?php echo number_format($transaction['amount'], 2); ?></strong></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Payment Method:</strong></td>
                                                                        <td><?php echo ucfirst($transaction['payment_method']); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Status:</strong></td>
                                                                        <td>
                                                                            <span class="status-badge status-<?php echo $transaction['payment_status']; ?>">
                                                                                <?php echo ucfirst($transaction['payment_status']); ?>
                                                                            </span>
                                                                        </td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6>Guest Information</h6>
                                                                <table class="table table-sm">
                                                                    <tr>
                                                                        <td><strong>Name:</strong></td>
                                                                        <td><?php echo htmlspecialchars($transaction['first_name'] . ' ' . $transaction['last_name']); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Email:</strong></td>
                                                                        <td><?php echo htmlspecialchars($transaction['email']); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Phone:</strong></td>
                                                                        <td><?php echo htmlspecialchars($transaction['phone']); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Room:</strong></td>
                                                                        <td><?php echo htmlspecialchars($transaction['room_number'] . ' - ' . $transaction['room_type_name']); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Check-in:</strong></td>
                                                                        <td><?php echo date('M d, Y', strtotime($transaction['check_in_date'])); ?></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <td><strong>Check-out:</strong></td>
                                                                        <td><?php echo date('M d, Y', strtotime($transaction['check_out_date'])); ?></td>
                                                                    </tr>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                        <a href="payment_history.php?id=<?php echo $transaction['id']; ?>" class="btn btn-primary">
                                                            <i class="fas fa-history me-2"></i>
                                                            View History
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 