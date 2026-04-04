<?php
require_once __DIR__ . '/../config/database.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function login($email, $password) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM \"User\" WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['usertype'] = $user['usertype'];
                $_SESSION['admin_type'] = $user['admin_type'] ?? null;
                
                // Debug: Log the admin_type
                error_log("Login - User Type: " . $user['usertype'] . ", Admin Type: " . ($user['admin_type'] ?? 'none'));
                
                // Get additional info based on usertype
                if ($user['usertype'] === 'student') {
                    $stmt = $this->pdo->prepare("SELECT * FROM Student WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $student = $stmt->fetch();
                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['fullname'] = $student['first_name'] . ' ' . $student['last_name'];
                } elseif ($user['usertype'] === 'coach') {
                    $stmt = $this->pdo->prepare("SELECT * FROM Coach WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    $coach = $stmt->fetch();
                    $_SESSION['coach_id'] = $coach['id'];
                    $_SESSION['fullname'] = $coach['first_name'] . ' ' . $coach['last_name'];
                }
                
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            return false;
        }
    }
    
    public function register($data) {
        try {
            $this->pdo->beginTransaction();
            
            // Check if email exists
            $stmt = $this->pdo->prepare("SELECT id FROM \"User\" WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email already exists'];
            }
            
            // Create user with admin_type if applicable
            $adminType = $data['admin_type'] ?? null;
            $stmt = $this->pdo->prepare("INSERT INTO \"User\" (email, password, usertype, admin_type) VALUES (?, ?, ?, ?) RETURNING id");
            $stmt->execute([$data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['usertype'], $adminType]);
            $userId = $stmt->fetchColumn();
            
            // Create profile based on usertype
            if ($data['usertype'] === 'student') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO Student (first_name, last_name, course, yr_level, college, user_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['course'],
                    $data['yr_level'],
                    $data['college'],
                    $userId
                ]);
            } elseif ($data['usertype'] === 'coach') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO Coach (first_name, last_name, specialization, user_id) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $data['first_name'],
                    $data['last_name'],
                    $data['specialization'] ?? null,
                    $userId
                ]);
            }
            
            $this->pdo->commit();
            return ['success' => true, 'message' => 'Registration successful'];
            
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log($e->getMessage());
            return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            header('Location: ../login.php');
            exit();
        }
    }
    
    public function requireRole($role) {
        if (!isset($_SESSION['usertype']) || $_SESSION['usertype'] !== $role) {
            header('Location: ../landing.php');
            exit();
        }
    }
    
    public function requireAdminType($type) {
        // Make sure admin_type exists and matches
        if (!isset($_SESSION['admin_type']) || $_SESSION['admin_type'] !== $type) {
            error_log("Admin type mismatch. Expected: $type, Got: " . ($_SESSION['admin_type'] ?? 'none'));
            header('Location: ../landing.php');
            exit();
        }
    }
    
    public function getAdminType() {
        return $_SESSION['admin_type'] ?? null;
    }
}
?>