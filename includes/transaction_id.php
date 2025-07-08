<?php
/**
 * Transaction ID Management System
 * Provides functions for generating, validating, and managing transaction IDs
 */

/**
 * Generate a sophisticated transaction ID based on reservation details
 * Format: [GUEST][MONTH][DAY][CHECKIN_MMYY]-[ROOM_TYPE][COUNT]
 * Example: JOFEB102302-FIC00001
 */
function generateTransactionId($guestName, $createdAt, $checkInDate, $roomType, $reservationCount) {
    // GUEST - first 2 letters of guest last name (or first name if last name not available)
    $namePart = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $guestName), 0, 2));
    
    // MONTH - month of reservation creation
    $monthPart = strtoupper(date('M', strtotime($createdAt)));
    
    // DAY - day of reservation creation
    $dayPart = date('d', strtotime($createdAt));
    
    // CHECKIN_MMYY - month and year of reservation schedule (check-in)
    $checkInMonth = date('m', strtotime($checkInDate));
    $checkInYear = date('y', strtotime($checkInDate));
    $monthYearPart = $checkInMonth . $checkInYear;
    
    // ROOM_TYPE - first 3 letters of room type
    $roomTypePart = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $roomType), 0, 3));
    
    // COUNT - reservation count, padded to 5 digits
    $countPart = str_pad($reservationCount, 5, '0', STR_PAD_LEFT);
    
    return "$namePart$monthPart$dayPart$monthYearPart-$roomTypePart$countPart";
}

/**
 * Generate a simple payment transaction ID
 * Format: PAY[YYYYMMDD][HHMMSS][RANDOM]
 * Example: PAY202312151430221234
 */
function generatePaymentTransactionId() {
    $timestamp = date('YmdHis');
    $random = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "PAY{$timestamp}{$random}";
}

/**
 * Generate a unique reservation transaction ID
 * Format: RES[YYYYMMDD][HHMMSS][RANDOM]
 * Example: RES202312151430221234
 */
function generateReservationTransactionId() {
    $timestamp = date('YmdHis');
    $random = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
    return "RES{$timestamp}{$random}";
}

/**
 * Validate transaction ID format
 */
function validateTransactionId($transactionId) {
    if (empty($transactionId)) {
        return false;
    }
    
    // Check for payment transaction ID format
    if (preg_match('/^PAY\d{12}\d{4}$/', $transactionId)) {
        return true;
    }
    
    // Check for reservation transaction ID format
    if (preg_match('/^RES\d{12}\d{4}$/', $transactionId)) {
        return true;
    }
    
    // Check for sophisticated format (GUESTMONTHDAYMMYY-ROOMTYPE00000)
    if (preg_match('/^[A-Z]{2}[A-Z]{3}\d{2}\d{4}-[A-Z]{3}\d{5}$/', $transactionId)) {
        return true;
    }
    
    return false;
}

/**
 * Extract information from transaction ID
 */
function parseTransactionId($transactionId) {
    if (!validateTransactionId($transactionId)) {
        return null;
    }
    
    $info = [
        'type' => 'unknown',
        'timestamp' => null,
        'guest' => null,
        'room_type' => null,
        'count' => null
    ];
    
    // Parse payment transaction ID
    if (preg_match('/^PAY(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{4})$/', $transactionId, $matches)) {
        $info['type'] = 'payment';
        $info['timestamp'] = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
        $info['random'] = $matches[7];
    }
    
    // Parse reservation transaction ID
    elseif (preg_match('/^RES(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{4})$/', $transactionId, $matches)) {
        $info['type'] = 'reservation';
        $info['timestamp'] = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
        $info['random'] = $matches[7];
    }
    
    // Parse sophisticated format
    elseif (preg_match('/^([A-Z]{2})([A-Z]{3})(\d{2})(\d{4})-([A-Z]{3})(\d{5})$/', $transactionId, $matches)) {
        $info['type'] = 'sophisticated';
        $info['guest'] = $matches[1];
        $info['month'] = $matches[2];
        $info['day'] = $matches[3];
        $info['checkin_mmyy'] = $matches[4];
        $info['room_type'] = $matches[5];
        $info['count'] = intval($matches[6]);
    }
    
    return $info;
}

/**
 * Get transaction ID for a reservation
 */
function getReservationTransactionId($reservationId, $conn) {
    $stmt = $conn->prepare("
        SELECT r.id, r.created_at, r.check_in_date, g.first_name, g.last_name, rt.name as room_type_name,
               (SELECT COUNT(*) FROM reservations WHERE guest_id = r.guest_id) as reservation_count
        FROM reservations r
        JOIN guests g ON r.guest_id = g.id
        JOIN rooms rm ON r.room_id = rm.id
        JOIN room_types rt ON rm.room_type_id = rt.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        return null;
    }
    
    $guestName = $reservation['first_name'] . ' ' . $reservation['last_name'];
    return generateTransactionId(
        $guestName,
        $reservation['created_at'],
        $reservation['check_in_date'],
        $reservation['room_type_name'],
        $reservation['reservation_count']
    );
}

/**
 * Check if transaction ID already exists in database
 */
function isTransactionIdUnique($transactionId, $conn, $excludeId = null) {
    $sql = "SELECT COUNT(*) as count FROM payments WHERE transaction_id = ?";
    $params = [$transactionId];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['count'] == 0;
}

/**
 * Generate a unique transaction ID that doesn't exist in database
 */
function generateUniqueTransactionId($conn, $type = 'payment') {
    $maxAttempts = 10;
    $attempts = 0;
    
    do {
        if ($type === 'payment') {
            $transactionId = generatePaymentTransactionId();
        } else {
            $transactionId = generateReservationTransactionId();
        }
        
        $attempts++;
    } while (!isTransactionIdUnique($transactionId, $conn) && $attempts < $maxAttempts);
    
    return $transactionId;
}
?> 