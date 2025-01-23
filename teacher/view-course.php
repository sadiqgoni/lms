<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

$courseId = $_GET['id'] ?? 0;
$error = '';
$course = null;
$materials = [];
$enrolledStudents = [];

// Fetch course details
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$courseId, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    $stmta = $pdo->prepare("
    SELECT COUNT(*) as pending_count 
    FROM enrollments e 
    JOIN courses c ON e.course_id = c.id 
    WHERE c.teacher_id = ? AND e.status = 'pending'
");
$stmta->execute([$_SESSION['user_id']]);
$pendingCount = $stmta->fetch()['pending_count'];


    if (!$course) {
        header('Location: courses.php');
        exit();
    }

    // Fetch course materials
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE course_id = ? ORDER BY created_at DESC");
    $stmt->execute([$courseId]);
    $materials = $stmt->fetchAll();

    // Fetch enrolled students
    $stmt = $pdo->prepare("
        SELECT u.*, e.status as enrollment_status, e.enrolled_at
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        WHERE e.course_id = ? AND u.role = 'student'
        ORDER BY u.name
    ");
    $stmt->execute([$courseId]);
    $enrolledStudents = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = 'Error fetching course details';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title']); ?> - LMS System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li>  <a href="courses.php">
                        My Courses
                        <?php if ($pendingCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a></li>
                <li><a href="create-course.php">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="view-course-container">
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php else: ?>
                <div class="course-header">
                    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                    <div class="course-actions">
                        <a href="edit-course.php?id=<?php echo $courseId; ?>" class="button">Edit Course</a>
                        <a href="manage-students.php?course_id=<?php echo $courseId; ?>" class="button">Manage Students</a>
                        <a href="add-material.php?course_id=<?php echo $courseId; ?>" class="button">Add Material</a>
                    </div>
                </div>

                <div class="course-status">
                    Status: <span class="status-badge status-<?php echo $course['status']; ?>">
                        <?php echo ucfirst($course['status']); ?>
                    </span>
                </div>

                <div class="course-description">
                    <h2>Course Description</h2>
                    <p><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                </div>

                <div class="course-stats">
                    <div class="stat-card">
                        <h3>Enrolled Students</h3>
                        <p class="stat-number"><?php echo count($enrolledStudents); ?></p>
                    </div>
                    <div class="stat-card">
                        <h3>Course Materials</h3>
                        <p class="stat-number"><?php echo count($materials); ?></p>
                    </div>
                </div>

                <section class="course-materials">
                    <h2>Course Materials</h2>
                    <?php if (empty($materials)): ?>
                        <p class="no-items">No materials added yet.</p>
                    <?php else: ?>
                        <div class="materials-list">
                            <?php foreach ($materials as $material): ?>
                                <div class="material-item">
                                    <div class="material-info">
                                        <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                        <p>Type: <?php echo ucfirst($material['type']); ?></p>
                                    </div>
                                    <div class="material-actions">
                                        <a href="edit-material.php?id=<?php echo $material['id']; ?>" class="button">Edit</a>
                                        <a href="delete-material.php?id=<?php echo $material['id']; ?>" 
                                           class="button button-danger"
                                           onclick="return confirm('Are you sure you want to delete this material?')">Delete</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="enrolled-students">
                    <h2>Enrolled Students</h2>
                    <?php if (empty($enrolledStudents)): ?>
                        <p class="no-items">No students enrolled yet.</p>
                    <?php else: ?>
                        <div class="students-list">
                            <?php foreach ($enrolledStudents as $student): ?>
                                <div class="student-item">
                                    <div class="student-info">
                                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                    <div class="enrollment-info">
                                        <p>Status: <?php echo ucfirst($student['enrollment_status']); ?></p>
                                        <p>Enrolled: <?php echo date('M j, Y', strtotime($student['enrolled_at'])); ?></p>
                                    </div>
                                    <div class="student-actions">
                                        <a href="view-student-progress.php?course_id=<?php echo $courseId; ?>&student_id=<?php echo $student['id']; ?>" 
                                           class="button">View Progress</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
