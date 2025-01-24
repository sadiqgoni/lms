<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ensure user is logged in and is a teacher
requireRole('teacher');

$error = '';
$success = '';
$course = null;

// Get course ID from URL
$courseId = $_GET['id'] ?? 0;

// Fetch course details
try {
    $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$courseId, $_SESSION['user_id']]);
    $course = $stmt->fetch();

    if (!$course) {
        header('Location: courses.php');
        exit();
    }
} catch(PDOException $e) {
    $error = 'Error fetching course details';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $status = $_POST['status'] ?? 'draft';

    if (empty($title) || empty($description)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE courses SET title = ?, description = ?, status = ? WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$title, $description, $status, $courseId, $_SESSION['user_id']]);
            $success = 'Course updated successfully!';
            
            // Refresh course data
            $stmt = $pdo->prepare("SELECT * FROM courses WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$courseId, $_SESSION['user_id']]);
            $course = $stmt->fetch();
        } catch(PDOException $e) {
            $error = 'Failed to update course. Please try again.';
        }
    }
}

// Fetch course materials
try {
    $stmt = $pdo->prepare("SELECT * FROM materials WHERE course_id = ? ORDER BY created_at DESC");
    $stmt->execute([$courseId]);
    $materials = $stmt->fetchAll();
} catch(PDOException $e) {
    $materials = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Course - FOC LMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <header>
        <nav>
            <div class="logo">FOC LMS</div>
            <ul>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="courses.php">My Courses</a></li>
                <li><a href="create-course.php">Create Course</a></li>
                <li><a href="../auth/logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <main class="dashboard-container">
        <div class="edit-course-container">
            <h1>Edit Course: <?php echo htmlspecialchars($course['title']); ?></h1>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" action="" class="course-form">
                <div class="form-group">
                    <label for="title">Course Title*</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo htmlspecialchars($course['title']); ?>">
                </div>

                <div class="form-group">
                    <label for="description">Course Description*</label>
                    <textarea id="description" name="description" rows="5" required><?php 
                        echo htmlspecialchars($course['description']); 
                    ?></textarea>
                </div>

                <div class="form-group">
                    <label for="status">Course Status</label>
                    <select id="status" name="status">
                        <option value="draft" <?php echo $course['status'] == 'draft' ? 'selected' : ''; ?>>Draft</option>
                        <option value="published" <?php echo $course['status'] == 'published' ? 'selected' : ''; ?>>Published</option>
                        <option value="archived" <?php echo $course['status'] == 'archived' ? 'selected' : ''; ?>>Archived</option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Update Course</button>
                    <a href="courses.php" class="button button-secondary">Back to Courses</a>
                </div>
            </form>

            <section class="course-materials">
                <h2>Course Materials</h2>
                <div class="add-material">
                    <a href="add-material.php?course_id=<?php echo $courseId; ?>" class="button">Add New Material</a>
                </div>

                <?php if (empty($materials)): ?>
                    <p class="no-materials">No materials added yet.</p>
                <?php else: ?>
                    <div class="materials-list">
                        <?php foreach ($materials as $material): ?>
                            <div class="material-item">
                                <h3><?php echo htmlspecialchars($material['title']); ?></h3>
                                <p>Type: <?php echo ucfirst($material['type']); ?></p>
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
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
