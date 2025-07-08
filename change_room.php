<?php
require_once 'includes/auth.php';
require_once 'config/database.php';

$conn = getDB();
$reservation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$message = '';

// Fetch reservation
$stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();
if (!$reservation) {
    header('Location: reservations.php?error=reservation_not_found');
    exit();
}

// Fetch available rooms (not occupied, not reserved for the same dates)
$stmt = $conn->prepare("SELECT r.*, rt.name as room_type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.status = 'available' OR r.id = ? ORDER BY r.room_number");
$stmt->execute([$reservation['room_id']]);
$rooms = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_room_id = intval($_POST['room_id'] ?? 0);
    if ($new_room_id && $new_room_id != $reservation['room_id']) {
        // Check if the new room is available for the reservation dates
        $stmt = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE room_id = ? AND id != ? AND status NOT IN ('cancelled', 'checked_out') AND ((check_in_date <= ? AND check_out_date > ?) OR (check_in_date < ? AND check_out_date >= ?) OR (check_in_date >= ? AND check_out_date <= ?))");
        $stmt->execute([
            $new_room_id,
            $reservation_id,
            $reservation['check_in_date'],
            $reservation['check_in_date'],
            $reservation['check_out_date'],
            $reservation['check_out_date'],
            $reservation['check_in_date'],
            $reservation['check_out_date']
        ]);
        $conflict = $stmt->fetchColumn();
        if ($conflict == 0) {
            // Fetch new room's base price
            $stmt = $conn->prepare("SELECT rt.base_price FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ?");
            $stmt->execute([$new_room_id]);
            $roomData = $stmt->fetch();
            $basePrice = $roomData['base_price'];
            // Calculate number of nights
            require_once 'config/database.php';
            $days = calculateDays($reservation['check_in_date'], $reservation['check_out_date']);
            $totalAmount = $basePrice * $days;
            // Update reservation with new room and total amount
            $stmt = $conn->prepare("UPDATE reservations SET room_id = ?, total_amount = ? WHERE id = ?");
            if ($stmt->execute([$new_room_id, $totalAmount, $reservation_id])) {
                header('Location: reservations.php?success=room_changed');
                exit();
            } else {
                $message = 'Failed to update reservation.';
            }
        } else {
            $message = 'Selected room is not available for the reservation dates.';
        }
    } else {
        $message = 'Please select a different room.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Room - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #e5e5e5;
        }
        .container { margin-top: 2rem; max-width: 500px; }
        .card { border-radius: 18px; box-shadow: 0 8px 32px rgba(0,0,0,0.15); background: #23272b; border: 1px solid #444; color: #e5e5e5; }
        h3 { color: #bfa046; }
        .btn-custom { border-radius: 25px; font-weight: 600; background: #bfa046; color: #181818; border: none; transition: all 0.3s ease; }
        .btn-custom:hover { background: #d4b24c; color: #181818; box-shadow: 0 5px 15px rgba(191,160,70,0.2); }
        .form-label { color: #bfa046; }
        .form-select, .form-control { border-radius: 10px; border: 2px solid #e5e5e5; padding: 0.75rem 1rem; transition: all 0.3s ease; }
        .form-select:focus, .form-control:focus { border-color: #bfa046; box-shadow: 0 0 0 0.2rem rgba(191, 160, 70, 0.15); }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4"><i class="fas fa-exchange-alt me-2"></i>Change Room</h3>
        <?php if ($message): ?>
            <div class="alert alert-danger"> <?php echo htmlspecialchars($message); ?> </div>
        <?php endif; ?>
        <form method="POST">
            <div class="mb-3">
                <label for="room_id" class="form-label">Select New Room</label>
                <select class="form-select" id="room_id" name="room_id" required>
                    <option value="">Select Room</option>
                    <?php foreach ($rooms as $room): ?>
                        <option value="<?php echo $room['id']; ?>" <?php if ($room['id'] == $reservation['room_id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($room['room_number'] . ' - ' . $room['room_type_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-custom w-100">Update Room</button>
            <a href="reservations.php" class="btn btn-secondary btn-custom w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 