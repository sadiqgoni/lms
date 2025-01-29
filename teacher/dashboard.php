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

    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as total_students 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        WHERE c.teacher_id = ? AND e.status = 'approved'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $totalStudents = $stmt->fetch()['total_students'];

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
    <title>Teacher Dashboard - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
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
        <div class="welcome-section">
            <h1>Welcome, <?php echo htmlspecialchars($teacher['name']); ?>!</h1>
            <p>Manage your courses and track student progress from your dashboard.</p>
        </div>

        <div class="dashboard-stats">
            <div class="stat-card">
                <i class="fas fa-book-open"></i>
                <h3>Your Courses</h3>
                <p class="stat-number"><?php echo count($courses); ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-user-graduate"></i>
                <h3>Pending Requests</h3>
                <p class="stat-number"><?php echo $pendingCount; ?></p>
            </div>
            <div class="stat-card">
                <i class="fas fa-users"></i>
                <h3>Total Students</h3>
                <p class="stat-number"><?php echo $totalStudents; ?></p>
            </div>
        </div>

        <section class="dashboard-sections">
            <div class="section-grid">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-clock"></i> Recent Courses</h2>
                        <a href="courses.php" class="view-all">View All</a>
                    </div>
                    <?php if (empty($courses)): ?>
                        <div class="empty-state">
                            <i class="fas fa-book"></i>
                            <p>You haven't created any courses yet.</p>
                            <a href="create-course.php" class="button">Create Your First Course</a>
                        </div>
                    <?php else: ?>
                        <div class="course-grid">
                            <?php foreach (array_slice($courses, 0, 3) as $course): ?>
                                <div class="course-card">
                                    <div class="course-header">
                                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                        <span class="status-badge <?php echo $course['status']; ?>">
                                            <?php echo ucfirst($course['status']); ?>
                                        </span>
                                    </div>
                                    <p class="course-description"><?php echo substr(htmlspecialchars($course['description']), 0, 100) . '...'; ?></p>
                                    <?php
                                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE course_id = ? AND status = 'approved'");
                                        $stmt->execute([$course['id']]);
                                        $studentCount = $stmt->fetchColumn();
                                    ?>
                                    <div class="course-meta">
                                        <span><i class="fas fa-users"></i> <?php echo $studentCount; ?> students</span>
                                        <?php
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM materials WHERE course_id = ?");
                                            $stmt->execute([$course['id']]);
                                            $materialCount = $stmt->fetchColumn();
                                        ?>
                                        <span><i class="fas fa-book"></i> <?php echo $materialCount; ?> materials</span>
                                    </div>
                                    <div class="course-actions">
                                        <a href="view-course.php?id=<?php echo $course['id']; ?>" class="button">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <a href="edit-course.php?id=<?php echo $course['id']; ?>" class="button button-secondary">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-bell"></i> Recent Enrollment Requests</h2>
                        <a href="courses.php" class="view-all">View All</a>
                    </div>
                    <?php
                        $stmt = $pdo->prepare("
                            SELECT u.name, c.title, e.enrolled_at, c.id as course_id
                            FROM enrollments e
                            JOIN users u ON e.user_id = u.id
                            JOIN courses c ON e.course_id = c.id
                            WHERE c.teacher_id = ? AND e.status = 'pending'
                            ORDER BY e.enrolled_at DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$_SESSION['user_id']]);
                        $recentRequests = $stmt->fetchAll();
                    ?>
                    <?php if (empty($recentRequests)): ?>
                        <div class="empty-state">
                            <i class="fas fa-check-circle"></i>
                            <p>No pending enrollment requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="requests-list">
                            <?php foreach ($recentRequests as $request): ?>
                                <div class="request-item">
                                    <div class="request-info">
                                        <h4><?php echo htmlspecialchars($request['name']); ?></h4>
                                        <p>Requested to join <strong><?php echo htmlspecialchars($request['title']); ?></strong></p>
                                        <span class="request-time">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo date('M d, Y', strtotime($request['enrolled_at'])); ?>
                                        </span>
                                    </div>
                                    <a href="manage-students.php?course_id=<?php echo $request['course_id']; ?>" class="button">
                                        Review
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>
</body>
</html>