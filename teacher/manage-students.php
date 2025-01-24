<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

$courseId = $_GET['course_id'] ?? 0;
$error = '';
$success = '';

try {
    // Verify the course belongs to this teacher
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$courseId, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit();
    }

    // Handle enrollment status updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $studentId = $_POST['student_id'] ?? 0;
        $action = $_POST['action'];

        if ($action === 'approve' || $action === 'reject') {
            $stmt = $pdo->prepare("
                UPDATE enrollments 
                SET status = ? 
                WHERE course_id = ? AND user_id = ?
            ");
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $stmt->execute([$status, $courseId, $studentId]);
            $success = "Student enrollment " . ($status === 'approved' ? 'approved' : 'rejected') . " successfully.";
        }
    }

    // Get pending enrollment requests
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, e.enrolled_at
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        WHERE e.course_id = ? AND e.status = 'pending'
        ORDER BY e.enrolled_at DESC
    ");
    $stmt->execute([$courseId]);
    $pendingStudents = $stmt->fetchAll();

    // Get enrolled (approved) students
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, e.enrolled_at,
            (SELECT COUNT(*) FROM materials WHERE course_id = ?) as total_materials,
            (SELECT COUNT(*) FROM materials m 
             JOIN student_progress sp ON m.id = sp.material_id 
             WHERE sp.user_id = u.id AND m.course_id = ? AND sp.status = 'completed') as completed_materials
        FROM users u
        JOIN enrollments e ON u.id = e.user_id
        WHERE e.course_id = ? AND e.status = 'approved'
        ORDER BY u.name
    ");
    $stmt->execute([$courseId, $courseId, $courseId]);
    $enrolledStudents = $stmt->fetchAll();

    // Get available students (not enrolled or rejected)
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email
        FROM users u
        WHERE u.role = 'student'
        AND u.id NOT IN (
            SELECT user_id FROM enrollments 
            WHERE course_id = ? AND status IN ('approved', 'pending')
        )
        ORDER BY u.name
    ");
    $stmt->execute([$courseId]);
    $availableStudents = $stmt->fetchAll();

    // Get notification count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending_count 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.teacher_id = ? AND e.status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingCount = $stmt->fetch()['pending_count'];

} catch(PDOException $e) {
    $error = 'Error managing students: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo htmlspecialchars($course['title'] ?? ''); ?></title>
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
        <div class="page-header">
            <h1>Manage Students - <?php echo htmlspecialchars($course['title'] ?? ''); ?></h1>
            <a href="courses.php" class="button">Back to Courses</a>
        </div>

        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- Pending Enrollment Requests -->
        <section class="student-section">
            <h2>Pending Enrollment Requests (<?php echo count($pendingStudents); ?>)</h2>
            <?php if (empty($pendingStudents)): ?>
                <p>No pending enrollment requests.</p>
            <?php else: ?>
                <div class="student-grid">
                    <?php foreach ($pendingStudents as $student): ?>
                        <div class="student-card">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                                <p class="enrollment-date">
                                    Requested: <?php echo date('M j, Y', strtotime($student['enrolled_at'])); ?>
                                </p>
                            </div>
                            <div class="student-actions">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <button type="submit" name="action" value="approve" class="button button-primary">
                                        Approve
                                    </button>
                                    <button type="submit" name="action" value="reject" class="button button-danger">
                                        Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Enrolled Students -->
        <section class="student-section">
            <h2>Enrolled Students (<?php echo count($enrolledStudents); ?>)</h2>
            <?php if (empty($enrolledStudents)): ?>
                <p>No students enrolled yet.</p>
            <?php else: ?>
                <div class="student-grid">
                    <?php foreach ($enrolledStudents as $student): ?>
                        <div class="student-card">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                                <p class="enrollment-date">
                                    Enrolled: <?php echo date('M j, Y', strtotime($student['enrolled_at'])); ?>
                                </p>
                                <?php if ($student['total_materials'] > 0): ?>
                                    <div class="progress-section">
                                        <?php 
                                            $progress = round(($student['completed_materials'] / $student['total_materials']) * 100);
                                        ?>
                                        <div class="progress-bar">
                                            <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="progress-text">
                                            <?php echo $progress; ?>% Complete
                                            (<?php echo $student['completed_materials']; ?>/<?php echo $student['total_materials']; ?> materials)
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Available Students -->
        <section class="student-section">
            <h2>Available Students (<?php echo count($availableStudents); ?>)</h2>
            <?php if (empty($availableStudents)): ?>
                <p>No available students to enroll.</p>
            <?php else: ?>
                <div class="student-grid">
                    <?php foreach ($availableStudents as $student): ?>
                        <div class="student-card">
                            <div class="student-info">
                                <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                <p><?php echo htmlspecialchars($student['email']); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
