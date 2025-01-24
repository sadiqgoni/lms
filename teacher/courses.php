<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

try {
    // Get pending enrollment count for notification
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.teacher_id = ? AND e.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingCount = $stmt->fetch()['pending_count'];

    // Get all courses for this teacher
    $stmt = $pdo->prepare("
        SELECT c.*, 
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id AND status = 'pending') as pending_requests,
            (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
        FROM courses c
        WHERE c.teacher_id = ?
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = 'Error fetching courses';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses - Teacher Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li>
                    <a href="courses.php" class="active">
                        My Courses
                        <?php if ($pendingCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="create-course.php">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="page-header">
            <h1>My Courses</h1>
            <a href="create-course.php" class="button">Create New Course</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="no-courses">
                <h2>No Courses Created</h2>
                <p>Start by creating your first course!</p>
                <a href="create-course.php" class="button">Create Course</a>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <span class="status-badge status-<?php echo $course['status']; ?>">
                                <?php echo ucfirst($course['status']); ?>
                            </span>
                        </div>
                        
                        <p class="course-description">
                            <?php echo substr(htmlspecialchars($course['description']), 0, 150) . '...'; ?>
                        </p>

                        <div class="course-meta">
                            <span><i class="fas fa-users"></i> <?php echo $course['enrolled_students']; ?> students</span>
                            <span><i class="fas fa-book"></i> <?php echo $course['material_count']; ?> materials</span>
                            <?php if ($course['pending_requests'] > 0): ?>
                                <span class="pending-requests">
                                    <i class="fas fa-clock"></i> <?php echo $course['pending_requests']; ?> pending
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="course-actions">
                            <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="button">Edit Course</a>
                            <a href="manage-students.php?course_id=<?php echo $course['id']; ?>" class="button">
                                Manage Students
                                <?php if ($course['pending_requests'] > 0): ?>
                                    <span class="notification-badge"><?php echo $course['pending_requests']; ?></span>
                                <?php endif; ?>
                            </a>
                            <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button button-secondary">View Course</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
