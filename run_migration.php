<?php
require_once 'config/database.php';

$conn = getDB();

echo "<h2>Database Migration: Adding Cancellation Fields</h2>";

try {
    // Add cancellation_reason field
    echo "<p>Adding cancellation_reason field...</p>";
    $conn->exec("ALTER TABLE reservations ADD COLUMN cancellation_reason VARCHAR(255) NULL AFTER special_requests");
    echo "<p style='color: green;'>✓ cancellation_reason field added successfully</p>";
    
    // Add cancelled_by field
    echo "<p>Adding cancelled_by field...</p>";
    $conn->exec("ALTER TABLE reservations ADD COLUMN cancelled_by INT NULL AFTER cancellation_reason");
    echo "<p style='color: green;'>✓ cancelled_by field added successfully</p>";
    
    // Add foreign key constraint
    echo "<p>Adding foreign key constraint...</p>";
    $conn->exec("ALTER TABLE reservations ADD FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL");
    echo "<p style='color: green;'>✓ Foreign key constraint added successfully</p>";
    
    // Add indexes for better performance
    echo "<p>Adding indexes...</p>";
    $conn->exec("CREATE INDEX idx_reservations_status ON reservations(status)");
    $conn->exec("CREATE INDEX idx_reservations_cancelled_at ON reservations(updated_at)");
    $conn->exec("CREATE INDEX idx_reservations_cancellation_reason ON reservations(cancellation_reason)");
    echo "<p style='color: green;'>✓ Indexes added successfully</p>";
    
    // Update existing cancelled reservations
    echo "<p>Updating existing cancelled reservations...</p>";
    $stmt = $conn->prepare("UPDATE reservations SET cancellation_reason = 'No reason provided' WHERE status = 'cancelled' AND cancellation_reason IS NULL");
    $stmt->execute();
    echo "<p style='color: green;'>✓ Existing cancelled reservations updated</p>";
    
    echo "<h3 style='color: green;'>Migration completed successfully!</h3>";
    echo "<p>The cancellation tracking system is now ready to use.</p>";
    echo "<p><a href='cancellation_reports.php'>View Cancellation Reports</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    
    // Check if fields already exist
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>Some fields may already exist. The migration might have been run before.</p>";
        echo "<p><a href='cancellation_reports.php'>View Cancellation Reports</a></p>";
    }
}
?> 