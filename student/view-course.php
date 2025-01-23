<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

$courseId = $_GET['id'] ?? 0;
$error = '';

try {
    // Get course details and enrollment status
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name, 
            COALESCE(e.status, 'not_enrolled') as enrollment_status,
            e.enrolled_at
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        LEFT JOIN enrollments e ON c.id = e.course_id AND e.user_id = ?
        WHERE c.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $courseId]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: available-courses.php');
        exit();
    }

    // Get course materials only if enrollment is approved
    $materials = [];
    if ($course['enrollment_status'] === 'approved') {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                COALESCE(sp.status, 'not_started') as progress_status,
                sp.last_accessed
            FROM materials m
            LEFT JOIN student_progress sp ON m.id = sp.material_id AND sp.user_id = ?
            WHERE m.course_id = ?
            ORDER BY m.order_index, m.created_at
        ");
        $stmt->execute([$_SESSION['user_id'], $courseId]);
        $materials = $stmt->fetchAll();

        // Calculate overall progress
        $totalMaterials = count($materials);
        $completedMaterials = 0;
        foreach ($materials as $material) {
            if ($material['progress_status'] === 'completed') {
                $completedMaterials++;
            }
        }
        $progress = $totalMaterials > 0 ? round(($completedMaterials / $totalMaterials) * 100) : 0;
    }

} catch(PDOException $e) {
    $error = 'Error loading course content';
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
            <div class="course-view">
                <div class="course-header">
                    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                    <span class="status-badge status-<?php echo $course['enrollment_status']; ?>">
                        <?php echo ucfirst($course['enrollment_status']); ?>
                    </span>
                </div>

                <div class="course-info">
                    <p class="course-teacher">Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                    <p class="course-description"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    
                    <?php if ($course['enrollment_status'] === 'approved'): ?>
                        <div class="progress-section">
                            <h3>Your Progress</h3>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $progress; ?>% Complete</span>
                        </div>

                        <div class="course-materials">
                            <h2>Course Materials</h2>
                            <?php if (empty($materials)): ?>
                                <p>No materials available yet.</p>
                            <?php else: ?>
                                <div class="materials-list">
                                    <?php foreach ($materials as $material): ?>
                                        <div class="material-item">
                                            <div class="material-info">
                                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                                <span class="material-type"><?php echo ucfirst($material['type']); ?></span>
                                            </div>
                                            
                                            <div class="material-content">
                                                <?php if ($material['type'] === 'video'): ?>
                                                    <div class="video-container">
                                                        <iframe src="<?php echo htmlspecialchars($material['content']); ?>" 
                                                                frameborder="0" allowfullscreen></iframe>
                                                    </div>
                                                <?php elseif ($material['type'] === 'document'): ?>
                                                    <a href="../uploads/materials/<?php echo htmlspecialchars($material['content']); ?>" 
                                                       class="button" target="_blank">View Document</a>
                                                <?php elseif ($material['type'] === 'link'): ?>
                                                    <a href="<?php echo htmlspecialchars($material['content']); ?>" 
                                                       class="button" target="_blank">Visit Link</a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="material-progress">
                                                <form method="POST">
                                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                                    <?php if ($material['progress_status'] === 'completed'): ?>
                                                        <button type="submit" name="status" value="not_started" class="button button-secondary">
                                                            Mark as Incomplete
                                                        </button>
                                                        <span class="completion-date">
                                                            Completed on <?php echo date('M d, Y', strtotime($material['last_accessed'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <button type="submit" name="status" value="completed" class="button">
                                                            Mark as Complete
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($course['enrollment_status'] === 'pending'): ?>
                        <div class="enrollment-pending">
                            <div class="pending-message">
                                <h3>Enrollment Request Pending</h3>
                                <p>Your enrollment request has been sent to the instructor.</p>
                                <p>You'll be able to access the course materials once your enrollment is approved.</p>
                                <p class="enrollment-date">
                                    Requested on: <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php elseif ($course['enrollment_status'] === 'rejected'): ?>
                        <div class="enrollment-rejected">
                            <div class="rejected-message">
                                <h3>Enrollment Request Rejected</h3>
                                <p>Unfortunately, your enrollment request was not approved.</p>
                                <p>Please contact the instructor for more information.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="course-actions">
                            <form method="POST" action="enroll.php">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="enroll" class="button button-primary">Enroll in Course</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
