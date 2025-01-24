<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

$courseId = $_GET['course_id'] ?? 0;
$error = '';
$success = '';

try {
    // Check if already enrolled
    $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$_SESSION['user_id'], $courseId]);
    
    if ($stmt->rowCount() > 0) {
        header('Location: my-courses.php');
        exit();
    }

    // Get course details
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name 
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

    // Process enrollment request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $stmt = $pdo->prepare("
            INSERT INTO enrollments (user_id, course_id, enrolled_at, status)
            VALUES (?, ?, NOW(), 'pending')
        ");
        $stmt->execute([$_SESSION['user_id'], $courseId]);

        $success = 'Your enrollment request has been sent to the teacher for approval.';
    }

} catch(PDOException $e) {
    $error = 'Error processing enrollment request. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enroll in Course - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
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
        <div class="enrollment-container">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message">
                    <?php echo $success; ?>
                    <p>You will be notified when the teacher approves your enrollment.</p>
                    <div class="button-group">
                        <a href="my-courses.php" class="button">View My Courses</a>
                        <a href="available-courses.php" class="button button-secondary">Browse More Courses</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="enrollment-confirmation">
                    <h1>Request Enrollment</h1>
                    <div class="course-summary">
                        <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                        <p class="course-teacher">Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                        <p class="course-duration">Duration: <?php echo htmlspecialchars($course['duration']); ?> weeks</p>
                        
                        <div class="course-description">
                            <h3>Course Description</h3>
                            <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                        </div>

                        <?php if ($course['prerequisites']): ?>
                        <div class="prerequisites">
                            <h3>Prerequisites</h3>
                            <p><?php echo nl2br(htmlspecialchars($course['prerequisites'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="enrollment-actions">
                            <form method="POST" action="">
                                <p class="confirmation-text">
                                    By requesting enrollment, your request will be sent to the course instructor for approval.
                                    You will be notified once your enrollment is approved.
                                </p>
                                <div class="button-group">
                                    <button type="submit" class="button">Request Enrollment</button>
                                    <a href="course-details.php?id=<?php echo $courseId; ?>" class="button button-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
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
