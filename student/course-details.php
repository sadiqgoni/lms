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
</head>
<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
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
                    <?php if (!$isEnrolled): ?>
                        <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="button">Enroll Now</a>
                    <?php endif; ?>
                </div>

                <div class="course-info">
                    <div class="info-card">
                        <h3>Course Overview</h3>
                        <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <h4>Instructor</h4>
                            <p><?php echo htmlspecialchars($course['teacher_name']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Duration</h4>
                            <p><?php echo htmlspecialchars($course['duration']); ?> weeks</p>
                        </div>
                        <div class="info-item">
                            <h4>Materials</h4>
                            <p><?php echo $course['material_count']; ?> learning resources</p>
                        </div>
                        <div class="info-item">
                            <h4>Level</h4>
                            <p><?php echo ucfirst(htmlspecialchars($course['level'])); ?></p>
                        </div>
                    </div>

                    <?php if ($course['prerequisites']): ?>
                    <div class="info-card">
                        <h3>Prerequisites</h3>
                        <p><?php echo nl2br(htmlspecialchars($course['prerequisites'])); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="info-card">
                        <h3>What You'll Learn</h3>
                        <ul class="learning-outcomes">
                            <?php
                            $outcomes = explode("\n", $course['learning_outcomes']);
                            foreach ($outcomes as $outcome):
                                if (trim($outcome)):
                            ?>
                                <li><?php echo htmlspecialchars(trim($outcome)); ?></li>
                            <?php
                                endif;
                            endforeach;
                            ?>
                        </ul>
                    </div>
                </div>

                <?php if (!$isEnrolled): ?>
                <div class="enrollment-cta">
                    <h2>Ready to Start Learning?</h2>
                    <p>Enroll now to access all course materials and start your learning journey.</p>
                    <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="button">Enroll in This Course</a>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
