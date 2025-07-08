<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';
require_once 'includes/transaction_id.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();

$message = '';
$messageType = '';
$generatedId = '';
$validationResult = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'generate_payment':
                $generatedId = generateUniqueTransactionId($conn, 'payment');
                $message = 'Payment transaction ID generated successfully!';
                $messageType = 'success';
                break;
                
            case 'generate_reservation':
                $generatedId = generateUniqueTransactionId($conn, 'reservation');
                $message = 'Reservation transaction ID generated successfully!';
                $messageType = 'success';
                break;
                
            case 'generate_sophisticated':
                $guestName = $_POST['guest_name'] ?? '';
                $checkInDate = $_POST['check_in_date'] ?? '';
                $roomType = $_POST['room_type'] ?? '';
                $reservationCount = intval($_POST['reservation_count'] ?? 1);
                
                if (empty($guestName) || empty($checkInDate) || empty($roomType)) {
                    throw new Exception('All fields are required for sophisticated transaction ID generation.');
                }
                
                $generatedId = generateTransactionId($guestName, date('Y-m-d H:i:s'), $checkInDate, $roomType, $reservationCount);
                $message = 'Sophisticated transaction ID generated successfully!';
                $messageType = 'success';
                break;
                
            case 'validate':
                $transactionId = $_POST['transaction_id'] ?? '';
                if (empty($transactionId)) {
                    throw new Exception('Please enter a transaction ID to validate.');
                }
                
                $isValid = validateTransactionId($transactionId);
                $parsed = parseTransactionId($transactionId);
                
                $validationResult = [
                    'transaction_id' => $transactionId,
                    'is_valid' => $isValid,
                    'parsed_info' => $parsed
                ];
                
                $message = $isValid ? 'Transaction ID is valid!' : 'Transaction ID is invalid!';
                $messageType = $isValid ? 'success' : 'danger';
                break;
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

// Get room types for sophisticated generation
$roomTypes = $conn->query("SELECT name FROM room_types ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction ID Generator - Hotel Reservation System</title>
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

        .card {
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            border: 1px solid var(--card-border);
            color: var(--text-color);
            margin-bottom: 2rem;
        }
        .card h5, .card h4 { color: var(--accent-color); }

        .generator-card {
            background: var(--silver-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .transaction-id-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            border: 2px solid var(--silver-color);
            text-align: center;
            margin: 1rem 0;
            word-break: break-all;
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
        .validation-result {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin: 1rem 0;
        }
        .validation-valid {
            border-left: 4px solid var(--accent-color);
        }
        .validation-invalid {
            border-left: 4px solid #e74c3c;
        }
        .info-box {
            background: var(--secondary-color);
            color: var(--accent-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .format-example {
            font-family: 'Courier New', monospace;
            background: rgba(0,0,0,0.04);
            padding: 0.5rem;
            border-radius: 5px;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container main-content">
        <div class="row">
            <div class="col-12">
                <div class="info-box">
                    <h4><i class="fas fa-tools me-2"></i>Transaction ID Generator</h4>
                    <p class="mb-0">Generate and validate transaction IDs for the hotel reservation system.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Generated Transaction ID Display -->
                <?php if ($generatedId): ?>
                    <div class="card p-4">
                        <h5><i class="fas fa-check-circle text-success me-2"></i>Generated Transaction ID</h5>
                        <div class="transaction-id-display">
                            <?php echo htmlspecialchars($generatedId); ?>
                        </div>
                        <div class="text-center">
                            <button class="btn btn-outline-primary btn-custom" onclick="copyToClipboard('<?php echo htmlspecialchars($generatedId); ?>')">
                                <i class="fas fa-copy me-2"></i>Copy to Clipboard
                            </button>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Validation Result -->
                <?php if ($validationResult): ?>
                    <div class="card p-4">
                        <h5><i class="fas fa-search me-2"></i>Validation Result</h5>
                        <div class="validation-result <?php echo $validationResult['is_valid'] ? 'validation-valid' : 'validation-invalid'; ?>">
                            <strong>Transaction ID:</strong> <?php echo htmlspecialchars($validationResult['transaction_id']); ?><br>
                            <strong>Valid:</strong> 
                            <span class="badge <?php echo $validationResult['is_valid'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $validationResult['is_valid'] ? 'Yes' : 'No'; ?>
                            </span>
                            
                            <?php if ($validationResult['parsed_info']): ?>
                                <br><br>
                                <strong>Parsed Information:</strong>
                                <ul class="list-unstyled mt-2">
                                    <li><strong>Type:</strong> <?php echo ucfirst($validationResult['parsed_info']['type']); ?></li>
                                    <?php if ($validationResult['parsed_info']['timestamp']): ?>
                                        <li><strong>Timestamp:</strong> <?php echo htmlspecialchars($validationResult['parsed_info']['timestamp']); ?></li>
                                    <?php endif; ?>
                                    <?php if ($validationResult['parsed_info']['guest']): ?>
                                        <li><strong>Guest Code:</strong> <?php echo htmlspecialchars($validationResult['parsed_info']['guest']); ?></li>
                                    <?php endif; ?>
                                    <?php if ($validationResult['parsed_info']['room_type']): ?>
                                        <li><strong>Room Type:</strong> <?php echo htmlspecialchars($validationResult['parsed_info']['room_type']); ?></li>
                                    <?php endif; ?>
                                    <?php if ($validationResult['parsed_info']['count']): ?>
                                        <li><strong>Count:</strong> <?php echo htmlspecialchars($validationResult['parsed_info']['count']); ?></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Generator Tools -->
                <div class="row">
                    <!-- Simple Generators -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Generators</h5>
                            
                            <div class="generator-card">
                                <h6>Payment Transaction ID</h6>
                                <p class="text-muted small">Format: PAY[YYYYMMDD][HHMMSS][RANDOM]</p>
                                <div class="format-example">Example: PAY202312151430221234</div>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="generate_payment">
                                    <button type="submit" class="btn btn-primary btn-custom w-100">
                                        <i class="fas fa-credit-card me-2"></i>Generate Payment ID
                                    </button>
                                </form>
                            </div>

                            <div class="generator-card">
                                <h6>Reservation Transaction ID</h6>
                                <p class="text-muted small">Format: RES[YYYYMMDD][HHMMSS][RANDOM]</p>
                                <div class="format-example">Example: RES202312151430221234</div>
                                <form method="POST" class="mt-3">
                                    <input type="hidden" name="action" value="generate_reservation">
                                    <button type="submit" class="btn btn-success btn-custom w-100">
                                        <i class="fas fa-calendar-plus me-2"></i>Generate Reservation ID
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Sophisticated Generator -->
                    <div class="col-md-6">
                        <div class="card p-4">
                            <h5><i class="fas fa-cogs me-2"></i>Sophisticated Generator</h5>
                            <p class="text-muted small">Generate transaction ID based on reservation details</p>
                            
                            <form method="POST">
                                <input type="hidden" name="action" value="generate_sophisticated">
                                
                                <div class="mb-3">
                                    <label for="guest_name" class="form-label">Guest Name</label>
                                    <input type="text" class="form-control" id="guest_name" name="guest_name" 
                                           placeholder="John Doe" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="check_in_date" class="form-label">Check-in Date</label>
                                    <input type="date" class="form-control" id="check_in_date" name="check_in_date" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="room_type" class="form-label">Room Type</label>
                                    <select class="form-select" id="room_type" name="room_type" required>
                                        <option value="">Select Room Type</option>
                                        <?php foreach ($roomTypes as $type): ?>
                                            <option value="<?php echo htmlspecialchars($type['name']); ?>">
                                                <?php echo htmlspecialchars($type['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="reservation_count" class="form-label">Reservation Count</label>
                                    <input type="number" class="form-control" id="reservation_count" name="reservation_count" 
                                           value="1" min="1" max="99999" required>
                                </div>
                                
                                <button type="submit" class="btn btn-warning btn-custom w-100">
                                    <i class="fas fa-magic me-2"></i>Generate Sophisticated ID
                                </button>
                            </form>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <strong>Format:</strong> [GUEST][MONTH][DAY][CHECKIN_MMYY]-[ROOM_TYPE][COUNT]<br>
                                    <strong>Example:</strong> JOFEB102302-FIC00001
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Validator -->
                <div class="card p-4">
                    <h5><i class="fas fa-check-double me-2"></i>Transaction ID Validator</h5>
                    <p class="text-muted">Validate and parse transaction IDs to extract information</p>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="validate">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <label for="transaction_id" class="form-label">Transaction ID</label>
                                <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                       placeholder="Enter transaction ID to validate" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-info btn-custom w-100">
                                    <i class="fas fa-search me-2"></i>Validate
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                alert('Transaction ID copied to clipboard!');
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
        
        // Set default date to today
        document.getElementById('check_in_date').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html> 