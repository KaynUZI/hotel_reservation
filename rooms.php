<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();

// Fetch all rooms with room type info
$stmt = $conn->prepare("SELECT rooms.*, room_types.name AS room_type_name FROM rooms JOIN room_types ON rooms.room_type_id = room_types.id ORDER BY rooms.room_number ASC");
$stmt->execute();
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rooms - Hotel Reservation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #181818; /* Black */
            --secondary-color: #23272b; /* Dark Gray */
            --accent-color: #bfa046; /* Gold */
            --silver-color: #e5e5e5; /* Silver */
            --card-bg: #fff; /* White for the table container */
            --card-border: #444;
            --text-color: #181818; /* Dark text for white background */
            --muted-text: #b0b0b0;
        }
        body {
            background: linear-gradient(135deg, #23272b 0%, #181818 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-color);
        }
        .container {
            margin-top: 2rem;
        }
        .dashboard-card, .rooms-card {
            background: var(--card-bg);
            border-radius: 18px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.25);
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        h2, .rooms-card h2 {
            color: var(--accent-color);
        }
        .table {
            color: var(--text-color);
            background: #fff;
        }
        .table thead {
            background: #fff;
            color: var(--accent-color);
        }
        .table tbody tr {
            border-bottom: 1px solid var(--card-border);
        }
        .table-hover tbody tr:hover {
            background: #f5f5f5;
        }
        .badge {
            font-size: 0.95em;
            border-radius: 8px;
            padding: 0.5em 1em;
        }
        .badge.bg-success, .badge.bg-warning, .badge.bg-danger, .badge.bg-info, .badge.bg-primary, .badge.bg-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-danger {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-danger:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container rooms-card">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-bed me-2"></i>Rooms</h2>
        <a href="room_form.php?action=new" class="btn btn-primary btn-custom">
            <i class="fas fa-plus me-1"></i> Add Room
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Room #</th>
                    <th>Type</th>
                    <th>Floor</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rooms as $room): ?>
                <tr>
                    <td>
                        <?php if (!empty($room['image'])): ?>
                            <img src="uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" alt="Room Image" style="width: 60px; height: 40px; object-fit: cover; border-radius: 6px;">
                        <?php else: ?>
                            <span style="font-size: 2rem; color: var(--accent-color);"><i class="fas fa-bed"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                    <td><?php echo htmlspecialchars($room['room_type_name']); ?></td>
                    <td><?php echo htmlspecialchars($room['floor_number']); ?></td>
                    <td>
                        <?php
                        $status = $room['status'];
                        $badge = 'secondary';
                        if ($status === 'available') $badge = 'success';
                        elseif ($status === 'occupied') $badge = 'danger';
                        elseif ($status === 'maintenance') $badge = 'warning';
                        elseif ($status === 'reserved') $badge = 'info';
                        ?>
                        <span class="badge bg-<?php echo $badge; ?>"> <?php echo ucfirst($status); ?> </span>
                    </td>
                    <td>
                        <a href="room_form.php?action=edit&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-info btn-custom me-1">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="room_form.php?action=delete&id=<?php echo $room['id']; ?>" class="btn btn-sm btn-danger btn-custom" onclick="return confirm('Are you sure you want to delete this room?');">
                            <i class="fas fa-trash"></i> Delete
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 