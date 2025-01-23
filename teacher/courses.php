<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

// Get all courses for this teacher
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = 'Error fetching courses';
    $courses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - LMS System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="courses.php" class="active">My Courses</a></li>
                <li><a href="create-course.php">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="courses-container">
            <div class="courses-header">
                <h1>My Courses</h1>
                <a href="create-course.php" class="button">Create New Course</a>
            </div>

            <?php if (empty($courses)): ?>
                <div class="no-courses">
                    <p>You haven't created any courses yet.</p>
                    <a href="create-course.php" class="button">Create Your First Course</a>
                </div>
            <?php else: ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                                <span class="status-badge status-<?php echo $course['status']; ?>">
                                    <?php echo ucfirst($course['status']); ?>
                                </span>
                            </div>
                            
                            <div class="course-description">
                                <?php echo substr(htmlspecialchars($course['description']), 0, 150) . '...'; ?>
                            </div>

                            <div class="course-meta">
                                <span>Created: <?php echo date('M j, Y', strtotime($course['created_at'])); ?></span>
                                <?php
                                    // Get number of enrolled students
                                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ?");
                                    $stmt->execute([$course['id']]);
                                    $enrolledCount = $stmt->fetchColumn();
                                ?>
                                <span><?php echo $enrolledCount; ?> students enrolled</span>
                            </div>

                            <div class="course-actions">
                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="button">Edit Course</a>
                                <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button">View Course</a>
                                <?php if ($course['status'] !== 'archived'): ?>
                                    <a href="manage-students.php?course_id=<?php echo $course['id']; ?>" class="button">Manage Students</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
