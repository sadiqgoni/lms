<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

$courseId = $_GET['id'] ?? 0;
$error = '';
$course = null;
$isEnrolled = false;

try {
    // Check if student is already enrolled
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$_SESSION['user_id'], $courseId]);
    $isEnrolled = $stmt->rowCount() > 0;

    // Get course details with teacher info and material count
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name, u.email as teacher_email,
            (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ? AND c.status = 'published'
    ");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: available-courses.php');
        exit();
    }

} catch(PDOException $e) {
    $error = 'Error fetching course details';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - Course Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="my-courses.php">My Courses</a></li>
                <li><a href="available-courses.php">Available Courses</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php else: ?>
            <div class="course-details">
                <div class="course-header">
                    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                    <?php if ($isEnrolled): ?>
                        <div class="enrollment-status enrolled">
                            <i class="fas fa-check-circle"></i> You are enrolled in this course
                        </div>
                    <?php endif; ?>
                </div>

                <div class="course-content">
                    <div class="course-description">
                        <div class="description-card">
                            <h3><i class="fas fa-info-circle"></i> About This Course</h3>
                            <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                        </div>
                    </div>

                    <div class="course-meta-grid">
                        <div class="meta-card">
                            <i class="fas fa-chalkboard-teacher"></i>
                            <h4>Lecturer</h4>
                            <p><?php echo htmlspecialchars($course['teacher_name']); ?></p>
                        </div>
                        <div class="meta-card">
                            <i class="fas fa-book"></i>
                            <h4>Materials</h4>
                            <p><?php echo $course['material_count']; ?> learning resources</p>
                        </div>
                        <div class="meta-card">
                            <i class="fas fa-layer-group"></i>
                            <h4>Level</h4>
                            <p><?php echo ucfirst(htmlspecialchars($course['level'])); ?></p>
                        </div>
                    </div>
                </div>

                <?php if (!$isEnrolled): ?>
                <div class="enrollment-cta">
                    <div class="cta-content">
                        <h2><i class="fas fa-graduation-cap"></i> Ready to Start Learning?</h2>
                        <p>Enroll now to access all course materials and start your learning journey.</p>
                        <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="button">
                            <i class="fas fa-sign-in-alt"></i> Enroll in This Course
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
