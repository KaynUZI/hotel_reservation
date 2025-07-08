<?php
require_once 'includes/auth.php';
$auth->requireAuth();
require_once 'config/database.php';

$conn = getDB();
$action = $_GET['action'] ?? 'new';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';

// Allow staff/admin, or allow users if coming from reservation
$fromReservation = isset($_GET['from_reservation']) && $_GET['from_reservation'] == 1;
$isEdit = ($action === 'edit' && $id);
if ($auth->isStaff()) {
    // Staff/admin can always access
} elseif ($fromReservation && !$isEdit) {
    // User can add their own guest profile
} elseif ($isEdit) {
    // User can only edit their own guest profile
    $stmt = $conn->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$id]);
    $guest = $stmt->fetch();
    if (!$guest || $guest['user_id'] != $auth->getCurrentUser()['id']) {
        header('Location: index.php?error=access_denied');
        exit();
    }
} else {
    header('Location: index.php?error=access_denied');
    exit();
}

// Initialize form values
$guest = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'address' => '',
    'id_type' => 'passport',
    'id_number' => '',
    'date_of_birth' => '',
];

// If editing, fetch guest data
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM guests WHERE id = ?");
    $stmt->execute([$id]);
    $guest = $stmt->fetch();
    // Only allow user to edit their own guest profile
    if (!$guest || (!$auth->isStaff() && $guest['user_id'] != $auth->getCurrentUser()['id'])) {
        header('Location: index.php');
        exit();
    }
}

// Handle delete
if ($action === 'delete' && $id) {
    $stmt = $conn->prepare("DELETE FROM guests WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: guests.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $guest['first_name'] = sanitize($_POST['first_name'] ?? '');
    $guest['last_name'] = sanitize($_POST['last_name'] ?? '');
    $guest['email'] = sanitize($_POST['email'] ?? '');
    $guest['phone'] = sanitize($_POST['phone'] ?? '');
    $guest['address'] = sanitize($_POST['address'] ?? '');
    $guest['id_type'] = $_POST['id_type'] ?? 'passport';
    $guest['id_number'] = sanitize($_POST['id_number'] ?? '');
    $guest['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    $userId = $auth->isStaff() ? (isset($_POST['user_id']) ? intval($_POST['user_id']) : null) : $auth->getCurrentUser()['id'];

    // Validation
    if (empty($guest['first_name']) || empty($guest['last_name']) || empty($guest['email']) || empty($guest['phone'])) {
        $error = 'First name, last name, email, and phone are required.';
    } elseif (!validateEmail($guest['email'])) {
        $error = 'Invalid email format.';
    } else {
        if ($action === 'edit' && $id) {
            // Check for duplicate email (excluding current guest)
            $stmt = $conn->prepare("SELECT id FROM guests WHERE email = ? AND id != ?");
            $stmt->execute([$guest['email'], $id]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already exists.';
            } else {
                // Update
                $stmt = $conn->prepare("UPDATE guests SET first_name=?, last_name=?, email=?, phone=?, address=?, id_type=?, id_number=?, date_of_birth=? WHERE id=?");
                $result = $stmt->execute([$guest['first_name'], $guest['last_name'], $guest['email'], $guest['phone'], $guest['address'], $guest['id_type'], $guest['id_number'], $guest['date_of_birth'], $id]);
                if ($result) {
                    if ($fromReservation) {
                        header('Location: reservation_form.php?action=new');
                    } else {
                        header('Location: guests.php');
                    }
                    exit();
                } else {
                    $error = 'Failed to update guest.';
                }
            }
        } else {
            // Check for duplicate email
            $stmt = $conn->prepare("SELECT id FROM guests WHERE email = ?");
            $stmt->execute([$guest['email']]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already exists.';
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO guests (first_name, last_name, email, phone, address, id_type, id_number, date_of_birth, user_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$guest['first_name'], $guest['last_name'], $guest['email'], $guest['phone'], $guest['address'], $guest['id_type'], $guest['id_number'], $guest['date_of_birth'], $userId]);
                if ($result) {
                    if ($fromReservation) {
                        header('Location: reservation_form.php?action=new');
                    } else {
                        header('Location: guests.php');
                    }
                    exit();
                } else {
                    $error = 'Failed to add guest.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ucfirst($action); ?> Guest - Hotel Reservation System</title>
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
        .container {
            margin-top: 2rem;
            max-width: 800px;
        }
        .card {
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15);
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-color);
        }
        .card h3 {
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
        .alert-danger {
            background: var(--secondary-color);
            color: var(--accent-color);
            border: 1px solid var(--accent-color);
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="card p-4">
        <h3 class="mb-4">
            <i class="fas fa-user me-2"></i>
            <?php echo $action === 'edit' ? 'Edit Guest' : 'Add Guest'; ?>
        </h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"> <?php echo htmlspecialchars($error); ?> </div>
        <?php endif; ?>
        <form method="POST" novalidate>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">First Name</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($guest['first_name']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">Last Name</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($guest['last_name']); ?>" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($guest['email']); ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($guest['phone']); ?>" required>
                </div>
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($guest['address']); ?></textarea>
            </div>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="id_type" class="form-label">ID Type</label>
                    <select class="form-select" id="id_type" name="id_type" required>
                        <option value="passport" <?php if ($guest['id_type'] === 'passport') echo 'selected'; ?>>Passport</option>
                        <option value="driver_license" <?php if ($guest['id_type'] === 'driver_license') echo 'selected'; ?>>Driver License</option>
                        <option value="national_id" <?php if ($guest['id_type'] === 'national_id') echo 'selected'; ?>>National ID</option>
                        <option value="other" <?php if ($guest['id_type'] === 'other') echo 'selected'; ?>>Other</option>
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="id_number" class="form-label">ID Number</label>
                    <input type="text" class="form-control" id="id_number" name="id_number" value="<?php echo htmlspecialchars($guest['id_number']); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label for="date_of_birth" class="form-label">Date of Birth</label>
                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($guest['date_of_birth']); ?>">
            </div>
            <button type="submit" class="btn btn-primary btn-custom w-100">
                <?php echo $action === 'edit' ? 'Update Guest' : 'Add Guest'; ?>
            </button>
            <a href="guests.php" class="btn btn-secondary btn-custom w-100 mt-2">Cancel</a>
        </form>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/js/all.min.js"></script>
</body>
</html> 