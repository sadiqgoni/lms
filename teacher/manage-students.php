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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li>
                    <a href="courses.php">
                        <i class="fas fa-book"></i> My Courses
                        <?php if ($pendingCount > 0): ?>
                            <span class="notification-badge"><?php echo $pendingCount; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="create-course.php"><i class="fas fa-plus-circle"></i> Create Course</a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <?php if ($error): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <div class="page-header">
            <div class="header-content">
                <h1><i class="fas fa-users"></i> Manage Students</h1>
                <p class="course-title"><?php echo htmlspecialchars($course['title']); ?></p>
            </div>
            <a href="courses.php" class="button button-secondary">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
        </div>

        <div class="student-management-grid">
            <!-- Pending Enrollment Requests -->
            <section class="student-section">
                <div class="section-header">
                    <h2><i class="fas fa-user-clock"></i> Pending Requests (<?php echo count($pendingStudents); ?>)</h2>
                </div>
                <?php if (empty($pendingStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending enrollment requests</p>
                    </div>
                <?php else: ?>
                    <div class="students-list">
                        <?php foreach ($pendingStudents as $student): ?>
                            <div class="student-card pending">
                                <div class="student-info">
                                    <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                                    <p class="request-date">
                                        <i class="fas fa-clock"></i> 
                                        Requested: <?php echo date('M d, Y', strtotime($student['enrolled_at'])); ?>
                                    </p>
                                </div>
                                <div class="student-actions">
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                        <button type="submit" name="action" value="approve" class="button button-success">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="action" value="reject" class="button button-danger">
                                            <i class="fas fa-times"></i> Reject
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
                <div class="section-header">
                    <h2><i class="fas fa-user-graduate"></i> Enrolled Students (<?php echo count($enrolledStudents); ?>)</h2>
                </div>
                <?php if (empty($enrolledStudents)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No students enrolled yet</p>
                    </div>
                <?php else: ?>
                    <div class="students-list">
                        <?php foreach ($enrolledStudents as $student): ?>
                            <div class="student-card enrolled">
                                <div class="student-info">
                                    <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?></p>
                                    <div class="progress-info">
                                        <div class="progress-bar">
                                            <?php 
                                                $progress = $student['total_materials'] > 0 
                                                    ? ($student['completed_materials'] / $student['total_materials']) * 100 
                                                    : 0;
                                            ?>
                                            <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                                        </div>
                                        <span class="progress-text">
                                            <?php echo $student['completed_materials']; ?>/<?php echo $student['total_materials']; ?> materials completed
                                        </span>
                                    </div>
                                </div>
                                <div class="student-meta">
                                    <span class="enrollment-date">
                                        <i class="fas fa-calendar-alt"></i>
                                        Enrolled: <?php echo date('M d, Y', strtotime($student['enrolled_at'])); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>
</body>
</html>
