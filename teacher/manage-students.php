<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

$courseId = $_GET['course_id'] ?? 0;
$error = '';
$success = '';
$course = null;
$enrolledStudents = [];
$availableStudents = [];

// Handle enrollment/unenrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['enroll_student'])) {
        $studentId = $_POST['student_id'];
        try {
            $stmt = $pdo->prepare("INSERT INTO enrollments (user_id, course_id, status) VALUES (?, ?, 'active')");
            $stmt->execute([$studentId, $courseId]);
            $success = 'Student enrolled successfully!';
        } catch(PDOException $e) {
            $error = 'Failed to enroll student. They might already be enrolled.';
        }
    } elseif (isset($_POST['unenroll_student'])) {
        $studentId = $_POST['student_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM enrollments WHERE user_id = ? AND course_id = ?");
            $stmt->execute([$studentId, $courseId]);
            $success = 'Student unenrolled successfully!';
        } catch(PDOException $e) {
            $error = 'Failed to unenroll student.';
        }
    }
}

// Fetch course details and verify ownership
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$courseId, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit();
    }

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

    // Fetch available students (not enrolled)
    $stmt = $pdo->prepare("
        SELECT u.*
        FROM users u
        WHERE u.role = 'student'
        AND u.id NOT IN (
            SELECT user_id FROM enrollments WHERE course_id = ?
        )
        ORDER BY u.name
    ");
    $stmt->execute([$courseId]);
    $availableStudents = $stmt->fetchAll();
} catch(PDOException $e) {
    $error = 'Error fetching course details';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">LMS System</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="courses.php">My Courses</a></li>
                <li><a href="create-course.php">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="manage-students-container">
            <div class="page-header">
                <h1>Manage Students: <?php echo htmlspecialchars($course['title']); ?></h1>
                <a href="view-course.php?id=<?php echo $courseId; ?>" class="button">Back to Course</a>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="students-management">
                <section class="enrolled-students">
                    <h2>Enrolled Students (<?php echo count($enrolledStudents); ?>)</h2>
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
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="unenroll_student" class="button button-danger"
                                                    onclick="return confirm('Are you sure you want to unenroll this student?')">
                                                Unenroll
                                            </button>
                                        </form>
                                        <a href="view-student-progress.php?course_id=<?php echo $courseId; ?>&student_id=<?php echo $student['id']; ?>" 
                                           class="button">View Progress</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="available-students">
                    <h2>Available Students (<?php echo count($availableStudents); ?>)</h2>
                    <?php if (empty($availableStudents)): ?>
                        <p class="no-items">No available students to enroll.</p>
                    <?php else: ?>
                        <div class="students-list">
                            <?php foreach ($availableStudents as $student): ?>
                                <div class="student-item">
                                    <div class="student-info">
                                        <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                                        <p><?php echo htmlspecialchars($student['email']); ?></p>
                                    </div>
                                    <div class="student-actions">
                                        <form method="POST">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <button type="submit" name="enroll_student" class="button">Enroll</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
