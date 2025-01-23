<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Learning Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
            <ul>
                <?php if (!$isLoggedIn): ?>
                    <li><a href="auth/login.php">Login</a></li>
                    <li><a href="auth/register.php">Register</a></li>
                <?php else: ?>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="auth/logout.php">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h1>Welcome to Our Learning Management System</h1>
            <p>Empower your learning journey with our comprehensive platform</p>
            <?php if (!$isLoggedIn): ?>
                <a href="auth/register.php" class="cta-button">Get Started</a>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
