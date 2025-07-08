<?php
require_once 'includes/auth.php';
require_once 'config/database.php';
require_once 'includes/transaction_id.php';

$auth->requireAuth();
$conn = getDB();

// Set JSON header
header('Content-Type: application/json');

// Handle different types of requests
$action = $_GET['action'] ?? 'generate';

try {
    switch ($action) {
        case 'generate':
            $type = $_GET['type'] ?? 'payment';
            $transactionId = generateUniqueTransactionId($conn, $type);
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $transactionId,
                'type' => $type,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;
            
        case 'validate':
            $transactionId = $_GET['id'] ?? '';
            $isValid = validateTransactionId($transactionId);
            $parsed = parseTransactionId($transactionId);
            
            echo json_encode([
                'success' => true,
                'transaction_id' => $transactionId,
                'is_valid' => $isValid,
                'parsed_info' => $parsed
            ]);
            break;
            
        case 'reservation':
            $reservationId = $_GET['reservation_id'] ?? null;
            if (!$reservationId) {
                throw new Exception('Reservation ID is required');
            }
            
            $transactionId = getReservationTransactionId($reservationId, $conn);
            
            echo json_encode([
                'success' => true,
                'reservation_id' => $reservationId,
                'transaction_id' => $transactionId
            ]);
            break;
            
        case 'test':
            // Test all transaction ID formats
            $testResults = [];
            
            // Test payment transaction ID
            $paymentId = generatePaymentTransactionId();
            $testResults['payment'] = [
                'id' => $paymentId,
                'valid' => validateTransactionId($paymentId),
                'parsed' => parseTransactionId($paymentId)
            ];
            
            // Test reservation transaction ID
            $reservationId = generateReservationTransactionId();
            $testResults['reservation'] = [
                'id' => $reservationId,
                'valid' => validateTransactionId($reservationId),
                'parsed' => parseTransactionId($reservationId)
            ];
            
            // Test sophisticated transaction ID
            $sophisticatedId = generateTransactionId('John Doe', '2023-12-15 14:30:22', '2024-02-15', 'Deluxe Suite', 1);
            $testResults['sophisticated'] = [
                'id' => $sophisticatedId,
                'valid' => validateTransactionId($sophisticatedId),
                'parsed' => parseTransactionId($sophisticatedId)
            ];
            
            echo json_encode([
                'success' => true,
                'test_results' => $testResults
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 