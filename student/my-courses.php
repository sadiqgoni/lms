<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

try {
    // Get all enrolled courses with their status
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name, e.status as enrollment_status, e.enrolled_at,
            (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
            (SELECT COUNT(*) FROM materials m 
             JOIN student_progress sp ON m.id = sp.material_id 
             WHERE sp.user_id = ? AND m.course_id = c.id AND sp.status = 'completed') as completed_materials
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        JOIN users u ON c.teacher_id = u.id
        WHERE e.user_id = ?
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $enrolledCourses = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = 'Error fetching enrolled courses';
}

// Helper function to calculate progress percentage
function calculateProgress($completed, $total) {
    if ($total == 0) return 0;
    return round(($completed / $total) * 100);
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
                <li><a href="my-courses.php" class="active">My Courses</a></li>
                <li><a href="available-courses.php">Available Courses</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="page-header">
            <h1>My Courses</h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($enrolledCourses)): ?>
            <div class="no-courses">
                <h2>No Enrolled Courses</h2>
                <p>You haven't enrolled in any courses yet.</p>
                <a href="available-courses.php" class="button">Browse Available Courses</a>
            </div>
        <?php else: ?>
            <div class="enrolled-courses">
                <?php foreach ($enrolledCourses as $course): ?>
                    <div class="course-card">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <span class="status-badge status-<?php echo $course['enrollment_status']; ?>">
                                <?php echo ucfirst($course['enrollment_status']); ?>
                            </span>
                        </div>

                        <p class="course-teacher">Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                        
                        <?php if ($course['enrollment_status'] == 'approved'): ?>
                            <div class="progress-section">
                                <?php 
                                    $progress = calculateProgress($course['completed_materials'], $course['material_count']);
                                ?>
                                <div class="progress-bar">
                                    <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                                </div>
                                <span class="progress-text"><?php echo $progress; ?>% Complete</span>
                            </div>

                            <div class="course-stats">
                                <div class="stat">
                                    <span class="stat-label">Materials</span>
                                    <span class="stat-value"><?php echo $course['completed_materials']; ?>/<?php echo $course['material_count']; ?></span>
                                </div>
                                <div class="stat">
                                    <span class="stat-label">Duration</span>
                                    <span class="stat-value"><?php echo $course['duration']; ?> weeks</span>
                                </div>
                            </div>

                            <div class="course-actions">
                                <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button">Continue Learning</a>
                            </div>
                        <?php elseif ($course['enrollment_status'] == 'pending'): ?>
                            <div class="pending-message">
                                <p>Your enrollment request is pending approval from the instructor.</p>
                                <p class="enrollment-date">Requested on: <?php echo date('M d, Y', strtotime($course['enrolled_at'])); ?></p>
                            </div>
                        <?php elseif ($course['enrollment_status'] == 'rejected'): ?>
                            <div class="rejected-message">
                                <p>Your enrollment request was not approved.</p>
                                <p>Please contact the instructor for more information.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
