<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$currentUser = $auth->getCurrentUser();
$isAdmin = $auth->isAdmin();
$isStaff = $auth->isStaff();

// Handle filters
$room_type = $_GET['room_type'] ?? '';
$floor = $_GET['floor'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$guests = $_GET['guests'] ?? '';

// Build query conditions
$whereConditions = [];
$params = [];

if (!empty($room_type)) {
    $whereConditions[] = "rt.id = ?";
    $params[] = $room_type;
}

if (!empty($floor)) {
    $whereConditions[] = "rm.floor_number = ?";
    $params[] = $floor;
}

if (!empty($price_min)) {
    $whereConditions[] = "rt.base_price >= ?";
    $params[] = $price_min;
}

if (!empty($price_max)) {
    $whereConditions[] = "rt.base_price <= ?";
    $params[] = $price_max;
}

if (!empty($guests)) {
    $whereConditions[] = "rt.capacity >= ?";
    $params[] = $guests;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get rooms with detailed information
$sql = "
    SELECT rm.*, rt.name as room_type_name, rt.description, rt.base_price, rt.capacity, rt.amenities,
           (SELECT COUNT(*) FROM reservations r WHERE r.room_id = rm.id AND r.status NOT IN ('cancelled', 'checked_out') AND r.check_in_date <= CURDATE() AND r.check_out_date > CURDATE()) as current_occupancy
    FROM rooms rm
    JOIN room_types rt ON rm.room_type_id = rt.id
    $whereClause
    ORDER BY rm.room_number ASC
";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$rooms = $stmt->fetchAll();

// Get filter options
$roomTypes = $conn->query("SELECT * FROM room_types ORDER BY name")->fetchAll();
$floors = $conn->query("SELECT DISTINCT floor_number FROM rooms ORDER BY floor_number")->fetchAll();

// Get statistics
$totalRooms = count($rooms);
$availableRooms = count(array_filter($rooms, function($room) { return $room['status'] === 'available'; }));
$occupiedRooms = count(array_filter($rooms, function($room) { return $room['status'] === 'occupied'; }));
$maintenanceRooms = count(array_filter($rooms, function($room) { return $room['status'] === 'maintenance'; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Rooms - Hotel Reservation System</title>
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

        .card, .room-card {
            background: var(--card-bg);
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            border: 1px solid var(--card-border);
            color: var(--text-color);
            margin-bottom: 2rem;
        }
        .card h5, .card h4, .room-card h5, .room-card h6 { color: var(--accent-color); }

        .room-image {
            height: 200px;
            background: var(--secondary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--accent-color);
            font-size: 3rem;
        }
        .room-status .badge {
            background: var(--accent-color) !important;
            color: #181818 !important;
        }
        .stats-card {
            background: var(--secondary-color);
            color: var(--accent-color);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            border: 1px solid var(--card-border);
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: var(--accent-color);
        }
        .stats-label {
            font-size: 0.9rem;
            color: #fff;
            opacity: 0.9;
        }
        .filter-card {
            background: var(--silver-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
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
        .btn-primary, .btn-success, .btn-info, .btn-warning, .btn-secondary {
            background: var(--accent-color) !important;
            color: #181818 !important;
            border: none !important;
        }
        .btn-primary:hover, .btn-success:hover, .btn-info:hover, .btn-warning:hover, .btn-secondary:hover {
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
        .amenities-list li:before {
            color: var(--accent-color);
            font-weight: bold;
        }
        .price-tag {
            background: var(--accent-color);
            color: #181818;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            display: inline-block;
        }
        .info-box {
            background: var(--secondary-color);
            color: var(--accent-color);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container main-content">
        <div class="row">
            <div class="col-12">
                <div class="info-box">
                    <h4><i class="fas fa-bed me-2"></i>Available Rooms</h4>
                    <p class="mb-0">Explore our comfortable accommodations and find the perfect room for your stay.</p>
                </div>

                <!-- Statistics -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $totalRooms; ?></div>
                            <div class="stats-label">Total Rooms</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $availableRooms; ?></div>
                            <div class="stats-label">Available</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $occupiedRooms; ?></div>
                            <div class="stats-label">Occupied</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number"><?php echo $maintenanceRooms; ?></div>
                            <div class="stats-label">Maintenance</div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="filter-card">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="room_type" class="form-label">Room Type</label>
                            <select class="form-select" id="room_type" name="room_type">
                                <option value="">All Types</option>
                                <?php foreach ($roomTypes as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" 
                                            <?php echo $room_type == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="floor" class="form-label">Floor</label>
                            <select class="form-select" id="floor" name="floor">
                                <option value="">All Floors</option>
                                <?php foreach ($floors as $floorOption): ?>
                                    <option value="<?php echo $floorOption['floor_number']; ?>" 
                                            <?php echo $floor == $floorOption['floor_number'] ? 'selected' : ''; ?>>
                                        Floor <?php echo $floorOption['floor_number']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="price_min" class="form-label">Min Price</label>
                            <input type="number" class="form-control" id="price_min" name="price_min" 
                                   value="<?php echo htmlspecialchars($price_min); ?>" placeholder="$0">
                        </div>
                        <div class="col-md-2">
                            <label for="price_max" class="form-label">Max Price</label>
                            <input type="number" class="form-control" id="price_max" name="price_max" 
                                   value="<?php echo htmlspecialchars($price_max); ?>" placeholder="$1000">
                        </div>
                        <div class="col-md-2">
                            <label for="guests" class="form-label">Guests</label>
                            <input type="number" class="form-control" id="guests" name="guests" 
                                   value="<?php echo htmlspecialchars($guests); ?>" placeholder="1" min="1" max="10">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary btn-custom me-2">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                            <a href="view_rooms.php" class="btn btn-secondary btn-custom">
                                <i class="fas fa-times me-2"></i>Clear
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Rooms Grid -->
                <div class="row">
                    <?php if (empty($rooms)): ?>
                        <div class="col-12">
                            <div class="card p-4 text-center">
                                <i class="fas fa-bed fa-3x text-muted mb-3"></i>
                                <h5>No rooms found</h5>
                                <p class="text-muted">Try adjusting your filters to see more results.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rooms as $room): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card room-card">
                                    <div class="position-relative">
                                        <div class="room-image">
                                            <?php if (!empty($room['image'])): ?>
                                                <img src="uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" alt="Room Image" style="width: 100%; height: 200px; object-fit: cover; border-radius: 12px;">
                                            <?php else: ?>
                                                <i class="fas fa-bed"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="room-status">
                                            <?php
                                            $status = $room['status'];
                                            $badge = 'secondary';
                                            if ($status === 'available') $badge = 'success';
                                            elseif ($status === 'occupied') $badge = 'danger';
                                            elseif ($status === 'maintenance') $badge = 'warning';
                                            elseif ($status === 'reserved') $badge = 'info';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            Room <?php echo htmlspecialchars($room['room_number']); ?>
                                            <small class="text-muted">(Floor <?php echo $room['floor_number']; ?>)</small>
                                        </h5>
                                        <h6 class="card-subtitle mb-2 text-muted">
                                            <?php echo htmlspecialchars($room['room_type_name']); ?>
                                        </h6>
                                        
                                        <div class="mb-3">
                                            <span class="price-tag">
                                                $<?php echo number_format($room['base_price'], 2); ?> / night
                                            </span>
                                        </div>
                                        
                                        <p class="card-text">
                                            <?php echo htmlspecialchars($room['description']); ?>
                                        </p>
                                        
                                        <div class="mb-3">
                                            <strong>Capacity:</strong> 
                                            <span class="badge bg-info">
                                                <i class="fas fa-users me-1"></i>
                                                <?php echo $room['capacity']; ?> guests
                                            </span>
                                        </div>
                                        
                                        <?php if ($room['amenities']): ?>
                                            <div class="mb-3">
                                                <strong>Amenities:</strong>
                                                <ul class="amenities-list">
                                                    <?php 
                                                    $amenities = explode(',', $room['amenities']);
                                                    foreach (array_slice($amenities, 0, 3) as $amenity): 
                                                    ?>
                                                        <li><?php echo htmlspecialchars(trim($amenity)); ?></li>
                                                    <?php endforeach; ?>
                                                    <?php if (count($amenities) > 3): ?>
                                                        <li><em>+<?php echo count($amenities) - 3; ?> more</em></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="d-grid gap-2">
                                            <?php if ($room['status'] === 'available'): ?>
                                                <a href="reservation_form.php?room_id=<?php echo $room['id']; ?>" 
                                                   class="btn btn-success btn-custom">
                                                    <i class="fas fa-calendar-plus me-2"></i>Book Now
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-custom" disabled>
                                                    <i class="fas fa-times me-2"></i>Not Available
                                                </button>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-outline-info btn-custom" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#roomModal<?php echo $room['id']; ?>">
                                                <i class="fas fa-info-circle me-2"></i>View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Room Details Modal -->
                            <div class="modal fade" id="roomModal<?php echo $room['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">
                                                <i class="fas fa-bed me-2"></i>
                                                Room <?php echo htmlspecialchars($room['room_number']); ?> Details
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <?php if (!empty($room['image'])): ?>
                                                        <img src="uploads/rooms/<?php echo htmlspecialchars($room['image']); ?>" alt="Room Image" style="width: 100%; max-width: 300px; border-radius: 12px; margin-bottom: 1rem;">
                                                    <?php else: ?>
                                                        <span style="font-size: 3rem; color: var(--accent-color);"><i class="fas fa-bed"></i></span>
                                                    <?php endif; ?>
                                                    <h6>Room Information</h6>
                                                    <table class="table table-sm">
                                                        <tr>
                                                            <td><strong>Room Number:</strong></td>
                                                            <td><?php echo htmlspecialchars($room['room_number']); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Type:</strong></td>
                                                            <td><?php echo htmlspecialchars($room['room_type_name']); ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Floor:</strong></td>
                                                            <td><?php echo $room['floor_number']; ?></td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Capacity:</strong></td>
                                                            <td><?php echo $room['capacity']; ?> guests</td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Status:</strong></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $badge; ?>">
                                                                    <?php echo ucfirst($status); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <td><strong>Price:</strong></td>
                                                            <td><span class="price-tag">$<?php echo number_format($room['base_price'], 2); ?> / night</span></td>
                                                        </tr>
                                                    </table>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6>Description</h6>
                                                    <p><?php echo htmlspecialchars($room['description']); ?></p>
                                                    
                                                    <?php if ($room['amenities']): ?>
                                                        <h6>Amenities</h6>
                                                        <ul class="amenities-list">
                                                            <?php foreach ($amenities as $amenity): ?>
                                                                <li><?php echo htmlspecialchars(trim($amenity)); ?></li>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            <?php if ($room['status'] === 'available'): ?>
                                                <a href="reservation_form.php?room_id=<?php echo $room['id']; ?>" 
                                                   class="btn btn-success btn-custom">
                                                    <i class="fas fa-calendar-plus me-2"></i>Book This Room
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 