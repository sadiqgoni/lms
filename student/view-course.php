<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

$courseId = $_GET['id'] ?? 0;
$error = '';
$success = '';

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

        // Handle material progress updates
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['material_id'], $_POST['status'])) {
            $materialId = $_POST['material_id'];
            $status = $_POST['status'];
            
            // Verify the material belongs to this course
            $validMaterial = false;
            foreach ($materials as $material) {
                if ($material['id'] == $materialId) {
                    $validMaterial = true;
                    break;
                }
            }
            
            if ($validMaterial) {
                if ($status === 'completed') {
                    // Mark as completed
                    $stmt = $pdo->prepare("
                        INSERT INTO student_progress (user_id, material_id, status, last_accessed)
                        VALUES (?, ?, 'completed', NOW())
                        ON DUPLICATE KEY UPDATE status = 'completed', last_accessed = NOW()
                    ");
                } else {
                    // Mark as incomplete
                    $stmt = $pdo->prepare("
                        INSERT INTO student_progress (user_id, material_id, status, last_accessed)
                        VALUES (?, ?, 'not_started', NOW())
                        ON DUPLICATE KEY UPDATE status = 'not_started', last_accessed = NOW()
                    ");
                }
                $stmt->execute([$_SESSION['user_id'], $materialId]);
                $success = 'Progress updated successfully!';
                
                // Refresh the page to show updated status
                header("Location: view-course.php?id=" . $courseId);
                exit();
            }
        }
    }
} catch(PDOException $e) {
    $error = 'Error loading course content: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($course['title'] ?? 'Course'); ?> - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="my-courses.php"><i class="fas fa-book"></i> My Courses</a></li>
                <li><a href="available-courses.php"><i class="fas fa-list"></i> Available Courses</a></li>
                <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (!$error): ?>
            <div class="course-view">
                <div class="course-header">
                    <h1><?php echo htmlspecialchars($course['title']); ?></h1>
                    <span class="status-badge status-<?php echo $course['enrollment_status']; ?>">
                        <?php echo ucfirst($course['enrollment_status']); ?>
                    </span>
                </div>

                <div class="course-info">
                    <p class="course-teacher">
                        <i class="fas fa-chalkboard-teacher"></i>
                        Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?>
                    </p>
                    <p class="course-description"><?php echo nl2br(htmlspecialchars($course['description'])); ?></p>
                    
                    <?php if ($course['enrollment_status'] === 'approved'): ?>
                        <div class="progress-section">
                            <h3><i class="fas fa-chart-line"></i> Your Progress</h3>
                            <div class="progress-bar">
                                <div class="progress" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo $progress; ?>% Complete</span>
                        </div>

                        <div class="course-materials">
                            <h2><i class="fas fa-book-reader"></i> Course Materials</h2>
                            <?php if (empty($materials)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-folder-open"></i>
                                    <p>No materials available yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="materials-list">
                                    <?php foreach ($materials as $material): ?>
                                        <div class="material-item">
                                            <div class="material-info">
                                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                                <span class="material-type">
                                                    <i class="fas fa-<?php echo $material['type'] === 'video' ? 'video' : ($material['type'] === 'document' ? 'file-alt' : 'link'); ?>"></i>
                                                    <?php echo ucfirst($material['type']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="material-content">
                                                <?php if ($material['type'] === 'video'): ?>
                                                    <div class="video-container">
                                                        <iframe src="<?php echo htmlspecialchars($material['content']); ?>" 
                                                                frameborder="0" allowfullscreen></iframe>
                                                    </div>
                                                <?php elseif ($material['type'] === 'document'): ?>
                                                    <a href="../uploads/materials/<?php echo htmlspecialchars($material['content']); ?>" 
                                                       class="button" target="_blank">
                                                       <i class="fas fa-file-download"></i> View Document
                                                    </a>
                                                <?php elseif ($material['type'] === 'link'): ?>
                                                    <a href="<?php echo htmlspecialchars($material['content']); ?>" 
                                                       class="button" target="_blank">
                                                       <i class="fas fa-external-link-alt"></i> Visit Link
                                                    </a>
                                                <?php endif; ?>
                                            </div>

                                            <div class="material-progress">
                                                <form method="POST">
                                                    <input type="hidden" name="material_id" value="<?php echo $material['id']; ?>">
                                                    <?php if ($material['progress_status'] === 'completed'): ?>
                                                        <button type="submit" name="status" value="not_started" class="button button-secondary">
                                                            <i class="fas fa-undo"></i> Mark as Incomplete
                                                        </button>
                                                        <span class="completion-date">
                                                            <i class="fas fa-calendar-check"></i>
                                                            Completed on <?php echo date('M d, Y', strtotime($material['last_accessed'])); ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <button type="submit" name="status" value="completed" class="button button-success">
                                                            <i class="fas fa-check"></i> Mark as Complete
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
                                <i class="fas fa-clock"></i>
                                <h3>Enrollment Request Pending</h3>
                                <p>Your enrollment request has been sent to the instructor.</p>
                                <p>You'll be able to access the course materials once your enrollment is approved.</p>
                                <p class="enrollment-date">
                                    <i class="fas fa-calendar-alt"></i>
                                    Requested on: <?php echo date('M j, Y', strtotime($course['enrolled_at'])); ?>
                                </p>
                            </div>
                        </div>
                    <?php elseif ($course['enrollment_status'] === 'rejected'): ?>
                        <div class="enrollment-rejected">
                            <div class="rejected-message">
                                <i class="fas fa-times-circle"></i>
                                <h3>Enrollment Request Rejected</h3>
                                <p>Unfortunately, your enrollment request was not approved.</p>
                                <p>Please contact the instructor for more information.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="course-actions">
                            <form method="POST" action="enroll.php">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" name="enroll" class="button button-primary">
                                    <i class="fas fa-user-plus"></i> Enroll in Course
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>
</body>
</html>
