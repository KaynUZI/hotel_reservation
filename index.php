<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();

// Dashboard statistics
$totalRooms = $conn->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$availableRooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetchColumn();
$occupiedRooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'occupied'")->fetchColumn();
$pendingReservations = $conn->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();

// Recent reservations (admin: all, user: own)
if ($isAdmin) {
    $stmt = $conn->prepare("SELECT r.*, g.first_name, g.last_name, rm.room_number, rt.name AS room_type FROM reservations r JOIN guests g ON r.guest_id = g.id JOIN rooms rm ON r.room_id = rm.id JOIN room_types rt ON rm.room_type_id = rt.id ORDER BY r.created_at DESC LIMIT 5");
    $stmt->execute();
} else {
    $stmt = $conn->prepare("SELECT r.*, g.first_name, g.last_name, rm.room_number, rt.name AS room_type FROM reservations r JOIN guests g ON r.guest_id = g.id JOIN rooms rm ON r.room_id = rm.id JOIN room_types rt ON rm.room_type_id = rt.id WHERE g.user_id = ? ORDER BY r.created_at DESC LIMIT 5");
    $stmt->execute([$currentUser['id']]);
}
$recentReservations = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Reservation System - Dashboard</title>
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

        .navbar {
            background: var(--primary-color) !important;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        .navbar-brand {
            font-weight: bold;
            color: var(--accent-color) !important;
            letter-spacing: 1px;
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
        .dashboard-card h3, .dashboard-card h1, .dashboard-card h2 {
            color: var(--accent-color);
        }
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
            color: var(--silver-color);
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
        .welcome-section {
            background: linear-gradient(135deg, rgba(191,160,70,0.08), rgba(229,229,229,0.05));
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--accent-color);
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .table {
            color: var(--text-color);
            background: var(--card-bg);
        }
        .table thead {
            background: var(--primary-color);
            color: var(--accent-color);
        }
        .table tbody tr {
            border-bottom: 1px solid var(--card-border);
        }
        .table-hover tbody tr:hover {
            background: rgba(191,160,70,0.08);
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
        .list-group-item {
            background: var(--secondary-color);
            color: var(--text-color);
            border: 1px solid var(--card-border);
        }
        .list-group-item .badge {
            background: var(--accent-color) !important;
            color: #181818 !important;
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

    <!-- Main Content -->
    <div class="container main-content">
        <!-- Welcome Section -->
        <div class="welcome-section text-white">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3">
                        Welcome back, <?php echo htmlspecialchars($currentUser['first_name']); ?>!
                    </h1>
                    <p class="lead mb-0">
                        Manage your hotel operations efficiently with our comprehensive reservation system.
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-hotel" style="font-size: 4rem; opacity: 0.7;"></i>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalRooms; ?></div>
                    <div class="stat-label">Total Rooms</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card success">
                    <div class="stat-number"><?php echo $availableRooms; ?></div>
                    <div class="stat-label">Available Rooms</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card warning">
                    <div class="stat-number"><?php echo $occupiedRooms; ?></div>
                    <div class="stat-label">Occupied Rooms</div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="stat-card danger">
                    <div class="stat-number"><?php echo $pendingReservations; ?></div>
                    <div class="stat-label">Pending Reservations</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h3 class="mb-4">
                <i class="fas fa-bolt me-2"></i>
                Quick Actions
            </h3>
            <div class="quick-actions">
                <a href="reservations.php?action=new" class="btn btn-custom btn-primary w-100">
                    <i class="fas fa-plus me-2"></i>
                    New Reservation
                </a>
                <a href="guest_form.php?action=new&from_reservation=1" class="btn btn-custom btn-success w-100">
                    <i class="fas fa-user-plus me-2"></i>
                    Add Guest
                </a>
                <?php if ($isAdmin): ?>
                <a href="rooms.php" class="btn btn-custom btn-info w-100">
                    <i class="fas fa-bed me-2"></i>
                    Manage Rooms
                </a>
                <a href="reports.php" class="btn btn-custom btn-warning w-100">
                    <i class="fas fa-chart-line me-2"></i>
                    View Reports
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-lg-8">
                <div class="dashboard-card">
                    <h3 class="mb-4">
                        <i class="fas fa-clock me-2"></i>
                        Recent Reservations
                    </h3>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Room Type</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentReservations as $res): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($res['id']); ?></td>
                                    <td><?php echo htmlspecialchars($res['first_name'] . ' ' . $res['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($res['room_number']); ?></td>
                                    <td><?php echo htmlspecialchars($res['room_type']); ?></td>
                                    <td><?php echo htmlspecialchars($res['check_in_date']); ?></td>
                                    <td><?php echo htmlspecialchars($res['check_out_date']); ?></td>
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
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="dashboard-card">
                    <h3 class="mb-4">
                        <i class="fas fa-tasks me-2"></i>
                        Today's Tasks
                    </h3>
                    <div class="list-group list-group-flush">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Check-in: Room 201</span>
                            <span class="badge bg-primary">2:00 PM</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Check-out: Room 102</span>
                            <span class="badge bg-success">11:00 AM</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Room Cleaning: 301</span>
                            <span class="badge bg-warning">Pending</span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Payment Due: Room 401</span>
                            <span class="badge bg-danger">Urgent</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 