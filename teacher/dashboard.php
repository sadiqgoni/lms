<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

// Get teacher's courses
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $courses = $stmt->fetchAll();


    $stmta = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.teacher_id = ? AND e.status = 'pending'
    ");
    $stmta->execute([$_SESSION['user_id']]);
    $pendingCount = $stmta->fetch()['pending_count'];



} catch (PDOException $e) {
    $error = 'Error fetching courses';
}

// Get teacher info
$teacher = getUserInfo($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - LMS System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>

<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li>
                    <a href="courses.php">
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
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($teacher['name']); ?>!</h1>
            <p>Manage your courses and track student progress from your dashboard.</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Your Courses</h3>
                <p class="stat-number"><?php echo count($courses); ?></p>
            </div>
            <!-- Add more stat cards as needed -->
        </div>

        <section class="recent-courses">
            <h2>Your Recent Courses</h2>
            <?php if (empty($courses)): ?>
                <p class="no-courses">You haven't created any courses yet. <a href="create-course.php">Create your first
                        course</a></p>
            <?php else: ?>
                <div class="course-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <p><?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?></p>
                            <div class="course-status">
                                Status: <span
                                    class="status-<?php echo $course['status']; ?>"><?php echo ucfirst($course['status']); ?></span>
                            </div>
                            <div class="course-actions">
                                <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="button">Edit</a>
                                <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button">View</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <a href="create-course.php" class="action-button">
                    <span class="icon">📚</span>
                    Create New Course
                </a>
                <a href="quizzes.php" class="action-button">
                    <span class="icon">📝</span>
                    Manage Quizzes
                </a>
                <a href="students.php" class="action-button">
                    <span class="icon">👥</span>
                    View Students
                </a>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>

</html>