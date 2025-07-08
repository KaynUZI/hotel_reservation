<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();

$reservation_id = $_GET['id'] ?? 0;
$message = '';
$messageType = '';

// Get reservation details
$stmt = $conn->prepare("
    SELECT r.*, g.first_name, g.last_name, g.email, g.phone,
           rt.name as room_type_name, rt.base_price,
           rm.room_number
    FROM reservations r
    JOIN guests g ON r.guest_id = g.id
    JOIN rooms rm ON r.room_id = rm.id
    JOIN room_types rt ON rm.room_type_id = rt.id
    WHERE r.id = ?
");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    header('Location: reservations.php?error=reservation_not_found');
    exit();
}

// Check if user can cancel this reservation
$canCancel = false;
$today = new DateTime();
$check_in = new DateTime($reservation['check_in_date']);
$isBeforeCheckin = $today < $check_in;
if ($isAdmin || $isStaff) {
    $canCancel = true;
} else {
    // Fetch the guest record for this reservation
    $stmt = $conn->prepare("SELECT user_id FROM guests WHERE id = ?");
    $stmt->execute([$reservation['guest_id']]);
    $guest = $stmt->fetch();
    if ($guest && $guest['user_id'] == $currentUser['id'] && $isBeforeCheckin) {
        $canCancel = true;
    }
}

if (!$canCancel) {
    header('Location: reservations.php?error=unauthorized');
    exit();
}

// Check if reservation can be cancelled
if ($reservation['status'] === 'cancelled') {
    header('Location: reservations.php?error=already_cancelled');
    exit();
}

if ($reservation['status'] === 'checked_out') {
    header('Location: reservations.php?error=cannot_cancel_checked_out');
    exit();
}

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cancellation_reason = trim($_POST['cancellation_reason'] ?? '');
    
    if (empty($cancellation_reason)) {
        $message = 'Please provide a reason for cancellation.';
        $messageType = 'danger';
    } else {
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Update reservation status
            $stmt = $conn->prepare("
                UPDATE reservations 
                SET status = 'cancelled', 
                    cancellation_reason = ?, 
                    cancelled_by = ?, 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$cancellation_reason, $currentUser['id'], $reservation_id]);
            
            // Update room status back to available
            $stmt = $conn->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['room_id']]);
            
            // If there's a payment, mark it as refunded
            $stmt = $conn->prepare("
                UPDATE payments 
                SET payment_status = 'refunded', 
                    updated_at = CURRENT_TIMESTAMP 
                WHERE reservation_id = ? AND payment_status = 'completed'
            ");
            $stmt->execute([$reservation_id]);
            
            $conn->commit();
            
            $message = 'Reservation cancelled successfully.';
            $messageType = 'success';
            
            // Redirect after 3 seconds
            header("refresh:3;url=reservations.php?success=cancelled");
            
        } catch (Exception $e) {
            $conn->rollBack();
            $message = 'Error cancelling reservation: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Calculate days and refund amount
$check_in = new DateTime($reservation['check_in_date']);
$check_out = new DateTime($reservation['check_out_date']);
$current_date = new DateTime();
$days = $check_out->diff($check_in)->days;

// Calculate days until check-in
$days_until_checkin = $current_date->diff($check_in)->days;

// Calculate refund based on cancellation policy
$refund_percentage = 0;

if ($days_until_checkin >= 7) {
    $refund_percentage = 100; // Full refund if cancelled 7+ days before
} elseif ($days_until_checkin >= 3) {
    $refund_percentage = 75; // 75% refund if cancelled 3-6 days before
} elseif ($days_until_checkin >= 1) {
    $refund_percentage = 50; // 50% refund if cancelled 1-2 days before
} else {
    $refund_percentage = 0; // No refund if cancelled on the same day
}

$refund_amount = ($reservation['total_amount'] * $refund_percentage) / 100;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Reservation - Hotel Reservation System</title>
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
        .container.main-content {
            margin-top: 2rem;
        }
        .card {
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        .card h3, .card h5, .card h6 {
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
        .btn-danger, .btn-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-danger:hover, .btn-secondary:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--silver-color);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(191, 160, 70, 0.15);
        }
        .alert {
            background: var(--secondary-color);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }
        .warning-box {
            background: rgba(191, 160, 70, 0.08);
            border: 2px solid var(--accent-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-color);
        }
        .policy-box {
            background: rgba(229, 229, 229, 0.5);
            border: 2px solid var(--silver-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--text-color);
        }
        .reservation-summary, .refund-info {
            margin-bottom: 2rem;
        }
        .badge {
            background: var(--accent-color) !important;
            color: #181818 !important;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card p-4">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h3 style="color: var(--accent-color);">
                            <i class="fas fa-times-circle me-2"></i>
                            Cancel Reservation
                        </h3>
                        <a href="reservations.php" class="btn btn-secondary btn-custom">
                            <i class="fas fa-arrow-left me-2"></i>
                            Back to Reservations
                        </a>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($message); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Reservation Summary -->
                    <div class="reservation-summary">
                        <h5><i class="fas fa-info-circle me-2"></i>Reservation Details</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Guest:</strong> <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                                <p><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
                                <p><strong>Phone:</strong> <?php echo htmlspecialchars($reservation['phone']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Room:</strong> <?php echo htmlspecialchars($reservation['room_number'] . ' - ' . $reservation['room_type_name']); ?></p>
                                <p><strong>Check-in:</strong> <?php echo date('M d, Y', strtotime($reservation['check_in_date'])); ?></p>
                                <p><strong>Check-out:</strong> <?php echo date('M d, Y', strtotime($reservation['check_out_date'])); ?></p>
                                <p><strong>Duration:</strong> <?php echo $days; ?> days</p>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <p><strong>Total Amount:</strong> <span class="h5">$<?php echo number_format($reservation['total_amount'], 2); ?></span></p>
                                <p><strong>Status:</strong> <span class="badge bg-<?php echo $reservation['status'] === 'confirmed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($reservation['status']); ?></span></p>
                            </div>
                        </div>
                    </div>

                    <!-- Refund Information -->
                    <div class="refund-info">
                        <h5><i class="fas fa-money-bill-wave me-2"></i>Refund Information</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Days until check-in:</strong> <?php echo $days_until_checkin; ?> days</p>
                                <p><strong>Refund percentage:</strong> <?php echo $refund_percentage; ?>%</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Refund amount:</strong> <span class="h4">$<?php echo number_format($refund_amount, 2); ?></span></p>
                                <p><strong>Processing time:</strong> 3-5 business days</p>
                            </div>
                        </div>
                    </div>

                    <!-- Cancellation Policy -->
                    <div class="policy-box">
                        <h6><i class="fas fa-file-contract me-2"></i>Cancellation Policy</h6>
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-check text-success me-2"></i>7+ days before check-in: 100% refund</li>
                            <li><i class="fas fa-check text-success me-2"></i>3-6 days before check-in: 75% refund</li>
                            <li><i class="fas fa-check text-warning me-2"></i>1-2 days before check-in: 50% refund</li>
                            <li><i class="fas fa-times text-danger me-2"></i>Same day or after check-in: No refund</li>
                        </ul>
                    </div>

                    <!-- Warning -->
                    <div class="warning-box">
                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notice</h6>
                        <p class="mb-0">Cancelling this reservation will immediately free up the room for other guests. This action cannot be undone. Please ensure you want to proceed with the cancellation.</p>
                    </div>

                    <!-- Cancellation Form -->
                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">
                                <strong>Reason for Cancellation *</strong>
                            </label>
                            <select class="form-select" id="cancellation_reason" name="cancellation_reason" required>
                                <option value="">Select a reason</option>
                                <option value="Change of plans">Change of plans</option>
                                <option value="Emergency situation">Emergency situation</option>
                                <option value="Travel restrictions">Travel restrictions</option>
                                <option value="Weather concerns">Weather concerns</option>
                                <option value="Found better deal elsewhere">Found better deal elsewhere</option>
                                <option value="Personal reasons">Personal reasons</option>
                                <option value="Business trip cancelled">Business trip cancelled</option>
                                <option value="Health issues">Health issues</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3" id="other_reason_div" style="display: none;">
                            <label for="other_reason" class="form-label">Please specify other reason</label>
                            <textarea class="form-control" id="other_reason" name="other_reason" rows="3" placeholder="Please provide details about your cancellation reason..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-danger btn-custom">
                                <i class="fas fa-times-circle me-2"></i>
                                Cancel Reservation
                            </button>
                            <a href="reservations.php" class="btn btn-secondary btn-custom">
                                <i class="fas fa-arrow-left me-2"></i>
                                Keep Reservation
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        // Handle "Other" reason selection
        document.getElementById('cancellation_reason').addEventListener('change', function() {
            const otherDiv = document.getElementById('other_reason_div');
            const otherReason = document.getElementById('other_reason');
            
            if (this.value === 'Other') {
                otherDiv.style.display = 'block';
                otherReason.required = true;
            } else {
                otherDiv.style.display = 'none';
                otherReason.required = false;
                otherReason.value = '';
            }
        });

        // Update cancellation reason when "Other" is selected
        document.getElementById('other_reason').addEventListener('input', function() {
            const cancellationReason = document.getElementById('cancellation_reason');
            if (cancellationReason.value === 'Other') {
                cancellationReason.value = this.value;
            }
        });
    </script>
</body>
</html> 