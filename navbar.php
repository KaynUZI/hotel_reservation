<?php
require_once __DIR__ . '/includes/auth.php';
$currentUser = $auth->getCurrentUser();
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background: var(--primary-color) !important; box-shadow: 0 2px 20px rgba(0,0,0,0.2); min-height: 58px;">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="index.php" style="font-weight: bold; color: var(--accent-color) !important; font-size: 1.35rem; font-family: 'Brush Script MT', cursive, 'Segoe Script', 'Comic Sans MS', sans-serif;">
            <i class="fas fa-gem me-2"></i>
            <span style="font-family: 'Brush Script MT', cursive, 'Segoe Script', 'Comic Sans MS', sans-serif; font-size: 1.5rem; letter-spacing: 1px;">Golden Diamond Hotel</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="index.php">
                        <i class="fas fa-home me-1"></i> <span>Dashboard</span>
                    </a>
                </li>
                <!-- Reservations Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="reservationsDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-calendar-check me-1"></i> <span>Reservations</span>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="reservations.php"><i class="fas fa-calendar-check me-2"></i> Reservations</a></li>
                        <li><a class="dropdown-item" href="payment_history.php"><i class="fas fa-credit-card me-2"></i> Payment History</a></li>
                        <li><a class="dropdown-item" href="transaction_ids.php"><i class="fas fa-receipt me-2"></i> Transaction IDs</a></li>
                        <li><a class="dropdown-item" href="transaction_generator.php"><i class="fas fa-tools me-2"></i> ID Generator</a></li>
                    </ul>
                </li>
                <!-- Management Dropdown for Staff/Admin -->
                <?php if ($auth->isAdmin() || $auth->isStaff()): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="managementDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cogs me-1"></i> <span>Management</span>
                    </a>
                    <ul class="dropdown-menu">
                        <?php if ($auth->isStaff()): ?>
                        <li><a class="dropdown-item" href="rooms.php"><i class="fas fa-cog me-2"></i> Manage Rooms</a></li>
                        <?php else: ?>
                        <li><a class="dropdown-item" href="view_rooms.php"><i class="fas fa-bed me-2"></i> Rooms</a></li>
                        <?php endif; ?>
                        <?php if ($auth->isAdmin()): ?>
                        <li><a class="dropdown-item" href="users.php"><i class="fas fa-user-cog me-2"></i> Users</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="cancellation_reports.php"><i class="fas fa-times-circle me-2"></i> Cancellation Reports</a></li>
                    </ul>
                </li>
                <?php endif; ?>
                <!-- For regular users, show Rooms directly -->
                <?php if (!$auth->isAdmin() && !$auth->isStaff()): ?>
                <li class="nav-item">
                    <a class="nav-link d-flex align-items-center" href="view_rooms.php">
                        <i class="fas fa-bed me-1"></i> <span>Rooms</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" style="color: rgba(255,255,255,0.8) !important;">
                        <i class="fas fa-user-circle me-1"></i>
                        <span><?php echo htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 