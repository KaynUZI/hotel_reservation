<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';
require_once 'includes/transaction_id.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$message = '';
$messageType = '';

// Get reservation ID from URL
$reservation_id = intval($_GET['id'] ?? 0);

if (!$reservation_id) {
    header('Location: reservations.php?error=invalid_reservation');
    exit();
}

// Fetch reservation details
$stmt = $conn->prepare("
    SELECT r.*, g.first_name, g.last_name, g.email, rm.room_number, rt.name AS room_type_name, rt.base_price
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

// Check if user has permission to view this reservation
if (!$auth->isAdmin() && $reservation['created_by'] != $currentUser['id']) {
    header('Location: reservations.php?error=access_denied');
    exit();
}

// Check if payment already exists
$stmt = $conn->prepare("SELECT * FROM payments WHERE reservation_id = ? AND payment_status = 'completed'");
$stmt->execute([$reservation_id]);
$existingPayment = $stmt->fetch();

if ($existingPayment) {
    header('Location: reservations.php?error=payment_already_made');
    exit();
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $card_number = $_POST['card_number'] ?? '';
    $card_holder = $_POST['card_holder'] ?? '';
    $expiry_month = $_POST['expiry_month'] ?? '';
    $expiry_year = $_POST['expiry_year'] ?? '';
    $cvv = $_POST['cvv'] ?? '';
    $paypal_email = $_POST['paypal_email'] ?? '';

    // Validate payment method
    if (empty($payment_method)) {
        $message = 'Please select a payment method.';
        $messageType = 'danger';
    } else {
        try {
            // Generate unique transaction ID
            $transaction_id = generateUniqueTransactionId($conn, 'payment');
            
            // Simulate payment processing (in real implementation, integrate with payment gateway)
            $payment_status = 'completed'; // Simulate successful payment
            
            // Insert payment record
            $stmt = $conn->prepare("
                INSERT INTO payments (reservation_id, amount, payment_method, payment_status, transaction_id) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([
                $reservation_id, 
                $reservation['total_amount'], 
                $payment_method, 
                $payment_status, 
                $transaction_id
            ]);

            if ($result) {
                // Update reservation status to confirmed
                $stmt = $conn->prepare("UPDATE reservations SET status = 'confirmed' WHERE id = ?");
                $stmt->execute([$reservation_id]);
                
                $message = 'Payment processed successfully! Your reservation has been confirmed.';
                $messageType = 'success';
                
                // Redirect to reservation details after 3 seconds
                header("refresh:3;url=reservations.php?success=payment_completed");
            } else {
                $message = 'Payment processing failed. Please try again.';
                $messageType = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

// Calculate days and total
$check_in = new DateTime($reservation['check_in_date']);
$check_out = new DateTime($reservation['check_out_date']);
$days = $check_out->diff($check_in)->days;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #181818; /* Black */
            --secondary-color: #23272b; /* Dark Gray */
            --accent-color: #bfa046; /* Gold */
            --silver-color: #e5e5e5; /* Silver */
            --success-color: #27ae60;
            --warning-color: #f39c12;
        }

        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--silver-color);
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

        .payment-card {
            background: var(--secondary-color);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
            color: var(--silver-color);
        }

        .reservation-summary {
            background: #23272b;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: var(--silver-color);
        }

        .payment-method-card {
            border: 2px solid #444;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--primary-color);
            color: var(--silver-color);
        }

        .payment-method-card:hover, .payment-method-card.selected {
            border-color: var(--accent-color);
            background: rgba(191,160,70,0.08);
            color: var(--accent-color);
        }

        .payment-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        .form-label {
            color: var(--accent-color);
        }

        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
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

        .total-amount-box {
            background: var(--success-color);
            color: #fff;
            border-radius: 15px;
            padding: 2rem 1rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .total-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .total-value {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .section-title {
            color: var(--accent-color);
            font-weight: bold;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }

        h2, h3, h4, .fw-bold {
            color: var(--accent-color) !important;
        }

        .divider {
            border-top: 1px solid #444;
            margin: 2rem 0;
        }

        @media (max-width: 768px) {
            .payment-card {
                padding: 1.5rem;
            }
            
            .total-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container main-content">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="payment-card">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card me-2"></i>Complete Payment
                    </h2>

                    <!-- Reservation Summary -->
                    <div class="reservation-summary">
                        <h4 class="mb-3">
                            <i class="fas fa-calendar-check me-2"></i>Reservation Summary
                        </h4>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Guest:</strong> <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
                                <p><strong>Room:</strong> <?php echo htmlspecialchars($reservation['room_number'] . ' (' . $reservation['room_type_name'] . ')'); ?></p>
                                <p><strong>Check-in:</strong> <?php echo date('F j, Y', strtotime($reservation['check_in_date'])); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Check-out:</strong> <?php echo date('F j, Y', strtotime($reservation['check_out_date'])); ?></p>
                                <p><strong>Duration:</strong> <?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?></p>
                                <p><strong>Guests:</strong> <?php echo $reservation['adults']; ?> adult<?php echo $reservation['adults'] > 1 ? 's' : ''; ?><?php echo $reservation['children'] > 0 ? ', ' . $reservation['children'] . ' child' . ($reservation['children'] > 1 ? 'ren' : '') : ''; ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Total Amount -->
                    <div class="total-amount-box">
                        <div class="total-label">Total Amount</div>
                        <div class="total-value">$<?php echo number_format($reservation['total_amount'], 2); ?></div>
                        <div class="total-label"><?php echo $days; ?> night<?php echo $days > 1 ? 's' : ''; ?> × $<?php echo number_format($reservation['base_price'], 2); ?> per night</div>
                    </div>

                    <!-- Payment Form -->
                    <form method="POST" action="" id="paymentForm">
                        <h4 class="section-title">
                            <i class="fas fa-payment me-2"></i>Payment Method
                        </h4>

                        <!-- Payment Method Selection -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="payment-method-card" onclick="selectPaymentMethod('credit_card')">
                                    <div class="payment-icon text-primary">
                                        <i class="fas fa-credit-card"></i>
                                    </div>
                                    <h5>Credit/Debit Card</h5>
                                    <p class="text-muted mb-0">Pay with Visa, MasterCard, or American Express</p>
                                    <input type="radio" name="payment_method" value="credit_card" class="d-none" id="credit_card_radio">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="payment-method-card" onclick="selectPaymentMethod('paypal')">
                                    <div class="payment-icon text-info">
                                        <i class="fab fa-paypal"></i>
                                    </div>
                                    <h5>PayPal</h5>
                                    <p class="text-muted mb-0">Pay securely with your PayPal account</p>
                                    <input type="radio" name="payment_method" value="paypal" class="d-none" id="paypal_radio">
                                </div>
                            </div>
                        </div>

                        <!-- Credit Card Form -->
                        <div id="creditCardForm" class="payment-form" style="display: none;">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <label for="card_holder" class="form-label">Cardholder Name</label>
                                    <input type="text" class="form-control" id="card_holder" name="card_holder" placeholder="Enter cardholder name">
                                </div>
                                <div class="col-12 mb-3">
                                    <label for="card_number" class="form-label">Card Number</label>
                                    <input type="text" class="form-control" id="card_number" name="card_number" placeholder="1234 5678 9012 3456" maxlength="19">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="expiry_month" class="form-label">Expiry Month</label>
                                    <select class="form-select" id="expiry_month" name="expiry_month">
                                        <option value="">Month</option>
                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                            <option value="<?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="expiry_year" class="form-label">Expiry Year</label>
                                    <select class="form-select" id="expiry_year" name="expiry_year">
                                        <option value="">Year</option>
                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                            <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="cvv" class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="cvv" name="cvv" placeholder="123" maxlength="4">
                                </div>
                            </div>
                        </div>

                        <!-- PayPal Form -->
                        <div id="paypalForm" class="payment-form" style="display: none;">
                            <div class="mb-3">
                                <label for="paypal_email" class="form-label">PayPal Email</label>
                                <input type="email" class="form-control" id="paypal_email" name="paypal_email" placeholder="Enter your PayPal email">
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <a href="reservations.php" class="btn btn-secondary btn-custom">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Reservations
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-custom" id="payButton" disabled>
                                        <i class="fas fa-lock me-2"></i>Pay $<?php echo number_format($reservation['total_amount'], 2); ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPaymentMethod(method) {
            // Remove selected class from all cards
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Hide all forms
            document.querySelectorAll('.payment-form').forEach(form => {
                form.style.display = 'none';
            });
            
            // Select the clicked method
            document.getElementById(method + '_radio').checked = true;
            document.querySelector('.payment-method-card').parentElement.querySelector('.payment-method-card').classList.add('selected');
            
            // Show corresponding form
            if (method === 'credit_card') {
                document.getElementById('creditCardForm').style.display = 'block';
            } else if (method === 'paypal') {
                document.getElementById('paypalForm').style.display = 'block';
            }
            
            // Enable pay button
            document.getElementById('payButton').disabled = false;
        }

        // Format card number with spaces
        document.getElementById('card_number').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            e.target.value = formattedValue;
        });

        // Validate form before submission
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method.');
                return;
            }

            if (paymentMethod.value === 'credit_card') {
                const cardHolder = document.getElementById('card_holder').value.trim();
                const cardNumber = document.getElementById('card_number').value.replace(/\s/g, '');
                const expiryMonth = document.getElementById('expiry_month').value;
                const expiryYear = document.getElementById('expiry_year').value;
                const cvv = document.getElementById('cvv').value;

                if (!cardHolder || !cardNumber || !expiryMonth || !expiryYear || !cvv) {
                    e.preventDefault();
                    alert('Please fill in all credit card details.');
                    return;
                }

                if (cardNumber.length < 13 || cardNumber.length > 19) {
                    e.preventDefault();
                    alert('Please enter a valid card number.');
                    return;
                }

                if (cvv.length < 3 || cvv.length > 4) {
                    e.preventDefault();
                    alert('Please enter a valid CVV.');
                    return;
                }
            } else if (paymentMethod.value === 'paypal') {
                const paypalEmail = document.getElementById('paypal_email').value.trim();
                if (!paypalEmail) {
                    e.preventDefault();
                    alert('Please enter your PayPal email.');
                    return;
                }
            }
        });
    </script>
</body>
</html> 