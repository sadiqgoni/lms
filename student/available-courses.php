<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a student
requireRole('student');

try {
    // Get pending enrollments for this student
    $stmt = $pdo->prepare("
        SELECT course_id FROM enrollments 
        WHERE user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $pendingEnrollments = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Get available courses (not enrolled)
    $stmt = $pdo->prepare("
        SELECT c.*, u.name as teacher_name,
            (SELECT COUNT(*) FROM materials WHERE course_id = c.id) as material_count,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as student_count
        FROM courses c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.status = 'published' 
        AND c.id NOT IN (
            SELECT course_id FROM enrollments 
            WHERE user_id = ? AND status IN ('approved', 'completed')
        )
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $availableCourses = $stmt->fetchAll();

    // Get course categories
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM courses WHERE status = 'published'");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch(PDOException $e) {
    $error = 'Error fetching courses';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Courses - LMS System</title>
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
                <li><a href="available-courses.php" class="active">Available Courses</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="page-header">
            <h1>Available Courses</h1>
            <div class="search-filters">
                <input type="text" id="courseSearch" placeholder="Search courses..." class="search-input">
                <select id="categoryFilter" class="filter-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category); ?>">
                            <?php echo htmlspecialchars($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select id="levelFilter" class="filter-select">
                    <option value="">All Levels</option>
                    <option value="beginner">Beginner</option>
                    <option value="intermediate">Intermediate</option>
                    <option value="advanced">Advanced</option>
                </select>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (empty($availableCourses)): ?>
            <div class="no-courses">
                <h2>No Available Courses</h2>
                <p>You're currently enrolled in all available courses. Check back later for new courses!</p>
            </div>
        <?php else: ?>
            <div class="course-grid">
                <?php foreach ($availableCourses as $course): ?>
                    <div class="course-card" 
                         data-category="<?php echo htmlspecialchars($course['category']); ?>"
                         data-level="<?php echo htmlspecialchars($course['level']); ?>">
                        <div class="course-header">
                            <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                            <span class="course-level"><?php echo ucfirst(htmlspecialchars($course['level'])); ?></span>
                        </div>
                        
                        <p class="course-teacher">By <?php echo htmlspecialchars($course['teacher_name']); ?></p>
                        
                        <p class="course-description">
                            <?php echo substr(htmlspecialchars($course['description']), 0, 150) . '...'; ?>
                        </p>
                        
                        <div class="course-meta">
                            <span><i class="fas fa-book"></i> <?php echo $course['material_count']; ?> materials</span>
                            <span><i class="fas fa-clock"></i> <?php echo $course['duration']; ?> weeks</span>
                            <span><i class="fas fa-users"></i> <?php echo $course['student_count']; ?> students</span>
                        </div>

                        <div class="course-category">
                            <span class="category-badge">
                                <?php echo htmlspecialchars($course['category']); ?>
                            </span>
                        </div>
                        
                        <div class="course-actions">
                            <?php if (in_array($course['id'], $pendingEnrollments)): ?>
                                <div class="enrollment-pending">
                                    <span class="status-badge status-pending">Enrollment Pending</span>
                                    <p class="pending-message">Your enrollment request is being reviewed by the instructor.</p>
                                </div>
                            <?php else: ?>
                                <a href="course-details.php?id=<?php echo $course['id']; ?>" class="button">View Details</a>
                                <a href="enroll.php?course_id=<?php echo $course['id']; ?>" class="button button-primary">Enroll Now</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> LMS System. All rights reserved.</p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const courseSearch = document.getElementById('courseSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const levelFilter = document.getElementById('levelFilter');
        const courseCards = document.querySelectorAll('.course-card');

        function filterCourses() {
            const searchTerm = courseSearch.value.toLowerCase();
            const selectedCategory = categoryFilter.value.toLowerCase();
            const selectedLevel = levelFilter.value.toLowerCase();

            courseCards.forEach(card => {
                const title = card.querySelector('h3').textContent.toLowerCase();
                const description = card.querySelector('.course-description').textContent.toLowerCase();
                const category = card.dataset.category.toLowerCase();
                const level = card.dataset.level.toLowerCase();

                const matchesSearch = title.includes(searchTerm) || description.includes(searchTerm);
                const matchesCategory = !selectedCategory || category === selectedCategory;
                const matchesLevel = !selectedLevel || level === selectedLevel;

                if (matchesSearch && matchesCategory && matchesLevel) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        courseSearch.addEventListener('input', filterCourses);
        categoryFilter.addEventListener('change', filterCourses);
        levelFilter.addEventListener('change', filterCourses);
    });
    </script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
