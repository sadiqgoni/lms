<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

$error = '';
$success = '';
$stmta = $pdo->prepare("
SELECT COUNT(*) as pending_count 
FROM enrollments e 
JOIN courses c ON e.course_id = c.id 
WHERE c.teacher_id = ? AND e.status = 'pending'
");
$stmta->execute([$_SESSION['user_id']]);
$pendingCount = $stmta->fetch()['pending_count'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    if (empty($title) || empty($description)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO courses (title, description, teacher_id, status) VALUES (?, ?, ?, ?)");
            $stmt->execute([$title, $description, $_SESSION['user_id'], $status]);
            $success = 'Course created successfully!';
            
            // Redirect to edit course page to add materials
            $courseId = $pdo->lastInsertId();
            header("Location: edit-course.php?id=" . $courseId);
            exit();
        } catch(PDOException $e) {
            $error = 'Failed to create course. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Course - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li>  <a href="courses.php">
                        My Courses
                        <?php if ($pendingCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a></li>
                <li><a href="create-course.php" class="active">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="create-course-container">
            <h1>Create New Course</h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="course-form">
                <div class="form-group">
                    <label for="title">Course Title*</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                </div>

                <div class="form-group">
                    <label for="description">Course Description*</label>
                    <textarea id="description" name="description" rows="5" required><?php 
                        echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                    ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Course Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo (isset($_POST['status']) && $_POST['status'] == 'draft') ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo (isset($_POST['status']) && $_POST['status'] == 'published') ? 'selected' : ''; ?>>Published</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Create Course</button>
                    <a href="dashboard.php" class="button button-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
