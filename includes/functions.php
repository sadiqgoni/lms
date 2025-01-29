<?php

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /auth/login.php');
        exit();
    }
}

// Redirect if not authorized for role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /index.php');
        exit();
    }
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Generate random string
function generateRandomString($length = 10) {
    return bin2hex(random_bytes($length));
}

// Format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Get user info
function getUserInfo($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, email, role, reg_number FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return false;
    }
}

// Get course info
function getCourseInfo($pdo, $courseId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->execute([$courseId]);
        return $stmt->fetch();
    } catch(PDOException $e) {
        return false;
    }
}

// Check if student is enrolled in course
function isEnrolled($pdo, $userId, $courseId) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM enrollments WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        return $stmt->rowCount() > 0;
    } catch(PDOException $e) {
        return false;
    }
}
?>
