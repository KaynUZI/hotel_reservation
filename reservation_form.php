<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';
require_once 'includes/transaction_id.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();
$action = $_GET['action'] ?? 'new';
$selected_room_id = $_GET['room_id'] ?? '';
$error = '';

// For users: auto-detect or create guest profile
if (!$isStaff) {
    $stmt = $conn->prepare("SELECT * FROM guests WHERE user_id = ? LIMIT 1");
    $stmt->execute([$currentUser['id']]);
    $userGuest = $stmt->fetch();
    if (!$userGuest) {
        header('Location: guest_form.php?action=new&from_reservation=1');
        exit();
    }
}

// Fetch guests and rooms
$guests = $conn->query("SELECT * FROM guests ORDER BY first_name, last_name")->fetchAll();
$rooms = $conn->query("SELECT r.*, rt.name AS room_type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.status = 'available' ORDER BY r.room_number")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest_id = $isStaff ? intval($_POST['guest_id'] ?? 0) : $userGuest['id'];
    $room_id = intval($_POST['room_id'] ?? 0);
    $check_in_date = $_POST['check_in_date'] ?? '';
    $check_out_date = $_POST['check_out_date'] ?? '';
    $adults = intval($_POST['adults'] ?? 1);
    $children = intval($_POST['children'] ?? 0);
    $special_requests = sanitize($_POST['special_requests'] ?? '');
    $status = $isStaff ? ($_POST['status'] ?? 'pending') : 'pending';

    // Validation
    if ($guest_id === 0 || $room_id === 0 || empty($check_in_date) || empty($check_out_date)) {
        $error = 'All fields are required.';
    } elseif (!validateDate($check_in_date) || !validateDate($check_out_date)) {
        $error = 'Invalid date format.';
    } elseif ($check_in_date >= $check_out_date) {
        $error = 'Check-out date must be after check-in date.';
    } else {
        // Check if reservation is at least 2 days in advance
        $checkInDate = new DateTime($check_in_date);
        $currentDate = new DateTime();
        $interval = $currentDate->diff($checkInDate);
        if ($interval->days < 2) {
            $error = 'Reservations must be made at least 2 days in advance.';
        } else {
            // Check room availability
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE room_id = ? AND status NOT IN ('cancelled', 'checked_out') AND ((check_in_date <= ? AND check_out_date > ?) OR (check_in_date < ? AND check_out_date >= ?) OR (check_in_date >= ? AND check_out_date <= ?))");
            $stmt->execute([$room_id, $check_in_date, $check_in_date, $check_out_date, $check_out_date, $check_in_date, $check_out_date]);
            $conflictCount = $stmt->fetch()['count'];
            if ($conflictCount > 0) {
                $error = 'Room is not available for the selected dates.';
            } else {
                // Calculate total amount
                $stmt = $conn->prepare("SELECT rt.base_price FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ?");
                $stmt->execute([$room_id]);
                $roomData = $stmt->fetch();
                $basePrice = $roomData['base_price'];
                $days = calculateDays($check_in_date, $check_out_date);
                $totalAmount = $basePrice * $days;
                // Insert reservation
                $stmt = $conn->prepare("INSERT INTO reservations (guest_id, room_id, check_in_date, check_out_date, adults, children, total_amount, status, special_requests, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$guest_id, $room_id, $check_in_date, $check_out_date, $adults, $children, $totalAmount, $status, $special_requests, $currentUser['id']]);
                if ($result) {
                    // Redirect to payment page for non-staff users
                    if (!$isStaff) {
                        header('Location: payment.php?id=' . $conn->lastInsertId());
                        exit();
                    } else {
                        header('Location: reservations.php');
                        exit();
                    }
                } else {
                    $error = 'Failed to create reservation.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Reservation - Hotel Reservation System</title>
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
        .container { margin-top: 2rem; max-width: 800px; }
        .card {
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            background: var(--secondary-color);
            border: 1px solid #444;
            color: var(--silver-color);
        }
        .btn-custom {
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
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
        .btn-secondary {
            background: #444 !important;
            color: var(--silver-color) !important;
            border: none !important;
        }
        .btn-secondary:hover {
            background: #23272b !important;
            color: var(--accent-color) !important;
        }
        .form-label { font-weight: 500; color: var(--accent-color); }
        h3 {
            color: var(--accent-color);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4" style="color: var(--primary-color);">
            <i class="fas fa-calendar-plus me-2"></i>
            New Reservation
        </h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?> </div>
        <?php endif; ?>
        <form method="POST" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="guest_id" class="form-label">Guest</label>
                    <?php if ($isStaff): ?>
                    <select class="form-select" id="guest_id" name="guest_id" required>
                        <option value="">Select Guest</option>
                        <?php foreach ($guests as $guest): ?>
                            <option value="<?php echo $guest['id']; ?>">
                                <?php echo htmlspecialchars($guest['first_name'] . ' ' . $guest['last_name'] . ' (' . $guest['email'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else: ?>
                    <input type="hidden" name="guest_id" value="<?php echo $userGuest['id']; ?>">
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($userGuest['first_name'] . ' ' . $userGuest['last_name'] . ' (' . $userGuest['email'] . ')'); ?>" readonly>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="room_id" class="form-label">Room</label>
                    <select class="form-select" id="room_id" name="room_id" required>
                        <option value="">Select Room</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo $room['id']; ?>" <?php echo $selected_room_id == $room['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['room_number'] . ' - ' . $room['room_type_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="check_in_date" class="form-label">Check-in Date</label>
                    <input type="date" class="form-control" id="check_in_date" name="check_in_date" required min="<?php echo date('Y-m-d', strtotime('+2 days')); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="check_out_date" class="form-label">Check-out Date</label>
                    <input type="date" class="form-control" id="check_out_date" name="check_out_date" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="adults" class="form-label">Adults</label>
                    <input type="number" class="form-control" id="adults" name="adults" value="1" min="1" max="10" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="children" class="form-label">Children</label>
                    <input type="number" class="form-control" id="children" name="children" value="0" min="0" max="10">
                </div>
            </div>
            <?php if ($isStaff): ?>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="checked_in">Checked In</option>
                    <option value="checked_out">Checked Out</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label for="special_requests" class="form-label">Special Requests</label>
                <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-custom w-100">Create Reservation</button>
            <a href="reservations.php" class="btn btn-secondary btn-custom w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 