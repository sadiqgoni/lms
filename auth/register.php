<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $reg_number = $_POST['reg_number'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password) || ($role === 'student' && empty($reg_number))) {
        $error = 'Please fill in all fields';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = 'Email already exists';
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, reg_number) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $role, $reg_number]);

                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['role'] = $role;
                $_SESSION['email'] = $email;

                // Redirect based on role
                switch($role) {
                    case 'admin':
                        header('Location: ../admin/dashboard.php');
                        break;
                    case 'teacher':
                        header('Location: ../teacher/dashboard.php');
                        break;
                    case 'student':
                        header('Location: ../student/dashboard.php');
                        break;
                    default:
                        header('Location: ../index.php');
                }
                exit();
            }
        } catch(PDOException $e) {
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <h1>FOC LMS</h1>
            <p>Create your account to get started.</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="name">Full Name</label>
                <input type="text" id="name" name="name" required 
                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>

            <?php if ($role !== 'teacher' && $role !== 'admin'): ?>
            <div class="form-group">
                <label for="reg_number">Registration Number</label>
                <input type="text" id="reg_number" name="reg_number" placeholder="e.g. CST/19/COM/00329"
                       value="<?php echo isset($_POST['reg_number']) ? htmlspecialchars($_POST['reg_number']) : ''; ?>">
            </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div class="form-group">
                <label for="role">I want to register as</label>
                <select id="role" name="role" required>
                    <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] == 'student') ? 'selected' : ''; ?>>Student</option>
                    <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] == 'teacher') ? 'selected' : ''; ?>>Teacher</option>
                </select>
            </div>

            <button type="submit">Create Account</button>
        </form>

        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
</body>
</html>
