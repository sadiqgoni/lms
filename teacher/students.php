<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

// Get all students enrolled in any of the teacher's courses
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, 
            (SELECT COUNT(*) FROM enrollments e2 
             JOIN courses c2 ON e2.course_id = c2.id 
             WHERE e2.user_id = u.id AND c2.teacher_id = ?) as enrolled_courses
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        JOIN courses c ON e.course_id = c.id
        WHERE c.teacher_id = ? AND u.role = 'student'
        ORDER BY u.name
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $students = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = 'Error fetching students';
    $students = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="courses.php">My Courses</a></li>
                <li><a href="students.php" class="active">Students</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="students-container">
            <h1>My Students</h1>

            <?php if (empty($students)): ?>
                <div class="no-students">
                    <p>No students are enrolled in your courses yet.</p>
                    <a href="courses.php" class="button">View My Courses</a>
                </div>
            <?php else: ?>
                <div class="students-grid">
                    <?php foreach ($students as $student): ?>
                        <div class="student-card">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                            </div>
                            
                            <div class="student-stats">
                                <div class="stat">
                                    <span class="stat-label">Enrolled Courses:</span>
                                    <span class="stat-value"><?php echo $student['enrolled_courses']; ?></span>
                                </div>
                                
                                <?php
                                    // Get completed courses count
                                    $stmt = $pdo->prepare("
                                        SELECT COUNT(*) FROM enrollments e
                                        JOIN courses c ON e.course_id = c.id
                                        WHERE e.user_id = ? AND c.teacher_id = ? AND e.status = 'completed'
                                    ");
                                    $stmt->execute([$student['id'], $_SESSION['user_id']]);
                                    $completedCourses = $stmt->fetchColumn();
                                ?>
                                <div class="stat">
                                    <span class="stat-label">Completed Courses:</span>
                                    <span class="stat-value"><?php echo $completedCourses; ?></span>
                                </div>
                            </div>

                            <div class="student-actions">
                                <a href="view-student.php?id=<?php echo $student['id']; ?>" class="button">View Progress</a>
                                <a href="student-courses.php?id=<?php echo $student['id']; ?>" class="button">View Enrolled Courses</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
