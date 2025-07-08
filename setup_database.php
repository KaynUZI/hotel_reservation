<?php
// Database Setup Script
// This script will create the hotel_reservation database and all required tables

echo "<h2>Hotel Reservation System - Database Setup</h2>";

try {
    // Connect to MySQL without specifying a database
    $pdo = new PDO("mysql:host=localhost", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✓ Connected to MySQL successfully</p>";
    
    // Create database if it doesn't exist
    echo "<p>Creating database 'hotel_reservation'...</p>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS hotel_reservation");
    echo "<p style='color: green;'>✓ Database 'hotel_reservation' created successfully</p>";
    
    // Select the database
    $pdo->exec("USE hotel_reservation");
    echo "<p style='color: green;'>✓ Database selected successfully</p>";
    
    // Create tables
    echo "<p>Creating tables...</p>";
    
    // Users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'staff', 'guest') DEFAULT 'guest',
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            phone VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Users table created</p>";
    
    // Room types table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS room_types (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            base_price DECIMAL(10,2) NOT NULL,
            capacity INT NOT NULL,
            amenities TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Room types table created</p>";
    
    // Rooms table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rooms (
            id INT PRIMARY KEY AUTO_INCREMENT,
            room_number VARCHAR(20) UNIQUE NOT NULL,
            room_type_id INT NOT NULL,
            floor_number INT NOT NULL,
            status ENUM('available', 'occupied', 'maintenance', 'reserved') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (room_type_id) REFERENCES room_types(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Rooms table created</p>";
    
    // Guests table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS guests (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20) NOT NULL,
            address TEXT,
            id_type ENUM('passport', 'driver_license', 'national_id', 'other') DEFAULT 'passport',
            id_number VARCHAR(50),
            date_of_birth DATE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "<p style='color: green;'>✓ Guests table created</p>";
    
    // Reservations table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reservations (
            id INT PRIMARY KEY AUTO_INCREMENT,
            guest_id INT NOT NULL,
            room_id INT NOT NULL,
            check_in_date DATE NOT NULL,
            check_out_date DATE NOT NULL,
            adults INT DEFAULT 1,
            children INT DEFAULT 0,
            total_amount DECIMAL(10,2) NOT NULL,
            status ENUM('pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled') DEFAULT 'pending',
            special_requests TEXT,
            cancellation_reason VARCHAR(255) NULL,
            cancelled_by INT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
            FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL
        )
    ");
    echo "<p style='color: green;'>✓ Reservations table created</p>";
    
    // Payments table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS payments (
            id INT PRIMARY KEY AUTO_INCREMENT,
            reservation_id INT NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'online') NOT NULL,
            payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            transaction_id VARCHAR(100),
            payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
        )
    ");
    echo "<p style='color: green;'>✓ Payments table created</p>";
    
    // Insert default admin user
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO users (username, email, password, role, first_name, last_name, phone) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['admin', 'admin@hotel.com', $adminPassword, 'admin', 'System', 'Administrator', '+1234567890']);
    echo "<p style='color: green;'>✓ Default admin user created</p>";
    
    // Insert sample room types
    $roomTypes = [
        ['Standard Single', 'Comfortable single room with basic amenities', 80.00, 1, 'WiFi, TV, Air Conditioning, Private Bathroom'],
        ['Standard Double', 'Spacious double room perfect for couples', 120.00, 2, 'WiFi, TV, Air Conditioning, Private Bathroom, Mini Fridge'],
        ['Deluxe Suite', 'Luxury suite with premium amenities', 200.00, 4, 'WiFi, Smart TV, Air Conditioning, Private Bathroom, Mini Bar, Balcony, Room Service'],
        ['Executive Room', 'Business-friendly room with work space', 150.00, 2, 'WiFi, TV, Air Conditioning, Private Bathroom, Work Desk, Coffee Maker'],
        ['Family Room', 'Large room suitable for families', 180.00, 6, 'WiFi, TV, Air Conditioning, Private Bathroom, Kitchenette, Extra Beds']
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO room_types (name, description, base_price, capacity, amenities) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($roomTypes as $roomType) {
        $stmt->execute($roomType);
    }
    echo "<p style='color: green;'>✓ Sample room types created</p>";
    
    // Insert sample rooms
    $rooms = [
        ['101', 1, 1, 'available'],
        ['102', 1, 1, 'available'],
        ['201', 2, 2, 'available'],
        ['202', 2, 2, 'available'],
        ['301', 3, 3, 'available'],
        ['302', 3, 3, 'available'],
        ['401', 4, 4, 'available'],
        ['402', 4, 4, 'available'],
        ['501', 5, 5, 'available'],
        ['502', 5, 5, 'available']
    ];
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO rooms (room_number, room_type_id, floor_number, status) 
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($rooms as $room) {
        $stmt->execute($room);
    }
    echo "<p style='color: green;'>✓ Sample rooms created</p>";
    
    // Create indexes for better performance
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_status ON reservations(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_cancelled_at ON reservations(updated_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_reservations_cancellation_reason ON reservations(cancellation_reason)");
    echo "<p style='color: green;'>✓ Performance indexes created</p>";
    
    echo "<h3 style='color: green;'>🎉 Database setup completed successfully!</h3>";
    echo "<p><strong>Default Admin Login:</strong></p>";
    echo "<ul>";
    echo "<li><strong>Username:</strong> admin</li>";
    echo "<li><strong>Password:</strong> admin123</li>";
    echo "<li><strong>Email:</strong> admin@hotel.com</li>";
    echo "</ul>";
    echo "<p><a href='index.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Homepage</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please make sure:</p>";
    echo "<ul>";
    echo "<li>XAMPP is running</li>";
    echo "<li>MySQL service is started</li>";
    echo "<li>You have proper permissions to create databases</li>";
    echo "</ul>";
}
?> 