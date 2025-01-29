<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

// Get enrolled courses
try {
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name,
            (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
        FROM courses c
        JOIN enrollments e ON c.id = e.course_id
        JOIN users u ON c.teacher_id = u.id
        WHERE e.user_id = ? AND c.status = 'published'
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $enrolledCourses = $stmt->fetchAll();

    // Get available courses (not enrolled)
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.status = 'published' 
        AND c.id NOT IN (
            SELECT course_id FROM enrollments WHERE user_id = ?
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $availableCourses = $stmt->fetchAll();

} catch(PDOException $e) {
    $error = 'Error fetching courses';
}

// Get student info
$student = getUserInfo($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="my-courses.php">My Courses</a></li>
                <li><a href="available-courses.php">Available Courses</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($student['name']); ?>!</h1>
            <p>Track your learning progress and explore new courses.</p>
            <p class="reg-number">Registration Number: <?php echo htmlspecialchars($student['reg_number']); ?></p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Enrolled Courses</h3>
                <p class="stat-number"><?php echo count($enrolledCourses); ?></p>
            </div>
            <div class="stat-card">
                <h3>Available Courses</h3>
                <p class="stat-number"><?php echo count($availableCourses); ?></p>
            </div>
            <div class="stat-card">
                <h3>Completed Courses</h3>
                <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE user_id = ? AND status = 'completed'");
                    $stmt->execute([$_SESSION['user_id']]);
                    $completedCount = $stmt->fetchColumn();
                ?>
                <p class="stat-number"><?php echo $completedCount; ?></p>
            </div>
        </div>

        <section class="my-courses">
            <h2>My Enrolled Courses</h2>
            <?php if (empty($enrolledCourses)): ?>
                <p class="no-courses">You haven't enrolled in any courses yet. 
                    <a href="available-courses.php">Browse available courses</a>
                </p>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($enrolledCourses as $course): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-teacher">Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                            <p class="course-description">
                                <?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?>
                            </p>
                            <div class="course-meta">
                                <span><?php echo $course['material_count']; ?> materials</span>
                                <?php
                                    $stmt = $pdo->prepare("
                                        SELECT status FROM enrollments 
                                        WHERE user_id = ? AND course_id = ?
                                    ");
                                    $stmt->execute([$_SESSION['user_id'], $course['id']]);
                                    $status = $stmt->fetchColumn();
                                ?>
                                <span class="status-badge status-<?php echo $status; ?>">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                            <div class="course-actions">
                                <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button">Continue Learning</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="available-courses">
            <h2>Available Courses</h2>
            <?php if (empty($availableCourses)): ?>
                <p class="no-courses">No new courses available at the moment.</p>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach (array_slice($availableCourses, 0, 3) as $course): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p class="course-teacher">Teacher: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                            <p class="course-description">
                                <?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?>
                            </p>
                            <div class="course-actions">
                                <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="button">Enroll Now</a>
                                <a href="course-details.php?id=<?php echo $course['id']; ?>" class="button button-secondary">Learn More</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($availableCourses) > 3): ?>
                    <div class="view-more">
                        <a href="available-courses.php" class="button">View All Available Courses</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
