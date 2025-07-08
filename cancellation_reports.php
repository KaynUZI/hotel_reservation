<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();

// Only admin and staff can access cancellation reports
if (!$isAdmin && !$isStaff) {
    header('Location: index.php');
    exit();
}

// Handle filters
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$room_type = $_GET['room_type'] ?? '';
$cancellation_reason = $_GET['cancellation_reason'] ?? '';
$export = $_GET['export'] ?? '';

// Build query conditions
$whereConditions = ["r.status = 'cancelled'"];
$params = [];

if (!empty($date_from)) {
    $whereConditions[] = "DATE(r.updated_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $whereConditions[] = "DATE(r.updated_at) <= ?";
    $params[] = $date_to;
}

if (!empty($room_type)) {
    $whereConditions[] = "rt.name = ?";
    $params[] = $room_type;
}

if (!empty($cancellation_reason)) {
    $whereConditions[] = "r.cancellation_reason = ?";
    $params[] = $cancellation_reason;
}

$whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

// Get cancelled reservations
$sql = "
    SELECT r.*, g.first_name, g.last_name, g.email, g.phone,
           rt.name as room_type_name, rt.base_price,
           rm.room_number,
           u.username as cancelled_by_username,
           DATEDIFF(r.check_out_date, r.check_in_date) as duration_days,
           (r.total_amount * 0.1) as estimated_loss
    FROM reservations r
    JOIN guests g ON r.guest_id = g.id
    JOIN rooms rm ON r.room_id = rm.id
    JOIN room_types rt ON rm.room_type_id = rt.id
    LEFT JOIN users u ON r.cancelled_by = u.id
    $whereClause
    ORDER BY r.updated_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$cancellations = $stmt->fetchAll();

// Get statistics
$statsSql = "
    SELECT 
        COUNT(*) as total_cancellations,
        SUM(r.total_amount) as total_lost_revenue,
        AVG(r.total_amount) as avg_cancellation_amount,
        COUNT(DISTINCT r.guest_id) as unique_guests_cancelled,
        COUNT(DISTINCT r.room_id) as rooms_affected
    FROM reservations r
    $whereClause
";

$statsStmt = $conn->prepare($statsSql);
$statsStmt->execute($params);
$stats = $statsStmt->fetch();

// Get cancellation reasons breakdown
$reasonsSql = "
    SELECT 
        COALESCE(r.cancellation_reason, 'No reason provided') as reason,
        COUNT(*) as count,
        SUM(r.total_amount) as total_amount
    FROM reservations r
    $whereClause
    GROUP BY r.cancellation_reason
    ORDER BY count DESC
";

$reasonsStmt = $conn->prepare($reasonsSql);
$reasonsStmt->execute($params);
$reasonsBreakdown = $reasonsStmt->fetchAll();

// Get room types breakdown
$roomTypesSql = "
    SELECT 
        rt.name as room_type,
        COUNT(*) as count,
        SUM(r.total_amount) as total_amount
    FROM reservations r
    JOIN rooms rm ON r.room_id = rm.id
    JOIN room_types rt ON rm.room_type_id = rt.id
    $whereClause
    GROUP BY rt.id, rt.name
    ORDER BY count DESC
";

$roomTypesStmt = $conn->prepare($roomTypesSql);
$roomTypesStmt->execute($params);
$roomTypesBreakdown = $roomTypesStmt->fetchAll();

// Get monthly trends
$monthlySql = "
    SELECT 
        DATE_FORMAT(r.updated_at, '%Y-%m') as month,
        COUNT(*) as count,
        SUM(r.total_amount) as total_amount
    FROM reservations r
    $whereClause
    GROUP BY DATE_FORMAT(r.updated_at, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
";

$monthlyStmt = $conn->prepare($monthlySql);
$monthlyStmt->execute($params);
$monthlyTrends = $monthlyStmt->fetchAll();

// Get filter options
$roomTypes = $conn->query("SELECT DISTINCT name FROM room_types ORDER BY name")->fetchAll();
$cancellationReasons = $conn->query("SELECT DISTINCT cancellation_reason FROM reservations WHERE cancellation_reason IS NOT NULL ORDER BY cancellation_reason")->fetchAll();

// Handle CSV export
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="cancellation_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV headers
    fputcsv($output, [
        'Reservation ID', 'Guest Name', 'Email', 'Phone', 'Room Number', 'Room Type',
        'Check-in Date', 'Check-out Date', 'Duration (Days)', 'Total Amount',
        'Cancellation Reason', 'Cancelled By', 'Cancellation Date'
    ]);
    
    // CSV data
    foreach ($cancellations as $cancellation) {
        fputcsv($output, [
            $cancellation['id'],
            $cancellation['first_name'] . ' ' . $cancellation['last_name'],
            $cancellation['email'],
            $cancellation['phone'],
            $cancellation['room_number'],
            $cancellation['room_type_name'],
            $cancellation['check_in_date'],
            $cancellation['check_out_date'],
            $cancellation['duration_days'],
            $cancellation['total_amount'],
            $cancellation['cancellation_reason'] ?? 'No reason provided',
            $cancellation['cancelled_by_username'] ?? 'System',
            $cancellation['updated_at']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancellation Reports - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .card {
            background: var(--secondary-color);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            margin-bottom: 2rem;
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
        .stats-card {
            background: linear-gradient(135deg, var(--accent-color), #c0392b);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .filter-card {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .table {
            border-radius: 15px;
            overflow: hidden;
        }
        .table th {
            background: linear-gradient(135deg, var(--primary-color), #34495e);
            color: white;
            border: none;
            font-weight: 600;
        }
        .table td {
            vertical-align: middle;
            border-color: #e9ecef;
        }
        .table tbody tr:hover {
            background: rgba(231, 76, 60, 0.05);
        }
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        .reason-badge {
            background: rgba(231, 76, 60, 0.1);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
            border-radius: 20px;
            padding: 0.25rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .loss-amount {
            color: var(--accent-color);
            font-weight: bold;
        }
        .info-box {
            background: linear-gradient(135deg, var(--secondary-color), #2980b9);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container main-content">
        <div class="row">
            <div class="col-12">
                <div class="info-box">
                    <h4><i class="fas fa-chart-line me-2"></i>Cancellation Reports</h4>
                    <p class="mb-0">Track and analyze reservation cancellations to improve business performance.</p>
                </div>

                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['total_cancellations']); ?></div>
                            <div class="stats-label">Total Cancellations</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number">$<?php echo number_format($stats['total_lost_revenue'], 2); ?></div>
                            <div class="stats-label">Lost Revenue</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number">$<?php echo number_format($stats['avg_cancellation_amount'], 2); ?></div>
                            <div class="stats-label">Avg. Loss per Cancellation</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['unique_guests_cancelled']); ?></div>
                            <div class="stats-label">Unique Guests</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo number_format($stats['rooms_affected']); ?></div>
                            <div class="stats-label">Rooms Affected</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stats-card">
                            <div class="stats-number">
                                <?php 
                                $totalReservations = $conn->query("SELECT COUNT(*) as total FROM reservations")->fetch()['total'];
                                $cancellationRate = $totalReservations > 0 ? ($stats['total_cancellations'] / $totalReservations) * 100 : 0;
                                echo number_format($cancellationRate, 1) . '%';
                                ?>
                            </div>
                            <div class="stats-label">Cancellation Rate</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
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
                        <div class="col-md-2">
                            <label for="room_type" class="form-label">Room Type</label>
                            <select class="form-select" id="room_type" name="room_type">
                                <option value="">All Types</option>
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?php echo $type['name']; ?>" 
                                            <?php echo $room_type === $type['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="cancellation_reason" class="form-label">Reason</label>
                            <select class="form-select" id="cancellation_reason" name="cancellation_reason">
                                <option value="">All Reasons</option>
                                <?php foreach ($cancellationReasons as $reason): ?>
                                    <option value="<?php echo $reason['cancellation_reason']; ?>" 
                                            <?php echo $cancellation_reason === $reason['cancellation_reason'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($reason['cancellation_reason']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-custom me-2">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="cancellation_reports.php" class="btn btn-secondary btn-custom me-2">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" 
                               class="btn btn-success btn-custom">
                                <i class="fas fa-download me-2"></i>Export CSV
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <!-- Cancellation Reasons Chart -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-pie-chart me-2"></i>Cancellation Reasons</h5>
                            <div class="chart-container">
                                <canvas id="reasonsChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Monthly Trends Chart -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-chart-line me-2"></i>Monthly Cancellation Trends</h5>
                            <div class="chart-container">
                                <canvas id="trendsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Breakdown Tables -->
                <div class="row">
                    <!-- Reasons Breakdown -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-list me-2"></i>Reasons Breakdown</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Reason</th>
                                            <th>Count</th>
                                            <th>Total Loss</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($reasonsBreakdown as $reason): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reason['reason']); ?></td>
                                                <td><span class="badge bg-danger"><?php echo $reason['count']; ?></span></td>
                                                <td class="loss-amount">$<?php echo number_format($reason['total_amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Room Types Breakdown -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-bed me-2"></i>Room Types Breakdown</h5>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Room Type</th>
                                            <th>Count</th>
                                            <th>Total Loss</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($roomTypesBreakdown as $roomType): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($roomType['room_type']); ?></td>
                                                <td><span class="badge bg-warning"><?php echo $roomType['count']; ?></span></td>
                                                <td class="loss-amount">$<?php echo number_format($roomType['total_amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cancellations Table -->
                <div class="card p-4">
                    <h5><i class="fas fa-table me-2"></i>Cancelled Reservations</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Dates</th>
                                    <th>Amount</th>
                                    <th>Reason</th>
                                    <th>Cancelled By</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cancellations)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No cancellations found</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($cancellations as $cancellation): ?>
                                        <tr>
                                            <td><strong>#<?php echo $cancellation['id']; ?></strong></td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($cancellation['first_name'] . ' ' . $cancellation['last_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($cancellation['email']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($cancellation['room_number']); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($cancellation['room_type_name']); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <strong><?php echo date('M d', strtotime($cancellation['check_in_date'])); ?> - <?php echo date('M d', strtotime($cancellation['check_out_date'])); ?></strong>
                                                    <br>
                                                    <small class="text-muted"><?php echo $cancellation['duration_days']; ?> days</small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="loss-amount">$<?php echo number_format($cancellation['total_amount'], 2); ?></span>
                                            </td>
                                            <td>
                                                <span class="reason-badge">
                                                    <?php echo htmlspecialchars($cancellation['cancellation_reason'] ?? 'No reason'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($cancellation['cancelled_by_username'] ?? 'System'); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('M d, Y H:i', strtotime($cancellation['updated_at'])); ?></small>
                                            </td>
                                        </tr>
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
    <script>
        // Reasons Chart
        const reasonsCtx = document.getElementById('reasonsChart').getContext('2d');
        new Chart(reasonsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($reasonsBreakdown, 'reason')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($reasonsBreakdown, 'count')); ?>,
                    backgroundColor: [
                        '#e74c3c', '#f39c12', '#f1c40f', '#27ae60', '#3498db',
                        '#9b59b6', '#34495e', '#e67e22', '#1abc9c', '#95a5a6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Trends Chart
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($monthlyTrends, 'month')); ?>,
                datasets: [{
                    label: 'Cancellations',
                    data: <?php echo json_encode(array_column($monthlyTrends, 'count')); ?>,
                    borderColor: '#e74c3c',
                    backgroundColor: 'rgba(231, 76, 60, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Lost Revenue ($)',
                    data: <?php echo json_encode(array_column($monthlyTrends, 'total_amount')); ?>,
                    borderColor: '#f39c12',
                    backgroundColor: 'rgba(243, 156, 18, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });
    </script>
</body>
</html> 