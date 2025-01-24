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

// Verify course ownership
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

// Handle material upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'] ?? '';
    $type = $_POST['type'] ?? '';
    $content = '';

    if (empty($title) || empty($type)) {
        $error = 'Please fill in all required fields';
    } else {
        try {
            // Handle file upload for document type
            if ($type == 'document' && isset($_FILES['file'])) {
                $uploadDir = '../uploads/materials/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileName = time() . '_' . basename($_FILES['file']['name']);
                $targetFile = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
                    $content = $fileName;
                } else {
                    throw new Exception('Failed to upload file');
                }
            } else {
                // For video and link types
                $content = $_POST['content'] ?? '';
            }

            // Insert material into database
            $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, type, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$courseId, $title, $type, $content]);
            
            $success = 'Material added successfully!';
            
            // Redirect back to course page after successful addition
            header("Location: view-course.php?id=" . $courseId);
            exit();
        } catch(Exception $e) {
            $error = 'Failed to add material. ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Material - <?php echo htmlspecialchars($course['title']); ?></title>
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
        <div class="add-material-container">
            <div class="page-header">
                <h1>Add Material to: <?php echo htmlspecialchars($course['title']); ?></h1>
                <a href="view-course.php?id=<?php echo $courseId; ?>" class="button">Back to Course</a>
            </div>

            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="material-form">
                <div class="form-group">
                    <label for="title">Material Title*</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="type">Material Type*</label>
                    <select id="type" name="type" required onchange="toggleContentField()">
                        <option value="">Select Type</option>
                        <option value="video">Video (YouTube/Vimeo URL)</option>
                        <option value="document">Document (PDF/DOC)</option>
                        <option value="link">External Link</option>
                    </select>
                </div>

                <div id="content-field" class="form-group" style="display: none;">
                    <label for="content">Content URL*</label>
                    <input type="url" id="content" name="content">
                </div>

                <div id="file-field" class="form-group" style="display: none;">
                    <label for="file">Upload File* (PDF, DOC, DOCX)</label>
                    <input type="file" id="file" name="file" accept=".pdf,.doc,.docx">
                </div>

                <div class="form-actions">
                    <button type="submit" class="button">Add Material</button>
                    <a href="view-course.php?id=<?php echo $courseId; ?>" class="button button-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> FOC LMS. All rights reserved.</p>
    </footer>

    <script>
    function toggleContentField() {
        const type = document.getElementById('type').value;
        const contentField = document.getElementById('content-field');
        const fileField = document.getElementById('file-field');

        if (type === 'document') {
            contentField.style.display = 'none';
            fileField.style.display = 'block';
            document.getElementById('content').required = false;
            document.getElementById('file').required = true;
        } else if (type === 'video' || type === 'link') {
            contentField.style.display = 'block';
            fileField.style.display = 'none';
            document.getElementById('content').required = true;
            document.getElementById('file').required = false;
        } else {
            contentField.style.display = 'none';
            fileField.style.display = 'none';
            document.getElementById('content').required = false;
            document.getElementById('file').required = false;
        }
    }
    </script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
