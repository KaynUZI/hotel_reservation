<?php
require_once 'includes/auth.php';
$auth->requireStaff();
require_once 'config/database.php';

$conn = getDB();
$action = $_GET['action'] ?? 'new';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

// Fetch room types for dropdown
$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY name ASC")->fetchAll();

// Initialize form values
$room = [
    'room_number' => '',
    'room_type_id' => '',
    'floor_number' => '',
    'status' => 'available',
];

// If editing, fetch room data
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    $room = $stmt->fetch();
    if (!$room) {
        header('Location: rooms.php');
        exit();
    }
}

// Handle delete
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM rooms WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: rooms.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_number = sanitize($_POST['room_number'] ?? '');
    $room_type_id = intval($_POST['room_type_id'] ?? 0);
    $floor_number = intval($_POST['floor_number'] ?? 0);
    $status = $_POST['status'] ?? 'available';

    // Handle image upload
    $imageFileName = $room['image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $tmpName = $_FILES['image']['tmp_name'];
        $originalName = basename($_FILES['image']['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed)) {
            $newName = uniqid('room_', true) . '.' . $ext;
            $dest = __DIR__ . '/uploads/rooms/' . $newName;
            if (move_uploaded_file($tmpName, $dest)) {
                $imageFileName = $newName;
            }
        }
    }

    // Validation
    if ($room_number === '' || $room_type_id === 0 || $floor_number === 0) {
        $error = 'All fields are required.';
    } elseif (!in_array($status, ['available', 'occupied', 'maintenance', 'reserved'])) {
        $error = 'Invalid status.';
    } else {
        if ($action === 'edit' && $id) {
            // Update
            $stmt = $conn->prepare("UPDATE rooms SET room_number=?, room_type_id=?, floor_number=?, status=?, image=? WHERE id=?");
            $result = $stmt->execute([$room_number, $room_type_id, $floor_number, $status, $imageFileName, $id]);
            if ($result) {
                header('Location: rooms.php');
                exit();
            } else {
                $error = 'Failed to update room.';
            }
        } else {
            // Check for duplicate room number
            $stmt = $conn->prepare("SELECT id FROM rooms WHERE room_number = ?");
            $stmt->execute([$room_number]);
            if ($stmt->rowCount() > 0) {
                $error = 'Room number already exists.';
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type_id, floor_number, status, image) VALUES (?, ?, ?, ?, ?)");
                $result = $stmt->execute([$room_number, $room_type_id, $floor_number, $status, $imageFileName]);
                if ($result) {
                    header('Location: rooms.php');
                    exit();
                } else {
                    $error = 'Failed to add room.';
                }
            }
        }
    }
    // Repopulate form on error
    $room = [
        'room_number' => $room_number,
        'room_type_id' => $room_type_id,
        'floor_number' => $floor_number,
        'status' => $status,
        'image' => $imageFileName,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Room - Hotel Reservation System</title>
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
        .container {
            margin-top: 2rem;
            max-width: 600px;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            background: var(--secondary-color);
            border: 1px solid #444;
            color: var(--silver-color);
        }
        h3 {
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
        .btn-primary, .btn-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-secondary:hover {
            background: #d4b24c !important;
            color: #181818 !important;
        }
        .form-label {
            color: var(--accent-color);
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
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4">
            <i class="fas fa-bed me-2"></i>
            <?php echo $action === 'edit' ? 'Edit Room' : 'Add Room'; ?>
        </h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?> </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data" novalidate>
            <div class="mb-3">
                <label for="room_number" class="form-label">Room Number</label>
                <input type="text" class="form-control" id="room_number" name="room_number" value="<?php echo htmlspecialchars($room['room_number']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="room_type_id" class="form-label">Room Type</label>
                <select class="form-select" id="room_type_id" name="room_type_id" required>
                    <option value="">Select Room Type</option>
                    <?php foreach ($roomTypes as $type): ?>
                        <option value="<?php echo $type['id']; ?>" <?php if ($room['room_type_id'] == $type['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="floor_number" class="form-label">Floor Number</label>
                <input type="number" class="form-control" id="floor_number" name="floor_number" value="<?php echo htmlspecialchars($room['floor_number']); ?>" min="1" required>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status" required>
                    <option value="available" <?php if ($room['status'] === 'available') echo 'selected'; ?>>Available</option>
                    <option value="occupied" <?php if ($room['status'] === 'occupied') echo 'selected'; ?>>Occupied</option>
                    <option value="maintenance" <?php if ($room['status'] === 'maintenance') echo 'selected'; ?>>Maintenance</option>
                    <option value="reserved" <?php if ($room['status'] === 'reserved') echo 'selected'; ?>>Reserved</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Room Image</label>
                <input type="file" class="form-control" id="image" name="image" accept="image/*">
                <?php if (!empty($room['image'])): ?>
                    <div class="mt-2">
                        <img src="uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" alt="Room Image" style="max-width: 200px; border-radius: 8px;">
                    </div>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary btn-custom w-100">
                <?php echo $action === 'edit' ? 'Update Room' : 'Add Room'; ?>
            </button>
            <a href="rooms.php" class="btn btn-secondary btn-custom w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 