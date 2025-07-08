<?php
session_start();
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    // User login
    public function login($username, $password) {
        try {
            $query = "SELECT * FROM users WHERE username = :username OR email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $username);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch();
                if (password_verify($password, $user['password'])) {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['first_name'] = $user['first_name'];
                    $_SESSION['last_name'] = $user['last_name'];
                    $_SESSION['logged_in'] = true;
                    
                    return ['success' => true, 'message' => 'Login successful', 'user' => $user];
                } else {
                    return ['success' => false, 'message' => 'Invalid password'];
                }
            } else {
                return ['success' => false, 'message' => 'User not found'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // User registration
    public function register($userData) {
        try {
            // Validate input
            if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                return ['success' => false, 'message' => 'All fields are required'];
            }

            if (!validateEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email format'];
            }

            if (strlen($userData['password']) < 6) {
                return ['success' => false, 'message' => 'Password must be at least 6 characters'];
            }

            // Check if username or email already exists
            $query = "SELECT id FROM users WHERE username = :username OR email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }

            // Hash password
            $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

            // Insert new user
            $query = "INSERT INTO users (username, email, password, first_name, last_name, phone, role) 
                     VALUES (:username, :email, :password, :first_name, :last_name, :phone, :role)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':first_name', $userData['first_name']);
            $stmt->bindParam(':last_name', $userData['last_name']);
            $stmt->bindParam(':phone', $userData['phone']);
            $stmt->bindParam(':role', $userData['role']);

            if ($stmt->execute()) {
                return ['success' => true, 'message' => 'Registration successful'];
            } else {
                return ['success' => false, 'message' => 'Registration failed'];
            }
        } catch (PDOException $e) {
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }

    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }

    // Check if user is admin
    public function isAdmin() {
        return $this->isLoggedIn() && $_SESSION['role'] === 'admin';
    }

    // Check if user is staff
    public function isStaff() {
        return $this->isLoggedIn() && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'staff');
    }

    // Get current user data
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'first_name' => $_SESSION['first_name'],
                'last_name' => $_SESSION['last_name']
            ];
        }
        return null;
    }

    // Logout user
    public function logout() {
        session_unset();
        session_destroy();
        return ['success' => true, 'message' => 'Logout successful'];
    }

    // Require authentication
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    // Require admin access
    public function requireAdmin() {
        if (!$this->isAdmin()) {
            header('Location: index.php?error=access_denied');
            exit();
        }
    }

    // Require staff access
    public function requireStaff() {
        if (!$this->isStaff()) {
            header('Location: index.php?error=access_denied');
            exit();
        }
    }
}

// Initialize authentication
$auth = new Auth(getDB());
?> 